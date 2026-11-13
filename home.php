<?php
// Landing page for visitors who are not signed in: hero + plans. The paper itself is
// behind a sign-in; reader_gate() sends logged-out visitors here.
declare(strict_types=1);
require __DIR__ . '/inc/bootstrap.php';
require __DIR__ . '/inc/account_layout.php';

// If the paywall is off, there is no landing wall — go straight to the paper.
if (!paywall_enabled()) {
    redirect(url('index.php'));
}
// Already signed in? Go read.
if (current_subscriber()) {
    redirect(url('index.php'));
}

$plans = q('SELECT * FROM plans WHERE is_active = 1 ORDER BY sort_order, amount_paise')->fetchAll();
$archivePaise = archive_price_paise();
$trial = trial_days();
$loginThenSub = url('account/login.php', ['next' => url('subscribe.php')]);

// a real front page to show in the hero, if there is one
$front = q("SELECT p.img_medium FROM pages p JOIN issues i ON i.id = p.issue_id
            WHERE i.status = 'published' AND p.page_number = 1
            ORDER BY i.issue_date DESC LIMIT 1")->fetchColumn();

account_header('Welcome', true);
?>
<section class="hero">
  <div class="hero__art">
    <?php if ($front): ?>
      <img src="<?= h(media((string)$front)) ?>" alt="Today's front page">
    <?php else: ?>
      <div class="hero__art-ph" aria-hidden="true"></div>
    <?php endif; ?>
  </div>
  <div class="hero__text">
    <h1>Newspaper at your fingertips</h1>
    <p>Leap one step ahead relishing the digital version of the eminent print newspaper first before anyone could grab a copy. Read, share, and save the best content anywhere, any time, on the go.</p>
    <div class="hero__cta">
      <a class="acc-btn" href="<?= h($loginThenSub) ?>">Subscribe</a>
      <a class="acc-btn acc-btn--primary" href="<?= h(url('account/login.php', ['next' => url('index.php')])) ?>">Login</a>
    </div>
    <?php if ($trial > 0): ?>
      <a class="hero__trial" href="<?= h(url('account/register.php', ['next' => url('index.php')])) ?>">
        Try <?= (int)$trial ?> Days for free
        <svg viewBox="0 0 24 24" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M10 8l4 4-4 4"/></svg>
      </a>
    <?php endif; ?>
  </div>
</section>

<section class="plans-section">
  <h2 class="plans-section__title">Subscription plans</h2>
  <?php if ($plans): ?>
  <div class="plan-grid">
    <?php foreach ($plans as $p):
        $days = plan_validity_days($p);
        $save = plan_savings_pct($p, $plans);
    ?>
      <div class="plan-card">
        <h3 class="plan-card__name"><?= h($p['name']) ?></h3>
        <p class="plan-card__price"><?= h(money_inr((int)$p['amount_paise'])) ?><span>/edition</span></p>
        <p class="plan-card__valid">Valid for <?= (int)$days ?> days</p>
        <div class="plan-card__badge-row">
          <?php if ($save > 0): ?><span class="plan-card__save">Save <?= (int)$save ?>%</span><?php endif; ?>
        </div>
        <div class="plan-card__cta"><a class="acc-btn" href="<?= h($loginThenSub) ?>">Subscribe</a></div>
      </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>

  <div class="archive-card">
    <div class="archive-card__text">
      <h3>Archives (Old Issues)</h3>
      <p>Get a copy of any one old issue in digital format — just the day you need.</p>
    </div>
    <div class="archive-card__price">
      <strong><?= h(money_inr($archivePaise)) ?></strong><span>/day (per edition)</span>
    </div>
    <div class="archive-card__cta"><a class="acc-btn" href="<?= h($loginThenSub) ?>">Subscribe</a></div>
  </div>
</section>
<?php account_footer();
