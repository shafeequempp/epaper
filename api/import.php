<?php
/**
 * Receives pages straight from EpaperExport.jsx (batch export) and stores them.
 *
 * POST, multipart/form-data:
 *   api_key     must equal 'api_key' in config.php
 *   action      import (default) | publish
 *   edition_code  code from the file name (9pg2e-... -> e); or
 *   edition       edition web name, or several separated by commas for a shared page
 *   batch_codes   every code in the same export, comma separated (settles shared codes like MK)
 *   issue_date  YYYY-MM-DD
 *   manifest    the page's manifest JSON file          (import)
 *   files[]     the page JPG(s) named in the manifest   (import)
 * Replies with JSON: { "ok": true, ... } or { "ok": false, "error": "..." }
 */
declare(strict_types=1);
require __DIR__ . '/../inc/bootstrap.php';
require __DIR__ . '/../inc/importer.php';
require __DIR__ . '/../inc/editions.php';

header('Content-Type: application/json; charset=utf-8');

function reply(int $status, array $data): void
{
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

$key = (string)(cfg('api_key') ?? '');
if ($key === '') {
    reply(403, ['ok' => false, 'error' => 'Upload from InDesign is switched off. Set api_key in config.php.']);
}
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    reply(405, ['ok' => false, 'error' => 'Send the pages with POST.']);
}
if (!$_POST && (int)($_SERVER['CONTENT_LENGTH'] ?? 0) > 0) {
    reply(413, ['ok' => false, 'error' => 'The upload is larger than post_max_size in php.ini.']);
}
if (!hash_equals($key, (string)($_POST['api_key'] ?? ''))) {
    reply(401, ['ok' => false, 'error' => 'Wrong API key. apiKey in the script must match api_key in config.php.']);
}

$date = (string)($_POST['issue_date'] ?? '');
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) || !strtotime($date)) {
    reply(422, ['ok' => false, 'error' => 'issue_date must look like 2026-09-16.']);
}
$editions = [];
$code = trim((string)($_POST['edition_code'] ?? ''));
if ($code !== '') {
    $batch = array_filter(array_map('trim', explode(',', (string)($_POST['batch_codes'] ?? ''))));
    try {
        $editions[] = resolve_edition_code($code, $batch);
    } catch (RuntimeException $e) {
        reply(422, ['ok' => false, 'error' => $e->getMessage()]);
    }
} else {
    $slugs = array_filter(array_map('trim', explode(',', strtolower((string)($_POST['edition'] ?? '')))));
    if (!$slugs) {
        reply(422, ['ok' => false, 'error' => 'Send edition_code (from the file name) or the edition web name.']);
    }
    foreach ($slugs as $slug) {
        $e = q('SELECT * FROM editions WHERE slug = ?', [$slug])->fetch();
        if (!$e) {
            reply(422, ['ok' => false, 'error' => "Edition \"$slug\" does not exist. Add it in the dashboard (Editions)."]);
        }
        $editions[] = $e;
    }
}
$editionNames = implode(', ', array_column($editions, 'name'));

try {
    $issueIds = [];
    foreach ($editions as $e) {
        $issueIds[] = ensure_issue((int)$e['id'], $date);
    }

    if (($_POST['action'] ?? 'import') === 'publish') {
        foreach ($issueIds as $id) {
            $pages = (int)q('SELECT COUNT(*) FROM pages WHERE issue_id = ?', [$id])->fetchColumn();
            if ($pages === 0) {
                reply(422, ['ok' => false, 'error' => 'The issue has no pages, so it was not published.']);
            }
            q("UPDATE issues SET status = 'published', published_at = NOW() WHERE id = ?", [$id]);
        }
        reply(200, ['ok' => true, 'published' => true, 'issue_ids' => $issueIds, 'edition_name' => $editionNames]);
    }

    $m = $_FILES['manifest'] ?? null;
    if (!$m || ($m['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        reply(422, ['ok' => false, 'error' => 'The manifest file did not arrive (upload error ' . (int)($m['error'] ?? 4) . ').']);
    }
    $manifest = json_decode((string)file_get_contents($m['tmp_name']), true);
    if (!is_array($manifest)) {
        reply(422, ['ok' => false, 'error' => 'The manifest is not valid JSON.']);
    }

    $images = [];
    $f = $_FILES['files'] ?? null;
    $count = $f && is_array($f['name']) ? count($f['name']) : 0;
    for ($i = 0; $i < $count; $i++) {
        if ($f['error'][$i] !== UPLOAD_ERR_OK) {
            reply(422, ['ok' => false, 'error' => 'Image "' . $f['name'][$i] . '" failed to upload (PHP error ' . $f['error'][$i] . '). Check upload_max_filesize.']);
        }
        $images[basename((string)$f['name'][$i])] = $f['tmp_name'][$i];
    }

    $report = import_export($manifest, $images, $issueIds);
    reply(200, [
        'ok'        => true,
        'issue_ids' => $issueIds,
        'edition_name' => $editionNames,
        'pages'     => $report['pages'],
        'articles'  => $report['articles'],
        'warnings'  => $report['warnings'],
    ]);
} catch (Throwable $e) {
    reply(422, ['ok' => false, 'error' => $e->getMessage()]);
}
