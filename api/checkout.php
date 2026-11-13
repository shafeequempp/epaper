<?php
// Creates a Razorpay subscription for the signed-in reader and returns its id for Checkout.
declare(strict_types=1);
require __DIR__ . '/../inc/bootstrap.php';
require __DIR__ . '/../inc/razorpay.php';

header('Content-Type: application/json; charset=utf-8');

function fail(string $msg, int $code = 400): void
{
    http_response_code($code);
    echo json_encode(['error' => $msg]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    fail('POST only.', 405);
}
if (!hash_equals(csrf_token(), (string)($_POST['csrf'] ?? ''))) {
    fail('Your session expired. Reload the page and try again.');
}

$sub = current_subscriber();
if (!$sub) {
    fail('Please sign in first.', 401);
}
if (!razorpay_enabled()) {
    fail('Online payment is not configured.');
}

// Which main edition is this subscription for? Any edition slug resolves to its main.
$edRow = q('SELECT * FROM editions WHERE slug = ? AND is_active = 1', [(string)($_POST['edition'] ?? '')])->fetch();
if (!$edRow) {
    fail('Choose an edition to subscribe to.');
}
$mainEd   = main_edition_of($edRow);
$mainId   = (int)$mainEd['id'];
$mainName = (string)$mainEd['name'];

$coverage = subscriber_coverage((int)$sub['id']);
if ($coverage['all'] || in_array($mainId, $coverage['mains'], true)) {
    fail('You already have access to ' . $mainName . '.');
}

$plan = q('SELECT * FROM plans WHERE slug = ? AND is_active = 1', [(string)($_POST['plan'] ?? '')])->fetch();
if (!$plan) {
    fail('That plan is not available.');
}

try {
    // Provision the plan in Razorpay the first time it's used.
    if (empty($plan['razorpay_plan_id'])) {
        $rzpPlanId = rzp_create_plan($plan);
        q('UPDATE plans SET razorpay_plan_id = ? WHERE id = ?', [$rzpPlanId, $plan['id']]);
        $plan['razorpay_plan_id'] = $rzpPlanId;
    }

    $subscription = rzp_create_subscription(
        (string)$plan['razorpay_plan_id'],
        (int)$plan['total_count'],
        ['subscriber_id' => (string)$sub['id'], 'plan_id' => (string)$plan['id'],
         'edition_id' => (string)$mainId, 'edition' => $mainName]
    );

    q('INSERT INTO subscriptions (subscriber_id, plan_id, edition_id, razorpay_subscription_id, status, source)
       VALUES (?, ?, ?, ?, ?, ?)',
      [(int)$sub['id'], (int)$plan['id'], $mainId, $subscription['id'], $subscription['status'] ?? 'created', 'razorpay']);

    echo json_encode([
        'subscription_id' => $subscription['id'],
        'key_id'          => rzp_cfg('key_id'),
        'name'            => (string)cfg('site_name'),
        'description'     => $plan['name'] . ' · ' . $mainName,
        'prefill'         => ['name' => $sub['name'], 'email' => $sub['email']],
    ]);
} catch (Throwable $e) {
    fail($e->getMessage(), 502);
}
