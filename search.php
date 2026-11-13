<?php
declare(strict_types=1);
require __DIR__ . '/inc/bootstrap.php';
require __DIR__ . '/inc/reader.php';
require __DIR__ . '/inc/reader_layout.php';

reader_gate();
$ctx = resolve_issue();
$q = trim((string)($_GET['q'] ?? ''));
$all = !empty($_GET['all']);
$results = $q !== '' ? search_articles($q, $all || !$ctx['edition'] ? [] : edition_chain_ids($ctx['edition'])) : [];
reader_header($ctx, 'search', $q ? "Search: $q" : 'Search');
?>
<section class="results">
  <h1><?= $q === '' ? 'Search the paper' : h(plural(count($results), 'story', 'stories')) . ' for “' . h($q) . '”' ?></h1>
  <?php if ($q !== '' && $ctx['edition']): ?>
    <p class="muted">
      <?php if ($all): ?>Showing all editions. <a href="<?= h(url('search.php', ['q' => $q, 'e' => $ctx['edition']['slug']])) ?>">Only <?= h($ctx['edition']['name']) ?></a>
      <?php else: ?>Showing <?= h($ctx['edition']['name']) ?> only. <a href="<?= h(url('search.php', ['q' => $q, 'e' => $ctx['edition']['slug'], 'all' => 1])) ?>">Search all editions</a><?php endif; ?>
    </p>
  <?php endif; ?>
  <?php if ($q !== '' && !$results): ?>
    <p>No stories match. Try a shorter word, for example the place name without its ending.</p>
  <?php endif; ?>
  <ol class="hits">
    <?php foreach ($results as $r): ?>
      <li>
        <a href="<?= h(url('article.php', array_filter(['id' => $r['id'], 'e' => !$all && $ctx['edition'] ? $ctx['edition']['slug'] : null]))) ?>">
          <?php if ($r['img']): ?><img src="<?= h(media($r['img'])) ?>" alt="" loading="lazy"><?php endif; ?>
          <div>
            <h2><?= h($r['headline'] ?: 'Untitled story') ?></h2>
            <p class="hits__where"><?= h($r['edition_name']) ?>, <?= h(fmt_date($r['issue_date'])) ?>, page <?= h($r['page_label']) ?></p>
            <p class="hits__snip"><?= $r['snippet'] ?></p>
          </div>
        </a>
      </li>
    <?php endforeach; ?>
  </ol>
</section>
<?php reader_footer();
