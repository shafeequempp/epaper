<?php
declare(strict_types=1);
require __DIR__ . '/../inc/bootstrap.php';
require __DIR__ . '/../inc/importer.php';
require __DIR__ . '/../inc/admin_layout.php';

require_login('editor');
@ini_set('memory_limit', '768M');
@set_time_limit(300);

[$ok, $reason] = watermark_status();

// Live preview: a recent unwatermarked page (or a blank page) with the current settings
if (isset($_GET['preview'])) {
    $img = null;
    foreach (q('SELECT * FROM pages ORDER BY id DESC LIMIT 50')->fetchAll() as $p) {
        if (!is_file(watermark_marker($p))) {
            $img = @imagecreatefromjpeg(rtrim((string)cfg('storage_dir'), '/') . '/' . $p['img_full']);
            if ($img) {
                break;
            }
        }
    }
    if (!$img) {
        $img = imagecreatetruecolor(2200, 3900);
        imagefill($img, 0, 0, imagecolorallocate($img, 250, 250, 248));
    }
    apply_watermark($img);
    $w = 1000;
    $out = imagecreatetruecolor($w, (int)round(imagesy($img) * $w / imagesx($img)));
    imagecopyresampled($out, $img, 0, 0, 0, 0, $w, imagesy($out), imagesx($img), imagesy($img));
    header('Content-Type: image/jpeg');
    header('Cache-Control: no-store');
    imagejpeg($out, null, 85);
    exit;
}

$base = rtrim((string)cfg('storage_dir'), '/') . '/';
$pending = [];
foreach (q('SELECT * FROM pages ORDER BY id DESC')->fetchAll() as $p) {
    if (!is_file(watermark_marker($p)) && is_file($base . $p['img_full'])) {
        $pending[] = $p;
    }
}

$report = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'apply') {
    check_csrf();
    if (!$ok) {
        flash('Fix the watermark settings first: ' . h($reason), 'error');
        redirect(url('admin/watermark.php'));
    }
    $done = 0;
    $errors = [];
    foreach (array_slice($pending, 0, 15) as $p) {      // 15 pages per request keeps PHP within its time limit
        $r = watermark_existing_page($p);
        if ($r === 'done') {
            $done++;
        } elseif ($r !== 'skipped') {
            $errors[] = 'Page id ' . (int)$p['id'] . ': ' . $r;
        }
    }
    $left = max(0, count($pending) - 15);
    $report = ['done' => $done, 'left' => $left, 'errors' => $errors];
    $pending = array_slice($pending, 15);
}

admin_header('Watermark', 'watermark');
?>
<h1>Watermark</h1>

<?php if ($ok): ?>
  <p class="a-flash">The watermark is set up. Every page uploaded from now on gets it.</p>
<?php else: ?>
  <p class="a-flash a-flash--error"><b>Pages are uploading without a watermark.</b> <?= h($reason) ?></p>
<?php endif; ?>

<?php if ($report): ?>
  <div class="a-card">
    <p>Watermarked <?= (int)$report['done'] ?> page(s) in this round. <?= (int)$report['left'] ?> left.</p>
    <?php foreach ($report['errors'] as $e): ?><p class="a-warn"><?= h($e) ?></p><?php endforeach; ?>
    <?php if ($report['left'] > 0 && !$report['errors']): ?>
      <form method="post" id="again"><?= csrf_field() ?><input type="hidden" name="action" value="apply">
        <p class="a-muted">Continuing automatically…</p></form>
      <script>setTimeout(function () { document.getElementById('again').submit(); }, 400);</script>
    <?php endif; ?>
  </div>
<?php endif; ?>

<div class="a-review">
  <div>
    <h2>Preview with the current settings</h2>
    <img src="<?= h(url('admin/watermark.php', ['preview' => 1, 't' => time()])) ?>" alt="Watermark preview"
         style="width:100%;border:1px solid var(--line);background:#fff">
  </div>
  <div>
    <h2>Pages uploaded before</h2>
    <?php if ($pending): ?>
      <p><?= count($pending) ?> page(s) have no watermark yet.</p>
      <form method="post" class="a-inline" onsubmit="this.querySelector('button').disabled=true">
        <?= csrf_field() ?>
        <button class="a-btn a-btn--primary" name="action" value="apply"<?= $ok ? '' : ' disabled' ?>>Watermark them now</button>
      </form>
      <p class="a-muted">Runs 15 pages at a time and continues by itself. Article images are cut again from the watermarked page.</p>
    <?php else: ?>
      <p>All pages have the watermark.</p>
    <?php endif; ?>

    <h2 style="margin-top:28px">Settings</h2>
    <p class="a-muted">Change these in <code>config.php</code> under <code>'watermark'</code>, then reload this page to see the preview.</p>
    <table class="a-table">
      <?php foreach ((array)(cfg('watermark') ?: []) as $k => $v): ?>
        <tr><th><?= h($k) ?></th><td><code><?= h(is_bool($v) ? ($v ? 'true' : 'false') : (string)$v) ?></code></td></tr>
      <?php endforeach; ?>
    </table>
  </div>
</div>
<?php admin_footer();