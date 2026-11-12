<?php
declare(strict_types=1);
require __DIR__ . '/../inc/bootstrap.php';
require __DIR__ . '/../inc/account_layout.php';

if (current_subscriber()) {
    redirect(url('account/index.php'));
}

$next = safe_next($_GET['next'] ?? $_POST['next'] ?? '');
$email = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    $email = trim((string)($_POST['email'] ?? ''));
    $pass  = (string)($_POST['password'] ?? '');
    $row = find_subscriber_by_email($email);
    if ($row && $row['status'] === 'active' && password_verify($pass, $row['password_hash'])) {
        subscriber_login($row);
        redirect($next ?: url('account/index.php'));
    }
    $error = $row && $row['status'] !== 'active'
        ? 'This account has been blocked. Please contact support.'
        : 'E-mail or password is incorrect.';
}

account_header('Sign in');
?>
<div class="acc-card acc-card--narrow">
  <h1>Sign in</h1>
  <p class="acc-sub">New here? <a href="<?= h(url('account/register.php', $next ? ['next' => $next] : [])) ?>">Create an account</a>.</p>
  <?php if ($error): ?><p class="acc-flash acc-flash--error"><?= h($error) ?></p><?php endif; ?>
  <form method="post" class="acc-form">
    <?= csrf_field() ?>
    <?php if ($next): ?><input type="hidden" name="next" value="<?= h($next) ?>"><?php endif; ?>
    <label>E-mail <input name="email" type="email" value="<?= h($email) ?>" autocomplete="email" required autofocus></label>
    <label>Password <input name="password" type="password" autocomplete="current-password" required></label>
    <button class="acc-btn acc-btn--primary">Sign in</button>
  </form>
  <p class="acc-sub"><a href="<?= h(url('account/forgot.php')) ?>">Forgot your password?</a></p>
</div>
<?php account_footer();
