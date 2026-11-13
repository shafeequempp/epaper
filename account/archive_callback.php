<?php
// After Razorpay Checkout succeeds for an archive Order, verify it and unlock that date.
declare(strict_types=1);
require __DIR__ . '/../inc/bootstrap.php';
require __DIR__ . '/../inc/razorpay.php';

$sub = require_subscriber();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect(url('subscribe.php'));
}
check_csrf();

$orderId   = (string)($_POST['razorpay_order_id'] ?? '');
$paymentId = (string)($_POST['razorpay_payment_id'] ?? '');
$signature = (string)($_POST['razorpay_signature'] ?? '');

$row = $orderId !== ''
    ? q("SELECT * FROM archive_purchases WHERE razorpay_order_id = ? AND subscriber_id = ?", [$orderId, (int)$sub['id']])->fetch()
    : null;

if (!$row || !rzp_verify_order_signature($orderId, $paymentId, $signature)) {
    flash('We could not confirm that payment. If you were charged, contact support.', 'error');
    redirect(url('subscribe.php'));
}

q("UPDATE archive_purchases SET status = 'paid', razorpay_payment_id = ? WHERE id = ?",
  [$paymentId, (int)$row['id']]);

// send them straight to the paper they just unlocked
$slug = (string)q('SELECT slug FROM editions WHERE id = ?', [(int)$row['edition_id']])->fetchColumn();
flash('Thank you — that day\'s paper is now open for you.');
redirect(url('index.php', ['e' => $slug, 'd' => $row['issue_date']]));
