<?php
declare(strict_types=1);
require __DIR__ . '/../inc/bootstrap.php';
require __DIR__ . '/../inc/mailer.php';
require __DIR__ . '/../inc/account_layout.php';

$email = '';
$done = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    $email = trim((string)($_POST['email'] ?? ''));
    $row = filter_var($email, FILTER_VALIDATE_EMAIL) ? find_subscriber_by_email($email) : null;
    if ($row && $row['status'] === 'active') {
        send_reset_email((int)$row['id'], (string)$row['email']);
    }
    // Always show the same message so the form can't be used to probe which e-mails exist.
    $done = true;
}

account_header('Forgot password');
?>
<div class="acc-card acc-card--narrow">
  <h1>Forgot your password?</h1>
  <?php if ($done): ?>
    <p class="acc-flash acc-flash--ok">If an account exists for <strong><?= h($email) ?></strong>, we've sent a reset link. It's valid for one hour.</p>
    <p class="acc-sub"><a href="<?= h(url('account/login.php')) ?>">Back to sign in</a></p>
  <?php else: ?>
    <p class="acc-sub">Enter your e-mail and we'll send you a link to set a new password.</p>
    <form method="post" class="acc-form">
      <?= csrf_field() ?>
      <label>E-mail <input name="email" type="email" value="<?= h($email) ?>" autocomplete="email" required autofocus></label>
      <button class="acc-btn acc-btn--primary">Send reset link</button>
    </form>
  <?php endif; ?>
</div>
<?php account_footer();
