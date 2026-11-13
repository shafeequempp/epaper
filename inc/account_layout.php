<?php
declare(strict_types=1);

function account_header(string $title, bool $wide = false): void
{
    $site = (string)cfg('site_name');
    $sub  = current_subscriber();
    ?><!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= h($title) ?> | <?= h($site) ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Anek+Malayalam:wght@400;500;600;700&family=Noto+Serif+Malayalam:wght@400;600;700&display=swap">
<link rel="stylesheet" href="<?= h(url('assets/epaper.css')) ?>?v=<?= @filemtime(__DIR__ . '/../assets/epaper.css') ?>">
<link rel="stylesheet" href="<?= h(url('assets/account.css')) ?>?v=<?= @filemtime(__DIR__ . '/../assets/account.css') ?>">
<script src="<?= h(url('assets/noinspect.js')) ?>?v=<?= (int)@filemtime(__DIR__ . '/../assets/noinspect.js') ?>" defer></script>
</head>
<body class="acc-body">
<header class="bar">
  <a class="brand" href="<?= h(url('index.php')) ?>">
    <span class="brand__name">Mathrubhumi</span><span class="brand__sub">ePaper</span>
  </a>
  <nav class="acc-topnav">
    <a href="<?= h(url('index.php')) ?>">Read paper</a>
    <?php if ($sub): ?>
      <a href="<?= h(url('subscribe.php')) ?>">Plans</a>
      <a href="<?= h(url('account/index.php')) ?>">My account</a>
      <a href="<?= h(url('account/logout.php')) ?>">Sign out</a>
    <?php else: ?>
      <a href="<?= h(url('subscribe.php')) ?>">Subscribe</a>
      <a href="<?= h(url('account/login.php')) ?>">Sign in</a>
    <?php endif; ?>
  </nav>
</header>
<main class="acc-main<?= $wide ? ' acc-main--wide' : '' ?>">
<?php foreach (flash() as $f): ?>
  <p class="acc-flash acc-flash--<?= h($f['type']) ?>"><?= $f['msg'] ?></p>
<?php endforeach;
}

function account_footer(): void
{
    echo "</main>\n<footer class=\"acc-foot\"><p>" . h((string)cfg('site_name')) . "</p></footer>\n</body>\n</html>";
}

/** Shown to non-subscribers when email verification is still pending. */
function verify_banner(?array $sub): void
{
    if ($sub && empty($sub['email_verified_at'])) {
        echo '<p class="acc-flash acc-flash--warn">Your e-mail isn\'t verified yet. '
           . '<a href="' . h(url('account/verify.php', ['resend' => 1])) . '">Resend the link</a>.</p>';
    }
}
