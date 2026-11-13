<?php
declare(strict_types=1);

/**
 * Reader accounts, subscription access checks and the paywall rule.
 * Readers are stored in `subscribers`, entirely separate from staff `users`.
 */

require_once __DIR__ . '/editions.php';   // main_edition_of(), main_edition_id_of(), main_editions()

// ---------------------------------------------------------------- accounts

function current_subscriber(): ?array
{
    $s = $_SESSION['subscriber'] ?? null;
    if (!$s) {
        return null;
    }
    $row = q('SELECT id, email, name, phone, status, email_verified_at, trial_started_at FROM subscribers WHERE id = ?', [$s['id']])->fetch();
    if (!$row || $row['status'] !== 'active') {
        unset($_SESSION['subscriber']);
        return null;
    }
    $_SESSION['subscriber'] = ['id' => (int)$row['id'], 'email' => $row['email'], 'name' => $row['name']];
    return $row;
}

function subscriber_login(array $row): void
{
    session_regenerate_id(true);
    $_SESSION['subscriber'] = ['id' => (int)$row['id'], 'email' => $row['email'], 'name' => $row['name']];

    // Start the free trial on the very first login.
    if (empty($row['trial_started_at'])) {
        q("UPDATE subscribers SET trial_started_at = NOW() WHERE id = ? AND trial_started_at IS NULL", [(int)$row['id']]);
    }
    // Record every login.
    q("INSERT INTO login_logs (subscriber_id, ip, user_agent) VALUES (?, ?, ?)", [
        (int)$row['id'],
        substr((string)($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45),
        substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255),
    ]);
}

// ---------------------------------------------------------------- free trial

/** How many free days a reader gets from their first login. Configurable in config.php. */
function trial_days(): int
{
    $v = cfg('trial_days');
    return $v !== null ? max(0, (int)$v) : 7;
}

/** The reader's trial start (first login), or null if they have never logged in. */
function subscriber_trial_start(int $subscriberId): ?string
{
    $t = q('SELECT trial_started_at FROM subscribers WHERE id = ?', [$subscriberId])->fetchColumn();
    return $t ?: null;
}

/** Is the reader still inside their free-trial window? */
function subscriber_trial_active(int $subscriberId): bool
{
    $t = subscriber_trial_start($subscriberId);
    if ($t === null || trial_days() <= 0) {
        return false;
    }
    return (strtotime($t) + trial_days() * 86400) > time();
}

/** When the free trial ends (Y-m-d H:i:s), or null if not started. */
function subscriber_trial_ends(int $subscriberId): ?string
{
    $t = subscriber_trial_start($subscriberId);
    return $t === null ? null : date('Y-m-d H:i:s', strtotime($t) + trial_days() * 86400);
}

function subscriber_logout(): void
{
    unset($_SESSION['subscriber']);
}

function require_subscriber(): array
{
    $s = current_subscriber();
    if (!$s) {
        redirect(url('account/login.php', ['next' => $_SERVER['REQUEST_URI'] ?? '']));
    }
    return $s;
}

function find_subscriber_by_email(string $email): ?array
{
    $row = q('SELECT * FROM subscribers WHERE email = ?', [strtolower(trim($email))])->fetch();
    return $row ?: null;
}

function create_subscriber(string $email, string $password, string $name, ?string $phone = null): int
{
    q('INSERT INTO subscribers (email, password_hash, name, phone) VALUES (?, ?, ?, ?)',
      [strtolower(trim($email)), password_hash($password, PASSWORD_DEFAULT), trim($name), $phone ?: null]);
    return (int)db()->lastInsertId();
}

// ---------------------------------------------------------------- tokens (verify / reset)

/** Create a one-time token, store only its hash, return the raw token for the e-mail link. */
function subscriber_make_token(int $subscriberId, string $type, int $ttlMinutes): string
{
    $raw = bin2hex(random_bytes(32));
    q('INSERT INTO subscriber_tokens (subscriber_id, type, token_hash, expires_at)
       VALUES (?, ?, ?, DATE_ADD(NOW(), INTERVAL ? MINUTE))',
      [$subscriberId, $type, hash('sha256', $raw), $ttlMinutes]);
    return $raw;
}

/** Validate and burn a token; returns the subscriber id, or null if invalid/expired/used. */
function subscriber_consume_token(string $raw, string $type): ?int
{
    $row = q('SELECT id, subscriber_id FROM subscriber_tokens
              WHERE token_hash = ? AND type = ? AND used_at IS NULL AND expires_at > NOW()
              ORDER BY id DESC LIMIT 1', [hash('sha256', $raw), $type])->fetch();
    if (!$row) {
        return null;
    }
    q('UPDATE subscriber_tokens SET used_at = NOW() WHERE id = ?', [$row['id']]);
    return (int)$row['subscriber_id'];
}

// ---------------------------------------------------------------- subscriptions / access

/** The subscription currently granting access, if any. */
function active_subscription(int $subscriberId): ?array
{
    $row = q("SELECT s.*, p.name AS plan_name, p.amount_paise, p.currency, p.period, p.interval_count
              FROM subscriptions s
              JOIN plans p ON p.id = s.plan_id
              WHERE s.subscriber_id = ?
                AND (
                     (s.status IN ('active','authenticated') AND s.current_end IS NOT NULL AND s.current_end > NOW())
                  OR (s.source = 'manual' AND (s.current_end IS NULL OR s.current_end > NOW()))
                )
              ORDER BY s.current_end IS NULL DESC, s.current_end DESC
              LIMIT 1", [$subscriberId])->fetch();
    return $row ?: null;
}

function has_active_subscription(int $subscriberId): bool
{
    return active_subscription($subscriberId) !== null;
}

/** True when the signed-in reader (if any) currently has ANY active subscription. */
function subscriber_has_access(): bool
{
    $s = current_subscriber();
    return $s !== null && has_active_subscription((int)$s['id']);
}

/** All currently-active subscriptions of a reader (with plan + covered-edition info). */
function active_subscriptions(int $subscriberId): array
{
    return q("SELECT s.*, p.name AS plan_name, p.amount_paise, p.currency, p.period, p.interval_count,
                     e.slug AS edition_slug, e.name AS edition_name, e.group_name AS edition_group
              FROM subscriptions s
              JOIN plans p ON p.id = s.plan_id
              LEFT JOIN editions e ON e.id = s.edition_id
              WHERE s.subscriber_id = ?
                AND (
                     (s.status IN ('active','authenticated') AND s.current_end IS NOT NULL AND s.current_end > NOW())
                  OR (s.source = 'manual' AND (s.current_end IS NULL OR s.current_end > NOW()))
                )
              ORDER BY s.current_end IS NULL DESC, s.current_end DESC", [$subscriberId])->fetchAll();
}

/**
 * What a reader can currently read: ['all' => bool, 'mains' => int[]].
 * 'all' is true when they hold an all-access (edition_id IS NULL) subscription;
 * 'mains' lists the main-edition ids of their per-edition subscriptions.
 */
function subscriber_coverage(int $subscriberId): array
{
    $all = false;
    $mains = [];
    foreach (active_subscriptions($subscriberId) as $s) {
        if ($s['edition_id'] === null) {
            $all = true;
        } else {
            $mains[(int)$s['edition_id']] = true;
        }
    }
    return ['all' => $all, 'mains' => array_keys($mains)];
}

/** Does the signed-in reader have paid access to this edition (via its group's main edition)? */
function subscriber_has_access_to(array $edition): bool
{
    $s = current_subscriber();
    if ($s === null) {
        return false;
    }
    $cov = subscriber_coverage((int)$s['id']);
    if ($cov['all']) {
        return true;
    }
    return in_array(main_edition_id_of($edition), $cov['mains'], true);
}

/** Price of one archive (single old date) purchase, in paise. Configurable via config.php. */
function archive_price_paise(): int
{
    $v = cfg('archive_price_paise');
    return $v !== null ? (int)$v : 2500;   // ₹25 default
}

/** Has the signed-in reader bought this one old date of this edition's group (Archives)? */
function subscriber_bought_archive(int $mainEditionId, string $date): bool
{
    $s = current_subscriber();
    if ($s === null || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
        return false;
    }
    return (bool)q("SELECT 1 FROM archive_purchases
                    WHERE subscriber_id = ? AND edition_id = ? AND issue_date = ? AND status = 'paid'
                    LIMIT 1", [(int)$s['id'], $mainEditionId, $date])->fetchColumn();
}

/** True when the reader has an archive purchase that unlocks this reader context's date. */
function ctx_archive_ok(array $ctx): bool
{
    if (empty($ctx['edition']) || empty($ctx['issue']['issue_date'])) {
        return false;
    }
    return subscriber_bought_archive(main_edition_id_of($ctx['edition']), (string)$ctx['issue']['issue_date']);
}

function paywall_enabled(): bool
{
    $pw = cfg('paywall') ?: [];
    return !empty($pw['enabled']);
}

function paywall_free_downloads(): bool
{
    $pw = cfg('paywall') ?: [];
    return !empty($pw['free_downloads']);
}

/** The newest published date of an edition is free to everyone (freemium). */
function date_is_free(string $editionSlug, string $date): bool
{
    if (!paywall_enabled()) {
        return true;
    }
    $pw = cfg('paywall') ?: [];
    if (empty($pw['free_latest'])) {
        return false;
    }
    $latest = q("SELECT MAX(i.issue_date) FROM issues i JOIN editions e ON e.id = i.edition_id
                 WHERE e.slug = ? AND i.status = 'published'", [$editionSlug])->fetchColumn();
    return $latest !== false && $latest !== null && $date === $latest;
}

/** Freeness decided from a resolved reader context (has the DESC date list already). */
function ctx_issue_is_free(array $ctx): bool
{
    if (!paywall_enabled()) {
        return true;
    }
    $pw = cfg('paywall') ?: [];
    if (empty($pw['free_latest'])) {
        return false;
    }
    $dates = $ctx['dates'] ?? [];
    $d = $ctx['issue']['issue_date'] ?? null;
    return $dates && $d !== null && $d === $dates[0];
}

/** May a NOT-subscribed trial reader open past days? Config: paywall.trial_archive. */
function trial_archive_allowed(): bool
{
    $pw = cfg('paywall') ?: [];
    return !empty($pw['trial_archive']);
}

/** May a NOT-subscribed trial reader download article images? Config: paywall.trial_downloads. */
function trial_downloads_allowed(): bool
{
    $pw = cfg('paywall') ?: [];
    return !empty($pw['trial_downloads']);
}

/** Is this the newest published date for the edition's group (i.e. "today's paper")? */
function is_latest_issue_date(array $edition, string $date): bool
{
    $group = (string)($edition['group_name'] ?? '');
    if ($group !== '') {
        $max = q("SELECT MAX(i.issue_date) FROM issues i JOIN editions e ON e.id = i.edition_id
                  WHERE e.group_name = ? AND i.status = 'published'", [$group])->fetchColumn();
    } else {
        $max = q("SELECT MAX(issue_date) FROM issues WHERE edition_id = ? AND status = 'published'", [(int)$edition['id']])->fetchColumn();
    }
    return $max !== null && $max !== false && $date === $max;
}

/** A paid reader: subscribed to this edition, or bought this exact date. */
function reader_is_paid(?array $edition, ?string $date): bool
{
    if (!empty($edition) && subscriber_has_access_to($edition)) {
        return true;
    }
    return !empty($edition) && $date !== null && subscriber_bought_archive(main_edition_id_of($edition), $date);
}

/**
 * READ gate. The paper is for signed-in readers. A paid reader reads anything they cover.
 * A trial (not-subscribed) reader reads only the latest day, unless paywall.trial_archive
 * allows past days too. Staff preview always passes; paywall off = open site.
 */
function reader_can_access(?array $edition, ?string $date): bool
{
    if (function_exists('can_preview') && can_preview()) {
        return true;
    }
    if (!paywall_enabled()) {
        return true;
    }
    $s = current_subscriber();
    if ($s === null) {
        return false;                                  // must be signed in
    }
    if (reader_is_paid($edition, $date)) {
        return true;
    }
    if (subscriber_trial_active((int)$s['id'])) {
        if (!empty($edition) && $date !== null && is_latest_issue_date($edition, $date)) {
            return true;                               // today's paper during the trial
        }
        return trial_archive_allowed();                // past days only if config allows
    }
    return false;
}

/** DOWNLOAD gate. Paid readers always; trial readers only if paywall.trial_downloads is on. */
function reader_can_download(?array $edition, ?string $date): bool
{
    if (function_exists('can_preview') && can_preview()) {
        return true;
    }
    if (!paywall_enabled()) {
        return true;
    }
    $s = current_subscriber();
    if ($s === null) {
        return false;
    }
    if (reader_is_paid($edition, $date)) {
        return true;
    }
    if (subscriber_trial_active((int)$s['id'])) {
        return trial_downloads_allowed() && reader_can_access($edition, $date);
    }
    return false;
}

function can_read_ctx(array $ctx): bool
{
    return reader_can_access($ctx['edition'] ?? null, $ctx['issue']['issue_date'] ?? null);
}

/** Whether downloads are allowed for the current viewer/issue. */
function can_download_ctx(array $ctx): bool
{
    return reader_can_download($ctx['edition'] ?? null, $ctx['issue']['issue_date'] ?? null);
}

function can_read_article(array $article): bool
{
    $ed = q('SELECT * FROM editions WHERE slug = ?', [(string)$article['edition_slug']])->fetch() ?: null;
    return reader_can_access($ed, (string)$article['issue_date']);
}

/** Whether the current reader may download this article's image. */
function can_download_article(array $article): bool
{
    $ed = q('SELECT * FROM editions WHERE slug = ?', [(string)$article['edition_slug']])->fetch() ?: null;
    return reader_can_download($ed, (string)$article['issue_date']);
}

/** True when the signed-in reader's free trial has ended and they hold no paid access. */
function trial_is_over_for_current(): bool
{
    $s = current_subscriber();
    if ($s === null) {
        return false;
    }
    if (subscriber_trial_start((int)$s['id']) === null || subscriber_trial_active((int)$s['id'])) {
        return false;   // never started, or still running
    }
    $cov = subscriber_coverage((int)$s['id']);
    return !$cov['all'] && !$cov['mains'];   // no subscription at all
}

// ---------------------------------------------------------------- plan helpers

/** End of one billing cycle from a given start (fallback when Razorpay hasn't sent current_end yet). */
function plan_cycle_end(array $plan, ?DateTime $from = null): DateTime
{
    $from = $from ? clone $from : new DateTime();
    $n = max(1, (int)$plan['interval_count']);
    switch ($plan['period']) {
        case 'daily':  $spec = "P{$n}D"; break;
        case 'weekly': $spec = "P{$n}W"; break;
        case 'yearly': $spec = "P{$n}Y"; break;
        default:       $spec = "P{$n}M";
    }
    return $from->add(new DateInterval($spec));
}

/**
 * Update a local subscription row from a Razorpay subscription entity
 * (used by the checkout callback and the webhook).
 */
function apply_rzp_subscription(array $entity): void
{
    $rid = (string)($entity['id'] ?? '');
    if ($rid === '') {
        return;
    }
    $local = q('SELECT s.*, p.period, p.interval_count FROM subscriptions s
                JOIN plans p ON p.id = s.plan_id
                WHERE s.razorpay_subscription_id = ?', [$rid])->fetch();
    if (!$local) {
        return;
    }
    $status   = (string)($entity['status'] ?? $local['status']);
    $curStart = !empty($entity['current_start']) ? date('Y-m-d H:i:s', (int)$entity['current_start']) : $local['current_start'];
    $curEnd   = !empty($entity['current_end'])   ? date('Y-m-d H:i:s', (int)$entity['current_end'])   : $local['current_end'];
    if (in_array($status, ['active', 'authenticated'], true) && !$curEnd) {
        $curEnd = plan_cycle_end($local)->format('Y-m-d H:i:s');
    }
    q('UPDATE subscriptions SET status = ?, current_start = ?, current_end = ? WHERE id = ?',
      [$status, $curStart, $curEnd, $local['id']]);
}

// ---------------------------------------------------------------- formatting

function money_inr(int $paise): string
{
    $r = $paise / 100;
    $n = (abs($r - round($r)) < 0.005) ? number_format($r, 0) : number_format($r, 2);
    return '₹' . $n;
}

/** "month", "year", "3 months" — for "₹149 / month". */
function plan_cycle_label(array $plan): string
{
    $n = max(1, (int)$plan['interval_count']);
    $unit = ['daily' => 'day', 'weekly' => 'week', 'monthly' => 'month', 'yearly' => 'year'][$plan['period']] ?? 'month';
    return $n === 1 ? $unit : "$n {$unit}s";
}

/** How many days one paid cycle of this plan lasts (for "Valid for N days"). */
function plan_validity_days(array $plan): int
{
    $n = max(1, (int)$plan['interval_count']);
    $per = ['daily' => 1, 'weekly' => 7, 'monthly' => 30, 'yearly' => 365][$plan['period']] ?? 30;
    return $n * $per;
}

/** Cost per day in paise, for comparing plans. */
function plan_per_day_paise(array $plan): float
{
    $days = max(1, plan_validity_days($plan));
    return (int)$plan['amount_paise'] / $days;
}

/**
 * Savings versus the most expensive-per-day plan in the list (usually the shortest one).
 * Returns a whole percentage; 0 for the baseline plan (no badge shown).
 */
function plan_savings_pct(array $plan, array $allPlans): int
{
    $baseline = 0.0;
    foreach ($allPlans as $p) {
        $baseline = max($baseline, plan_per_day_paise($p));
    }
    if ($baseline <= 0) {
        return 0;
    }
    $pct = (1 - plan_per_day_paise($plan) / $baseline) * 100;
    return $pct < 1 ? 0 : (int)round($pct);
}

// ---------------------------------------------------------------- redirect helpers

/** Only allow same-site relative paths as a post-login redirect target. */
function safe_next($v): string
{
    $v = (string)$v;
    return ($v !== '' && $v[0] === '/' && strpos($v, '//') !== 0) ? $v : '';
}

/** Build an absolute URL (for e-mail links) from a site-relative one. */
function absolute_url(string $path): string
{
    if (preg_match('#^https?://#', $path)) {
        return $path;
    }
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    return $scheme . '://' . $host . $path;
}
