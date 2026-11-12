<?php
declare(strict_types=1);
require __DIR__ . '/../inc/bootstrap.php';
require __DIR__ . '/../inc/admin_layout.php';

$hasUsers = (int)q('SELECT COUNT(*) FROM users')->fetchColumn() > 0;
if (!$hasUsers) {
    redirect(url('admin/setup.php'));
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    $u = q('SELECT * FROM users WHERE username = ?', [trim((string)($_POST['username'] ?? ''))])->fetch();
    if ($u && password_verify((string)($_POST['password'] ?? ''), $u['password_hash'])) {
        session_regenerate_id(true);
        $_SESSION['user'] = ['id' => (int)$u['id'], 'username' => $u['username'], 'role' => $u['role']];
        $next = (string)($_GET['next'] ?? '');
        redirect($next !== '' && $next[0] === '/' && strpos($next, '//') !== 0 ? $next : url('admin/index.php'));
    }
    $error = 'Username or password is incorrect.';
}

admin_header('Sign in');
?>
<form class="a-card a-narrow" method="post">
  <h1>Sign in</h1>
  <?= csrf_field() ?>
  <?php if ($error): ?><p class="a-flash a-flash--error"><?= h($error) ?></p><?php endif; ?>
  <label>Username <input name="username" autocomplete="username" required autofocus></label>
  <label>Password <input name="password" type="password" autocomplete="current-password" required></label>
  <button class="a-btn a-btn--primary">Sign in</button>
</form>
<?php admin_footer();
