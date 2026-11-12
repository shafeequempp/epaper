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
    if ($u && in_array($u['role'], ['editor', 'admin'], true)) {
        $nav['watermark'] = ['Watermark', url('admin/watermark.php')];
    }
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
