<?php
// Full page view: one page, zoomable, with clickable articles
declare(strict_types=1);
require __DIR__ . '/inc/bootstrap.php';
require __DIR__ . '/inc/reader.php';
require __DIR__ . '/inc/reader_layout.php';

$ctx = resolve_issue();
$pages = $ctx['issue'] ? edition_pages($ctx) : [];
$want = (int)($_GET['p'] ?? 0);
$idx = 0;
foreach ($pages as $i => $p) {
    if ((int)$p['page_number'] === $want) {
        $idx = $i;
    }
}
$page = $pages[$idx] ?? null;
$prev = $pages[$idx - 1] ?? null;
$next = $pages[$idx + 1] ?? null;
$arts = $page ? page_articles((int)$page['id']) : [];

reader_header($ctx, 'page', $page ? 'Page ' . $page['page_label'] : '');

if (!$page) {
    no_issue($ctx);
} elseif (!can_read_ctx($ctx)) {
    paywall_block($page['img_thumb'], $ctx['main_edition'] ?? ($ctx['edition'] ?? []));
} else { ?>
<div class="reader" data-api="<?= h(url('api/article.php')) ?>"<?= can_preview() ? ' data-preview="1"' : '' ?>>
  <aside class="strip" aria-label="Pages">
    <?php foreach ($pages as $p): ?>
      <a href="<?= h(rlink('page.php', $ctx, ['p' => $p['page_number']])) ?>"<?= $p['id'] === $page['id'] ? ' aria-current="page"' : '' ?>>
        <img src="<?= h(media($p['img_thumb'])) ?>" alt="" loading="lazy"><span><?= h($p['page_label']) ?></span></a>
    <?php endforeach; ?>
  </aside>

  <div class="stage">
    <div class="tools" role="toolbar" aria-label="Page tools">
      <a class="tool" <?= $prev ? 'href="' . h(rlink('page.php', $ctx, ['p' => $prev['page_number']])) . '" data-key="prev"' : 'aria-disabled="true"' ?>>Previous page</a>
      <span class="tools__label">Page <?= h($page['page_label']) ?> <span class="tools__of">(<?= $idx + 1 ?> of <?= count($pages) ?>)</span></span>
      <a class="tool" <?= $next ? 'href="' . h(rlink('page.php', $ctx, ['p' => $next['page_number']])) . '" data-key="next"' : 'aria-disabled="true"' ?>>Next page</a>
      <span class="tools__gap"></span>
      <button class="tool" type="button" data-zoom="out" aria-label="Zoom out">−</button>
      <button class="tool" type="button" data-zoom="fit">Fit</button>
      <button class="tool" type="button" data-zoom="in" aria-label="Zoom in">+</button>
      <?php if (can_download_ctx($ctx)): ?>
        <a class="tool" href="<?= h(media($page['img_full'])) ?>" download>Download page</a>
      <?php else: ?>
        <a class="tool" href="<?= h(url('subscribe.php')) ?>" title="Subscribe to download pages">Download page</a>
      <?php endif; ?>
    </div>

    <?php if ($prev): ?>
      <a class="page-arrow page-arrow--prev" href="<?= h(rlink('page.php', $ctx, ['p' => $prev['page_number']])) ?>" aria-label="Previous page" title="Previous page">
        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M15 5l-7 7 7 7"/></svg></a>
    <?php endif; ?>
    <?php if ($next): ?>
      <a class="page-arrow page-arrow--next" href="<?= h(rlink('page.php', $ctx, ['p' => $next['page_number']])) ?>" aria-label="Next page" title="Next page">
        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M9 5l7 7-7 7"/></svg></a>
    <?php endif; ?>
    
    <div class="viewport" tabindex="0" aria-label="Page <?= h($page['page_label']) ?>. Drag to move, click a story to read it.">
      <div class="sheet" data-w="<?= (int)$page['width_px'] ?>" data-h="<?= (int)$page['height_px'] ?>">
        <div class="paper" style="aspect-ratio: <?= (int)$page['width_px'] ?> / <?= (int)$page['height_px'] ?>">
          <img src="<?= h(media($page['img_full'])) ?>" alt="Page <?= h($page['page_label']) ?>" draggable="false">
          <?php foreach ($arts as $a): foreach ($a['regions'] as $r): ?>
            <a class="spot" data-article="<?= (int)$a['id'] ?>" href="<?= h(rlink('article.php', $ctx, ['id' => $a['id']])) ?>"
              title="<?= h($a['headline']) ?>"
              style="left:<?= $r['x'] * 100 ?>%;top:<?= $r['y'] * 100 ?>%;width:<?= $r['w'] * 100 ?>%;height:<?= $r['h'] * 100 ?>%">
              <span class="sr"><?= h($a['headline']) ?></span></a>
          <?php endforeach; endforeach; ?>
        </div>
      </div>
    </div>
  </div>

  <aside class="panel" aria-live="polite" hidden>
    <div class="panel__bar">
      <div class="seg" role="group" aria-label="Show article as">
        <button type="button" data-mode="image" aria-pressed="true">As printed</button>
        <button type="button" data-mode="text" aria-pressed="false">Text</button>
      </div>
      <a class="panel__open" href="#" target="_blank" rel="noopener">Open article</a>
      <button type="button" class="panel__close" aria-label="Close article">×</button>
    </div>
    <div class="panel__body"></div>
  </aside>
</div>
<?php }
reader_footer();
