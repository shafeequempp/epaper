<?php
declare(strict_types=1);
require __DIR__ . '/inc/bootstrap.php';
require __DIR__ . '/inc/razorpay.php';
require __DIR__ . '/inc/account_layout.php';

$sub   = current_subscriber();
$plans = q('SELECT * FROM plans WHERE is_active = 1 ORDER BY sort_order, amount_paise')->fetchAll();
$ready = razorpay_enabled();
$mains = main_editions();

// Which main edition is being subscribed to? Accept any edition slug (a sub-edition
// resolves to its main) and default to the first main edition.
$selMain = $mains[0] ?? null;
$wanted  = (string)($_GET['e'] ?? '');
if ($wanted !== '') {
    $row = q('SELECT * FROM editions WHERE slug = ? AND is_active = 1', [$wanted])->fetch();
    if ($row) {
        $selMain = main_edition_of($row);
    }
}

$coverage   = $sub ? subscriber_coverage((int)$sub['id']) : ['all' => false, 'mains' => []];
$alreadyHas = $selMain && ($coverage['all'] || in_array((int)$selMain['id'], $coverage['mains'], true));
$archivePaise = archive_price_paise();

account_header('Subscribe');
?>
<div class="acc-plans">
  <div class="acc-plans__head">
    <h1>Subscription plans</h1>
    <p class="acc-sub">Today's paper is free for everyone. A subscription unlocks the full archive of your chosen edition — all its local sub-editions and supplements, article text and page downloads.</p>
  </div>

  <?php if (!$mains): ?>
    <p class="acc-sub">No editions are set up yet.</p>
  <?php else: ?>

  <form class="acc-ed-pick" method="get" action="<?= h(url('subscribe.php')) ?>">
    <label for="sub-edition"><strong>Edition</strong></label>
    <select id="sub-edition" name="e" onchange="this.form.submit()">
      <?php foreach ($mains as $m): ?>
        <option value="<?= h($m['slug']) ?>"<?= $selMain && (int)$m['id'] === (int)$selMain['id'] ? ' selected' : '' ?>>
          <?= h(edition_label($m)) ?></option>
      <?php endforeach; ?>
    </select>
    <noscript><button class="acc-btn">Choose</button></noscript>
  </form>

  <?php if ($coverage['all']): ?>
    <p class="acc-flash acc-flash--ok">You have <strong>all-access</strong> — every edition is already open for you.
      <a href="<?= h(url('account/index.php')) ?>">Manage</a>.</p>
  <?php elseif ($alreadyHas): ?>
    <p class="acc-flash acc-flash--ok">You're already subscribed to <strong><?= h($selMain['name']) ?></strong>.
      <a href="<?= h(url('account/index.php')) ?>">Manage it</a>, or pick another edition above.</p>
  <?php endif; ?>

  <?php if (!$ready): ?>
    <p class="acc-flash acc-flash--warn">Online payment isn't configured yet. Please check back soon.</p>
  <?php endif; ?>

  <?php if (!$plans): ?>
    <p class="acc-sub">No plans are available right now.</p>
  <?php else: ?>
  <div class="plan-grid">
    <?php foreach ($plans as $p):
        $days = plan_validity_days($p);
        $save = plan_savings_pct($p, $plans);
    ?>
      <div class="plan-card">
        <h2 class="plan-card__name"><?= h($p['name']) ?></h2>
        <p class="plan-card__price"><?= h(money_inr((int)$p['amount_paise'])) ?><span>/edition</span></p>
        <p class="plan-card__valid">Valid for <?= (int)$days ?> days</p>
        <div class="plan-card__badge-row">
          <?php if ($save > 0): ?><span class="plan-card__save">Save <?= (int)$save ?>%</span><?php endif; ?>
        </div>
        <div class="plan-card__cta">
          <?php if (!$sub): ?>
            <a class="acc-btn" href="<?= h(url('account/login.php', ['next' => url('subscribe.php', ['e' => $selMain['slug'] ?? ''])])) ?>">Subscribe</a>
          <?php elseif ($alreadyHas): ?>
            <button class="acc-btn" disabled>Subscribed</button>
          <?php elseif (!$ready): ?>
            <button class="acc-btn" disabled>Unavailable</button>
          <?php else: ?>
            <button class="acc-btn js-subscribe"
                    data-plan="<?= h($p['slug']) ?>"
                    data-edition="<?= h($selMain['slug'] ?? '') ?>">Subscribe</button>
          <?php endif; ?>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>

  <!-- ---- Archives: buy a single old issue date ---- -->
  <div class="archive-card">
    <div class="archive-card__text">
      <h2>Archives (Old Issues)</h2>
      <p>Get a copy of any one old issue in digital format — just the day you need.</p>
    </div>
    <form class="archive-card__form" onsubmit="return false">
      <select class="archive-ed" aria-label="Edition">
        <?php foreach ($mains as $m): ?>
          <option value="<?= h($m['slug']) ?>"<?= $selMain && (int)$m['id'] === (int)$selMain['id'] ? ' selected' : '' ?>><?= h($m['name']) ?></option>
        <?php endforeach; ?>
      </select>
      <input class="archive-date" type="date" aria-label="Date" max="<?= h(date('Y-m-d')) ?>">
    </form>
    <div class="archive-card__price">
      <strong><?= h(money_inr($archivePaise)) ?></strong><span>/day (per edition)</span>
    </div>
    <div class="archive-card__cta">
      <?php if (!$sub): ?>
        <a class="acc-btn" href="<?= h(url('account/login.php', ['next' => url('subscribe.php')])) ?>">Subscribe</a>
      <?php elseif (!$ready): ?>
        <button class="acc-btn" disabled>Unavailable</button>
      <?php else: ?>
        <button class="acc-btn js-buy-archive">Subscribe</button>
      <?php endif; ?>
    </div>
  </div>

  <?php endif; ?>

  <p class="acc-fineprint">Payments are processed securely by Razorpay. Auto-renewal can be turned off any time from your account.</p>
</div>

<?php if ($sub && $ready): ?>
<form id="rzp-callback" method="post" action="<?= h(url('account/subscribe_callback.php')) ?>" hidden>
  <?= csrf_field() ?>
  <input type="hidden" name="razorpay_payment_id">
  <input type="hidden" name="razorpay_subscription_id">
  <input type="hidden" name="razorpay_signature">
</form>
<form id="rzp-archive-callback" method="post" action="<?= h(url('account/archive_callback.php')) ?>" hidden>
  <?= csrf_field() ?>
  <input type="hidden" name="razorpay_order_id">
  <input type="hidden" name="razorpay_payment_id">
  <input type="hidden" name="razorpay_signature">
</form>
<script src="https://checkout.razorpay.com/v1/checkout.js"></script>
<script>
  window.EPAPER_SUBSCRIBE = {
    checkoutUrl: <?= json_encode(url('api/checkout.php')) ?>,
    archiveUrl:  <?= json_encode(url('api/checkout_archive.php')) ?>,
    csrf: <?= json_encode(csrf_token()) ?>,
    keyId: <?= json_encode(rzp_cfg('key_id')) ?>,
    siteName: <?= json_encode((string)cfg('site_name')) ?>,
    prefill: { name: <?= json_encode($sub['name']) ?>, email: <?= json_encode($sub['email']) ?> }
  };
</script>
<script src="<?= h(url('assets/subscribe.js')) ?>?v=<?= @filemtime(__DIR__ . '/assets/subscribe.js') ?>"></script>
<?php endif; ?>
<?php account_footer();
