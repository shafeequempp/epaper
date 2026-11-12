<?php
declare(strict_types=1);

/**
 * Tiles a faint, tilted logo over a page image, in staggered rows like the printed e-paper.
 * Settings: 'watermark' => [...] in config.php.
 */

/** Checks the settings and logo. Returns [true, ''] or [false, 'reason a person can act on']. */
function watermark_status(): array
{
    $c = cfg('watermark');
    if (!is_array($c)) {
        return [false, "There is no 'watermark' section in config.php. Add it (see the README) and upload again."];
    }
    if (empty($c['enabled'])) {
        return [false, "The watermark is switched off ('enabled' => false in config.php)."];
    }
    $logo = (string)($c['logo'] ?? '');
    if ($logo === '' || !is_file($logo)) {
        return [false, 'Logo file not found at ' . ($logo ?: '(no path)') . '. Save the logo PNG there.'];
    }
    $info = @getimagesize($logo);
    if (!$info || $info[2] !== IMAGETYPE_PNG) {
        return [false, 'The logo at ' . $logo . ' is not a real PNG (a renamed JPG will not work). Export it as PNG with a transparent background.'];
    }
    return [true, ''];
}

/**
 * @param resource|GdImage $img page image, changed in place
 * @return bool true when the watermark was applied
 */
function apply_watermark($img): bool
{
    [$ok] = watermark_status();
    if (!$ok) {
        return false;
    }
    $c = cfg('watermark');
    $logo = @imagecreatefrompng($c['logo']);
    if (!$logo) {
        return false;
    }
    $W = imagesx($img);

    // 1. Scale the logo to a share of the page width
    $lw = max(20, (int)round($W * (float)($c['size'] ?? 0.11)));
    $lh = max(8, (int)round(imagesy($logo) * $lw / imagesx($logo)));
    $tile = imagecreatetruecolor($lw, $lh);
    imagealphablending($tile, false);
    imagesavealpha($tile, true);
    imagefill($tile, 0, 0, imagecolorallocatealpha($tile, 0, 0, 0, 127));
    imagecopyresampled($tile, $logo, 0, 0, 0, 0, $lw, $lh, imagesx($logo), imagesy($logo));
    imagedestroy($logo);

    // 2. Recolour and make faint. Only the logo's shape is used, so a white or light logo still shows.
    //    Transparent PNG: shape = its transparency. Logo on a solid background: shape = how dark each pixel is.
    $opacity = max(0.0, min(1.0, (float)($c['opacity'] ?? 0.12)));
    $hex = ltrim((string)($c['color'] ?? '#000000'), '#');
    $cr = hexdec(substr($hex, 0, 2));
    $cg = hexdec(substr($hex, 2, 2));
    $cb = hexdec(substr($hex, 4, 2));
    $hasAlpha = false;
    for ($x = 0; $x < $lw && !$hasAlpha; $x += 2) {
        for ($y = 0; $y < $lh; $y += 2) {
            if (((imagecolorat($tile, $x, $y) >> 24) & 0x7F) > 20) { $hasAlpha = true; break; }
        }
    }
    for ($x = 0; $x < $lw; $x++) {
        for ($y = 0; $y < $lh; $y++) {
            $rgba = imagecolorat($tile, $x, $y);
            if ($hasAlpha) {
                $cover = (127 - (($rgba >> 24) & 0x7F)) / 127;                     // 1 = solid logo pixel
            } else {
                $lum = (0.299 * (($rgba >> 16) & 0xFF) + 0.587 * (($rgba >> 8) & 0xFF) + 0.114 * ($rgba & 0xFF)) / 255;
                $cover = max(0.0, min(1.0, (0.92 - $lum) / 0.6));                  // dark = logo, light = background
            }
            $newA = 127 - (int)round(127 * $cover * $opacity);
            imagesetpixel($tile, $x, $y, ($newA << 24) | ($cr << 16) | ($cg << 8) | $cb);
        }
    }

    // 3. Tilt it (positive angle = rising to the right)
    $angle = (float)($c['angle'] ?? 30);
    if ($angle != 0.0) {
        $rot = imagerotate($tile, $angle, imagecolorallocatealpha($tile, 0, 0, 0, 127));
        imagedestroy($tile);
        imagealphablending($rot, false);
        imagesavealpha($rot, true);
        $tile = $rot;
    }
    $tw = imagesx($tile);
    $th = imagesy($tile);

    // 4. Repeat across the page; every other row shifted by half a step
    $stepX = max($tw + 10, (int)round($W * (float)($c['gap_x'] ?? 0.30)));
    $stepY = max($th + 10, (int)round($W * (float)($c['gap_y'] ?? 0.16)));
    imagealphablending($img, true);
    $row = 0;
    for ($y = -$th / 2; $y < imagesy($img); $y += $stepY, $row++) {
        $shift = ($row % 2) ? $stepX / 2 : 0;
        for ($x = -$tw / 2 + $shift; $x < $W; $x += $stepX) {
            imagecopy($img, $tile, (int)$x, (int)$y, 0, 0, $tw, $th);
        }
    }
    imagedestroy($tile);
    return true;
}

/** Path of the marker file that says a page folder is already watermarked. */
function watermark_marker(array $page): string
{
    return rtrim((string)cfg('storage_dir'), '/') . '/' . dirname($page['img_full']) . '/.watermarked';
}

/**
 * Watermarks a page that was imported earlier: the full image, then medium, thumb and its
 * article images are made again from it. Skips pages already done.
 * @return string done | skipped | error message
 */
function watermark_existing_page(array $page): string
{
    if (is_file(watermark_marker($page))) {
        return 'skipped';
    }
    $base = rtrim((string)cfg('storage_dir'), '/') . '/';
    $full = $base . $page['img_full'];
    $src = @imagecreatefromjpeg($full);
    if (!$src) {
        return 'cannot read ' . $page['img_full'];
    }
    if (!apply_watermark($src)) {
        imagedestroy($src);
        return watermark_status()[1];
    }
    $q = (int)cfg('jpeg_quality');
    imagejpeg($src, $full, 88);
    save_resized($src, $base . $page['img_medium'], (int)cfg('medium_width'), $q);
    save_resized($src, $base . $page['img_thumb'], (int)cfg('thumb_width'), $q);

    $W = imagesx($src);
    $H = imagesy($src);
    $pad = (int)cfg('crop_padding');
    $regions = q('SELECT r.* FROM article_regions r JOIN articles a ON a.id = r.article_id WHERE a.page_id = ?', [$page['id']])->fetchAll();
    foreach ($regions as $r) {
        $x = max(0, (int)floor((float)$r['x'] * $W) - $pad);
        $y = max(0, (int)floor((float)$r['y'] * $H) - $pad);
        $w = min($W - $x, (int)ceil((float)$r['w'] * $W) + 2 * $pad);
        $h = min($H - $y, (int)ceil((float)$r['h'] * $H) + 2 * $pad);
        if ($w < 20 || $h < 20) {
            continue;
        }
        $crop = imagecrop($src, ['x' => $x, 'y' => $y, 'width' => $w, 'height' => $h]);
        if ($crop) {
            imagejpeg($crop, $base . $r['img'], $q);
            imagedestroy($crop);
        }
    }
    imagedestroy($src);
    @file_put_contents(watermark_marker($page), date('c'));
    return 'done';
}