<?php
declare(strict_types=1);
require __DIR__ . '/../inc/bootstrap.php';
require __DIR__ . '/../inc/admin_layout.php';

require_login('admin');

$search = trim((string)($_GET['q'] ?? ''));
if ($search !== '') {
    $like = '%' . addcslashes($search, '%_\\') . '%';
    $subs = q('SELECT * FROM subscribers WHERE email LIKE ? OR name LIKE ? ORDER BY created_at DESC LIMIT 200', [$like, $like])->fetchAll();
} else {
    $subs = q('SELECT * FROM subscribers ORDER BY created_at DESC LIMIT 200')->fetchAll();
}
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
  <thead><tr><th>Name / e-mail</th><th>Joined</th><th>Verified</th><th>Access</th><th></th></tr></thead>
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
            <div><strong><?= h($act['edition_name'] ?: 'All editions') ?></strong>
              <span class="a-muted"><?= h($act['plan_name']) ?><?= $act['source'] === 'manual' ? ' · manual' : '' ?><?= !empty($act['current_end']) ? ' · until ' . h(date('j M Y', strtotime($act['current_end']))) : '' ?></span>
            </div>
          <?php endforeach; ?>
        <?php else: ?>
          <span class="a-muted">none</span>
        <?php endif; ?>
      </td>
      <td class="a-actions"><a class="a-btn a-btn--sm" href="<?= h(url('admin/subscriber.php', ['id' => (int)$s['id']])) ?>">Manage</a></td>
    </tr>
  <?php endforeach; ?>
  <?php if (!$subs): ?><tr><td colspan="5" class="a-muted">No subscribers<?= $search !== '' ? ' match that search' : ' yet' ?>.</td></tr><?php endif; ?>
  </tbody>
</table>
<?php admin_footer();
