<?php
declare(strict_types=1);

$configFile = dirname(__DIR__) . '/config.php';
if (!is_file($configFile)) {
    http_response_code(500);
    exit('Missing config.php — copy config.sample.php to config.php and set your database details.');
}
$CONFIG = require $configFile;
date_default_timezone_set($CONFIG['timezone'] ?? 'Asia/Kolkata');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function cfg(string $key)
{
    global $CONFIG;
    return $CONFIG[$key] ?? null;
}

function db(): PDO
{
    static $pdo = null;
    if ($pdo === null) {
        $c = cfg('db');
        $pdo = new PDO($c['dsn'], $c['user'], $c['pass'], [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
    }
    return $pdo;
}

function q(string $sql, array $params = []): PDOStatement
{
    $st = db()->prepare($sql);
    $st->execute($params);
    return $st;
}

function h($s): string
{
    return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function url(string $path = '', array $params = []): string
{
    $u = rtrim((string)cfg('base_url'), '/') . '/' . ltrim($path, '/');
    return $params ? $u . '?' . http_build_query($params) : $u;
}

function media(?string $rel): string
{
    if (!$rel) {
        return '';
    }
    $rel = ltrim($rel, '/');
    // ?v=<file time>: when a page is watermarked again, browsers fetch the new image instead of a cached copy
    $file = rtrim((string)cfg('storage_dir'), '/') . '/' . $rel;
    $v = is_file($file) ? '?v=' . filemtime($file) : '';
    return rtrim((string)cfg('storage_url'), '/') . '/' . $rel . $v;
}

function redirect(string $to): void
{
    header('Location: ' . $to);
    exit;
}

function flash(?string $msg = null, string $type = 'ok')
{
    if ($msg !== null) {
        $_SESSION['flash'][] = ['msg' => $msg, 'type' => $type];
        return null;
    }
    $all = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $all;
}

// ---------------------------------------------------------------- auth

function current_user(): ?array
{
    return $_SESSION['user'] ?? null;
}

function require_login(string $role = 'uploader'): array
{
    $u = current_user();
    if ($u) {
        // re-check every request so role changes and deleted accounts take effect at once
        $row = q('SELECT id, username, role FROM users WHERE id = ?', [$u['id']])->fetch();
        $u = $row ? ['id' => (int)$row['id'], 'username' => $row['username'], 'role' => $row['role']] : null;
        $_SESSION['user'] = $u;
    }
    if (!$u) {
        redirect(url('admin/login.php', ['next' => $_SERVER['REQUEST_URI'] ?? '']));
    }
    $rank = ['uploader' => 1, 'editor' => 2, 'admin' => 3];
    if ($rank[$u['role']] < $rank[$role]) {
        http_response_code(403);
        exit('Your role cannot do this. Ask an admin for ' . h($role) . ' access.');
    }
    return $u;
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(16));
    }
    return $_SESSION['csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf" value="' . h(csrf_token()) . '">';
}

function check_csrf(): void
{
    if (!hash_equals(csrf_token(), (string)($_POST['csrf'] ?? ''))) {
        http_response_code(400);
        exit('Form expired. Go back, reload the page and try again.');
    }
}

// ---------------------------------------------------------------- text

/**
 * Normalise Malayalam so the same word always stores and searches the same way:
 * ZWJ chillu sequences -> atomic chillu, strip stray joiners and control chars.
 */
function normalize_ml(string $s): string
{
    $zwj = "\u{200D}";
    $virama = "\u{0D4D}";
    $map = [
        "\u{0D23}{$virama}{$zwj}" => "\u{0D7A}", // ൺ
        "\u{0D28}{$virama}{$zwj}" => "\u{0D7B}", // ൻ
        "\u{0D30}{$virama}{$zwj}" => "\u{0D7C}", // ർ
        "\u{0D32}{$virama}{$zwj}" => "\u{0D7D}", // ൽ
        "\u{0D33}{$virama}{$zwj}" => "\u{0D7E}", // ൾ
        "\u{0D15}{$virama}{$zwj}" => "\u{0D7F}", // ൿ
    ];
    $s = strtr($s, $map);
    if (class_exists('Normalizer')) {
        $s = Normalizer::normalize($s, Normalizer::FORM_C) ?: $s;
    }
    $s = preg_replace('/[\x{0000}-\x{0008}\x{000B}\x{000C}\x{000E}-\x{001F}\x{00AD}\x{FEFF}]/u', '', $s) ?? $s;
    $s = str_replace("\r", "\n", $s);
    return trim(preg_replace("/\n{3,}/", "\n\n", $s) ?? $s);
}

function excerpt(string $s, int $len = 220): string
{
    $s = trim(preg_replace('/\s+/u', ' ', $s) ?? $s);
    return mb_strlen($s) > $len ? mb_substr($s, 0, $len) . '…' : $s;
}

function fmt_date(string $ymd): string
{
    return date('j F Y', strtotime($ymd));
}

function plural(int $n, string $one, string $many): string
{
    return $n . ' ' . ($n === 1 ? $one : $many);
}

// Reader accounts, subscriptions and the paywall rule (safe to load everywhere).
require_once __DIR__ . '/subscriber.php';
