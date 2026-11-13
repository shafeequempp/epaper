<?php
declare(strict_types=1);
require __DIR__ . '/../inc/bootstrap.php';
require __DIR__ . '/../inc/admin_layout.php';
require __DIR__ . '/../inc/razorpay.php';

require_login('admin');

$id = (int)($_GET['id'] ?? 0);
$sub = q('SELECT * FROM subscribers WHERE id = ?', [$id])->fetch();
if (!$sub) {
    http_response_code(404);
    admin_header('Subscriber', 'subscribers');
    echo '<p class="a-muted">Subscriber not found. <a href="' . h(url('admin/subscribers.php')) . '">Back to subscribers</a>.</p>';
    admin_footer();
    exit;
}
$back = url('admin/subscriber.php', ['id' => $id]);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    $action = (string)($_POST['action'] ?? '');

    if ($action === 'block') {
        q("UPDATE subscribers SET status = 'blocked' WHERE id = ?", [$id]);
        flash('Account blocked.');
    } elseif ($action === 'unblock') {
        q("UPDATE subscribers SET status = 'active' WHERE id = ?", [$id]);
        flash('Account unblocked.');
    } elseif ($action === 'grant') {
        $planId = (int)($_POST['plan_id'] ?? 0);
        $days   = (int)($_POST['days'] ?? 0);
        $edId   = (int)($_POST['edition_id'] ?? 0);   // 0 = all editions (NULL)
        $plan   = q('SELECT id FROM plans WHERE id = ?', [$planId])->fetch();
        $edName = 'all editions';
        if ($edId > 0) {
            $edRow = q('SELECT id, name FROM editions WHERE id = ? AND is_main = 1', [$edId])->fetch();
            if (!$edRow) { $edId = -1; } else { $edName = (string)$edRow['name']; }
        }
        if (!$plan) {
            flash('Choose a plan to record the grant against.', 'error');
        } elseif ($edId === -1) {
            flash('Pick a valid main edition, or choose All editions.', 'error');
        } else {
            $end = $days > 0 ? (new DateTime())->modify("+$days days")->format('Y-m-d H:i:s') : null;
            q("INSERT INTO subscriptions (subscriber_id, plan_id, edition_id, status, current_start, current_end, source, note)
               VALUES (?, ?, ?, 'manual', NOW(), ?, 'manual', ?)",
              [$id, $planId, ($edId > 0 ? $edId : null), $end, trim((string)($_POST['note'] ?? 'Granted by admin'))]);
            flash(($days > 0 ? "Granted $days days of access" : 'Granted indefinite access') . ' to ' . $edName . '.');
        }
    } elseif ($action === 'cancel_sub') {
        $row = q('SELECT * FROM subscriptions WHERE id = ? AND subscriber_id = ?', [(int)($_POST['sub_id'] ?? 0), $id])->fetch();
        if ($row) {
            if ($row['source'] === 'razorpay' && !empty($row['razorpay_subscription_id']) && razorpay_enabled()) {
                try { rzp_cancel_subscription((string)$row['razorpay_subscription_id'], false); } catch (Throwable $e) {}
            }
            q("UPDATE subscriptions SET status = 'cancelled', current_end = NOW() WHERE id = ?", [(int)$row['id']]);
            flash('Subscription cancelled.');
        }
    } elseif ($action === 'extend_sub') {
        $days = max(1, (int)($_POST['days'] ?? 0));
        $row = q('SELECT id FROM subscriptions WHERE id = ? AND subscriber_id = ?', [(int)($_POST['sub_id'] ?? 0), $id])->fetch();
        if ($row) {
            q("UPDATE subscriptions
               SET current_end = DATE_ADD(GREATEST(COALESCE(current_end, NOW()), NOW()), INTERVAL ? DAY),
                   status = CASE WHEN status IN ('cancelled','expired') THEN 'manual' ELSE status END
               WHERE id = ?", [$days, (int)$row['id']]);
            flash("Extended by $days days.");
        }
    }
    redirect($back);
}

$plans = q('SELECT * FROM plans ORDER BY sort_order')->fetchAll();
$mains = main_editions();

// full subscription history
$subs = q("SELECT s.*, p.name AS plan_name, e.name AS edition_name
           FROM subscriptions s
           JOIN plans p ON p.id = s.plan_id
           LEFT JOIN editions e ON e.id = s.edition_id
           WHERE s.subscriber_id = ?
           ORDER BY s.created_at DESC", [$id])->fetchAll();
$payments = q("SELECT pay.*, p.name AS plan_name
               FROM payments pay
               LEFT JOIN subscriptions s ON s.id = pay.subscription_id
               LEFT JOIN plans p ON p.id = s.plan_id
               WHERE pay.subscriber_id = ?
               ORDER BY pay.created_at DESC LIMIT 100", [$id])->fetchAll();
$archives = q("SELECT ap.*, e.name AS edition_name
               FROM archive_purchases ap
               LEFT JOIN editions e ON e.id = ap.edition_id
               WHERE ap.subscriber_id = ?
               ORDER BY ap.created_at DESC LIMIT 100", [$id])->fetchAll();
$logins = q("SELECT * FROM login_logs WHERE subscriber_id = ? ORDER BY created_at DESC LIMIT 50", [$id])->fetchAll();
$loginCount = (int)q("SELECT COUNT(*) FROM login_logs WHERE subscriber_id = ?", [$id])->fetchColumn();

function sub_is_active(array $s): bool
{
    $live = $s['current_end'] === null || strtotime($s['current_end']) > time();
    return $live && in_array($s['status'], ['active', 'authenticated', 'manual'], true);
}
function dt(?string $v): string { return $v ? date('j M Y, H:i', strtotime($v)) : '—'; }

admin_header('Subscriber · ' . ($sub['name'] ?: $sub['email']), 'subscribers');
?>
<p class="a-muted"><a href="<?= h(url('admin/subscribers.php')) ?>">&larr; All subscribers</a></p>

<div class="a-head">
  <h1><?= h($sub['name'] ?: '—') ?>
    <?php if ($sub['status'] === 'blocked'): ?><span class="a-badge a-badge--off">blocked</span><?php endif; ?>
  </h1>
  <form method="post" class="a-inline"><?= csrf_field() ?>
    <?php if ($sub['status'] === 'blocked'): ?>
      <button class="a-btn" name="action" value="unblock">Unblock account</button>
    <?php else: ?>
      <button class="a-btn a-btn--danger" name="action" value="block" onclick="return confirm('Block this account? They won\'t be able to sign in.')">Block account</button>
    <?php endif; ?>
  </form>
</div>
<p class="a-muted">
  <?= h($sub['email']) ?> · <?= $sub['email_verified_at'] ? 'verified' : 'not verified' ?> ·
  joined <?= h(dt($sub['created_at'])) ?> ·
  trial <?= $sub['trial_started_at'] ? 'started ' . h(dt($sub['trial_started_at'])) : 'not started' ?> ·
  <?= (int)$loginCount ?> login(s)
</p>

<!-- ---- Grant a manual subscription ---- -->
<div class="a-card">
  <h2>Grant access (manual)</h2>
  <form method="post" class="a-row"><?= csrf_field() ?>
    <label>Edition
      <select name="edition_id">
        <option value="0">All editions (full access)</option>
        <?php foreach ($mains as $m): ?><option value="<?= (int)$m['id'] ?>"><?= h($m['name']) ?></option><?php endforeach; ?>
      </select>
    </label>
    <label>Plan
      <select name="plan_id" class="js-plan">
        <?php foreach ($plans as $pl): ?><option value="<?= (int)$pl['id'] ?>" data-days="<?= (int)plan_validity_days($pl) ?>"><?= h($pl['name']) ?> (<?= (int)plan_validity_days($pl) ?>d)</option><?php endforeach; ?>
      </select>
    </label>
    <label>Days (0 = no expiry) <input name="days" type="number" min="0" value="<?= $plans ? (int)plan_validity_days($plans[0]) : 30 ?>" class="js-days" style="width:6rem"></label>
    <label style="flex-basis:100%">Note <input name="note" placeholder="Complimentary"></label>
    <button class="a-btn a-btn--primary" name="action" value="grant">Grant access</button>
  </form>
</div>

<!-- ---- Subscriptions (history + manage) ---- -->
<h2>Subscriptions</h2>
<table class="a-table">
  <thead><tr><th>Edition</th><th>Plan</th><th>Source</th><th>Status</th><th>Started</th><th>Ends</th><th>Note</th><th></th></tr></thead>
  <tbody>
  <?php foreach ($subs as $s): $active = sub_is_active($s); ?>
    <tr>
      <td><?= h($s['edition_name'] ?: 'All editions') ?></td>
      <td><?= h($s['plan_name']) ?></td>
      <td><?= h($s['source']) ?></td>
      <td><span class="a-status a-status--<?= $active ? 'published' : 'draft' ?>"><?= $active ? 'Active' : h(ucfirst($s['status'])) ?></span></td>
      <td><?= h(dt($s['current_start'])) ?></td>
      <td><?= $s['current_end'] ? h(dt($s['current_end'])) : 'no expiry' ?></td>
      <td class="a-muted"><?= h($s['note'] ?? '') ?></td>
      <td class="a-actions">
        <?php if ($active): ?>
          <form method="post" class="a-inline"><?= csrf_field() ?>
            <input type="hidden" name="sub_id" value="<?= (int)$s['id'] ?>"><input type="hidden" name="days" value="30">
            <button class="a-link" name="action" value="extend_sub">+30d</button>
          </form>
          <form method="post" class="a-inline"><?= csrf_field() ?>
            <input type="hidden" name="sub_id" value="<?= (int)$s['id'] ?>">
            <button class="a-link" name="action" value="cancel_sub" onclick="return confirm('Cancel this subscription now?')">Cancel</button>
          </form>
        <?php endif; ?>
      </td>
    </tr>
  <?php endforeach; ?>
  <?php if (!$subs): ?><tr><td colspan="8" class="a-muted">No subscriptions.</td></tr><?php endif; ?>
  </tbody>
</table>

<!-- ---- Payments ---- -->
<h2>Payments</h2>
<table class="a-table">
  <thead><tr><th>Date</th><th>Plan</th><th>Amount</th><th>Method</th><th>Status</th><th>Razorpay payment</th></tr></thead>
  <tbody>
  <?php foreach ($payments as $pm): ?>
    <tr>
      <td><?= h(dt($pm['created_at'])) ?></td>
      <td><?= h($pm['plan_name'] ?? '—') ?></td>
      <td><?= h(money_inr((int)$pm['amount_paise'])) ?></td>
      <td><?= h($pm['method'] ?: '—') ?></td>
      <td><?= h($pm['status']) ?></td>
      <td class="a-muted"><?= h($pm['razorpay_payment_id'] ?: '—') ?></td>
    </tr>
  <?php endforeach; ?>
  <?php if (!$payments): ?><tr><td colspan="6" class="a-muted">No payments recorded.</td></tr><?php endif; ?>
  </tbody>
</table>

<!-- ---- Archive purchases ---- -->
<h2>Archive purchases (single old issues)</h2>
<table class="a-table">
  <thead><tr><th>Bought</th><th>Edition</th><th>Issue date</th><th>Amount</th><th>Status</th><th>Razorpay</th></tr></thead>
  <tbody>
  <?php foreach ($archives as $ap): ?>
    <tr>
      <td><?= h(dt($ap['created_at'])) ?></td>
      <td><?= h($ap['edition_name'] ?: '—') ?></td>
      <td><?= h(date('j M Y', strtotime($ap['issue_date']))) ?></td>
      <td><?= h(money_inr((int)$ap['amount_paise'])) ?></td>
      <td><?= h($ap['status']) ?></td>
      <td class="a-muted"><?= h($ap['razorpay_payment_id'] ?: ($ap['razorpay_order_id'] ?: '—')) ?></td>
    </tr>
  <?php endforeach; ?>
  <?php if (!$archives): ?><tr><td colspan="6" class="a-muted">No archive purchases.</td></tr><?php endif; ?>
  </tbody>
</table>

<!-- ---- Login log ---- -->
<h2>Login history <span class="a-muted">(<?= (int)$loginCount ?> total, latest 50)</span></h2>
<table class="a-table">
  <thead><tr><th>When</th><th>IP</th><th>Device / browser</th></tr></thead>
  <tbody>
  <?php foreach ($logins as $lg): ?>
    <tr>
      <td><?= h(dt($lg['created_at'])) ?></td>
      <td><?= h($lg['ip'] ?: '—') ?></td>
      <td class="a-muted"><?= h($lg['user_agent'] ?: '—') ?></td>
    </tr>
  <?php endforeach; ?>
  <?php if (!$logins): ?><tr><td colspan="3" class="a-muted">No logins recorded.</td></tr><?php endif; ?>
  </tbody>
</table>

<script>
document.querySelectorAll('.js-plan').forEach(function (sel) {
  sel.addEventListener('change', function () {
    var opt = sel.options[sel.selectedIndex];
    var days = opt && opt.getAttribute('data-days');
    var box = sel.closest('form').querySelector('.js-days');
    if (box && days != null) box.value = days;
  });
});
</script>
<?php admin_footer();
