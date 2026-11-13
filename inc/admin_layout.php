<?php
declare(strict_types=1);

function admin_header(string $title, string $active = ''): void
{
    $u = current_user();
    $nav = [
        'issues'   => ['Issues', url('admin/index.php')],
        'upload'   => ['Upload pages', url('admin/upload.php')],
        'editions' => ['Editions', url('admin/editions.php')],
    ];
    // Watermark menu hidden from the nav (the feature still applies via config.php
    // 'watermark'; the page is still reachable directly at admin/watermark.php).
    // To bring the menu back, uncomment the block below.
    // if ($u && in_array($u['role'], ['editor', 'admin'], true)) {
    //     $nav['watermark'] = ['Watermark', url('admin/watermark.php')];
    // }
    if ($u && $u['role'] === 'admin') {
        $nav['plans']       = ['Plans', url('admin/plans.php')];
        $nav['subscribers'] = ['Subscribers', url('admin/subscribers.php')];
        $nav['users']       = ['Users', url('admin/users.php')];
    }
    ?><!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= h($title) ?> · ePaper dashboard</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Anek+Malayalam:wght@400;500;600;700&display=swap">
<link rel="stylesheet" href="<?= h(url('assets/admin.css')) ?>">
</head>
<body class="admin">
<header class="a-bar">
  <a class="a-brand" href="<?= h(url('admin/index.php')) ?>">ePaper dashboard</a>
  <?php if ($u): ?>
  <nav class="a-nav">
    <?php foreach ($nav as $key => [$label, $href]): ?>
      <a href="<?= h($href) ?>"<?= $active === $key ? ' aria-current="page"' : '' ?>><?= h($label) ?></a>
    <?php endforeach; ?>
  </nav>
  <div class="a-user">
    <a href="<?= h(url('index.php')) ?>" target="_blank" rel="noopener">Open reader</a>
    <span><?= h($u['username']) ?></span>
    <a href="<?= h(url('admin/logout.php')) ?>">Sign out</a>
  </div>
  <?php endif; ?>
</header>
<main class="a-main">
<?php foreach (flash() as $f): ?>
  <p class="a-flash a-flash--<?= h($f['type']) ?>"><?= $f['msg'] ?></p>
<?php endforeach;
}

function admin_footer(): void
{
    echo "</main>\n</body>\n</html>";
}

/**
 * Publish or unpublish every edition of one paper (a date + district group, or a single
 * standalone edition). Only issues that have pages are published. Returns a flash message,
 * or null when the request is not valid. Caller must already require the 'editor' role.
 */
function publish_date_group(string $action, string $date, string $groupName, int $editionId): ?string
{
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
        return null;
    }
    if ($groupName !== '') {
        $scope = 'e.group_name = ?';
        $sp = [$groupName];
    } else {
        $scope = 'i.edition_id = ?';
        $sp = [$editionId];
    }
    if ($action === 'publish_group') {
        q("UPDATE issues i JOIN editions e ON e.id = i.edition_id
           SET i.status = 'published', i.published_at = NOW()
           WHERE i.issue_date = ? AND $scope AND i.status <> 'published'
             AND EXISTS (SELECT 1 FROM pages p WHERE p.issue_id = i.id)",
          array_merge([$date], $sp));
        return 'Published every edition that has pages for ' . fmt_date($date) . '.';
    }
    if ($action === 'unpublish_group') {
        q("UPDATE issues i JOIN editions e ON e.id = i.edition_id
           SET i.status = 'draft', i.published_at = NULL
           WHERE i.issue_date = ? AND $scope AND i.status = 'published'",
          array_merge([$date], $sp));
        return 'Unpublished every edition for ' . fmt_date($date) . '.';
    }
    return null;
}
