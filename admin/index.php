<?php
declare(strict_types=1);
require __DIR__ . '/../inc/bootstrap.php';
require __DIR__ . '/../inc/admin_layout.php';

require_login('uploader');

// ---- one-click publish / unpublish for a whole date + district group ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    require_login('editor');
    $msg = publish_date_group(
        (string)($_POST['action'] ?? ''),
        (string)($_POST['date'] ?? ''),
        (string)($_POST['group'] ?? ''),
        (int)($_POST['edition_id'] ?? 0)
    );
    if ($msg) {
        flash($msg);
    }
    redirect(url('admin/index.php'));
}

$editionId = (int)($_GET['edition'] ?? 0);
$params = [];
$where = '';
if ($editionId) {
    $where = 'WHERE i.edition_id = ?';
    $params[] = $editionId;
}
$issues = q("SELECT i.*, e.name AS edition_name,
                    e.group_name, e.is_main,
                    (SELECT COUNT(*) FROM pages p WHERE p.issue_id = i.id) AS page_count
             FROM issues i JOIN editions e ON e.id = i.edition_id
             $where
             ORDER BY i.issue_date DESC, e.group_sort, e.is_main DESC, e.is_supplement, e.sort_order, e.name
             LIMIT 600", $params)->fetchAll();
$editions = q('SELECT id, name FROM editions ORDER BY sort_order, name')->fetchAll();

// ---- group the flat list by (date + district group) ---------------------------
$groups = [];
foreach ($issues as $r) {
    $hasGroup = $r['group_name'] !== null && $r['group_name'] !== '';
    $gkey = $r['issue_date'] . '|' . ($hasGroup ? 'g:' . $r['group_name'] : 'e:' . $r['edition_id']);
    if (!isset($groups[$gkey])) {
        $groups[$gkey] = [
            'date' => $r['issue_date'], 'group_name' => $hasGroup ? $r['group_name'] : '',
            'edition_id' => (int)$r['edition_id'], 'main' => null, 'first' => $r['edition_name'],
            'pages' => 0, 'eds' => 0, 'published' => 0, 'with_pages' => 0,
        ];
    }
    $groups[$gkey]['pages']    += (int)$r['page_count'];
    $groups[$gkey]['eds']      += 1;
    if ($r['status'] === 'published')  $groups[$gkey]['published']  += 1;
    if ((int)$r['page_count'] > 0)     $groups[$gkey]['with_pages'] += 1;
    if ((int)$r['is_main'] === 1 && !$groups[$gkey]['main']) {
        $groups[$gkey]['main'] = $r['edition_name'];
    }
}

/** Overall status of a date-group: [class, label]. */
function group_status(array $g): array
{
    if ($g['with_pages'] > 0 && $g['published'] >= $g['with_pages']) {
        return ['published', 'Published'];
    }
    if ($g['published'] === 0) {
        return ['draft', 'Draft'];
    }
    return ['partial', 'Partly published'];
}

admin_header('Issues', 'issues');
?>
<div class="a-head">
  <h1>Issues</h1>
  <form method="get" class="a-inline">
    <select name="edition" onchange="this.form.submit()">
      <option value="">All editions</option>
      <?php foreach ($editions as $e): ?>
        <option value="<?= (int)$e['id'] ?>"<?= $editionId === (int)$e['id'] ? ' selected' : '' ?>><?= h($e['name']) ?></option>
      <?php endforeach; ?>
    </select>
  </form>
  <a class="a-btn a-btn--primary" href="<?= h(url('admin/upload.php')) ?>">Upload pages</a>
</div>

<?php if (!$groups): ?>
  <div class="a-card a-empty">
    <p>No issues yet. Export a page from InDesign with EpaperExport.jsx, then upload it.</p>
    <a class="a-btn a-btn--primary" href="<?= h(url('admin/upload.php')) ?>">Upload pages</a>
  </div>
<?php else: ?>
<p class="a-muted">One row per paper (district &amp; date). Open a row to see its main and sub-editions; use <strong>Publish all</strong> to put the whole day live at once.</p>
<table class="a-table a-issues">
  <thead><tr><th>Date</th><th>Edition</th><th>Editions</th><th>Pages</th><th>Status</th><th></th></tr></thead>
  <tbody>
  <?php foreach ($groups as $g):
      $label = $g['main'] ?? ($g['group_name'] ?: $g['first']);
      [$sClass, $sText] = group_status($g);
      $canPublish = $g['published'] < $g['with_pages'];
      $href = url('admin/day.php', ['date' => $g['date'], 'group' => $g['group_name'], 'edition_id' => $g['edition_id']]);
  ?>
    <tr class="a-grow" data-href="<?= h($href) ?>">
      <td><?= h(fmt_date($g['date'])) ?></td>
      <td><a class="a-grow__link" href="<?= h($href) ?>"><strong><?= h($label) ?></strong></a></td>
      <td><?= (int)$g['eds'] ?></td>
      <td><?= (int)$g['pages'] ?></td>
      <td><span class="a-status a-status--<?= h($sClass) ?>"><?= h($sText) ?></span></td>
      <td class="a-actions">
        <?php if ($canPublish): ?>
          <form method="post" class="a-inline a-noexpand">
            <?= csrf_field() ?>
            <input type="hidden" name="date" value="<?= h($g['date']) ?>">
            <input type="hidden" name="group" value="<?= h($g['group_name']) ?>">
            <input type="hidden" name="edition_id" value="<?= (int)$g['edition_id'] ?>">
            <button class="a-btn a-btn--primary a-btn--sm" name="action" value="publish_group">Publish all</button>
          </form>
        <?php elseif ($g['published'] > 0): ?>
          <form method="post" class="a-inline a-noexpand" onsubmit="return confirm('Unpublish every edition for this day?')">
            <?= csrf_field() ?>
            <input type="hidden" name="date" value="<?= h($g['date']) ?>">
            <input type="hidden" name="group" value="<?= h($g['group_name']) ?>">
            <input type="hidden" name="edition_id" value="<?= (int)$g['edition_id'] ?>">
            <button class="a-btn a-btn--sm" name="action" value="unpublish_group">Unpublish all</button>
          </form>
        <?php endif; ?>
      </td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table>
<script>
// Whole row opens the day page; clicks on the publish form or a link are left alone.
document.querySelectorAll('.a-grow').forEach(function (row) {
  row.addEventListener('click', function (e) {
    if (e.target.closest('.a-noexpand') || e.target.closest('a')) return;
    window.location = row.getAttribute('data-href');
  });
});
</script>
<?php endif; ?>
<?php admin_footer();
