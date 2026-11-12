<?php
declare(strict_types=1);
require __DIR__ . '/../inc/bootstrap.php';
require __DIR__ . '/../inc/admin_layout.php';

require_login('uploader');

$editionId = (int)($_GET['edition'] ?? 0);
$params = [];
$where = '';
if ($editionId) {
    $where = 'WHERE i.edition_id = ?';
    $params[] = $editionId;
}
$issues = q("SELECT i.*, e.name AS edition_name, e.slug AS edition_slug,
                    (SELECT COUNT(*) FROM pages p WHERE p.issue_id = i.id) AS page_count,
                    (SELECT COUNT(*) FROM articles a JOIN pages p ON p.id = a.page_id WHERE p.issue_id = i.id) AS article_count
             FROM issues i JOIN editions e ON e.id = i.edition_id
             $where
             ORDER BY i.issue_date DESC, e.sort_order
             LIMIT 200", $params)->fetchAll();
$editions = q('SELECT id, name FROM editions ORDER BY sort_order, name')->fetchAll();

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

<?php if (!$issues): ?>
  <div class="a-card a-empty">
    <p>No issues yet. Export a page from InDesign with EpaperExport.jsx, then upload it.</p>
    <a class="a-btn a-btn--primary" href="<?= h(url('admin/upload.php')) ?>">Upload pages</a>
  </div>
<?php else: ?>
<table class="a-table">
  <thead><tr><th>Date</th><th>Edition</th><th>Pages</th><th>Articles</th><th>Status</th><th></th></tr></thead>
  <tbody>
  <?php foreach ($issues as $i): ?>
    <tr>
      <td><?= h(fmt_date($i['issue_date'])) ?></td>
      <td><?= h($i['edition_name']) ?></td>
      <td><?= (int)$i['page_count'] ?></td>
      <td><?= (int)$i['article_count'] ?></td>
      <td><span class="a-status a-status--<?= h($i['status']) ?>"><?= $i['status'] === 'published' ? 'Published' : 'Draft' ?></span></td>
      <td class="a-actions"><a href="<?= h(url('admin/issue.php', ['id' => $i['id']])) ?>">Review</a></td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table>
<?php endif; ?>
<?php admin_footer();
