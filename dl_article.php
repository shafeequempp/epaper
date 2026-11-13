<?php
/**
 * Download one article as a single branded image: Mathrubhumi masthead on top, the
 * article clip(s) in the middle, and a footer code "DD/MM/YYYY  EDITION  Pg NN".
 * Only signed-in readers with access (subscription / trial / archive) can download.
 */
declare(strict_types=1);
require __DIR__ . '/inc/bootstrap.php';
require __DIR__ . '/inc/reader.php';

reader_gate();   // must be signed in (else -> landing)

$a = load_article((int)($_GET['id'] ?? 0));
if (!$a) {
    http_response_code(404);
    exit('Article not found.');
}
if (!can_download_article($a)) {
    http_response_code(403);
    exit('Subscribe to download this article.');
}

$storage = rtrim((string)cfg('storage_dir'), '/');
$imgs = [];
foreach (($a['regions'] ?? []) as $r) {
    $p = $storage . '/' . ltrim((string)$r['img'], '/');
    if (is_file($p)) {
        $im = @imagecreatefromjpeg($p);
        if ($im) {
            $imgs[] = $im;
        }
    }
}
if (!$imgs) {
    http_response_code(404);
    exit('No image for this article.');
}

$pad = 24; $gap = 14; $maxW = 1100;
$contentW = 0;
foreach ($imgs as $im) {
    $contentW = max($contentW, imagesx($im));
}
$contentW = min($contentW, $maxW);

// masthead logo (dark wordmark on transparent)
$logo = @imagecreatefrompng(__DIR__ . '/assets/watermark.png');
$logoW = 0; $logoH = 0;
if ($logo) {
    $logoW = (int)min($contentW, 320);
    $logoH = (int)round(imagesy($logo) * ($logoW / imagesx($logo)));
}

// scale each region to fit the content width
$rows = []; $bodyH = 0;
foreach ($imgs as $i => $im) {
    $w = imagesx($im); $h = imagesy($im);
    $sw = min($w, $contentW); $sh = (int)round($h * ($sw / $w));
    $rows[] = ['im' => $im, 'w' => $sw, 'h' => $sh];
    $bodyH += $sh + ($i > 0 ? $gap : 0);
}

$ruleH = 3; $footerH = 46;
$headTop = $pad + ($logoH ? $logoH + 14 : 14);
$W = $contentW + $pad * 2;
$H = $headTop + $ruleH + 16 + $bodyH + 16 + $footerH + $pad;

$cv = imagecreatetruecolor($W, $H);
imagealphablending($cv, true);
$white = imagecolorallocate($cv, 255, 255, 255);
imagefilledrectangle($cv, 0, 0, $W, $H, $white);
$red  = imagecolorallocate($cv, 196, 22, 28);
$grey = imagecolorallocate($cv, 90, 90, 90);
$line = imagecolorallocate($cv, 210, 210, 210);

$y = $pad;
if ($logo) {
    imagecopyresampled($cv, $logo, (int)(($W - $logoW) / 2), $y, 0, 0, $logoW, $logoH, imagesx($logo), imagesy($logo));
    $y += $logoH + 14;
} else {
    $y += 14;
}
imagefilledrectangle($cv, $pad, $y, $W - $pad, $y + $ruleH - 1, $red);   // masthead rule
$y += $ruleH + 16;

foreach ($rows as $row) {
    imagecopyresampled($cv, $row['im'], (int)(($W - $row['w']) / 2), $y, 0, 0, $row['w'], $row['h'], imagesx($row['im']), imagesy($row['im']));
    $y += $row['h'] + $gap;
}
$y = $y - $gap + 16;
imagefilledrectangle($cv, $pad, $y, $W - $pad, $y, $line);               // footer divider
$y += 14;

// footer code: DD/MM/YYYY  EDITION  Pg NN
$date = date('d/m/Y', strtotime((string)$a['issue_date']));
$ed   = strtoupper((string)($a['edition_name'] ?? ''));
$pgN  = (int)preg_replace('/\D/', '', (string)$a['page_label']);
$pg   = 'Pg ' . ($pgN > 0 ? sprintf('%02d', $pgN) : (string)$a['page_label']);
$text = $date . '     ' . $ed . '     ' . $pg;

$font = null;
foreach (['C:/Windows/Fonts/arialbd.ttf', 'C:/Windows/Fonts/arial.ttf'] as $f) {
    if (is_file($f)) { $font = $f; break; }
}
if ($font) {
    imagettftext($cv, 12, 0, $pad, $y + 16, $grey, $font, $text);
} else {
    imagestring($cv, 4, $pad, $y + 4, $text, $grey);
}

$fname = preg_replace('/[^A-Za-z0-9]+/', '-', $ed . '-' . $date . '-' . $pg . '-a' . $a['id']) . '.jpg';
header('Content-Type: image/jpeg');
header('Content-Disposition: attachment; filename="' . $fname . '"');
header('Cache-Control: private, no-store');
imagejpeg($cv, null, 92);
