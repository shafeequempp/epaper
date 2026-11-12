<?php
declare(strict_types=1);
require __DIR__ . '/../inc/bootstrap.php';
require __DIR__ . '/../inc/admin_layout.php';

require_login('editor');
$id = (int)($_GET['id'] ?? 0);
$a = q('SELECT a.*, p.issue_id, p.page_number FROM articles a JOIN pages p ON p.id = a.page_id WHERE a.id = ?', [$id])->fetch();
if (!$a) {
    http_response_code(404);
    exit('Article not found.');
}
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    q('UPDATE articles SET headline = ?, body = ? WHERE id = ?', [
        mb_substr(normalize_ml((string)$_POST['headline']), 0, 500),
        normalize_ml((string)$_POST['body']),
        $id,
    ]);
    flash('Article text saved.');
    redirect(url('admin/issue.php', ['id' => $a['issue_id'], 'page' => $a['page_number']]));
}
$region = q('SELECT img FROM article_regions WHERE article_id = ? ORDER BY seq LIMIT 1', [$id])->fetchColumn();
admin_header('Edit article', 'issues');
?>
<h1>Edit article text</h1>
<p class="a-muted">The text is what readers search and see in text mode. The image comes from the page and does not change here.</p>
<div class="a-edit">
  <?php if ($region): ?><img src="<?= h(media($region)) ?>" alt="Article as printed"><?php endif; ?>
  <form method="post" class="a-card">
    <?= csrf_field() ?>
    <label>Headline <input name="headline" lang="ml" value="<?= h($a['headline']) ?>"></label>
    <label>Body <textarea name="body" lang="ml" rows="18"><?= h($a['body']) ?></textarea></label>
    <button class="a-btn a-btn--primary">Save text</button>
    <a href="<?= h(url('admin/issue.php', ['id' => $a['issue_id'], 'page' => $a['page_number']])) ?>">Cancel</a>
  </form>
</div>
<?php admin_footer();
