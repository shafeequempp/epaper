<?php
declare(strict_types=1);

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
