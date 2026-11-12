<?php
// JSON for the Full page view's article panel
declare(strict_types=1);
require __DIR__ . '/../inc/bootstrap.php';
require __DIR__ . '/../inc/reader.php';

header('Content-Type: application/json; charset=utf-8');
$a = load_article((int)($_GET['id'] ?? 0));
if (!$a) {
    http_response_code(404);
    echo json_encode(['error' => 'Story not found']);
    exit;
}
if (!can_read_article($a)) {
    http_response_code(402);
    echo json_encode([
        'locked'   => true,
        'headline' => $a['headline'],
        'error'    => 'Subscribe to read this story.',
        'subscribe_url' => url('subscribe.php'),
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}
echo json_encode([
    'id'       => (int)$a['id'],
    'headline' => $a['headline'],
    'body'     => $a['body'],
    'captions' => $a['captions'],
    'page'     => $a['page_label'],
    'images'   => array_map(function ($r) {
        return ['src' => media($r['img']), 'w' => (int)$r['img_width'], 'h' => (int)$r['img_height']];
    }, $a['regions']),
    'url'      => url('article.php', array_filter(['id' => $a['id'], 'preview' => can_preview() ? 1 : null])),
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
