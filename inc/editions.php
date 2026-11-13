<?php
declare(strict_types=1);

// Load guard: on Windows, require_once does not always dedupe the same file when
// it is reached through different path spellings (e.g. api/../inc/editions.php vs
// inc/editions.php), which would redeclare these functions. This makes a second
// include a no-op no matter how it is spelled.
if (defined('EPAPER_EDITIONS_LOADED')) {
    return;
}
define('EPAPER_EDITIONS_LOADED', true);

/**
 * Finds the edition for a file-name code (9pg2e-... -> "E").
 * Two codes are shared by two editions in the edition list (WKN, MK). Those are settled by the
 * other codes in the same batch: the candidate whose district group most of them belong to wins.
 *
 * @param string   $code       code from the file name, any case
 * @param string[] $batchCodes every code in the same export (helps with shared codes)
 * @return array edition row
 * @throws RuntimeException when the code is unknown or cannot be settled
 */
function resolve_edition_code(string $code, array $batchCodes = []): array
{
    $code = strtoupper(trim($code));
    $rows = q('SELECT * FROM editions WHERE code = ? ORDER BY sort_order', [$code])->fetchAll();
    if (!$rows) {
        throw new RuntimeException("Edition code \"$code\" is not in the edition list. Add it on the dashboard's Editions page.");
    }
    if (count($rows) === 1) {
        return $rows[0];
    }

    // Shared code: count the groups of the other, unambiguous codes in this batch
    $votes = [];
    foreach (array_unique(array_map('strtoupper', $batchCodes)) as $other) {
        if ($other === $code || $other === '') {
            continue;
        }
        $groups = q('SELECT DISTINCT group_name FROM editions WHERE code = ?', [$other])->fetchAll(PDO::FETCH_COLUMN);
        if (count($groups) === 1) {
            $votes[$groups[0]] = ($votes[$groups[0]] ?? 0) + 1;
        }
    }
    $best = null;
    $bestVotes = 0;
    $tie = false;
    foreach ($rows as $r) {
        $v = $votes[$r['group_name']] ?? 0;
        if ($v > $bestVotes) {
            $best = $r;
            $bestVotes = $v;
            $tie = false;
        } elseif ($v === $bestVotes && $v > 0) {
            $tie = true;
        }
    }
    if ($best && !$tie) {
        return $best;
    }
    $names = implode(' or ', array_map(function ($r) { return $r['name'] . ' (' . $r['group_name'] . ')'; }, $rows));
    throw new RuntimeException("Edition code \"$code\" belongs to $names. Export it together with other pages of the same district, "
        . 'or give one of them a different code on the Editions page.');
}

/** The edition followed by the editions it takes missing pages from (main edition, common pages). */
function edition_chain(array $edition): array
{
    $chain = [$edition];
    $seen = [(int)$edition['id'] => true];
    $next = $edition['inherit_from_id'] ?? null;
    while ($next && !isset($seen[(int)$next]) && count($chain) < 4) {
        $row = q('SELECT * FROM editions WHERE id = ?', [(int)$next])->fetch();
        if (!$row) {
            break;
        }
        $chain[] = $row;
        $seen[(int)$row['id']] = true;
        $next = $row['inherit_from_id'];
    }
    return $chain;
}

function edition_chain_ids(array $edition): array
{
    return array_map(function ($e) { return (int)$e['id']; }, edition_chain($edition));
}

// ---------------------------------------------------------------- edition groups
// A "group" (group_name, e.g. "Kozhikode") has one main edition (is_main = 1), any
// number of local sub-editions (inherit their main's missing pages) and supplements.
// The reader picks a main edition; a subscription to that main edition covers the
// whole group.

/** The main edition of each district/group — what the reader's edition picker shows. */
function main_editions(): array
{
    return q('SELECT * FROM editions WHERE is_active = 1 AND is_main = 1
              ORDER BY group_sort, sort_order, name')->fetchAll();
}

/** Every active edition in a group, ordered main → sub-editions → supplements. */
function group_editions(?string $groupName): array
{
    if ($groupName === null || $groupName === '') {
        return [];
    }
    return q('SELECT * FROM editions WHERE is_active = 1 AND group_name = ?
              ORDER BY is_main DESC, is_supplement ASC, sort_order, name', [$groupName])->fetchAll();
}

/** The main edition of the group this edition belongs to (itself if it is the main). */
function main_edition_of(array $edition): array
{
    if (!empty($edition['is_main'])) {
        return $edition;
    }
    $group = $edition['group_name'] ?? null;
    if ($group) {
        $main = q('SELECT * FROM editions WHERE group_name = ? AND is_main = 1 LIMIT 1', [$group])->fetch();
        if ($main) {
            return $main;
        }
    }
    // fall back to the inherit chain if the group has no explicit main
    if (!empty($edition['inherit_from_id'])) {
        $parent = q('SELECT * FROM editions WHERE id = ?', [(int)$edition['inherit_from_id']])->fetch();
        if ($parent && (int)$parent['id'] !== (int)$edition['id']) {
            return main_edition_of($parent);
        }
    }
    return $edition;
}

function main_edition_id_of(array $edition): int
{
    return (int)main_edition_of($edition)['id'];
}

/** Display label for an edition: Malayalam name with the English name in brackets. */
function edition_label(array $edition): string
{
    return !empty($edition['name_ml'])
        ? $edition['name_ml'] . ' (' . $edition['name'] . ')'
        : (string)$edition['name'];
}
