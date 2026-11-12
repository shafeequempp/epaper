<?php
declare(strict_types=1);

/** Link inside the reader that keeps edition, date and preview mode. */
function rlink(string $file, array $ctx, array $extra = []): string
{
    $params = [];
    if (!empty($ctx['edition'])) {
        $params['e'] = $ctx['edition']['slug'];
    }
    if (!empty($ctx['issue'])) {
        $params['d'] = $ctx['issue']['issue_date'];
    }
    if (can_preview()) {
        $params['preview'] = 1;
    }
    return url($file, array_merge($params, $extra));
}

function reader_header(array $ctx, string $view, string $title = '', array $meta = []): void
{
    $site = (string)cfg('site_name');
    $pageTitle = $title ? "$title | $site" : $site;
    $views = [
        'thumbs' => ['All pages', 'index.php'],
        'list'   => ['Page list', 'list.php'],
        'page'   => ['Full page', 'page.php'],
    ];
    ?><!doctype html>
<html lang="ml">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= h($pageTitle) ?></title>
<?php foreach ($meta as $k => $v): ?><meta property="<?= h($k) ?>" content="<?= h($v) ?>">
<?php endforeach; ?>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Anek+Malayalam:wght@400;500;600;700&family=Noto+Serif+Malayalam:wght@400;600;700&display=swap">
<link rel="stylesheet" href="<?= h(url('assets/epaper.css')) ?>?v=<?= @filemtime(__DIR__ . '/../assets/epaper.css') ?>">
</head>
<body class="view-<?= h($view) ?>">
<a class="skip" href="#main">Skip to the paper</a>
<header class="bar">
  <a class="brand" href="<?= h(url('index.php')) ?>">
    <span class="brand__name">Mathrubhumi</span><span class="brand__sub">ePaper</span>
  </a>

  <?php $stay = ['list' => 'list.php', 'page' => 'page.php'][$view] ?? 'index.php'; // keep the reader's view when the date changes ?>
  <form class="pick" method="get" action="<?= h(url($stay)) ?>">
    <?php if (can_preview()): ?><input type="hidden" name="preview" value="1"><?php endif; ?>
    <label class="sr" for="pick-e">Edition</label>
    <select id="pick-e" name="e" onchange="this.form.d.value='';this.form.submit()">
      <?php $group = null; foreach ($ctx['editions'] as $e):
        if ($e['group_name'] !== $group): if ($group !== null) echo '</optgroup>'; $group = $e['group_name']; ?>
        <optgroup label="<?= h($group ?: 'Other') ?>">
      <?php endif; ?>
        <option value="<?= h($e['slug']) ?>"<?= $ctx['edition'] && $e['id'] === $ctx['edition']['id'] ? ' selected' : '' ?>>
          <?= h($e['name_ml'] ? $e['name_ml'] . ' (' . $e['name'] . ')' : $e['name']) ?></option>
      <?php endforeach; if ($group !== null) echo '</optgroup>'; ?>
    </select>
    <label class="sr" for="pick-d">Date</label>
    <?php $cur = $ctx['issue']['issue_date'] ?? ($ctx['dates'][0] ?? ''); ?>
    <input id="pick-d" class="pick-date" type="date" name="d" value="<?= h($cur) ?>"
      min="<?= h($ctx['dates'] ? end($ctx['dates']) : '') ?>" max="<?= h($ctx['dates'][0] ?? '') ?>"
      data-dates="<?= h(json_encode(array_values($ctx['dates']))) ?>"<?= $ctx['dates'] ? '' : ' disabled' ?>
      onchange="this.form.submit()">
  </form>

  <?php if ($ctx['issue']): ?>
  <nav class="views" aria-label="View">
    <?php foreach ($views as $key => [$label, $file]): ?>
      <a href="<?= h(rlink($file, $ctx)) ?>"<?= $view === $key ? ' aria-current="page"' : '' ?>>
        <?= view_icon($key) ?><span><?= h($label) ?></span></a>
    <?php endforeach; ?>
  </nav>
  <?php endif; ?>

  <form class="find" method="get" action="<?= h(url('search.php')) ?>" role="search">
    <?php if ($ctx['edition']): ?><input type="hidden" name="e" value="<?= h($ctx['edition']['slug']) ?>"><?php endif; ?>
    <label class="sr" for="find-q">Search news</label>
    <input id="find-q" name="q" type="search" placeholder="വാർത്ത തിരയുക / Search" value="<?= h($_GET['q'] ?? '') ?>">
  </form>

  <?php $sub = current_subscriber(); ?>
  <div class="acct">
    <?php if ($sub): ?>
      <?php if (!has_active_subscription((int)$sub['id'])): ?>
        <a class="acct__btn" href="<?= h(url('subscribe.php')) ?>">Subscribe</a>
      <?php endif; ?>
      <a class="acct__link" href="<?= h(url('account/index.php')) ?>"><?= h($sub['name'] ? explode(' ', trim($sub['name']))[0] : 'Account') ?></a>
    <?php else: ?>
      <a class="acct__link" href="<?= h(url('account/login.php')) ?>">Sign in</a>
      <a class="acct__btn" href="<?= h(url('subscribe.php')) ?>">Subscribe</a>
    <?php endif; ?>
  </div>
</header>
<?php if (can_preview()): ?><p class="preview-note">Preview: you are seeing unpublished pages.</p><?php endif; ?>
<main id="main">
<?php
}

function reader_footer(): void
{
    echo '</main><script src="' . h(url('assets/reader.js')) . '?v=' . (int)@filemtime(__DIR__ . '/../assets/reader.js') . '"></script></body></html>';
}

function view_icon(string $key): string
{
    $paths = [
        'thumbs' => '<rect x="3" y="3" width="7" height="9"/><rect x="14" y="3" width="7" height="9"/><rect x="3" y="15" width="7" height="6"/><rect x="14" y="15" width="7" height="6"/>',
        'list'   => '<rect x="3" y="3" width="8" height="18"/><path d="M14 5h7M14 9h7M14 13h5"/>',
        'page'   => '<rect x="5" y="2" width="14" height="20"/><path d="M8 6h8M8 10h8M8 14h5"/>',
    ];
    return '<svg viewBox="0 0 24 24" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="1.7">' . $paths[$key] . '</svg>';
}

/**
 * Paywall for a locked (archived) issue, page or article. $peek is an optional
 * storage-relative image (a thumbnail) shown faint behind the card as a teaser.
 */
function paywall_block(string $peek = ''): void
{
    $sub = current_subscriber();
    echo '<section class="paywall">';
    if ($peek !== '') {
        echo '<img class="paywall__peek" src="' . h(media($peek)) . '" alt="" aria-hidden="true">';
    }
    echo '<div class="paywall__card">'
       . '<svg class="paywall__lock" viewBox="0 0 24 24" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="1.7">'
       . '<rect x="4" y="10" width="16" height="11" rx="2"/><path d="M8 10V7a4 4 0 0 1 8 0v3"/></svg>'
       . '<h2>This edition is for subscribers</h2>'
       . '<p>Today\'s paper is free for everyone. Subscribe to open this and the full archive — every past edition, article text and page downloads.</p>'
       . '<div class="paywall__actions">'
       . '<a class="pw-btn pw-btn--primary" href="' . h(url('subscribe.php')) . '">See plans</a>';
    if (!$sub) {
        $next = $_SERVER['REQUEST_URI'] ?? '';
        echo '<a class="pw-btn pw-btn--ghost" href="' . h(url('account/login.php', ['next' => $next])) . '">Sign in</a>';
    }
    echo '</div></div></section>';
}

function no_issue(array $ctx): void
{
    $msg = $ctx['edition']
        ? (preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)($ctx['requested_date'] ?? '')) ? 'There is no ' . h($ctx['edition']['name']) . ' paper for ' . h(fmt_date($ctx['requested_date'])) . '. Pick another date above.'
                                          : 'The ' . h($ctx['edition']['name']) . ' edition has no published papers yet.')
        : 'No editions are set up yet.';
    echo '<section class="empty"><p>' . $msg . '</p></section>';
}
