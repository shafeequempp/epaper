<?php
declare(strict_types=1);

/**
 * Plain-text e-mail via PHP mail(). On WAMP mail() usually isn't wired to an SMTP
 * server, so every message is ALSO written to storage/_mail/*.txt — open the newest
 * file there to copy a verification or reset link while testing. Configure a real
 * SMTP server (sendmail / msmtp / a transactional provider) before going live.
 */
function send_mail(string $to, string $subject, string $body): bool
{
    $m = cfg('mail') ?: [];
    $fromEmail = (string)($m['from_email'] ?? 'no-reply@localhost');
    $fromName  = (string)($m['from_name'] ?? (cfg('site_name') ?: 'ePaper'));

    $encSubject = '=?UTF-8?B?' . base64_encode($subject) . '?=';
    $encFrom    = '=?UTF-8?B?' . base64_encode($fromName) . '?= <' . $fromEmail . '>';
    $headers = implode("\r\n", [
        'From: ' . $encFrom,
        'MIME-Version: 1.0',
        'Content-Type: text/plain; charset=UTF-8',
        'Content-Transfer-Encoding: 8bit',
    ]);

    mail_log($to, $subject, $body);
    return @mail($to, $encSubject, $body, $headers);
}

/** Send (or resend) the e-mail-verification link to a subscriber. */
function send_verify_email(int $subscriberId, string $email): void
{
    $token = subscriber_make_token($subscriberId, 'verify', 60 * 24 * 3); // 3 days
    $link = absolute_url(url('account/verify.php', ['token' => $token]));
    $site = (string)cfg('site_name');
    send_mail($email, "Verify your e-mail — $site",
        "Welcome to $site.\n\nPlease confirm your e-mail address by opening this link:\n$link\n\n"
        . "The link is valid for 3 days. If you didn't create this account, ignore this message.");
}

/** Send a password-reset link. */
function send_reset_email(int $subscriberId, string $email): void
{
    $token = subscriber_make_token($subscriberId, 'reset', 60); // 1 hour
    $link = absolute_url(url('account/reset.php', ['token' => $token]));
    $site = (string)cfg('site_name');
    send_mail($email, "Reset your password — $site",
        "We received a request to reset your $site password.\n\nOpen this link to choose a new password:\n$link\n\n"
        . "The link is valid for 1 hour. If you didn't ask for this, you can ignore this message.");
}

/** Keep a copy of every outgoing mail on disk (dev convenience). */
function mail_log(string $to, string $subject, string $body): void
{
    $dir = rtrim((string)cfg('storage_dir'), '/') . '/_mail';
    if (!is_dir($dir)) {
        @mkdir($dir, 0775, true);
    }
    if (!is_dir($dir) || !is_writable($dir)) {
        return;
    }
    // The mail log holds one-time links — keep it out of the public storage URL.
    $deny = $dir . '/.htaccess';
    if (!is_file($deny)) {
        @file_put_contents($deny, "Require all denied\n");
    }
    $file = $dir . '/' . date('Ymd-His') . '-' . substr(preg_replace('/[^a-z0-9]/i', '', $to), 0, 20) . '.txt';
    @file_put_contents($file, "To: $to\nSubject: $subject\nDate: " . date('r') . "\n\n" . $body . "\n");
}
