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
 * The paper is for signed-in readers. When the paywall is on, a visitor who is not signed in
 * (and is not staff previewing) is sent to the landing page instead of the reader.
 */
function reader_gate(): void
{
    if (!paywall_enabled() || can_preview() || current_subscriber() !== null) {
        return;
    }
    redirect(url('home.php'));
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
    // Calendar dates: every date ANY edition in this group published, so a main
    // edition also surfaces dates that exist only under one of its sub-editions.
    $group = group_editions($edition['group_name'] ?? null);
    $dateEds = $group ?: $chain;
    $dateIds = array_map(function ($e) { return (int)$e['id']; }, $dateEds);
    $in = implode(',', $dateIds ?: [0]);
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
            'requested_date' => $date, 'chain' => $chain,
            'main_editions' => main_editions(),
            'main_edition'  => main_edition_of($edition),
            'group_editions' => group_editions($edition['group_name'] ?? null)];
}

/**
 * Every page of the group for the issue date, indexed by edition and page number, plus
 * who "owns" each page number. A page number that only ONE edition in the group has is a
 * COMMON page (part of the shared paper); a page number that TWO OR MORE editions each
 * have their own file for (e.g. page 11) is a LOCAL page that differs per edition.
 *
 * Returns ['pages' => [editionId => [n => pageRow]], 'owners' => [n => [editionId,...]],
 *          'eds' => [editionId => editionRow]].
 */
function group_page_map(array $ctx): array
{
    $out = ['pages' => [], 'owners' => [], 'eds' => []];
    if (empty($ctx['issue']) || empty($ctx['group_editions'])) {
        return $out;
    }
    $date = $ctx['issue']['issue_date'];
    $statusSql = can_preview() ? '' : "AND i.status = 'published'";
    foreach ($ctx['group_editions'] as $ge) {
        $gid = (int)$ge['id'];
        $out['eds'][$gid] = $ge;
        $rows = q("SELECT p.* FROM pages p JOIN issues i ON i.id = p.issue_id
                   WHERE i.edition_id = ? AND i.issue_date = ? $statusSql ORDER BY p.page_number",
                  [$gid, $date])->fetchAll();
        foreach ($rows as $p) {
            $n = (int)$p['page_number'];
            $out['pages'][$gid][$n] = $p;
            $out['owners'][$n][] = $gid;
        }
    }
    return $out;
}

/** A page number is "local" when two or more editions in the group each have their own. */
function page_is_local(array $owners, int $n): bool
{
    return isset($owners[$n]) && count(array_unique($owners[$n])) >= 2;
}

/**
 * Pages shown when reading one edition, as a full paper:
 *   - COMMON pages (only one edition in the group has that number) are part of the shared
 *     paper and show for every edition — so the main edition shows all of them.
 *   - a LOCAL page (several editions each have their own, e.g. page 11) shows the reading
 *     edition's OWN version when it has one, otherwise the main edition's.
 * This way the main edition shows the whole paper and each sub-edition shows the same
 * paper with only its own differing page(s) swapped in — no matter which file-name code
 * the pages were uploaded under.
 */
function edition_pages(array $ctx): array
{
    if (!$ctx['issue']) {
        return [];
    }
    if (empty($ctx['group_editions'])) {
        return edition_pages_chain($ctx);   // standalone edition: old inherit-chain behaviour
    }

    $map        = group_page_map($ctx);
    $owners     = $map['owners'];
    $pagesByEd  = $map['pages'];
    $eds        = $map['eds'];
    $curId      = (int)$ctx['edition']['id'];
    $mainId     = (int)($ctx['main_edition']['id'] ?? $curId);

    $result = [];
    foreach ($owners as $n => $ownerIds) {
        $local = page_is_local($owners, $n);
        $srcId = null;
        if ($local && isset($pagesByEd[$curId][$n])) {
            $srcId = $curId;                                   // my own local page
        } elseif ($local && isset($pagesByEd[$mainId][$n])) {
            $srcId = $mainId;                                  // main's version of a local page
        } else {
            $srcId = $ownerIds[0];                             // the common page's single owner
        }
        if (isset($pagesByEd[$srcId][$n])) {
            $p = $pagesByEd[$srcId][$n];
            $p['from_edition'] = $srcId === $curId ? null : ($eds[$srcId]['name'] ?? null);
            $result[$n] = $p;
        }
    }
    ksort($result);
    return array_values($result);
}

/** Old behaviour for an edition with no group: own pages, then fill gaps from its chain. */
function edition_pages_chain(array $ctx): array
{
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

/**
 * Page-jump menu for the whole group:
 *   - the MAIN edition lists the full paper (every page number — common pages plus its
 *     own version of any local page), and
 *   - each sub-edition lists ONLY its differing (local) pages, e.g. its own page 11.
 * Returns [ ['edition'=>row, 'pages'=>[pageRow,...]] ], main first.
 */
function group_page_nav(array $ctx): array
{
    if (empty($ctx['issue']) || empty($ctx['group_editions'])) {
        return [];
    }
    $map       = group_page_map($ctx);
    $owners    = $map['owners'];
    $pagesByEd = $map['pages'];
    $eds       = $map['eds'];
    $mainId    = (int)($ctx['main_edition']['id'] ?? 0);
    if (!$mainId || !isset($eds[$mainId])) {
        $mainId = (int)$ctx['group_editions'][0]['id'];
    }

    $out = [];

    // 1) Main: the whole paper — every page number, using the main's own version of a
    //    local page, else the page's owner.
    $mainPages = [];
    foreach ($owners as $n => $ownerIds) {
        $srcId = (page_is_local($owners, $n) && isset($pagesByEd[$mainId][$n])) ? $mainId : $ownerIds[0];
        if (isset($pagesByEd[$srcId][$n])) {
            $mainPages[$n] = $pagesByEd[$srcId][$n];
        }
    }
    ksort($mainPages);
    if ($mainPages) {
        $out[] = ['edition' => $eds[$mainId] ?? $ctx['main_edition'], 'pages' => array_values($mainPages)];
    }

    // 2) Each sub-edition: only the page numbers it has its OWN (local) file for.
    foreach ($ctx['group_editions'] as $ge) {
        $gid = (int)$ge['id'];
        if ($gid === $mainId) {
            continue;
        }
        $subPages = [];
        foreach (($pagesByEd[$gid] ?? []) as $n => $p) {
            if (page_is_local($owners, $n)) {
                $subPages[$n] = $p;
            }
        }
        ksort($subPages);
        if ($subPages) {
            $out[] = ['edition' => $ge, 'pages' => array_values($subPages)];
        }
    }
    return $out;
}

/**
 * The section name shown next to a page number ("Local News", "Editorial", "Sports").
 * Uses the stored section, else the suffix of the source file name
 * ("12pg6e-Editorial.indd" -> "Editorial"); plain pages default to "News".
 */
function page_section_text(array $p): string
{
    if (!empty($p['section'])) {
        return (string)$p['section'];
    }
    $src = (string)($p['source_file'] ?? '');
    if (preg_match('/^\d{1,2}pg\d+[a-z0-9]+[-_ ]+(.+?)\.indd$/i', $src, $m)) {
        return trim($m[1]);
    }
    return 'News';
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
