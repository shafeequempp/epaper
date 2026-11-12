<?php
declare(strict_types=1);
require __DIR__ . '/../inc/bootstrap.php';
require __DIR__ . '/../inc/admin_layout.php';

$me = require_login('admin');
$roles = [
    'uploader' => 'Uploader: uploads pages',
    'editor'   => 'Editor: also reviews, edits text and publishes',
    'admin'    => 'Admin: everything, including editions and users',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    $action = (string)($_POST['action'] ?? '');
    $id = (int)($_POST['id'] ?? 0);

    if ($action === 'add') {
        $name = trim((string)($_POST['username'] ?? ''));
        $pass = (string)($_POST['password'] ?? '');
        $role = (string)($_POST['role'] ?? 'uploader');
        if (!preg_match('/^[A-Za-z0-9_.-]{3,60}$/', $name)) {
            flash('Username must be 3-60 letters, numbers, dots, dashes or underscores.', 'error');
        } elseif (strlen($pass) < 10) {
            flash('Password must be at least 10 characters.', 'error');
        } elseif (!isset($roles[$role])) {
            flash('Choose a role.', 'error');
        } elseif (q('SELECT id FROM users WHERE username = ?', [$name])->fetch()) {
            flash('The username "' . h($name) . '" is already taken.', 'error');
        } else {
            q('INSERT INTO users (username, password_hash, role) VALUES (?, ?, ?)', [$name, password_hash($pass, PASSWORD_DEFAULT), $role]);
            flash('User ' . h($name) . ' added. Share the password with them privately.');
        }
    } elseif ($action === 'role' && isset($roles[(string)($_POST['role'] ?? '')])) {
        if ($id === $me['id'] && $_POST['role'] !== 'admin') {
            flash('You cannot remove your own admin role. Ask another admin.', 'error');
        } else {
            q('UPDATE users SET role = ? WHERE id = ?', [$_POST['role'], $id]);
            flash('Role updated.');
        }
    } elseif ($action === 'password') {
        $pass = (string)($_POST['password'] ?? '');
        if (strlen($pass) < 10) {
            flash('New password must be at least 10 characters.', 'error');
        } else {
            q('UPDATE users SET password_hash = ? WHERE id = ?', [password_hash($pass, PASSWORD_DEFAULT), $id]);
            flash('Password changed.');
        }
    } elseif ($action === 'delete') {
        if ($id === $me['id']) {
            flash('You cannot delete your own account.', 'error');
        } else {
            q('DELETE FROM users WHERE id = ?', [$id]);
            flash('User deleted.');
        }
    }
    redirect(url('admin/users.php'));
}

$users = q('SELECT id, username, role, created_at FROM users ORDER BY username')->fetchAll();
admin_header('Users', 'users');
?>
<h1>Users</h1>

<form method="post" class="a-card a-row">
  <?= csrf_field() ?>
  <h2 style="flex-basis:100%">Register a user</h2>
  <label>Username <input name="username" autocomplete="off" required pattern="[A-Za-z0-9_.\-]{3,60}"></label>
  <label>Password <input name="password" type="text" autocomplete="new-password" minlength="10" required></label>
  <label>Role
    <select name="role">
      <?php foreach ($roles as $key => $label): ?><option value="<?= h($key) ?>"><?= h($label) ?></option><?php endforeach; ?>
    </select>
  </label>
  <button class="a-btn a-btn--primary" name="action" value="add">Add user</button>
</form>

<table class="a-table">
  <thead><tr><th>Username</th><th>Role</th><th>New password</th><th>Added</th><th></th></tr></thead>
  <tbody>
  <?php foreach ($users as $u): $self = (int)$u['id'] === $me['id']; ?>
    <tr>
      <td><?= h($u['username']) ?><?= $self ? ' <span class="a-muted">(you)</span>' : '' ?></td>
      <td>
        <form method="post" class="a-inline"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$u['id'] ?>">
          <select name="role" onchange="this.form.submit()">
            <?php foreach ($roles as $key => $label): ?>
              <option value="<?= h($key) ?>"<?= $u['role'] === $key ? ' selected' : '' ?>><?= h(ucfirst($key)) ?></option>
            <?php endforeach; ?>
          </select>
          <input type="hidden" name="action" value="role">
        </form>
      </td>
      <td>
        <form method="post" class="a-inline"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$u['id'] ?>">
          <input name="password" type="text" minlength="10" placeholder="At least 10 characters" autocomplete="new-password" required>
          <button class="a-link" name="action" value="password">Change</button>
        </form>
      </td>
      <td><?= h(date('j M Y', strtotime($u['created_at']))) ?></td>
      <td class="a-actions">
        <?php if (!$self): ?>
        <form method="post" class="a-inline" onsubmit="return confirm('Delete <?= h($u['username']) ?>?')">
          <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$u['id'] ?>">
          <button class="a-link" name="action" value="delete">Delete</button>
        </form>
        <?php endif; ?>
      </td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table>
<?php admin_footer();
