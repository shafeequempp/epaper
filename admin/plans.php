<?php
declare(strict_types=1);
require __DIR__ . '/../inc/bootstrap.php';
require __DIR__ . '/../inc/razorpay.php';
require __DIR__ . '/../inc/admin_layout.php';

require_login('admin');

$periods = ['daily' => 'day', 'weekly' => 'week', 'monthly' => 'month', 'yearly' => 'year'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    $action = (string)($_POST['action'] ?? '');
    $id = (int)($_POST['id'] ?? 0);

    if ($action === 'add' || $action === 'edit') {
        $name   = trim((string)($_POST['name'] ?? ''));
        $slug   = strtolower(trim((string)($_POST['slug'] ?? '')));
        $desc   = trim((string)($_POST['description'] ?? ''));
        $rupees = (float)($_POST['amount'] ?? 0);
        $paise  = (int)round($rupees * 100);
        $period = (string)($_POST['period'] ?? 'monthly');
        $interval = max(1, (int)($_POST['interval_count'] ?? 1));
        $total  = max(1, (int)($_POST['total_count'] ?? 120));
        $active = isset($_POST['is_active']) ? 1 : 0;
        $sort   = (int)($_POST['sort_order'] ?? 0);

        if ($name === '' || !preg_match('/^[a-z0-9-]{2,60}$/', $slug) || $paise < 100 || !isset($periods[$period])) {
            flash('Check the plan details: slug is 2-60 of a-z 0-9 -, and amount is at least ₹1.', 'error');
        } elseif ($action === 'add') {
            if (q('SELECT id FROM plans WHERE slug = ?', [$slug])->fetch()) {
                flash('A plan with that slug already exists.', 'error');
            } else {
                q('INSERT INTO plans (slug, name, description, amount_paise, period, interval_count, total_count, is_active, sort_order)
                   VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)',
                  [$slug, $name, $desc, $paise, $period, $interval, $total, $active, $sort]);
                flash('Plan "' . h($name) . '" added.');
            }
        } else {
            $old = q('SELECT * FROM plans WHERE id = ?', [$id])->fetch();
            if (!$old) {
                flash('Plan not found.', 'error');
            } else {
                // Razorpay plans are immutable: if price/period/interval changed, drop the link so a
                // fresh Razorpay plan is provisioned on the next subscription. Existing subscribers keep theirs.
                $billingChanged = (int)$old['amount_paise'] !== $paise
                    || $old['period'] !== $period || (int)$old['interval_count'] !== $interval;
                $rzpId = $billingChanged ? null : $old['razorpay_plan_id'];
                q('UPDATE plans SET slug=?, name=?, description=?, amount_paise=?, period=?, interval_count=?,
                     total_count=?, is_active=?, sort_order=?, razorpay_plan_id=? WHERE id=?',
                  [$slug, $name, $desc, $paise, $period, $interval, $total, $active, $sort, $rzpId, $id]);
                flash($billingChanged
                    ? 'Plan updated. Billing changed, so new subscribers will get a new Razorpay plan; current subscribers are unaffected.'
                    : 'Plan updated.');
            }
        }
    } elseif ($action === 'provision') {
        $plan = q('SELECT * FROM plans WHERE id = ?', [$id])->fetch();
        if (!$plan) {
            flash('Plan not found.', 'error');
        } elseif (!razorpay_enabled()) {
            flash('Set your Razorpay keys in config.php first.', 'error');
        } elseif ($plan['razorpay_plan_id']) {
            flash('This plan is already linked to Razorpay.', 'error');
        } else {
            try {
                $rzpId = rzp_create_plan($plan);
                q('UPDATE plans SET razorpay_plan_id = ? WHERE id = ?', [$rzpId, $id]);
                flash('Provisioned in Razorpay (' . h($rzpId) . ').');
            } catch (Throwable $e) {
                flash('Razorpay error: ' . h($e->getMessage()), 'error');
            }
        }
    }
    redirect(url('admin/plans.php'));
}

$plans = q('SELECT * FROM plans ORDER BY sort_order, amount_paise')->fetchAll();
admin_header('Plans', 'plans');
?>
<h1>Subscription plans</h1>
<?php if (!razorpay_enabled()): ?>
  <p class="a-flash a-flash--error">Razorpay keys are not set in <code>config.php</code>. Plans are editable, but readers can't pay until you add <code>razorpay.key_id</code> and <code>key_secret</code>.</p>
<?php endif; ?>

<table class="a-table">
  <thead><tr><th>Name</th><th>Price</th><th>Billing</th><th>Active</th><th>Razorpay</th><th></th></tr></thead>
  <tbody>
  <?php foreach ($plans as $p): ?>
    <tr>
      <td><strong><?= h($p['name']) ?></strong><br><span class="a-muted"><?= h($p['slug']) ?></span></td>
      <td><?= h(money_inr((int)$p['amount_paise'])) ?></td>
      <td>every <?= (int)$p['interval_count'] > 1 ? (int)$p['interval_count'] . ' ' : '' ?><?= h($periods[$p['period']]) ?><?= (int)$p['interval_count'] > 1 ? 's' : '' ?><br><span class="a-muted"><?= (int)$p['total_count'] ?> cycles</span></td>
      <td><?= $p['is_active'] ? 'Yes' : 'No' ?></td>
      <td>
        <?php if ($p['razorpay_plan_id']): ?>
          <span class="a-muted"><?= h($p['razorpay_plan_id']) ?></span>
        <?php else: ?>
          <form method="post" class="a-inline"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
            <button class="a-link" name="action" value="provision"<?= razorpay_enabled() ? '' : ' disabled' ?>>Provision now</button>
          </form>
        <?php endif; ?>
      </td>
      <td><a class="a-link" href="#edit-<?= (int)$p['id'] ?>">Edit</a></td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table>

<?php foreach ($plans as $p): ?>
<form method="post" class="a-card a-row" id="edit-<?= (int)$p['id'] ?>">
  <?= csrf_field() ?>
  <input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
  <h2 style="flex-basis:100%">Edit: <?= h($p['name']) ?></h2>
  <label>Name <input name="name" value="<?= h($p['name']) ?>" required></label>
  <label>Slug <input name="slug" value="<?= h($p['slug']) ?>" pattern="[a-z0-9-]{2,60}" required></label>
  <label style="flex-basis:100%">Description <input name="description" value="<?= h($p['description']) ?>"></label>
  <label>Price (₹) <input name="amount" type="number" step="0.01" min="1" value="<?= h(number_format($p['amount_paise'] / 100, 2, '.', '')) ?>" required></label>
  <label>Period
    <select name="period"><?php foreach ($periods as $key => $lbl): ?><option value="<?= h($key) ?>"<?= $p['period'] === $key ? ' selected' : '' ?>><?= h($lbl) ?></option><?php endforeach; ?></select>
  </label>
  <label>Every N <input name="interval_count" type="number" min="1" value="<?= (int)$p['interval_count'] ?>"></label>
  <label>Billing cycles <input name="total_count" type="number" min="1" value="<?= (int)$p['total_count'] ?>"></label>
  <label>Sort <input name="sort_order" type="number" value="<?= (int)$p['sort_order'] ?>"></label>
  <label class="a-check"><input type="checkbox" name="is_active" value="1"<?= $p['is_active'] ? ' checked' : '' ?>> Active</label>
  <button class="a-btn a-btn--primary" name="action" value="edit">Save</button>
</form>
<?php endforeach; ?>

<form method="post" class="a-card a-row">
  <?= csrf_field() ?>
  <h2 style="flex-basis:100%">Add a plan</h2>
  <label>Name <input name="name" placeholder="Quarterly" required></label>
  <label>Slug <input name="slug" placeholder="quarterly" pattern="[a-z0-9-]{2,60}" required></label>
  <label style="flex-basis:100%">Description <input name="description" placeholder="Full archive access, billed every 3 months"></label>
  <label>Price (₹) <input name="amount" type="number" step="0.01" min="1" placeholder="399" required></label>
  <label>Period
    <select name="period"><?php foreach ($periods as $key => $lbl): ?><option value="<?= h($key) ?>"<?= $key === 'monthly' ? ' selected' : '' ?>><?= h($lbl) ?></option><?php endforeach; ?></select>
  </label>
  <label>Every N <input name="interval_count" type="number" min="1" value="1"></label>
  <label>Billing cycles <input name="total_count" type="number" min="1" value="120"></label>
  <label>Sort <input name="sort_order" type="number" value="0"></label>
  <label class="a-check"><input type="checkbox" name="is_active" value="1" checked> Active</label>
  <button class="a-btn a-btn--primary" name="action" value="add">Add plan</button>
</form>
<?php admin_footer();
