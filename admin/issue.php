<?php
declare(strict_types=1);
require __DIR__ . '/../inc/bootstrap.php';
require __DIR__ . '/../inc/reader.php';
require __DIR__ . '/../inc/admin_layout.php';

$user = require_login('uploader');
$id = (int)($_GET['id'] ?? 0);
$issue = q('SELECT i.*, e.name AS edition_name, e.slug AS edition_slug FROM issues i JOIN editions e ON e.id = i.edition_id WHERE i.id = ?', [$id])->fetch();
if (!$issue) {
    http_response_code(404);
    exit('Issue not found.');
}
$back = url('admin/issue.php', ['id' => $id]);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    $action = (string)($_POST['action'] ?? '');
    if ($action === 'publish' || $action === 'unpublish') {
        require_login('editor');
        if ($action === 'publish') {
            q("UPDATE issues SET status = 'published', published_at = NOW() WHERE id = ?", [$id]);
            flash('Published. Readers can now see this issue.');
        } else {
            q("UPDATE issues SET status = 'draft', published_at = NULL WHERE id = ?", [$id]);
            flash('Unpublished. The issue is hidden from readers.');
        }
    } elseif ($action === 'delete_page') {
        require_login('editor');
        q('DELETE FROM pages WHERE id = ? AND issue_id = ?', [(int)$_POST['page_id'], $id]);
        flash('Page deleted.');
    } elseif ($action === 'delete_article') {
        require_login('editor');
        q('DELETE a FROM articles a JOIN pages p ON p.id = a.page_id WHERE a.id = ? AND p.issue_id = ?', [(int)$_POST['article_id'], $id]);
        flash('Article removed from the page.');
        $back = url('admin/issue.php', ['id' => $id, 'page' => (int)($_POST['page'] ?? 0)]);
    } elseif ($action === 'delete_issue') {
        require_login('admin');
        q('DELETE FROM issues WHERE id = ?', [$id]);
        flash('Issue deleted.');
        redirect(url('admin/index.php'));
    }
    redirect($back);
}

$pages = issue_pages($id);
$selected = null;
$sel = (int)($_GET['page'] ?? 0);
foreach ($pages as $p) {
    if ((int)$p['page_number'] === $sel) {
        $selected = $p;
    }
}
$articles = $selected ? page_articles((int)$selected['id']) : [];
$preview = url('index.php', ['e' => $issue['edition_slug'], 'd' => $issue['issue_date'], 'preview' => 1]);

admin_header($issue['edition_name'] . ' ' . $issue['issue_date'], 'issues');
?>
<div class="a-head">
  <h1><?= h($issue['edition_name']) ?>, <?= h(fmt_date($issue['issue_date'])) ?></h1>
  <span class="a-status a-status--<?= h($issue['status']) ?>"><?= $issue['status'] === 'published' ? 'Published' : 'Draft' ?></span>
  <a class="a-btn" href="<?= h($preview) ?>" target="_blank" rel="noopener">Preview in reader</a>
  <form method="post" class="a-inline">
    <?= csrf_field() ?>
    <?php if ($issue['status'] === 'published'): ?>
      <button class="a-btn" name="action" value="unpublish">Unpublish</button>
    <?php else: ?>
      <button class="a-btn a-btn--primary" name="action" value="publish"<?= $pages ? '' : ' disabled' ?>>Publish</button>
    <?php endif; ?>
  </form>
</div>

<?php if (!$pages): ?>
  <div class="a-card a-empty">
    <p>This issue has no pages yet.</p>
    <a class="a-btn a-btn--primary" href="<?= h(url('admin/upload.php')) ?>">Upload pages</a>
  </div>
<?php else: ?>
<ul class="a-pages">
  <?php foreach ($pages as $p): $isSel = $selected && $selected['id'] === $p['id']; ?>
    <li<?= $isSel ? ' class="is-selected"' : '' ?>>
      <a href="<?= h(url('admin/issue.php', ['id' => $id, 'page' => $p['page_number']])) ?>">
        <img src="<?= h(media($p['img_thumb'])) ?>" alt="Page <?= h($p['page_label']) ?>" loading="lazy">
        <span>Page <?= h($p['page_label']) ?></span>
      </a>
    </li>
  <?php endforeach; ?>
</ul>
<?php endif; ?>

<?php if ($selected): ?>
<section class="a-review">
  <div class="a-review__page">
    <div class="a-hotspots" style="aspect-ratio: <?= (int)$selected['width_px'] ?> / <?= (int)$selected['height_px'] ?>">
      <img src="<?= h(media($selected['img_medium'])) ?>" alt="">
      <?php foreach ($articles as $n => $a): foreach ($a['regions'] as $r): ?>
        <a href="#art-<?= (int)$a['id'] ?>" class="a-spot" title="<?= h($a['headline']) ?>"
           style="left:<?= $r['x'] * 100 ?>%;top:<?= $r['y'] * 100 ?>%;width:<?= $r['w'] * 100 ?>%;height:<?= $r['h'] * 100 ?>%">
          <span><?= $n + 1 ?></span></a>
      <?php endforeach; endforeach; ?>
    </div>
  </div>
  <div class="a-review__list">
    <div class="a-head">
      <h2>Page <?= h($selected['page_label']) ?>: <?= count($articles) ?> article(s)</h2>
      <form method="post" class="a-inline" onsubmit="return confirm('Delete this page and its articles?')">
        <?= csrf_field() ?><input type="hidden" name="page_id" value="<?= (int)$selected['id'] ?>">
        <button class="a-btn a-btn--danger" name="action" value="delete_page">Delete page</button>
      </form>
    </div>
    <p class="a-muted">Source: <?= h($selected['source_file']) ?>, version <?= (int)$selected['version'] ?>. Boxes wrong? Group the story in InDesign's Articles panel and re-upload.</p>
    <?php if (!$articles): ?>
      <p class="a-flash a-flash--error">No articles on this page, so readers cannot click anything. Re-run the updated EpaperExport.jsx, check export_log.txt, and upload again.</p>
    <?php endif; ?>
    <ol class="a-articles">
      <?php foreach ($articles as $a): ?>
        <li id="art-<?= (int)$a['id'] ?>">
          <?php if (!empty($a['regions'][0])): ?><img src="<?= h(media($a['regions'][0]['img'])) ?>" alt="" loading="lazy"><?php endif; ?>
          <div>
            <p class="a-art-title" lang="ml"><?= h($a['headline'] ?: '(no headline)') ?></p>
            <a href="<?= h(url('admin/article.php', ['id' => $a['id']])) ?>">Edit text</a>
            <form method="post" class="a-inline" onsubmit="return confirm('Remove this article?')">
              <?= csrf_field() ?><input type="hidden" name="article_id" value="<?= (int)$a['id'] ?>">
              <input type="hidden" name="page" value="<?= (int)$selected['page_number'] ?>">
              <button class="a-link" name="action" value="delete_article">Remove</button>
            </form>
          </div>
        </li>
      <?php endforeach; ?>
    </ol>
  </div>
</section>
<?php elseif ($pages): ?>
  <p class="a-muted">Choose a page to check its article boxes and text.</p>
<?php endif; ?>

<?php if ($user['role'] === 'admin'): ?>
<form method="post" class="a-danger-zone" onsubmit="return confirm('Delete the whole issue?')">
  <?= csrf_field() ?>
  <button class="a-btn a-btn--danger" name="action" value="delete_issue">Delete issue</button>
</form>
<?php endif; ?>
<?php admin_footer();
