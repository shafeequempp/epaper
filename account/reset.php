<?php
declare(strict_types=1);
require __DIR__ . '/../inc/bootstrap.php';
require __DIR__ . '/../inc/account_layout.php';

$token = (string)($_GET['token'] ?? $_POST['token'] ?? '');
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    $pass    = (string)($_POST['password'] ?? '');
    $confirm = (string)($_POST['confirm'] ?? '');
    if (strlen($pass) < 8) {
        $error = 'Password must be at least 8 characters.';
    } elseif ($pass !== $confirm) {
        $error = 'The two passwords do not match.';
    } else {
        $id = $token !== '' ? subscriber_consume_token($token, 'reset') : null;
        if (!$id) {
            $error = 'That reset link is invalid or has expired. Request a new one.';
        } else {
            q('UPDATE subscribers SET password_hash = ? WHERE id = ?', [password_hash($pass, PASSWORD_DEFAULT), $id]);
            flash('Your password has been changed. Please sign in.');
            redirect(url('account/login.php'));
        }
    }
}

account_header('Reset password');
?>
<div class="acc-card acc-card--narrow">
  <h1>Choose a new password</h1>
  <?php if ($error): ?><p class="acc-flash acc-flash--error"><?= h($error) ?></p><?php endif; ?>
  <?php if ($token === ''): ?>
    <p class="acc-sub">This page needs a reset link. <a href="<?= h(url('account/forgot.php')) ?>">Request one</a>.</p>
  <?php else: ?>
    <form method="post" class="acc-form">
      <?= csrf_field() ?>
      <input type="hidden" name="token" value="<?= h($token) ?>">
      <label>New password <input name="password" type="password" minlength="8" autocomplete="new-password" required autofocus></label>
      <label>Confirm password <input name="confirm" type="password" minlength="8" autocomplete="new-password" required></label>
      <button class="acc-btn acc-btn--primary">Change password</button>
    </form>
  <?php endif; ?>
</div>
<?php account_footer();
