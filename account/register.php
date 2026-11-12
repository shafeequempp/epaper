<?php
declare(strict_types=1);
require __DIR__ . '/../inc/bootstrap.php';
require __DIR__ . '/../inc/mailer.php';
require __DIR__ . '/../inc/account_layout.php';

if (current_subscriber()) {
    redirect(url('account/index.php'));
}

$next = safe_next($_GET['next'] ?? $_POST['next'] ?? '');
$vals = ['name' => '', 'email' => ''];
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    $vals['name']  = trim((string)($_POST['name'] ?? ''));
    $vals['email'] = trim((string)($_POST['email'] ?? ''));
    $pass    = (string)($_POST['password'] ?? '');
    $confirm = (string)($_POST['confirm'] ?? '');

    if ($vals['name'] === '') {
        $error = 'Please enter your name.';
    } elseif (!filter_var($vals['email'], FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid e-mail address.';
    } elseif (strlen($pass) < 8) {
        $error = 'Password must be at least 8 characters.';
    } elseif ($pass !== $confirm) {
        $error = 'The two passwords do not match.';
    } elseif (find_subscriber_by_email($vals['email'])) {
        $error = 'An account with that e-mail already exists. Try signing in.';
    } else {
        $id = create_subscriber($vals['email'], $pass, $vals['name']);
        send_verify_email($id, $vals['email']);
        $row = q('SELECT id, email, name FROM subscribers WHERE id = ?', [$id])->fetch();
        subscriber_login($row);
        flash('Welcome! We sent a verification link to ' . h($vals['email']) . '.');
        redirect($next ?: url('account/index.php'));
    }
}

account_header('Create account');
?>
<div class="acc-card acc-card--narrow">
  <h1>Create your account</h1>
  <p class="acc-sub">Already have one? <a href="<?= h(url('account/login.php', $next ? ['next' => $next] : [])) ?>">Sign in</a>.</p>
  <?php if ($error): ?><p class="acc-flash acc-flash--error"><?= h($error) ?></p><?php endif; ?>
  <form method="post" class="acc-form">
    <?= csrf_field() ?>
    <?php if ($next): ?><input type="hidden" name="next" value="<?= h($next) ?>"><?php endif; ?>
    <label>Name <input name="name" value="<?= h($vals['name']) ?>" autocomplete="name" required autofocus></label>
    <label>E-mail <input name="email" type="email" value="<?= h($vals['email']) ?>" autocomplete="email" required></label>
    <label>Password <input name="password" type="password" minlength="8" autocomplete="new-password" required></label>
    <label>Confirm password <input name="confirm" type="password" minlength="8" autocomplete="new-password" required></label>
    <button class="acc-btn acc-btn--primary">Create account</button>
  </form>
</div>
<?php account_footer();
