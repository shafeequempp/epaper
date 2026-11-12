<?php
declare(strict_types=1);
require __DIR__ . '/../inc/bootstrap.php';
require __DIR__ . '/../inc/admin_layout.php';

// Only works while there are no users: creates the first admin account.
if ((int)q('SELECT COUNT(*) FROM users')->fetchColumn() > 0) {
    redirect(url('admin/login.php'));
}
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    $name = trim((string)($_POST['username'] ?? ''));
    $pass = (string)($_POST['password'] ?? '');
    if (!preg_match('/^[A-Za-z0-9_.-]{3,60}$/', $name)) {
        $error = 'Username must be 3-60 letters, numbers, dots, dashes or underscores.';
    } elseif (strlen($pass) < 10) {
        $error = 'Password must be at least 10 characters.';
    } else {
        q('INSERT INTO users (username, password_hash, role) VALUES (?, ?, ?)', [$name, password_hash($pass, PASSWORD_DEFAULT), 'admin']);
        flash('Admin account created. Sign in to continue.');
        redirect(url('admin/login.php'));
    }
}
admin_header('Create admin');
?>
<form class="a-card a-narrow" method="post">
  <h1>Create the first admin</h1>
  <p class="a-muted">This page works once, while no accounts exist.</p>
  <?= csrf_field() ?>
  <?php if ($error): ?><p class="a-flash a-flash--error"><?= h($error) ?></p><?php endif; ?>
  <label>Username <input name="username" required></label>
  <label>Password <input name="password" type="password" minlength="10" required></label>
  <button class="a-btn a-btn--primary">Create admin</button>
</form>
<?php admin_footer();
