<?php
// Razorpay webhook receiver — the source of truth for renewals and cancellations.
// Configure in Razorpay Dashboard -> Settings -> Webhooks, URL:
//   https://your-domain/epaper/api/razorpay_webhook.php
// Subscribe to: subscription.activated, subscription.charged, subscription.pending,
//   subscription.halted, subscription.cancelled, subscription.completed, payment.failed.
// Set the webhook secret to the same value as config.php razorpay.webhook_secret.
declare(strict_types=1);
require __DIR__ . '/../inc/bootstrap.php';
require __DIR__ . '/../inc/razorpay.php';

$raw = file_get_contents('php://input') ?: '';
$signature = (string)($_SERVER['HTTP_X_RAZORPAY_SIGNATURE'] ?? '');

if (!rzp_verify_webhook_signature($raw, $signature)) {
    http_response_code(400);
    echo 'invalid signature';
    exit;
}

$data = json_decode($raw, true);
if (!is_array($data) || empty($data['event'])) {
    http_response_code(400);
    echo 'bad payload';
    exit;
}

$event   = (string)$data['event'];
$eventId = (string)($_SERVER['HTTP_X_RAZORPAY_EVENT_ID'] ?? ('sha1:' . sha1($raw)));

// Idempotency: if we've already logged this event id, acknowledge and stop.
try {
    q('INSERT INTO webhook_events (event_id, event, payload) VALUES (?, ?, ?)', [$eventId, $event, $raw]);
} catch (PDOException $e) {
    if ((int)$e->errorInfo[1] === 1062) { // duplicate key
        http_response_code(200);
        echo 'duplicate';
        exit;
    }
    throw $e;
}

$subEntity = $data['payload']['subscription']['entity'] ?? null;
$payEntity = $data['payload']['payment']['entity'] ?? null;

// Update subscription state from any event that carries it.
if (is_array($subEntity)) {
    apply_rzp_subscription($subEntity);
}

// Record a successful renewal/first charge.
if ($event === 'subscription.charged' && is_array($payEntity) && is_array($subEntity)) {
    $local = q('SELECT id, subscriber_id FROM subscriptions WHERE razorpay_subscription_id = ?',
               [(string)$subEntity['id']])->fetch();
    if ($local && !empty($payEntity['id'])) {
        q('INSERT INTO payments (subscription_id, subscriber_id, razorpay_payment_id, razorpay_invoice_id,
                                 amount_paise, currency, status, method)
           VALUES (?, ?, ?, ?, ?, ?, ?, ?)
           ON DUPLICATE KEY UPDATE status = VALUES(status), amount_paise = VALUES(amount_paise), method = VALUES(method)',
          [
            (int)$local['id'], (int)$local['subscriber_id'], (string)$payEntity['id'],
            (string)($data['payload']['invoice']['entity']['id'] ?? ($payEntity['invoice_id'] ?? '')) ?: null,
            (int)($payEntity['amount'] ?? 0), (string)($payEntity['currency'] ?? 'INR'),
            'captured', (string)($payEntity['method'] ?? ''),
          ]);
    }
}

// Record a failed charge against the subscription (so the reader sees it).
if ($event === 'payment.failed' && is_array($payEntity) && is_array($subEntity)) {
    $local = q('SELECT id, subscriber_id FROM subscriptions WHERE razorpay_subscription_id = ?',
               [(string)$subEntity['id']])->fetch();
    if ($local && !empty($payEntity['id'])) {
        q('INSERT INTO payments (subscription_id, subscriber_id, razorpay_payment_id, amount_paise, currency, status, method)
           VALUES (?, ?, ?, ?, ?, ?, ?)
           ON DUPLICATE KEY UPDATE status = VALUES(status)',
          [
            (int)$local['id'], (int)$local['subscriber_id'], (string)$payEntity['id'],
            (int)($payEntity['amount'] ?? 0), (string)($payEntity['currency'] ?? 'INR'),
            'failed', (string)($payEntity['method'] ?? ''),
          ]);
    }
}

http_response_code(200);
echo 'ok';
