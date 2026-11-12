<?php
declare(strict_types=1);

/**
 * Minimal Razorpay REST client (no SDK / Composer needed).
 * Docs: https://razorpay.com/docs/api/  and  https://razorpay.com/docs/payments/subscriptions/
 *
 * Config (config.php): razorpay.key_id, razorpay.key_secret, razorpay.webhook_secret.
 */

function rzp_cfg(string $key): string
{
    $r = cfg('razorpay') ?: [];
    return (string)($r[$key] ?? '');
}

/** True once the live/test API keys are set. The site works without them; payments don't. */
function razorpay_enabled(): bool
{
    return rzp_cfg('key_id') !== '' && rzp_cfg('key_secret') !== '';
}

/**
 * One authenticated call to the Razorpay API. Returns the decoded JSON body.
 * Throws RuntimeException with Razorpay's error description on any non-2xx reply.
 */
function rzp_request(string $method, string $path, array $data = []): array
{
    if (!razorpay_enabled()) {
        throw new RuntimeException('Razorpay is not configured. Set razorpay.key_id and key_secret in config.php.');
    }
    $url = 'https://api.razorpay.com/v1/' . ltrim($path, '/');
    $ch = curl_init($url);
    $opts = [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST  => strtoupper($method),
        CURLOPT_USERPWD        => rzp_cfg('key_id') . ':' . rzp_cfg('key_secret'),
        CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
        CURLOPT_TIMEOUT        => 30,
        CURLOPT_CONNECTTIMEOUT => 15,
    ];
    if ($data && in_array(strtoupper($method), ['POST', 'PUT', 'PATCH'], true)) {
        $opts[CURLOPT_POSTFIELDS] = json_encode($data);
    }
    curl_setopt_array($ch, $opts);
    $body = curl_exec($ch);
    if ($body === false) {
        $err = curl_error($ch);
        curl_close($ch);
        throw new RuntimeException('Could not reach Razorpay: ' . $err);
    }
    $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    $json = json_decode((string)$body, true);
    if (!is_array($json)) {
        throw new RuntimeException('Unexpected reply from Razorpay (HTTP ' . $code . ').');
    }
    if ($code < 200 || $code >= 300) {
        $msg = $json['error']['description'] ?? ('Razorpay error (HTTP ' . $code . ').');
        throw new RuntimeException($msg);
    }
    return $json;
}

/** Create a Razorpay Plan from a local plan row; returns the Razorpay plan id. */
function rzp_create_plan(array $plan): string
{
    $res = rzp_request('POST', 'plans', [
        'period'   => $plan['period'],
        'interval' => (int)$plan['interval_count'],
        'item'     => [
            'name'        => $plan['name'],
            'amount'      => (int)$plan['amount_paise'],
            'currency'    => $plan['currency'] ?: 'INR',
            'description' => (string)$plan['description'],
        ],
    ]);
    return (string)$res['id'];
}

/** Create a subscription a reader will then authorise via Checkout. */
function rzp_create_subscription(string $planId, int $totalCount, array $notes = []): array
{
    return rzp_request('POST', 'subscriptions', [
        'plan_id'         => $planId,
        'total_count'     => max(1, $totalCount),
        'customer_notify' => 1,
        'notes'           => $notes,
    ]);
}

function rzp_fetch_subscription(string $subscriptionId): array
{
    return rzp_request('GET', 'subscriptions/' . rawurlencode($subscriptionId));
}

/** Cancel a subscription, either now or at the end of the paid period. */
function rzp_cancel_subscription(string $subscriptionId, bool $atCycleEnd): array
{
    return rzp_request('POST', 'subscriptions/' . rawurlencode($subscriptionId) . '/cancel', [
        'cancel_at_cycle_end' => $atCycleEnd ? 1 : 0,
    ]);
}

/**
 * Verify the signature Checkout returns after the reader authorises a subscription.
 * For subscriptions the signed string is payment_id + '|' + subscription_id.
 */
function rzp_verify_checkout_signature(string $subscriptionId, string $paymentId, string $signature): bool
{
    $expected = hash_hmac('sha256', $paymentId . '|' . $subscriptionId, rzp_cfg('key_secret'));
    return hash_equals($expected, $signature);
}

/** Verify a webhook request body against the webhook secret. */
function rzp_verify_webhook_signature(string $rawBody, string $signature): bool
{
    $secret = rzp_cfg('webhook_secret');
    if ($secret === '') {
        return false;
    }
    $expected = hash_hmac('sha256', $rawBody, $secret);
    return hash_equals($expected, $signature);
}
