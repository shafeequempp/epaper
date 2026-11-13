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

  <?php
    $stay = ['list' => 'list.php', 'page' => 'page.php'][$view] ?? 'index.php'; // keep the reader's view
    $pv  = can_preview() ? ['preview' => 1] : [];
    $cur = $ctx['issue']['issue_date'] ?? ($ctx['dates'][0] ?? '');
    $mainEd = $ctx['main_edition'] ?? $ctx['edition'];
    $groupEds = $ctx['group_editions'] ?? [];
    $nav = !empty($ctx['issue']) ? group_page_nav($ctx) : [];
    $curPage = (int)($_GET['p'] ?? 0);
    $curEdId = (int)($ctx['edition']['id'] ?? 0);
    $curNavLabel = '';
    foreach ($nav as $g) {
        if ((int)$g['edition']['id'] !== $curEdId) { continue; }
        foreach ($g['pages'] as $pp) {
            if ((int)$pp['page_number'] === $curPage) {
                $curNavLabel = sprintf('%02d', $curPage) . ': ' . page_section_text($pp);
            }
        }
    }
  ?>
  <div class="pick">
    <label class="sr">Edition</label>
    <button type="button" class="pick-ed-btn" data-ed-open aria-haspopup="dialog" aria-expanded="false" aria-controls="ed-modal">
      <svg class="pick-ed-btn__pin" viewBox="0 0 24 24" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="1.7"><path d="M12 21s7-6.3 7-11a7 7 0 1 0-14 0c0 4.7 7 11 7 11Z"/><circle cx="12" cy="10" r="2.5"/></svg>
      <span class="pick-ed-btn__name"><?= h($mainEd ? ($mainEd['name_ml'] ?: $mainEd['name']) : 'Edition') ?></span>
      <svg class="pick-ed-btn__caret" viewBox="0 0 24 24" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="2"><path d="m6 9 6 6 6-6"/></svg>
    </button>
    <noscript>
      <select class="pick-ed" aria-label="Edition" onchange="if(this.value)location.href=this.value">
        <?php foreach (($ctx['main_editions'] ?? []) as $m):
          $sel = $mainEd && (int)$m['id'] === (int)$mainEd['id']; ?>
          <option value="<?= h(url($stay, ['e' => $m['slug']] + $pv)) ?>"<?= $sel ? ' selected' : '' ?>><?= h(edition_label($m)) ?></option>
        <?php endforeach; ?>
      </select>
    </noscript>

    <?php if ($nav): ?>
      <div class="pagenav">
        <button type="button" class="pagenav__btn" data-pagenav-open aria-haspopup="true" aria-expanded="false" aria-controls="pagenav-pop">
          <svg class="pagenav__ic" viewBox="0 0 24 24" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="1.7"><rect x="4" y="3" width="16" height="18" rx="1.5"/><path d="M8 7h8M8 11h8M8 15h5"/></svg>
          <span class="pagenav__cur"><?= h($curNavLabel ?: 'Pages') ?></span>
          <svg class="pagenav__caret" viewBox="0 0 24 24" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="2"><path d="m6 9 6 6 6-6"/></svg>
        </button>
        <div class="pagenav__pop" id="pagenav-pop" role="menu" hidden>
          <?php foreach ($nav as $g): $ge = $g['edition'];
            $gtag = !empty($ge['is_main']) ? '' : (!empty($ge['is_supplement']) ? ' · supplement' : ' · local'); ?>
            <div class="pagenav__group"><?= h($ge['name_ml'] ?: $ge['name']) ?><?= h($gtag) ?></div>
            <?php foreach ($g['pages'] as $pp):
              $isCur = (int)$ge['id'] === $curEdId && (int)$pp['page_number'] === $curPage; ?>
              <a class="pagenav__item<?= $isCur ? ' is-cur' : '' ?>" role="menuitem"
                 href="<?= h(url('page.php', ['e' => $ge['slug'], 'd' => $cur, 'p' => $pp['page_number']] + $pv)) ?>"<?= $isCur ? ' aria-current="page"' : '' ?>>
                <span class="pagenav__no"><?= h(sprintf('%02d', (int)$pp['page_number'])) ?></span>
                <span class="pagenav__sec"><?= h(page_section_text($pp)) ?></span>
              </a>
            <?php endforeach; ?>
          <?php endforeach; ?>
        </div>
      </div>
    <?php endif; ?>

    <form class="pick-dateform" method="get" action="<?= h(url($stay)) ?>">
      <?php foreach ($pv as $k => $v): ?><input type="hidden" name="<?= h($k) ?>" value="<?= h($v) ?>"><?php endforeach; ?>
      <input type="hidden" name="e" value="<?= h($ctx['edition']['slug'] ?? '') ?>">
      <label class="sr" for="pick-d">Date</label>
      <input id="pick-d" class="pick-date" type="date" name="d" value="<?= h($cur) ?>"
        min="<?= h($ctx['dates'] ? end($ctx['dates']) : '') ?>" max="<?= h($ctx['dates'][0] ?? '') ?>"
        data-dates="<?= h(json_encode(array_values($ctx['dates']))) ?>"<?= $ctx['dates'] ? '' : ' disabled' ?>
        onchange="this.form.submit()">
    </form>
  </div>

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

  <?php
    $sub = current_subscriber();
    $subUrl = url('subscribe.php', !empty($ctx['main_edition']) ? ['e' => $ctx['main_edition']['slug']] : []);
    $needsSub = !empty($ctx['edition'])
        ? !subscriber_has_access_to($ctx['edition'])
        : !($sub && has_active_subscription((int)$sub['id']));
  ?>
  <div class="acct">
    <?php if ($sub): ?>
      <?php if ($needsSub): ?>
        <a class="acct__btn" href="<?= h($subUrl) ?>">Subscribe</a>
      <?php endif; ?>
      <a class="acct__link" href="<?= h(url('account/index.php')) ?>"><?= h($sub['name'] ? explode(' ', trim($sub['name']))[0] : 'Account') ?></a>
    <?php else: ?>
      <a class="acct__link" href="<?= h(url('account/login.php')) ?>">Sign in</a>
      <a class="acct__btn" href="<?= h($subUrl) ?>">Subscribe</a>
    <?php endif; ?>
  </div>
</header>
<?php if (!empty($ctx['main_editions'])): ?>
<div class="ed-modal" id="ed-modal" hidden>
  <div class="ed-modal__overlay" data-ed-close></div>
  <div class="ed-modal__panel" role="dialog" aria-modal="true" aria-label="Choose an edition">
    <div class="ed-modal__bar">
      <h2 class="ed-modal__title">Choose edition</h2>
      <button type="button" class="ed-modal__close" data-ed-close aria-label="Close">
        <svg viewBox="0 0 24 24" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="2"><path d="m6 6 12 12M18 6 6 18"/></svg>
      </button>
    </div>
    <div class="ed-grid">
      <?php foreach ($ctx['main_editions'] as $m):
        $sel = $mainEd && (int)$m['id'] === (int)$mainEd['id']; ?>
        <a class="ed-grid__item<?= $sel ? ' is-current' : '' ?>" href="<?= h(url($stay, ['e' => $m['slug']] + $pv)) ?>"<?= $sel ? ' aria-current="true"' : '' ?>>
          <span class="ed-grid__name"><?= h($m['name_ml'] ?: $m['name']) ?></span>
          <?php if ($m['name_ml']): ?><span class="ed-grid__en"><?= h($m['name']) ?></span><?php endif; ?>
          <?php if ($sel): ?><svg class="ed-grid__check" viewBox="0 0 24 24" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="2.4"><path d="m5 12 5 5 9-10"/></svg><?php endif; ?>
        </a>
      <?php endforeach; ?>
    </div>
  </div>
</div>
<?php endif; ?>
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
function paywall_block(string $peek = '', array $edition = []): void
{
    $sub = current_subscriber();
    $edName  = $edition ? (string)($edition['name'] ?? '') : '';
    $subUrl  = url('subscribe.php', $edition && !empty($edition['slug']) ? ['e' => $edition['slug']] : []);
    echo '<section class="paywall">';
    if ($peek !== '') {
        echo '<img class="paywall__peek" src="' . h(media($peek)) . '" alt="" aria-hidden="true">';
    }
    echo '<div class="paywall__card">'
       . '<svg class="paywall__lock" viewBox="0 0 24 24" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="1.7">'
       . '<rect x="4" y="10" width="16" height="11" rx="2"/><path d="M8 10V7a4 4 0 0 1 8 0v3"/></svg>'
       . '<h2>' . ($edName !== '' ? h($edName) . ' is for subscribers' : 'This edition is for subscribers') . '</h2>'
       . '<p>Today\'s paper is free for everyone. '
       . ($edName !== ''
            ? 'Subscribe to <strong>' . h($edName) . '</strong> to open its full archive, all its local sub-editions and supplements, article text and page downloads.'
            : 'Subscribe to open this and the full archive — every past edition, article text and page downloads.')
       . '</p>'
       . '<div class="paywall__actions">'
       . '<a class="pw-btn pw-btn--primary" href="' . h($subUrl) . '">'
       . ($edName !== '' ? 'Subscribe to ' . h($edName) : 'See plans') . '</a>';
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
