<?php
declare(strict_types=1);

/**
 * Reader accounts, subscription access checks and the paywall rule.
 * Readers are stored in `subscribers`, entirely separate from staff `users`.
 */

// ---------------------------------------------------------------- accounts

function current_subscriber(): ?array
{
    $s = $_SESSION['subscriber'] ?? null;
    if (!$s) {
        return null;
    }
    $row = q('SELECT id, email, name, phone, status, email_verified_at FROM subscribers WHERE id = ?', [$s['id']])->fetch();
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

/** True when the signed-in reader (if any) currently has access. */
function subscriber_has_access(): bool
{
    $s = current_subscriber();
    return $s !== null && has_active_subscription((int)$s['id']);
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

/** Staff preview and free issues are always readable; otherwise an active subscription is needed. */
function can_read_ctx(array $ctx): bool
{
    if (function_exists('can_preview') && can_preview()) {
        return true;
    }
    return ctx_issue_is_free($ctx) || subscriber_has_access();
}

/** Whether the "Download page" link is allowed for the current viewer/issue. */
function can_download_ctx(array $ctx): bool
{
    if (function_exists('can_preview') && can_preview()) {
        return true;
    }
    if (!paywall_enabled()) {
        return true;
    }
    if (subscriber_has_access()) {
        return true;
    }
    return ctx_issue_is_free($ctx) && paywall_free_downloads();
}

function can_read_article(array $article): bool
{
    if (function_exists('can_preview') && can_preview()) {
        return true;
    }
    if (date_is_free((string)$article['edition_slug'], (string)$article['issue_date'])) {
        return true;
    }
    return subscriber_has_access();
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
