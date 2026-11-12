<?php
declare(strict_types=1);
require __DIR__ . '/../inc/bootstrap.php';
require __DIR__ . '/../inc/admin_layout.php';

require_login('admin');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    $action = (string)($_POST['action'] ?? '');
    $id = (int)($_POST['id'] ?? 0);
    $code = strtoupper(trim((string)($_POST['code'] ?? '')));
    $inherit = (int)($_POST['inherit_from_id'] ?? 0) ?: null;

    if ($action === 'add') {
        $slug = strtolower(trim((string)($_POST['slug'] ?? '')));
        $name = trim((string)($_POST['name'] ?? ''));
        $groupName = trim((string)($_POST['group_name'] ?? ''));
        if (!preg_match('/^[a-z0-9-]{2,80}$/', $slug) || $name === '' || !preg_match('/^[A-Z][A-Z0-9]{0,9}$/', $code)) {
            flash('Give the edition a name, a file-name code (a letter, then letters or digits) and a web name (lowercase letters, numbers, dashes).', 'error');
        } else {
            try {
                $gs = (int)q('SELECT COALESCE(MAX(group_sort), 0) FROM editions WHERE group_name = ?', [$groupName])->fetchColumn();
                if ($gs === 0) {
                    $gs = (int)q('SELECT COALESCE(MAX(group_sort), 0) + 100 FROM editions')->fetchColumn();
                }
                q('INSERT INTO editions (slug, code, name, name_ml, group_name, group_sort, inherit_from_id, sort_order) VALUES (?,?,?,?,?,?,?,?)', [
                    $slug, $code, $name, trim((string)($_POST['name_ml'] ?? '')), $groupName, $gs, $inherit, $gs + (int)$_POST['sort_order'],
                ]);
                flash('Edition added.');
            } catch (PDOException $e) {
                flash('An edition with the web name "' . h($slug) . '" already exists.', 'error');
            }
        }
    } elseif ($action === 'save') {
        if (!preg_match('/^[A-Z][A-Z0-9]{0,9}$/', $code)) {
            flash('Codes start with a letter, then letters or digits, as in the file names.', 'error');
        } elseif ($inherit === $id) {
            flash('An edition cannot take pages from itself.', 'error');
        } else {
            q('UPDATE editions SET code = ?, name_ml = ?, inherit_from_id = ? WHERE id = ?', [$code, trim((string)($_POST['name_ml'] ?? '')), $inherit, $id]);
            flash('Saved.');
        }
    } elseif ($action === 'toggle') {
        q('UPDATE editions SET is_active = 1 - is_active WHERE id = ?', [$id]);
    }
    redirect(url('admin/editions.php') . ($id ? '#e' . $id : ''));
}

$editions = q('SELECT * FROM editions ORDER BY group_sort, sort_order, name')->fetchAll();
$codeCount = [];
foreach ($editions as $e) {
    if ($e['code']) {
        $codeCount[$e['code']] = ($codeCount[$e['code']] ?? 0) + 1;
    }
}
$mains = array_filter($editions, function ($e) { return !$e['is_supplement']; });

admin_header('Editions', 'editions');
?>
<h1>Editions</h1>
<p class="a-muted">The <b>code</b> is the part after the page number in InDesign file names: <code>9pg2e-LocalNews.indd</code> is code <b>E</b>.
  <b>Missing pages from</b> fills page numbers an edition has no file for, for example Ernakulam's common pages in its sub-editions.
  A code marked <span class="a-warn">shared</span> belongs to two editions and is settled by the other files in the same folder.</p>

<table class="a-table">
  <thead><tr><th>Edition</th><th>Code, Malayalam name, missing pages from</th><th>Status</th><th></th></tr></thead>
  <tbody>
  <?php $group = null; foreach ($editions as $e): ?>
    <?php if ($e['group_name'] !== $group): $group = $e['group_name']; ?>
      <tr class="a-group"><th colspan="4"><?= h($group ?: 'Other') ?></th></tr>
    <?php endif; ?>
    <tr id="e<?= (int)$e['id'] ?>"<?= $e['is_active'] ? '' : ' class="is-hidden"' ?>>
      <td>
        <?= h($e['name']) ?><?= $e['is_main'] ? ' <span class="a-tag">main</span>' : '' ?><?= $e['is_supplement'] ? ' <span class="a-tag">supplement</span>' : '' ?>
        <?php if ($e['full_name'] && strcasecmp($e['full_name'], $e['name']) !== 0): ?><br><span class="a-muted"><?= h($e['full_name']) ?></span><?php endif; ?>
      </td>
      <td>
        <form method="post" class="a-inline a-edrow"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$e['id'] ?>">
          <input name="code" value="<?= h($e['code']) ?>" size="5" aria-label="Code">
          <?php if ($e['code'] && ($codeCount[$e['code']] ?? 0) > 1): ?><span class="a-warn">shared</span><?php endif; ?>
          <input name="name_ml" value="<?= h($e['name_ml']) ?>" lang="ml" placeholder="മലയാളം" aria-label="Malayalam name">
          <select name="inherit_from_id" aria-label="Missing pages from">
            <option value="">No shared pages</option>
            <?php foreach ($mains as $m): if ($m['id'] === $e['id']) continue; ?>
              <option value="<?= (int)$m['id'] ?>"<?= (int)$e['inherit_from_id'] === (int)$m['id'] ? ' selected' : '' ?>>From <?= h($m['name']) ?></option>
            <?php endforeach; ?>
          </select>
          <button class="a-link" name="action" value="save">Save</button>
        </form>
      </td>
      <td><?= $e['is_active'] ? 'Shown' : 'Hidden' ?></td>
      <td class="a-actions">
        <form method="post" class="a-inline"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$e['id'] ?>">
          <button class="a-link" name="action" value="toggle"><?= $e['is_active'] ? 'Hide' : 'Show' ?></button></form>
      </td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table>

<form method="post" class="a-card a-row">
  <?= csrf_field() ?>
  <h2 style="flex-basis:100%">Add edition</h2>
  <label>Name <input name="name" placeholder="Thodupuzha" required></label>
  <label>Code in file names <input name="code" placeholder="TD" required pattern="[A-Za-z][A-Za-z0-9]{0,9}"></label>
  <label>Malayalam name <input name="name_ml" lang="ml" placeholder="തൊടുപുഴ"></label>
  <label>Web name <input name="slug" placeholder="thodupuzha" pattern="[a-z0-9\-]{2,80}" required></label>
  <label>District group <input name="group_name" placeholder="Idukky" list="groups"></label>
  <datalist id="groups"><?php foreach (array_unique(array_filter(array_column($editions, 'group_name'))) as $g): ?><option value="<?= h($g) ?>"><?php endforeach; ?></datalist>
  <label>Missing pages from
    <select name="inherit_from_id"><option value="">No shared pages</option>
      <?php foreach ($mains as $m): ?><option value="<?= (int)$m['id'] ?>"><?= h($m['name']) ?></option><?php endforeach; ?>
    </select>
  </label>
  <label>Order in group <input name="sort_order" type="number" value="50"></label>
  <button class="a-btn a-btn--primary" name="action" value="add">Add edition</button>
</form>
<?php admin_footer();
