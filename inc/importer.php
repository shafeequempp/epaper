<?php
declare(strict_types=1);
require_once __DIR__ . '/watermark.php';

/**
 * Imports one InDesign export (manifest.json + page JPGs) into one or more issues.
 *
 * @param array $manifest decoded manifest.json
 * @param array $files    uploaded image files: basename => temp path
 * @param int[] $issueIds issues to attach the pages to (shared pages = several editions)
 * @return array report: pages, articles, warnings
 */
function import_export(array $manifest, array $files, array $issueIds): array
{
    @ini_set('memory_limit', '768M');
    @set_time_limit(300);

    if (empty($manifest['pages']) || !is_array($manifest['pages'])) {
        throw new RuntimeException('manifest.json has no pages. Re-run EpaperExport.jsx in InDesign.');
    }
    if (!$issueIds) {
        throw new RuntimeException('Choose at least one edition.');
    }

    $report = ['pages' => 0, 'articles' => 0, 'warnings' => []];
    foreach ($manifest['missing_links'] ?? [] as $m) {
        $report['warnings'][] = "Missing link in InDesign, exported at low resolution: $m";
    }

    // Validate everything before writing anything
    $prepared = [];
    foreach ($manifest['pages'] as $i => $pg) {
        $img = basename((string)($pg['image'] ?? ''));
        if ($img === '' || !isset($files[$img])) {
            throw new RuntimeException("Page image \"$img\" is listed in manifest.json but was not uploaded.");
        }
        $info = @getimagesize($files[$img]);
        if (!$info || $info[2] !== IMAGETYPE_JPEG) {
            throw new RuntimeException("\"$img\" is not a JPEG image.");
        }
        $label = trim((string)($pg['name'] ?? ''));
        if (isset($pg['page_number']) && (int)$pg['page_number'] > 0) {
            $number = (int)$pg['page_number'];          // set by EpaperExport 2.0 (file name or page number)
        } else {
            $number = ctype_digit($label) ? (int)$label : ((int)($pg['index'] ?? $i) + 1);
        }
        $prepared[] = ['pg' => $pg, 'tmp' => $files[$img], 'number' => $number, 'label' => $label ?: (string)$number];
    }

    foreach ($prepared as $p) {
        $source = basename((string)($p['pg']['source'] ?? $manifest['source'] ?? ''));
        $listed = count($p['pg']['articles'] ?? []);
        $stored = store_page_images($p['tmp'], $p['number'], $p['pg']['articles'] ?? []);
        if ($listed === 0) {
            $report['warnings'][] = 'Page ' . $p['label'] . ': manifest.json has no articles, so nothing is clickable. '
                . 'The InDesign script found no stories on this page. Check export_log.txt in the export folder.';
        } elseif (!$stored['articles']) {
            $report['warnings'][] = 'Page ' . $p['label'] . ": manifest.json lists $listed article(s) but none had a usable area. "
                . 'Their positions may be outside the page.';
        }
        $report['warnings'] = array_merge($report['warnings'], $stored['warnings']);

        db()->beginTransaction();
        try {
            foreach ($issueIds as $issueId) {
                $pageId = upsert_page((int)$issueId, $p, $stored, $source);
                $report['articles'] += insert_articles($pageId, $stored['articles']);
            }
            db()->commit();
        } catch (Throwable $e) {
            db()->rollBack();
            throw $e;
        }
        $report['pages']++;
    }
    return $report;
}

/**
 * Writes full/medium/thumb renditions and one crop per article region.
 * Files go in a fresh folder per upload so a re-upload never overwrites what readers are viewing.
 */
function store_page_images(string $tmp, int $number, array $articles): array
{
    $relDir = date('Y/m/d') . '/' . bin2hex(random_bytes(6)) . '/p' . $number;
    $absDir = rtrim((string)cfg('storage_dir'), '/') . '/' . $relDir;
    if (!is_dir($absDir) && !mkdir($absDir, 0775, true)) {
        throw new RuntimeException("Cannot create folder $absDir. Check that storage/ is writable.");
    }

    $src = @imagecreatefromjpeg($tmp);
    if (!$src) {
        throw new RuntimeException('Could not read the page JPEG. Is the GD extension enabled in PHP?');
    }
    $W = imagesx($src);
    $H = imagesy($src);
    $quality = (int)cfg('jpeg_quality');

    // Watermark first, so the full page, thumbnails and every article crop carry it
    $watermarkWarning = null;
    if (apply_watermark($src)) {
        if (!imagejpeg($src, "$absDir/full.jpg", 88)) {
            throw new RuntimeException('Could not save the page image.');
        }
        @file_put_contents("$absDir/.watermarked", date('c'));
    } else {
        $watermarkWarning = 'Page ' . $number . ' has NO watermark: ' . watermark_status()[1];
        if (!copy($tmp, "$absDir/full.jpg")) {
            throw new RuntimeException('Could not save the page image.');
        }
    }
    save_resized($src, "$absDir/medium.jpg", (int)cfg('medium_width'), $quality);
    save_resized($src, "$absDir/thumb.jpg", (int)cfg('thumb_width'), $quality);

    $pad = (int)cfg('crop_padding');
    $out = [];
    $warnings = $watermarkWarning ? [$watermarkWarning] : [];
    foreach ($articles as $ai => $a) {
        $regions = [];
        foreach (($a['regions'] ?? []) as $ri => $r) {
            $x = max(0, (int)floor((float)$r['x'] * $W) - $pad);
            $y = max(0, (int)floor((float)$r['y'] * $H) - $pad);
            $w = min($W - $x, (int)ceil((float)$r['w'] * $W) + 2 * $pad);
            $h = min($H - $y, (int)ceil((float)$r['h'] * $H) + 2 * $pad);
            if ($w < 20 || $h < 20) {
                continue;
            }
            $crop = imagecrop($src, ['x' => $x, 'y' => $y, 'width' => $w, 'height' => $h]);
            if (!$crop) {
                continue;
            }
            $name = sprintf('a%d_r%d.jpg', $ai + 1, $ri + 1);
            imagejpeg($crop, "$absDir/$name", $quality);
            imagedestroy($crop);
            $regions[] = [
                'x' => (float)$r['x'], 'y' => (float)$r['y'], 'w' => (float)$r['w'], 'h' => (float)$r['h'],
                'img' => "$relDir/$name", 'img_width' => $w, 'img_height' => $h,
            ];
        }
        if (!$regions) {
            $warnings[] = 'Skipped an article with no usable area on page ' . $number . '.';
            continue;
        }
        $headline = normalize_ml((string)($a['headline'] ?? ''));
        $body = normalize_ml((string)($a['body'] ?? ''));
        if ($headline === '' && $body !== '') {
            $headline = excerpt(strtok($body, "\n") ?: $body, 120);
        }
        $out[] = [
            'headline' => mb_substr($headline, 0, 500),
            'body'     => $body,
            'captions' => array_values(array_map('normalize_ml', (array)($a['captions'] ?? []))),
            'method'   => substr((string)($a['method'] ?? ''), 0, 20),
            'regions'  => $regions,
        ];
    }
    imagedestroy($src);

    return [
        'dir' => $relDir, 'width' => $W, 'height' => $H,
        'full' => "$relDir/full.jpg", 'medium' => "$relDir/medium.jpg", 'thumb' => "$relDir/thumb.jpg",
        'articles' => $out, 'warnings' => $warnings,
    ];
}

function save_resized($src, string $dest, int $targetW, int $quality): void
{
    $W = imagesx($src);
    $H = imagesy($src);
    $targetW = min($targetW, $W);
    $targetH = (int)round($H * $targetW / $W);
    $dst = imagecreatetruecolor($targetW, $targetH);
    imagecopyresampled($dst, $src, 0, 0, 0, 0, $targetW, $targetH, $W, $H);
    imagejpeg($dst, $dest, $quality);
    imagedestroy($dst);
}

/** Replaces the page at this number (and its articles) or inserts it. */
function upsert_page(int $issueId, array $p, array $stored, string $source): int
{
    $existing = q('SELECT id, version FROM pages WHERE issue_id = ? AND page_number = ?', [$issueId, $p['number']])->fetch();
    if ($existing) {
        q('DELETE FROM articles WHERE page_id = ?', [$existing['id']]);
        q('UPDATE pages SET page_label=?, width_px=?, height_px=?, img_full=?, img_medium=?, img_thumb=?,
             source_file=?, version=version+1 WHERE id=?', [
            $p['label'], $stored['width'], $stored['height'], $stored['full'], $stored['medium'], $stored['thumb'],
            $source, $existing['id'],
        ]);
        return (int)$existing['id'];
    }
    q('INSERT INTO pages (issue_id, page_number, page_label, width_px, height_px, img_full, img_medium, img_thumb, source_file)
       VALUES (?,?,?,?,?,?,?,?,?)', [
        $issueId, $p['number'], $p['label'], $stored['width'], $stored['height'],
        $stored['full'], $stored['medium'], $stored['thumb'], $source,
    ]);
    return (int)db()->lastInsertId();
}

function insert_articles(int $pageId, array $articles): int
{
    $n = 0;
    foreach ($articles as $i => $a) {
        q('INSERT INTO articles (page_id, sort_order, headline, body, captions, method) VALUES (?,?,?,?,?,?)', [
            $pageId, $i + 1, $a['headline'], $a['body'],
            json_encode($a['captions'], JSON_UNESCAPED_UNICODE), $a['method'],
        ]);
        $articleId = (int)db()->lastInsertId();
        foreach ($a['regions'] as $s => $r) {
            q('INSERT INTO article_regions (article_id, seq, x, y, w, h, img, img_width, img_height) VALUES (?,?,?,?,?,?,?,?,?)', [
                $articleId, $s + 1, $r['x'], $r['y'], $r['w'], $r['h'], $r['img'], $r['img_width'], $r['img_height'],
            ]);
        }
        $n++;
    }
    return $n;
}

/** Finds or creates the issue for an edition + date. */
function ensure_issue(int $editionId, string $date): int
{
    $row = q('SELECT id FROM issues WHERE edition_id = ? AND issue_date = ?', [$editionId, $date])->fetch();
    if ($row) {
        return (int)$row['id'];
    }
    q('INSERT INTO issues (edition_id, issue_date) VALUES (?, ?)', [$editionId, $date]);
    return (int)db()->lastInsertId();
}

/**
 * Imports a manifest whose pages carry their own edition_code and issue_date
 * (EpaperExport 3: read from file names like 9pg2e-LocalNews.indd).
 * Pages are grouped per edition and date; each group goes into its own issue.
 *
 * @return array report: pages, articles, warnings, issues => [ [issue_id, edition, date, pages] ]
 */
function import_by_codes(array $manifest, array $files, array $batchCodes = []): array
{
    require_once __DIR__ . '/editions.php';
    $pages = $manifest['pages'] ?? [];
    if (!$pages) {
        throw new RuntimeException('manifest.json has no pages. Re-run EpaperExport.jsx in InDesign.');
    }
    if (!$batchCodes) {
        foreach ($pages as $pg) {
            $batchCodes[] = (string)($pg['edition_code'] ?? '');
        }
    }
    $groups = [];
    foreach ($pages as $pg) {
        $code = strtoupper(trim((string)($pg['edition_code'] ?? '')));
        $date = (string)($pg['issue_date'] ?? '');
        if ($code === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            throw new RuntimeException('Page "' . ($pg['source'] ?? $pg['image'] ?? '?') . '" has no edition code or date. '
                . 'Its file name should look like 9pg2e-LocalNews.indd.');
        }
        $groups["$code|$date"][] = $pg;
    }

    $report = ['pages' => 0, 'articles' => 0, 'warnings' => [], 'issues' => []];
    foreach ($groups as $key => $groupPages) {
        [$code, $date] = explode('|', $key);
        $edition = resolve_edition_code($code, $batchCodes);
        $issueId = ensure_issue((int)$edition['id'], $date);
        $sub = $manifest;
        $sub['pages'] = $groupPages;
        $r = import_export($sub, $files, [$issueId]);
        $report['pages'] += $r['pages'];
        $report['articles'] += $r['articles'];
        $report['warnings'] = array_merge($report['warnings'], $r['warnings']);
        $report['issues'][] = ['issue_id' => $issueId, 'edition' => $edition['name'], 'code' => $code, 'date' => $date, 'pages' => $r['pages']];
    }
    return $report;
}

/** True when every page in the manifest names its own edition code and date. */
function manifest_has_codes(array $manifest): bool
{
    foreach ($manifest['pages'] ?? [] as $pg) {
        if (empty($pg['edition_code']) || empty($pg['issue_date'])) {
            return false;
        }
    }
    return !empty($manifest['pages']);
}
