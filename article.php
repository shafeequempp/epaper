<?php
// Single article view: the story as printed, or as text. Shareable URL.
declare(strict_types=1);
require __DIR__ . '/inc/bootstrap.php';
require __DIR__ . '/inc/reader.php';
require __DIR__ . '/inc/reader_layout.php';

reader_gate();
$a = load_article((int)($_GET['id'] ?? 0));
if ($a) {
    // Stay in the reader's edition when the story comes from a page it shares (e.g. Ernakulam pages
    // inside Kothamangalam-Muvattupuzha); otherwise switch to the story's own edition.
    $keep = false;
    if (!empty($_GET['e'])) {
        $cur = q('SELECT * FROM editions WHERE slug = ?', [(string)$_GET['e']])->fetch();
        $keep = $cur && in_array((int)q('SELECT edition_id FROM issues WHERE id = ?', [$a['issue_id']])->fetchColumn(), edition_chain_ids($cur), true);
    }
    if (!$keep) {
        $_GET['e'] = $a['edition_slug'];
    }
    $_GET['d'] = $a['issue_date'];
}
$ctx = resolve_issue();

if (!$a) {
    http_response_code(404);
    reader_header($ctx, 'article', 'Story not found');
    echo '<section class="empty"><p>This story is not available. It may have been removed or not published yet.</p></section>';
    reader_footer();
    exit;
}
$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host = $scheme . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost');
$mode = ($_GET['mode'] ?? '') === 'text' ? 'text' : 'image';
reader_header($ctx, 'article', $a['headline'], [
    'og:title' => $a['headline'],
    'og:description' => excerpt($a['body'], 160),
    'og:image' => $a['regions'] ? $host . media($a['regions'][0]['img']) : '',
    'og:type' => 'article',
]);

if (!can_read_article($a)) {
    echo '<article class="story story--locked">';
    echo '<header class="story__head"><p class="story__where">'
       . h($a['edition_name_ml'] ?: $a['edition_name']) . ', ' . h(fmt_date($a['issue_date'])) . '</p></header>';
    echo '<div class="story__text"><h1>' . h($a['headline']) . '</h1><p>' . h(excerpt($a['body'], 300)) . '</p></div>';
    echo '</article>';
    $artEd = q('SELECT * FROM editions WHERE slug = ?', [(string)$a['edition_slug']])->fetch();
    paywall_block($a['regions'][0]['img'] ?? '', $artEd ? main_edition_of($artEd) : []);
    reader_footer();
    exit;
}
?>
<article class="story" data-mode="<?= h($mode) ?>">
  <header class="story__head">
    <p class="story__where">
      <a href="<?= h(rlink('page.php', $ctx, ['p' => $a['page_number']])) ?>">Page <?= h($a['page_label']) ?></a>,
      <?= h($a['edition_name_ml'] ?: $a['edition_name']) ?>, <?= h(fmt_date($a['issue_date'])) ?>
    </p>
    <div class="seg" role="group" aria-label="Show article as">
      <a href="<?= h(rlink('article.php', $ctx, ['id' => $a['id']])) ?>" aria-pressed="<?= $mode === 'image' ? 'true' : 'false' ?>">As printed</a>
      <a href="<?= h(rlink('article.php', $ctx, ['id' => $a['id'], 'mode' => 'text'])) ?>" aria-pressed="<?= $mode === 'text' ? 'true' : 'false' ?>">Text</a>
    </div>
    <?php if ($a['regions'] && can_download_article($a)): ?>
      <a class="story__dl" href="<?= h(url('dl_article.php', ['id' => $a['id']])) ?>">
        <svg viewBox="0 0 24 24" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="1.7"><path d="M12 3v12m0 0l-4-4m4 4l4-4"/><path d="M5 21h14"/></svg>
        Download image
      </a>
    <?php endif; ?>
  </header>

  <?php if ($mode === 'image'): ?>
    <div class="story__clip">
      <?php foreach ($a['regions'] as $r): ?>
        <img src="<?= h(media($r['img'])) ?>" width="<?= (int)$r['img_width'] ?>" height="<?= (int)$r['img_height'] ?>" alt="<?= h($a['headline']) ?>">
      <?php endforeach; ?>
    </div>
  <?php else: ?>
    <div class="story__text">
      <h1><?= h($a['headline']) ?></h1>
      <?php foreach (preg_split("/\n+/", $a['body']) as $para): if (trim($para) === '') continue; ?>
        <p><?= h($para) ?></p>
      <?php endforeach; ?>
      <?php foreach ($a['captions'] as $c): ?><p class="caption"><?= h($c) ?></p><?php endforeach; ?>
    </div>
  <?php endif; ?>

  <nav class="story__nav" aria-label="More on this page">
    <?php if ($a['prev_id']): ?><a href="<?= h(rlink('article.php', $ctx, ['id' => $a['prev_id'], 'mode' => $mode])) ?>">Previous story</a><?php endif; ?>
    <a href="<?= h(rlink('page.php', $ctx, ['p' => $a['page_number']])) ?>">Back to page <?= h($a['page_label']) ?></a>
    <?php if ($a['next_id']): ?><a href="<?= h(rlink('article.php', $ctx, ['id' => $a['next_id'], 'mode' => $mode])) ?>">Next story</a><?php endif; ?>
    <button type="button" class="share" data-title="<?= h($a['headline']) ?>">Share</button>
  </nav>
</article>
<?php reader_footer();
