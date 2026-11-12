<?php
declare(strict_types=1);
require __DIR__ . '/inc/bootstrap.php';
require __DIR__ . '/inc/razorpay.php';
require __DIR__ . '/inc/account_layout.php';

$sub = current_subscriber();
$active = $sub ? active_subscription((int)$sub['id']) : null;
$plans = q('SELECT * FROM plans WHERE is_active = 1 ORDER BY sort_order, amount_paise')->fetchAll();
$ready = razorpay_enabled();

account_header('Subscribe');
?>
<div class="acc-plans">
  <div class="acc-plans__head">
    <h1>Subscribe to the full archive</h1>
    <p class="acc-sub">Today's paper is free for everyone. A subscription unlocks every past edition, article text and downloads.</p>
  </div>

  <?php if ($active): ?>
    <p class="acc-flash acc-flash--ok">You already have an active <strong><?= h($active['plan_name']) ?></strong> subscription.
      <a href="<?= h(url('account/index.php')) ?>">Manage it</a>.</p>
  <?php endif; ?>

  <?php if (!$ready): ?>
    <p class="acc-flash acc-flash--warn">Online payment isn't configured yet. Please check back soon.</p>
  <?php endif; ?>

  <?php if (!$plans): ?>
    <p class="acc-sub">No plans are available right now.</p>
  <?php else: ?>
  <div class="acc-plan-grid">
    <?php foreach ($plans as $p): ?>
      <div class="acc-plan">
        <h2><?= h($p['name']) ?></h2>
        <p class="acc-plan__price"><?= h(money_inr((int)$p['amount_paise'])) ?><span>/ <?= h(plan_cycle_label($p)) ?></span></p>
        <?php if ($p['description']): ?><p class="acc-plan__desc"><?= h($p['description']) ?></p><?php endif; ?>
        <?php if (!$sub): ?>
          <a class="acc-btn acc-btn--primary" href="<?= h(url('account/login.php', ['next' => url('subscribe.php')])) ?>">Sign in to subscribe</a>
        <?php elseif ($active): ?>
          <button class="acc-btn" disabled>Current / unavailable</button>
        <?php elseif (!$ready): ?>
          <button class="acc-btn" disabled>Unavailable</button>
        <?php else: ?>
          <button class="acc-btn acc-btn--primary js-subscribe" data-plan="<?= h($p['slug']) ?>">Subscribe</button>
        <?php endif; ?>
      </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>

  <p class="acc-fineprint">Payments are processed securely by Razorpay. Auto-renewal can be turned off any time from your account.</p>
</div>

<?php if ($sub && $ready && !$active): ?>
<form id="rzp-callback" method="post" action="<?= h(url('account/subscribe_callback.php')) ?>" hidden>
  <?= csrf_field() ?>
  <input type="hidden" name="razorpay_payment_id">
  <input type="hidden" name="razorpay_subscription_id">
  <input type="hidden" name="razorpay_signature">
</form>
<script src="https://checkout.razorpay.com/v1/checkout.js"></script>
<script>
  window.EPAPER_SUBSCRIBE = {
    checkoutUrl: <?= json_encode(url('api/checkout.php')) ?>,
    csrf: <?= json_encode(csrf_token()) ?>,
    keyId: <?= json_encode(rzp_cfg('key_id')) ?>,
    siteName: <?= json_encode((string)cfg('site_name')) ?>,
    prefill: { name: <?= json_encode($sub['name']) ?>, email: <?= json_encode($sub['email']) ?> }
  };
</script>
<script src="<?= h(url('assets/subscribe.js')) ?>?v=<?= @filemtime(__DIR__ . '/assets/subscribe.js') ?>"></script>
<?php endif; ?>
<?php account_footer();
