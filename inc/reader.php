<?php
declare(strict_types=1);
require_once __DIR__ . '/editions.php';

function active_editions(): array
{
    return q('SELECT * FROM editions WHERE is_active = 1 ORDER BY group_sort, sort_order, name')->fetchAll();
}

/** Admins see drafts with ?preview=1; everyone else sees published issues only. */
function can_preview(): bool
{
    return !empty($_GET['preview']) && current_user() !== null;
}

/**
 * Resolves edition + date from the query string, falling back to the first edition
 * and its latest date. A date counts when the edition, or an edition it takes pages
 * from (its main edition), has a published issue that day.
 */
function resolve_issue(): array
{
    $editions = active_editions();
    if (!$editions) {
        return ['editions' => [], 'edition' => null, 'issue' => null, 'dates' => [], 'chain' => []];
    }
    $slug = (string)($_GET['e'] ?? '');
    $edition = $editions[0];
    foreach ($editions as $e) {
        if ($e['slug'] === $slug) {
            $edition = $e;
        }
    }
    $chain = edition_chain($edition);
    $ids = array_map(function ($e) { return (int)$e['id']; }, $chain);
    $in = implode(',', $ids);
    $statusSql = can_preview() ? '' : "AND status = 'published'";
    $dates = q("SELECT DISTINCT issue_date FROM issues WHERE edition_id IN ($in) $statusSql ORDER BY issue_date DESC LIMIT 800")
        ->fetchAll(PDO::FETCH_COLUMN);

    $date = (string)($_GET['d'] ?? '');
    $issue = null;
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
        if (in_array($date, $dates, true)) {
            $issue = ['issue_date' => $date];
        }
    } elseif ($dates) {
        $issue = ['issue_date' => $dates[0]];
    }
    if ($issue) {
        $own = q("SELECT * FROM issues WHERE edition_id = ? AND issue_date = ? $statusSql", [$edition['id'], $issue['issue_date']])->fetch();
        $issue = $own ?: ['id' => 0, 'edition_id' => $edition['id'], 'issue_date' => $issue['issue_date']];
    }
    return ['editions' => $editions, 'edition' => $edition, 'issue' => $issue, 'dates' => $dates,
            'requested_date' => $date, 'chain' => $chain];
}

/**
 * Pages for the chosen edition and date: its own pages, then for each page number it lacks,
 * the page from the edition it takes pages from (e.g. Ernakulam for Kothamangalam-Muvattupuzha).
 * Inherited pages carry 'from_edition'.
 */
function edition_pages(array $ctx): array
{
    if (!$ctx['issue']) {
        return [];
    }
    $statusSql = can_preview() ? '' : "AND i.status = 'published'";
    $byNumber = [];
    foreach ($ctx['chain'] as $level => $ed) {
        $rows = q("SELECT p.* FROM pages p JOIN issues i ON i.id = p.issue_id
                   WHERE i.edition_id = ? AND i.issue_date = ? $statusSql ORDER BY p.page_number",
                  [$ed['id'], $ctx['issue']['issue_date']])->fetchAll();
        foreach ($rows as $p) {
            $n = (int)$p['page_number'];
            if (!isset($byNumber[$n])) {
                $p['from_edition'] = $level === 0 ? null : $ed['name'];
                $byNumber[$n] = $p;
            }
        }
    }
    ksort($byNumber);
    return array_values($byNumber);
}

/** Own pages of one issue (dashboard). */
function issue_pages(int $issueId): array
{
    return q('SELECT * FROM pages WHERE issue_id = ? ORDER BY page_number', [$issueId])->fetchAll();
}

/** Articles of a page with their regions, for hotspots and headline lists. */
function page_articles(int $pageId): array
{
    $arts = q('SELECT id, headline, sort_order FROM articles WHERE page_id = ? ORDER BY sort_order', [$pageId])->fetchAll();
    if (!$arts) {
        return [];
    }
    $ids = array_column($arts, 'id');
    $in = implode(',', array_fill(0, count($ids), '?'));
    $regions = q("SELECT article_id, x, y, w, h, img FROM article_regions WHERE article_id IN ($in) ORDER BY seq", $ids)->fetchAll();
    $byArticle = [];
    foreach ($regions as $r) {
        $byArticle[$r['article_id']][] = $r;
    }
    foreach ($arts as &$a) {
        $a['regions'] = $byArticle[$a['id']] ?? [];
    }
    return $arts;
}

/** Full article with page/issue/edition context and neighbours on the same page. */
function load_article(int $id): ?array
{
    $statusSql = can_preview() ? '' : "AND i.status = 'published'";
    $a = q("SELECT a.*, p.page_number, p.page_label, p.id AS page_id, i.issue_date, i.id AS issue_id,
                   e.slug AS edition_slug, e.name AS edition_name, e.name_ml AS edition_name_ml
            FROM articles a
            JOIN pages p ON p.id = a.page_id
            JOIN issues i ON i.id = p.issue_id
            JOIN editions e ON e.id = i.edition_id
            WHERE a.id = ? $statusSql", [$id])->fetch();
    if (!$a) {
        return null;
    }
    $a['regions'] = q('SELECT * FROM article_regions WHERE article_id = ? ORDER BY seq', [$id])->fetchAll();
    $a['captions'] = json_decode((string)$a['captions'], true) ?: [];
    $sib = q('SELECT id FROM articles WHERE page_id = ? ORDER BY sort_order', [$a['page_id']])->fetchAll(PDO::FETCH_COLUMN);
    $pos = array_search($a['id'], $sib);
    $a['prev_id'] = $pos > 0 ? (int)$sib[$pos - 1] : null;
    $a['next_id'] = $pos !== false && $pos < count($sib) - 1 ? (int)$sib[$pos + 1] : null;
    return $a;
}

/**
 * Full-text search. Malayalam words take suffixes (കൊച്ചിയിൽ), so each term is a prefix match.
 * Falls back to LIKE over the last 90 days when FULLTEXT finds nothing (e.g. very short terms).
 */
function search_articles(string $query, array $editionIds, int $limit = 40): array
{
    $query = normalize_ml($query);
    $terms = preg_split('/\s+/u', trim(preg_replace('/[+\-<>()~*"@]+/u', ' ', $query) ?? ''), -1, PREG_SPLIT_NO_EMPTY);
    if (!$terms) {
        return [];
    }
    $editionSql = $editionIds ? 'AND e.id IN (' . implode(',', array_map('intval', $editionIds)) . ')' : '';
    $select = "SELECT a.id, a.headline, a.body, p.page_number, p.page_label, i.issue_date,
                      e.id AS edition_id, e.slug AS edition_slug, e.name AS edition_name,
                      (SELECT img FROM article_regions r WHERE r.article_id = a.id ORDER BY seq LIMIT 1) AS img
               FROM articles a
               JOIN pages p ON p.id = a.page_id
               JOIN issues i ON i.id = p.issue_id
               JOIN editions e ON e.id = i.edition_id
               WHERE i.status = 'published' $editionSql";

    $boolean = implode(' ', array_map(function ($t) { return '+' . $t . '*'; }, $terms));
    $rows = q("$select AND MATCH(a.headline, a.body) AGAINST (? IN BOOLEAN MODE)
               ORDER BY i.issue_date DESC, MATCH(a.headline, a.body) AGAINST (? IN BOOLEAN MODE) DESC
               LIMIT $limit", [$boolean, $boolean])->fetchAll();

    if (!$rows) {
        $where = [];
        $params = [];
        foreach ($terms as $t) {
            $where[] = '(a.headline LIKE ? OR a.body LIKE ?)';
            $like = '%' . addcslashes($t, '%_\\') . '%';
            $params[] = $like;
            $params[] = $like;
        }
        $rows = q("$select AND i.issue_date >= DATE_SUB(CURDATE(), INTERVAL 90 DAY) AND " . implode(' AND ', $where) .
                  " ORDER BY i.issue_date DESC LIMIT $limit", $params)->fetchAll();
    }
    // A story on an inherited page only counts when the reader's edition has no own page with that number
    if (count($editionIds) > 1 && $rows) {
        $own = [];
        foreach (q('SELECT i.issue_date, p.page_number FROM pages p JOIN issues i ON i.id = p.issue_id
                    WHERE i.edition_id = ?', [$editionIds[0]])->fetchAll() as $o) {
            $own[$o['issue_date'] . '|' . $o['page_number']] = true;
        }
        $rows = array_values(array_filter($rows, function ($r) use ($own, $editionIds) {
            return (int)$r['edition_id'] === $editionIds[0] || !isset($own[$r['issue_date'] . '|' . $r['page_number']]);
        }));
    }
    foreach ($rows as &$r) {
        $r['snippet'] = search_snippet($r['body'], $terms);
        unset($r['body']);
    }
    return $rows;
}

function search_snippet(string $body, array $terms, int $len = 200): string
{
    $flat = trim(preg_replace('/\s+/u', ' ', $body) ?? $body);
    $pos = false;
    foreach ($terms as $t) {
        $pos = mb_stripos($flat, $t);
        if ($pos !== false) {
            break;
        }
    }
    $start = $pos === false ? 0 : max(0, $pos - 60);
    $snip = mb_substr($flat, $start, $len);
    $html = h(($start > 0 ? '…' : '') . $snip . (mb_strlen($flat) > $start + $len ? '…' : ''));
    foreach ($terms as $t) {
        $html = preg_replace('/(' . preg_quote(h($t), '/') . ')/iu', '<mark>$1</mark>', $html) ?? $html;
    }
    return $html;
}
