<?php
declare(strict_types=1);
require __DIR__ . '/../inc/bootstrap.php';
require __DIR__ . '/../inc/admin_layout.php';
require __DIR__ . '/../inc/razorpay.php';

require_login('admin');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    $action = (string)($_POST['action'] ?? '');
    $id = (int)($_POST['id'] ?? 0);
    $who = q('SELECT * FROM subscribers WHERE id = ?', [$id])->fetch();

    if (!$who) {
        flash('Subscriber not found.', 'error');
    } elseif ($action === 'block') {
        q("UPDATE subscribers SET status = 'blocked' WHERE id = ?", [$id]);
        flash('Account blocked.');
    } elseif ($action === 'unblock') {
        q("UPDATE subscribers SET status = 'active' WHERE id = ?", [$id]);
        flash('Account unblocked.');
    } elseif ($action === 'grant') {
        $planId = (int)($_POST['plan_id'] ?? 0);
        $days = (int)($_POST['days'] ?? 0);
        $edId = (int)($_POST['edition_id'] ?? 0);   // 0 = all editions (NULL)
        $plan = q('SELECT id FROM plans WHERE id = ?', [$planId])->fetch();
        $edName = 'all editions';
        if ($edId > 0) {
            $edRow = q('SELECT id, name FROM editions WHERE id = ? AND is_main = 1', [$edId])->fetch();
            if (!$edRow) {
                $edId = -1; // signals an invalid edition choice
            } else {
                $edName = (string)$edRow['name'];
            }
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
    } elseif ($action === 'revoke') {
        q("UPDATE subscriptions SET status = 'cancelled', current_end = NOW()
           WHERE subscriber_id = ? AND source = 'manual'
             AND status = 'manual' AND (current_end IS NULL OR current_end > NOW())", [$id]);
        flash('Manual access revoked.');
    } elseif ($action === 'cancel_sub') {
        // cancel one specific subscription (any source)
        $subRow = q('SELECT * FROM subscriptions WHERE id = ? AND subscriber_id = ?',
                    [(int)($_POST['sub_id'] ?? 0), $id])->fetch();
        if ($subRow) {
            if ($subRow['source'] === 'razorpay' && !empty($subRow['razorpay_subscription_id']) && razorpay_enabled()) {
                try { rzp_cancel_subscription((string)$subRow['razorpay_subscription_id'], false); } catch (Throwable $e) { /* local cancel still applies */ }
            }
            q("UPDATE subscriptions SET status = 'cancelled', current_end = NOW() WHERE id = ?", [(int)$subRow['id']]);
            flash('Subscription cancelled.');
        } else {
            flash('Subscription not found.', 'error');
        }
    } elseif ($action === 'extend_sub') {
        $days = max(1, (int)($_POST['days'] ?? 0));
        $subRow = q('SELECT * FROM subscriptions WHERE id = ? AND subscriber_id = ?',
                    [(int)($_POST['sub_id'] ?? 0), $id])->fetch();
        if ($subRow) {
            // extend from the later of now or the current end
            q("UPDATE subscriptions
               SET current_end = DATE_ADD(GREATEST(COALESCE(current_end, NOW()), NOW()), INTERVAL ? DAY),
                   status = CASE WHEN status IN ('cancelled','expired') THEN 'manual' ELSE status END
               WHERE id = ?", [$days, (int)$subRow['id']]);
            flash("Extended by $days days.");
        } else {
            flash('Subscription not found.', 'error');
        }
    }
    $q = trim((string)($_GET['q'] ?? ''));
    redirect(url('admin/subscribers.php', $q !== '' ? ['q' => $q] : []));
}

$search = trim((string)($_GET['q'] ?? ''));
if ($search !== '') {
    $like = '%' . addcslashes($search, '%_\\') . '%';
    $subs = q('SELECT * FROM subscribers WHERE email LIKE ? OR name LIKE ? ORDER BY created_at DESC LIMIT 200', [$like, $like])->fetchAll();
} else {
    $subs = q('SELECT * FROM subscribers ORDER BY created_at DESC LIMIT 200')->fetchAll();
}
$plans = q('SELECT * FROM plans ORDER BY sort_order')->fetchAll();
$mains = main_editions();
$total = (int)q('SELECT COUNT(*) FROM subscribers')->fetchColumn();
$activeCount = (int)q("SELECT COUNT(DISTINCT subscriber_id) FROM subscriptions
    WHERE (status IN ('active','authenticated') AND current_end > NOW())
       OR (source='manual' AND (current_end IS NULL OR current_end > NOW()))")->fetchColumn();

admin_header('Subscribers', 'subscribers');
?>
<h1>Subscribers</h1>
<p class="a-muted"><?= $total ?> accounts · <?= $activeCount ?> with active access</p>

<form method="get" class="a-inline" style="margin-bottom:1rem">
  <input name="q" value="<?= h($search) ?>" placeholder="Search e-mail or name">
  <button class="a-btn">Search</button>
  <?php if ($search !== ''): ?><a class="a-link" href="<?= h(url('admin/subscribers.php')) ?>">Clear</a><?php endif; ?>
</form>

<table class="a-table">
  <thead><tr><th>Name / e-mail</th><th>Joined</th><th>Verified</th><th>Access</th><th>Actions</th></tr></thead>
  <tbody>
  <?php foreach ($subs as $s): $acts = active_subscriptions((int)$s['id']); ?>
    <tr>
      <td>
        <strong><?= h($s['name'] ?: '—') ?></strong>
        <?php if ($s['status'] === 'blocked'): ?><span class="a-badge a-badge--off">blocked</span><?php endif; ?>
        <br><span class="a-muted"><?= h($s['email']) ?></span>
      </td>
      <td><?= h(date('j M Y', strtotime($s['created_at']))) ?></td>
      <td><?= $s['email_verified_at'] ? 'Yes' : '<span class="a-muted">No</span>' ?></td>
      <td>
        <?php if ($acts): ?>
          <?php foreach ($acts as $act): ?>
            <div class="a-sub-item">
              <strong><?= h($act['edition_name'] ?: 'All editions') ?></strong>
              <span class="a-muted"><?= h($act['plan_name']) ?><?= $act['source'] === 'manual' ? ' · manual' : '' ?></span><br>
              <span class="a-muted"><?= !empty($act['current_end']) ? 'until ' . h(date('j M Y', strtotime($act['current_end']))) : 'no expiry' ?><?= $act['cancel_at_period_end'] ? ', not renewing' : '' ?></span>
              <div class="a-sub-item__acts">
                <form method="post" class="a-inline"><?= csrf_field() ?>
                  <input type="hidden" name="id" value="<?= (int)$s['id'] ?>">
                  <input type="hidden" name="sub_id" value="<?= (int)$act['id'] ?>">
                  <input type="hidden" name="days" value="30">
                  <button class="a-link" name="action" value="extend_sub">+30 days</button>
                </form>
                <form method="post" class="a-inline"><?= csrf_field() ?>
                  <input type="hidden" name="id" value="<?= (int)$s['id'] ?>">
                  <input type="hidden" name="sub_id" value="<?= (int)$act['id'] ?>">
                  <button class="a-link" name="action" value="cancel_sub" onclick="return confirm('Cancel this subscription now?')">Cancel</button>
                </form>
              </div>
            </div>
          <?php endforeach; ?>
        <?php else: ?>
          <span class="a-muted">none</span>
        <?php endif; ?>
      </td>
      <td class="a-actions">
        <details>
          <summary class="a-link">Manage</summary>
          <div class="a-pop">
            <form method="post" class="a-row">
              <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$s['id'] ?>">
              <label>Edition
                <select name="edition_id">
                  <option value="0">All editions (full access)</option>
                  <?php foreach ($mains as $m): ?><option value="<?= (int)$m['id'] ?>"><?= h($m['name']) ?></option><?php endforeach; ?>
                </select>
              </label>
              <label>Plan
                <select name="plan_id" class="js-plan"><?php foreach ($plans as $pl): ?><option value="<?= (int)$pl['id'] ?>" data-days="<?= (int)plan_validity_days($pl) ?>"><?= h($pl['name']) ?> (<?= (int)plan_validity_days($pl) ?>d)</option><?php endforeach; ?></select>
              </label>
              <label>Days (0 = no expiry) <input name="days" type="number" min="0" value="<?= $plans ? (int)plan_validity_days($plans[0]) : 30 ?>" class="js-days" style="width:6rem"></label>
              <label style="flex-basis:100%">Note <input name="note" placeholder="Complimentary"></label>
              <button class="a-btn" name="action" value="grant">Grant access</button>
            </form>
            <form method="post" class="a-inline"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$s['id'] ?>">
              <button class="a-link" name="action" value="revoke" onclick="return confirm('Revoke manual access?')">Revoke manual access</button>
            </form>
            <form method="post" class="a-inline"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$s['id'] ?>">
              <?php if ($s['status'] === 'blocked'): ?>
                <button class="a-link" name="action" value="unblock">Unblock account</button>
              <?php else: ?>
                <button class="a-link" name="action" value="block" onclick="return confirm('Block this account? They won\'t be able to sign in.')">Block account</button>
              <?php endif; ?>
            </form>
          </div>
        </details>
      </td>
    </tr>
  <?php endforeach; ?>
  <?php if (!$subs): ?><tr><td colspan="5" class="a-muted">No subscribers<?= $search !== '' ? ' match that search' : ' yet' ?>.</td></tr><?php endif; ?>
  </tbody>
</table>
<script>
// Grant form: picking a plan fills in its validity (admin can still override).
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
