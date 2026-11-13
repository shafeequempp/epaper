<?php
// Page list view: pages one under another, each with its headlines
declare(strict_types=1);
require __DIR__ . '/inc/bootstrap.php';
require __DIR__ . '/inc/reader.php';
require __DIR__ . '/inc/reader_layout.php';

$ctx = resolve_issue();
$pages = $ctx['issue'] ? edition_pages($ctx) : [];
reader_header($ctx, 'list', $ctx['edition'] ? $ctx['edition']['name'] : '');

if (!$ctx['issue']) {
    no_issue($ctx);
} elseif (!can_read_ctx($ctx)) {
    paywall_block($pages[0]['img_thumb'] ?? '', $ctx['main_edition'] ?? ($ctx['edition'] ?? []));
} else {
    foreach ($pages as $p):
        $arts = page_articles((int)$p['id']); ?>
  <section class="plist" id="p<?= (int)$p['page_number'] ?>">
    <a class="plist__page hot" href="<?= h(rlink('page.php', $ctx, ['p' => $p['page_number']])) ?>">
      <span class="paper" style="aspect-ratio: <?= (int)$p['width_px'] ?> / <?= (int)$p['height_px'] ?>">
        <img src="<?= h(media($p['img_medium'])) ?>" alt="Page <?= h($p['page_label']) ?>" loading="lazy">
        <?php foreach ($arts as $a): foreach ($a['regions'] as $r): ?>
          <span class="spot" data-article="<?= (int)$a['id'] ?>"
                style="left:<?= $r['x'] * 100 ?>%;top:<?= $r['y'] * 100 ?>%;width:<?= $r['w'] * 100 ?>%;height:<?= $r['h'] * 100 ?>%"></span>
        <?php endforeach; endforeach; ?>
      </span>
    </a>
    <div class="plist__side">
      <h2>Page <?= h($p['page_label']) ?></h2>
      <?php if ($arts): ?>
        <ul class="heads">
          <?php foreach ($arts as $a): ?>
            <li><a data-article="<?= (int)$a['id'] ?>" href="<?= h(rlink('article.php', $ctx, ['id' => $a['id']])) ?>"><?= h($a['headline'] ?: 'Untitled story') ?></a></li>
          <?php endforeach; ?>
        </ul>
      <?php else: ?>
        <p class="muted">No stories on this page, only ads or pictures.</p>
      <?php endif; ?>
    </div>
  </section>
<?php endforeach;
}
reader_footer();
