<?php
// Thumb view: every page of the issue as a small image
declare(strict_types=1);
require __DIR__ . '/inc/bootstrap.php';
require __DIR__ . '/inc/reader.php';
require __DIR__ . '/inc/reader_layout.php';

$ctx = resolve_issue();
$pages = $ctx['issue'] ? edition_pages($ctx) : [];
reader_header($ctx, 'thumbs', $ctx['edition'] ? $ctx['edition']['name'] : '');

if (!$ctx['issue']) {
    no_issue($ctx);
} else { ?>
  <div class="issue-head">
    <h1><?= h($ctx['edition']['name_ml'] ?: $ctx['edition']['name']) ?></h1>
    <p><?= h(fmt_date($ctx['issue']['issue_date'])) ?>, <?= h(plural(count($pages), 'page', 'pages')) ?></p>
  </div>
  <?php if (!can_read_ctx($ctx)): ?>
    <p class="locked-note">
      <svg viewBox="0 0 24 24" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="1.7"><rect x="4" y="10" width="16" height="11" rx="2"/><path d="M8 10V7a4 4 0 0 1 8 0v3"/></svg>
      This is a past edition. <a href="<?= h(url('subscribe.php')) ?>">Subscribe</a> to open full pages, article text and downloads.
    </p>
  <?php endif; ?>
  <ol class="thumbs">
    <?php foreach ($pages as $p): ?>
      <li>
        <a href="<?= h(rlink('page.php', $ctx, ['p' => $p['page_number']])) ?>">
          <img src="<?= h(media($p['img_thumb'])) ?>" alt="Page <?= h($p['page_label']) ?>"
               width="<?= (int)cfg('thumb_width') ?>" height="<?= (int)round(cfg('thumb_width') * $p['height_px'] / $p['width_px']) ?>" loading="lazy">
          <span class="thumbs__no"><?= h($p['page_label']) ?></span>
        </a>
      </li>
    <?php endforeach; ?>
  </ol>
<?php }
reader_footer();
