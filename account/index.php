<?php
declare(strict_types=1);
require __DIR__ . '/../inc/bootstrap.php';
require __DIR__ . '/../inc/razorpay.php';
require __DIR__ . '/../inc/account_layout.php';

$sub = require_subscriber();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    $action = (string)($_POST['action'] ?? '');

    if ($action === 'cancel') {
        $row = q('SELECT * FROM subscriptions WHERE id = ? AND subscriber_id = ?',
                 [(int)($_POST['subscription_id'] ?? 0), (int)$sub['id']])->fetch();
        if (!$row) {
            flash('Subscription not found.', 'error');
        } elseif ($row['source'] === 'manual') {
            flash('This access was granted by an administrator and cannot be cancelled here.', 'error');
        } else {
            try {
                if ($row['razorpay_subscription_id'] && razorpay_enabled()) {
                    $res = rzp_cancel_subscription((string)$row['razorpay_subscription_id'], true); // at cycle end
                    apply_rzp_subscription($res);
                }
                q('UPDATE subscriptions SET cancel_at_period_end = 1 WHERE id = ?', [$row['id']]);
                flash('Auto-renewal turned off. You keep access until the current period ends.');
            } catch (Throwable $e) {
                flash('Could not cancel with the payment provider: ' . h($e->getMessage()), 'error');
            }
        }
        redirect(url('account/index.php'));
    }

    if ($action === 'password') {
        $cur = (string)($_POST['current'] ?? '');
        $new = (string)($_POST['password'] ?? '');
        $full = q('SELECT password_hash FROM subscribers WHERE id = ?', [(int)$sub['id']])->fetch();
        if (!password_verify($cur, $full['password_hash'])) {
            flash('Your current password is incorrect.', 'error');
        } elseif (strlen($new) < 8) {
            flash('New password must be at least 8 characters.', 'error');
        } else {
            q('UPDATE subscribers SET password_hash = ? WHERE id = ?', [password_hash($new, PASSWORD_DEFAULT), (int)$sub['id']]);
            flash('Password changed.');
        }
        redirect(url('account/index.php'));
    }
}

$actives = active_subscriptions((int)$sub['id']);
$payments = q('SELECT * FROM payments WHERE subscriber_id = ? ORDER BY created_at DESC LIMIT 50', [(int)$sub['id']])->fetchAll();

account_header('My account', true);
verify_banner($sub);
?>
<div class="acc-dash">
  <section class="acc-card">
    <h1>Hello, <?= h($sub['name'] ?: $sub['email']) ?></h1>
    <p class="acc-sub"><?= h($sub['email']) ?></p>

    <?php if ($actives): ?>
      <?php foreach ($actives as $active): ?>
      <div class="acc-status acc-status--on">
        <div>
          <span class="acc-badge acc-badge--on">Active</span>
          <strong><?= h($active['edition_name'] ?: 'All editions') ?></strong>
          <span class="acc-muted"><?= h($active['plan_name']) ?><?= $active['source'] === 'manual' ? ' · granted by admin' : '' ?></span>
        </div>
        <p>
          <?= $active['edition_name']
                ? 'Covers ' . h($active['edition_name']) . ', its local sub-editions and supplements. '
                : 'Covers every edition. ' ?>
          <?php if (!empty($active['current_end'])): ?>
            <?= $active['cancel_at_period_end'] ? 'Access ends on ' : 'Renews on ' ?>
            <strong><?= h(date('j M Y', strtotime($active['current_end']))) ?></strong>.
          <?php else: ?>
            Access does not expire.
          <?php endif; ?>
        </p>
        <?php if ($active['source'] !== 'manual' && !$active['cancel_at_period_end']): ?>
          <form method="post" onsubmit="return confirm('Turn off auto-renewal? You keep access until the period ends.')">
            <?= csrf_field() ?>
            <input type="hidden" name="subscription_id" value="<?= (int)$active['id'] ?>">
            <button class="acc-btn acc-btn--ghost" name="action" value="cancel">Turn off auto-renewal</button>
          </form>
        <?php elseif ($active['cancel_at_period_end']): ?>
          <p class="acc-muted">Auto-renewal is off. <a href="<?= h(url('subscribe.php', $active['edition_slug'] ? ['e' => $active['edition_slug']] : [])) ?>">Resubscribe</a> any time.</p>
        <?php endif; ?>
      </div>
      <?php endforeach; ?>
      <p><a class="acc-btn acc-btn--primary" href="<?= h(url('subscribe.php')) ?>">Add another edition</a></p>
    <?php else: ?>
      <div class="acc-status acc-status--off">
        <p><span class="acc-badge">No active subscription</span></p>
        <p>You can read today's paper for free. Subscribe to open the full archive of your edition.</p>
        <a class="acc-btn acc-btn--primary" href="<?= h(url('subscribe.php')) ?>">See plans</a>
      </div>
    <?php endif; ?>
  </section>

  <?php if ($payments): ?>
  <section class="acc-card">
    <h2>Payments</h2>
    <table class="acc-table">
      <thead><tr><th>Date</th><th>Amount</th><th>Status</th><th>Method</th></tr></thead>
      <tbody>
      <?php foreach ($payments as $p): ?>
        <tr>
          <td><?= h(date('j M Y', strtotime($p['created_at']))) ?></td>
          <td><?= h(money_inr((int)$p['amount_paise'])) ?></td>
          <td><?= h(ucfirst($p['status'])) ?></td>
          <td><?= h($p['method'] ?: '—') ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </section>
  <?php endif; ?>

  <section class="acc-card">
    <h2>Change password</h2>
    <form method="post" class="acc-form">
      <?= csrf_field() ?>
      <label>Current password <input name="current" type="password" autocomplete="current-password" required></label>
      <label>New password <input name="password" type="password" minlength="8" autocomplete="new-password" required></label>
      <button class="acc-btn" name="action" value="password">Change password</button>
    </form>
  </section>
</div>
<?php account_footer();
