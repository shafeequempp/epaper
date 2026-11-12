<?php
declare(strict_types=1);
require __DIR__ . '/../inc/bootstrap.php';
require __DIR__ . '/../inc/mailer.php';

// Resend the link to the signed-in reader.
if (isset($_GET['resend'])) {
    $sub = require_subscriber();
    if (empty($sub['email_verified_at'])) {
        send_verify_email((int)$sub['id'], (string)$sub['email']);
        flash('Verification link sent to ' . h($sub['email']) . '.');
    } else {
        flash('Your e-mail is already verified.');
    }
    redirect(url('account/index.php'));
}

// Confirm via the token in the link.
$token = (string)($_GET['token'] ?? '');
$id = $token !== '' ? subscriber_consume_token($token, 'verify') : null;
if ($id) {
    q('UPDATE subscribers SET email_verified_at = NOW() WHERE id = ? AND email_verified_at IS NULL', [$id]);
    flash('Thanks — your e-mail is verified.');
    redirect(url(current_subscriber() ? 'account/index.php' : 'account/login.php'));
}

flash('That verification link is invalid or has expired. Sign in and resend it.', 'error');
redirect(url('account/login.php'));
