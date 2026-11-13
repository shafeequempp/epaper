<?php
// Creates a Razorpay Order for buying ONE old issue date of one edition (Archives).
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

$edRow = q('SELECT * FROM editions WHERE slug = ? AND is_active = 1', [(string)($_POST['edition'] ?? '')])->fetch();
if (!$edRow) {
    fail('Choose an edition.');
}
$mainEd = main_edition_of($edRow);
$mainId = (int)$mainEd['id'];

$date = (string)($_POST['date'] ?? '');
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
    fail('Choose a valid date.');
}

// the group must actually have a published paper that day
$has = q("SELECT 1 FROM issues i JOIN editions e ON e.id = i.edition_id
          WHERE i.issue_date = ? AND e.group_name = ? AND i.status = 'published'
          LIMIT 1", [$date, (string)$mainEd['group_name']])->fetchColumn();
if (!$has) {
    fail('No paper was published for that date.');
}

// already covered? (full subscription or already bought this date)
if (subscriber_has_access_to($mainEd) || subscriber_bought_archive($mainId, $date)) {
    fail('You already have access to that day.');
}

$amount = archive_price_paise();
try {
    $order = rzp_create_order($amount, [
        'kind' => 'archive', 'subscriber_id' => (string)$sub['id'],
        'edition_id' => (string)$mainId, 'edition' => (string)$mainEd['name'], 'issue_date' => $date,
    ]);
    q("INSERT INTO archive_purchases (subscriber_id, edition_id, issue_date, amount_paise, currency, razorpay_order_id, status)
       VALUES (?, ?, ?, ?, 'INR', ?, 'created')",
      [(int)$sub['id'], $mainId, $date, $amount, (string)$order['id']]);

    echo json_encode([
        'order_id'    => $order['id'],
        'amount'      => $amount,
        'key_id'      => rzp_cfg('key_id'),
        'name'        => (string)cfg('site_name'),
        'description' => 'Archive · ' . $mainEd['name'] . ' · ' . $date,
        'prefill'     => ['name' => $sub['name'], 'email' => $sub['email']],
    ]);
} catch (Throwable $e) {
    fail($e->getMessage(), 502);
}
