<?php
// After Razorpay Checkout succeeds, verify the signature and activate the subscription.
declare(strict_types=1);
require __DIR__ . '/../inc/bootstrap.php';
require __DIR__ . '/../inc/razorpay.php';

$sub = require_subscriber();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect(url('subscribe.php'));
}
check_csrf();

$paymentId = (string)($_POST['razorpay_payment_id'] ?? '');
$subId     = (string)($_POST['razorpay_subscription_id'] ?? '');
$signature = (string)($_POST['razorpay_signature'] ?? '');

$row = $subId !== ''
    ? q('SELECT * FROM subscriptions WHERE razorpay_subscription_id = ? AND subscriber_id = ?', [$subId, (int)$sub['id']])->fetch()
    : null;

if (!$row || !rzp_verify_checkout_signature($subId, $paymentId, $signature)) {
    flash('We could not confirm that payment. If you were charged, contact support — your subscription will still activate automatically.', 'error');
    redirect(url('account/index.php'));
}

try {
    // Pull the authoritative state from Razorpay and store it.
    $entity = rzp_fetch_subscription($subId);
    apply_rzp_subscription($entity);
} catch (Throwable $e) {
    // The webhook will reconcile this shortly; grant access from the plan meanwhile.
    $plan = q('SELECT period, interval_count FROM plans WHERE id = ?', [(int)$row['plan_id']])->fetch();
    $end = plan_cycle_end($plan)->format('Y-m-d H:i:s');
    q('UPDATE subscriptions SET status = ?, current_start = NOW(), current_end = ? WHERE id = ?',
      ['active', $end, (int)$row['id']]);
}

// Record the authorisation payment (idempotent on razorpay_payment_id).
if ($paymentId !== '') {
    $plan = q('SELECT amount_paise, currency FROM plans WHERE id = ?', [(int)$row['plan_id']])->fetch();
    q('INSERT INTO payments (subscription_id, subscriber_id, razorpay_payment_id, amount_paise, currency, status)
       VALUES (?, ?, ?, ?, ?, ?)
       ON DUPLICATE KEY UPDATE status = VALUES(status)',
      [(int)$row['id'], (int)$sub['id'], $paymentId, (int)$plan['amount_paise'], $plan['currency'] ?: 'INR', 'captured']);
}

flash('Thank you — your subscription is active. Enjoy the full archive.');
redirect(url('account/index.php'));
