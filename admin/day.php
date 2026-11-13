<?php
declare(strict_types=1);
require __DIR__ . '/../inc/bootstrap.php';
require __DIR__ . '/../inc/admin_layout.php';

require_login('uploader');

$date  = (string)($_GET['date'] ?? '');
$gname = (string)($_GET['group'] ?? '');
$edid  = (int)($_GET['edition_id'] ?? 0);
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
    redirect(url('admin/index.php'));
}
$back = url('admin/day.php', ['date' => $date, 'group' => $gname, 'edition_id' => $edid]);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    require_login('editor');
    $msg = publish_date_group((string)($_POST['action'] ?? ''), $date, $gname, $edid);
    if ($msg) {
        flash($msg);
    }
    redirect($back);
}

// editions that make up this paper for the date
if ($gname !== '') {
    $scope = 'e.group_name = ?';
    $sp = [$date, $gname];
} else {
    $scope = 'i.edition_id = ?';
    $sp = [$date, $edid];
}
$list = q("SELECT i.*, e.name AS edition_name, e.is_main, e.is_supplement,
                  (SELECT COUNT(*) FROM pages p WHERE p.issue_id = i.id) AS page_count,
                  (SELECT COUNT(*) FROM articles a JOIN pages p ON p.id = a.page_id WHERE p.issue_id = i.id) AS article_count
           FROM issues i JOIN editions e ON e.id = i.edition_id
           WHERE i.issue_date = ? AND $scope
           ORDER BY e.is_main DESC, e.is_supplement, e.sort_order, e.name", $sp)->fetchAll();
if (!$list) {
    redirect(url('admin/index.php'));
}

$pages = 0; $published = 0; $withPages = 0; $main = null;
foreach ($list as $r) {
    $pages += (int)$r['page_count'];
    if ($r['status'] === 'published') $published++;
    if ((int)$r['page_count'] > 0)    $withPages++;
    if ((int)$r['is_main'] === 1 && !$main) $main = $r;
}
$title = ($main['edition_name'] ?? ($gname ?: $list[0]['edition_name']));
$canPublish = $published < $withPages;

admin_header($title . ' ' . $date, 'issues');
?>
<p class="a-muted"><a href="<?= h(url('admin/index.php')) ?>">&larr; All issues</a></p>
<div class="a-head">
  <h1><?= h($title) ?>, <?= h(fmt_date($date)) ?></h1>
  <span class="a-status a-status--<?= $withPages > 0 && $published >= $withPages ? 'published' : ($published === 0 ? 'draft' : 'partial') ?>">
    <?= $withPages > 0 && $published >= $withPages ? 'Published' : ($published === 0 ? 'Draft' : 'Partly published') ?>
  </span>
  <?php if ($canPublish): ?>
    <form method="post" class="a-inline">
      <?= csrf_field() ?>
      <button class="a-btn a-btn--primary" name="action" value="publish_group">Publish all editions</button>
    </form>
  <?php endif; ?>
  <?php if ($published > 0): ?>
    <form method="post" class="a-inline" onsubmit="return confirm('Unpublish every edition for this day?')">
      <?= csrf_field() ?>
      <button class="a-btn" name="action" value="unpublish_group">Unpublish all</button>
    </form>
  <?php endif; ?>
</div>
<p class="a-muted"><?= count($list) ?> edition(s), <?= (int)$pages ?> page(s) in all. The main edition carries the shared pages; each sub-edition carries only its own differing page(s).</p>

<table class="a-table">
  <thead><tr><th>Edition</th><th>Pages</th><th>Articles</th><th>Status</th><th></th></tr></thead>
  <tbody>
  <?php foreach ($list as $i):
      $tag = (int)$i['is_main'] === 1 ? 'main' : ((int)$i['is_supplement'] === 1 ? 'supplement' : 'local');
  ?>
    <tr>
      <td><?= h($i['edition_name']) ?> <span class="a-tag a-tag--<?= h($tag) ?>"><?= h($tag) ?></span></td>
      <td><?= (int)$i['page_count'] ?></td>
      <td><?= (int)$i['article_count'] ?></td>
      <td><span class="a-status a-status--<?= h($i['status']) ?>"><?= $i['status'] === 'published' ? 'Published' : 'Draft' ?></span></td>
      <td class="a-actions"><a href="<?= h(url('admin/issue.php', ['id' => $i['id']])) ?>">Review</a></td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table>
<?php admin_footer();
