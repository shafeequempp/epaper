<?php
declare(strict_types=1);
require __DIR__ . '/../inc/bootstrap.php';
require __DIR__ . '/../inc/importer.php';
require __DIR__ . '/../inc/admin_layout.php';

require_login('uploader');
$editions = q('SELECT * FROM editions WHERE is_active = 1 ORDER BY group_sort, sort_order, name')->fetchAll();

$result = null;
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    try {
        if (empty($_FILES['files']) && (int)($_SERVER['CONTENT_LENGTH'] ?? 0) > 0) {
            throw new RuntimeException('The upload is larger than PHP allows. Raise post_max_size and upload_max_filesize in php.ini.');
        }
        // Collect uploaded files by basename
        $manifest = null;
        $images = [];
        $f = $_FILES['files'];
        $count = is_array($f['name'] ?? null) ? count($f['name']) : 0;
        for ($i = 0; $i < $count; $i++) {
            if ($f['error'][$i] === UPLOAD_ERR_NO_FILE) {
                continue;
            }
            if ($f['error'][$i] !== UPLOAD_ERR_OK) {
                throw new RuntimeException('Upload of "' . $f['name'][$i] . '" failed (PHP error ' . $f['error'][$i] . '). Check upload_max_filesize and max_file_uploads.');
            }
            $name = basename((string)$f['name'][$i]);
            if (strtolower($name) === 'manifest.json') {
                $manifest = json_decode((string)file_get_contents($f['tmp_name'][$i]), true);
                if (!is_array($manifest)) {
                    throw new RuntimeException('manifest.json could not be read. Re-export it from InDesign.');
                }
            } elseif (preg_match('/\.jpe?g$/i', $name)) {
                $images[$name] = $f['tmp_name'][$i];
            }
        }
        if (!$manifest) {
            throw new RuntimeException('Include manifest.json from the export folder.');
        }

        if (manifest_has_codes($manifest)) {
            // EpaperExport 3: every page knows its edition (file name code) and date
            $result = import_by_codes($manifest, $images);
            $result['issue_ids'] = array_column($result['issues'], 'issue_id');
        } else {
            $date = (string)($_POST['issue_date'] ?? '');
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
                throw new RuntimeException('Choose the issue date.');
            }
            $editionIds = array_map('intval', (array)($_POST['editions'] ?? []));
            $valid = array_map('intval', array_column($editions, 'id'));
            $editionIds = array_values(array_intersect($editionIds, $valid));
            if (!$editionIds) {
                throw new RuntimeException('Choose at least one edition, or export again with the new script so file names supply the edition.');
            }
            $issueIds = [];
            foreach ($editionIds as $eid) {
                $issueIds[] = ensure_issue($eid, $date);
            }
            $result = import_export($manifest, $images, $issueIds);
            $result['issue_ids'] = $issueIds;
        }
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}

admin_header('Upload pages', 'upload');
?>
<h1>Upload pages</h1>

<?php if ($error): ?>
  <p class="a-flash a-flash--error"><?= h($error) ?></p>
<?php endif; ?>

<?php if ($result): ?>
  <div class="a-card a-flash--ok">
    <h2>Imported <?= (int)$result['pages'] ?> page(s) with <?= (int)$result['articles'] ?> article(s)</h2>
    <?php foreach ($result['issues'] ?? [] as $is): ?>
      <p><?= h($is['edition']) ?> (<?= h($is['code']) ?>), <?= h(fmt_date($is['date'])) ?>: <?= (int)$is['pages'] ?> page(s)
        <a href="<?= h(url('admin/issue.php', ['id' => $is['issue_id']])) ?>">Review</a></p>
    <?php endforeach; ?>
    <?php foreach ($result['warnings'] as $w): ?><p class="a-warn"><?= h($w) ?></p><?php endforeach; ?>
    <p<?= !empty($result['issues']) ? ' hidden' : '' ?>>
      <?php foreach ($result['issue_ids'] as $iid): ?>
        <a class="a-btn" href="<?= h(url('admin/issue.php', ['id' => $iid])) ?>">Review issue</a>
      <?php endforeach; ?>
    </p>
  </div>
<?php endif; ?>

<form class="a-card" method="post" enctype="multipart/form-data" id="upload-form">
  <?= csrf_field() ?>
  <ol class="a-steps">
    <li>In InDesign, run <b>EpaperExport.jsx</b>. It creates a folder with <code>manifest.json</code> and one JPG per page. With the upload option switched on, the script sends the pages here by itself and you can skip this screen.</li>
    <li>Files named like <code>9pg2e-LocalNews.indd</code> carry their own date and edition, so the two fields below are only needed for older exports.</li>
    <li>Drop the whole export folder's files below and upload. Re-uploading a page number replaces it.</li>
  </ol>

  <div class="a-row">
    <label>Issue date
      <input type="date" name="issue_date" value="<?= h($_POST['issue_date'] ?? date('Y-m-d', strtotime('+1 day'))) ?>">
    </label>
  </div>

  <details class="a-editions-wrap"<?= !empty($_POST['editions']) ? ' open' : '' ?>>
  <summary>Choose editions by hand (only for exports without codes in the file names)</summary>
  <fieldset class="a-editions">
    <legend>Editions</legend>
    <?php foreach ($editions as $e): $checked = in_array((string)$e['id'], (array)($_POST['editions'] ?? []), true); ?>
      <label class="a-check"><input type="checkbox" name="editions[]" value="<?= (int)$e['id'] ?>"<?= $checked ? ' checked' : '' ?>>
        <?= h($e['name']) ?> <span lang="ml"><?= h($e['name_ml']) ?></span></label>
    <?php endforeach; ?>
  </fieldset>
  </details>

  <label class="a-drop" id="drop">
    <input type="file" name="files[]" multiple accept=".json,.jpg,.jpeg" id="files" required>
    <span class="a-drop__text">Drop <b>manifest.json</b> and the page JPGs here, or click to choose</span>
    <ul class="a-drop__list" id="file-list"></ul>
  </label>

  <button class="a-btn a-btn--primary" id="submit">Upload and process</button>
  <p class="a-muted" id="busy" hidden>Processing pages and cutting article images. Large pages take up to a minute.</p>
</form>

<script>
(function () {
  var input = document.getElementById('files'), list = document.getElementById('file-list'), drop = document.getElementById('drop');
  function show() {
    list.innerHTML = '';
    var hasManifest = false;
    Array.prototype.forEach.call(input.files, function (f) {
      if (f.name.toLowerCase() === 'manifest.json') hasManifest = true;
      var li = document.createElement('li');
      li.textContent = f.name + ' (' + (f.size / 1048576).toFixed(1) + ' MB)';
      list.appendChild(li);
    });
    if (input.files.length && !hasManifest) {
      var w = document.createElement('li'); w.className = 'a-warn'; w.textContent = 'manifest.json is missing from the selection';
      list.appendChild(w);
    }
  }
  input.addEventListener('change', show);
  ['dragenter', 'dragover'].forEach(function (t) { drop.addEventListener(t, function () { drop.classList.add('is-over'); }); });
  ['dragleave', 'drop'].forEach(function (t) { drop.addEventListener(t, function () { drop.classList.remove('is-over'); }); });
  document.getElementById('upload-form').addEventListener('submit', function () {
    document.getElementById('submit').disabled = true;
    document.getElementById('busy').hidden = false;
  });
})();
</script>
<?php admin_footer();
