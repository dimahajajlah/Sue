<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/helpers.php';
$u = require_login();

$sid = (int)($_GET['id'] ?? $_POST['section_id'] ?? 0);
$section = fetch_one('SELECT * FROM sections WHERE id=? AND user_id=?', [$sid, $u['id']], 'ii');
if (!$section) { http_response_code(404); die('Section not found.'); }

$fields = SECTION_TYPES[$section['type']]['fields'] ?? ['title','body'];

// Add item
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'add_item') {
    check_csrf();
    $title    = trim($_POST['title'] ?? '');
    $subtitle = trim($_POST['subtitle'] ?? '');
    $body     = trim($_POST['body'] ?? '');
    $rating   = $_POST['rating'] !== '' ? (float)$_POST['rating'] : null;
    $year     = $_POST['year']   !== '' ? (int)$_POST['year']     : null;
    $status   = trim($_POST['status'] ?? '') ?: null;
    $image    = safe_image_url($_POST['image_url'] ?? '');
    $next = (int)(fetch_one('SELECT COALESCE(MAX(sort_order),0)+1 AS n FROM items WHERE section_id=?', [$sid], 'i')['n'] ?? 1);
    insert(
        'INSERT INTO items (section_id, title, subtitle, body, rating, year, status, image_url, sort_order)
         VALUES (?,?,?,?,?,?,?,?,?)',
        [$sid, $title, $subtitle, $body, $rating, $year, $status, $image, $next],
        'isssdisss'
    );
    flash('Added.');
    redirect('/edit-section.php?id=' . $sid);
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete_item') {
    check_csrf();
    $iid = (int)($_POST['item_id'] ?? 0);
    q('DELETE FROM items WHERE id=? AND section_id=?', [$iid, $sid], 'ii');
    redirect('/edit-section.php?id=' . $sid);
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'rename') {
    check_csrf();
    $title = trim($_POST['title'] ?? '') ?: $section['title'];
    q('UPDATE sections SET title=? WHERE id=? AND user_id=?', [$title, $sid, $u['id']], 'sii');
    flash('Renamed.');
    redirect('/edit-section.php?id=' . $sid);
}

$items = fetch_all('SELECT * FROM items WHERE section_id=? ORDER BY sort_order, id', [$sid], 'i');

$page_title = 'Edit · ' . $section['title'];
include __DIR__ . '/includes/header.php';
?>

<p class="small"><a href="<?= url('/dashboard.php') ?>">← back to my notebook</a></p>

<div class="between">
  <div>
    <h1><?= h($section['title']) ?></h1>
    <p class="tagline"><?= h(SECTION_TYPES[$section['type']]['label'] ?? 'Custom') ?> · <?= count($items) ?> items</p>
  </div>
  <div class="row-flex">
    <a class="btn btn-ghost" href="<?= profile_url($u['username'], $section['slug']) ?>">view public →</a>
    <?php if (in_array($section['type'], ['movies','shows','books','music','custom_list'])): ?>
      <a class="btn" href="<?= url('/import.php?id=' . $sid) ?>">import CSV / XLSX</a>
    <?php endif; ?>
  </div>
</div>

<hr class="rule">

<form method="post" class="notebook" style="max-width:480px;">
  <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
  <input type="hidden" name="action" value="rename">
  <div class="row">
    <label>page title</label>
    <input type="text" name="title" value="<?= h($section['title']) ?>" maxlength="120">
  </div>
  <button type="submit" class="btn-ghost btn">rename</button>
</form>

<hr class="rule">

<h2>+ add an item</h2>
<form method="post" class="notebook" style="max-width:680px;">
  <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
  <input type="hidden" name="action" value="add_item">

  <?php if (in_array($section['type'], ['movies','shows','books'])): ?>
    <div class="row"><label>title</label><input type="text" name="title" required></div>
    <div class="row"><label><?= $section['type']==='books' ? 'author' : 'year' ?></label>
      <?php if ($section['type']==='books'): ?>
        <input type="text" name="subtitle" placeholder="author">
      <?php else: ?>
        <input type="number" name="year" placeholder="2024">
      <?php endif; ?>
    </div>
    <?php if ($section['type']==='books'): ?>
      <div class="row"><label>year</label><input type="number" name="year" placeholder="2024"></div>
    <?php endif; ?>
    <div class="row"><label>rating (0-10)</label><input type="number" step="0.1" min="0" max="10" name="rating"></div>
    <div class="row"><label>status</label>
      <select name="status">
        <option value="">—</option>
        <?php foreach (['watching','watched','reading','read','want to','dropped','on hold'] as $st): ?>
          <option><?= $st ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="row"><label>poster / cover URL (optional)</label><input type="url" name="image_url" placeholder="https://..."></div>
    <div class="row"><label><?= $section['type']==='books' ? 'favourite quote / one-line review' : 'one-line review' ?></label><textarea name="body" maxlength="2000"></textarea></div>

  <?php elseif ($section['type'] === 'cool_things'): ?>
    <div class="row"><label>thing about you</label><input type="text" name="title" required maxlength="255" placeholder="I can solve a Rubik's cube in under two minutes."></div>

  <?php elseif ($section['type'] === 'quotes'): ?>
    <div class="row"><label>the quote</label><textarea name="body" required></textarea></div>
    <div class="row"><label>attributed to (optional)</label><input type="text" name="subtitle" placeholder="overheard at a cafe"></div>

  <?php elseif ($section['type'] === 'takes'): ?>
    <div class="row"><label>the take</label><input type="text" name="title" required maxlength="255"></div>
    <div class="row"><label>elaborate (optional)</label><textarea name="body"></textarea></div>

  <?php elseif ($section['type'] === 'skills'): ?>
    <div class="row"><label>skill / party trick</label><input type="text" name="title" required maxlength="255"></div>
    <div class="row"><label>level</label>
      <select name="status">
        <option value="">—</option>
        <option>taught myself</option>
        <option>weirdly good</option>
        <option>certified menace</option>
      </select>
    </div>
    <div class="row"><label>story (optional)</label><textarea name="body"></textarea></div>

  <?php elseif ($section['type'] === 'now'): ?>
    <div class="row"><label>category (e.g. reading, watching, into)</label><input type="text" name="title" maxlength="80"></div>
    <div class="row"><label>what</label><textarea name="body" required></textarea></div>

  <?php elseif ($section['type'] === 'places'): ?>
    <div class="row"><label>place</label><input type="text" name="title" required maxlength="255"></div>
    <div class="row"><label>status</label>
      <select name="status">
        <option value="visited">visited</option>
        <option value="want to">want to</option>
        <option value="lived in">lived in</option>
      </select>
    </div>
    <div class="row"><label>note (optional)</label><textarea name="body"></textarea></div>

  <?php elseif ($section['type'] === 'music'): ?>
    <div class="row"><label>title (artist / playlist / stat)</label><input type="text" name="title" required></div>
    <div class="row"><label>artist or context</label><input type="text" name="subtitle"></div>
    <div class="row"><label>minutes</label><input type="number" name="year" placeholder="83654"></div>
    <div class="row"><label>note</label><textarea name="body"></textarea></div>

  <?php elseif ($section['type'] === 'stats'): ?>
    <div class="row"><label>stat label</label><input type="text" name="title" required></div>
    <div class="row"><label>value / story</label><textarea name="body" required></textarea></div>

  <?php else: /* custom_list, ama, guestbook fall here too */ ?>
    <div class="row"><label>title</label><input type="text" name="title" maxlength="255"></div>
    <div class="row"><label>body</label><textarea name="body"></textarea></div>
  <?php endif; ?>

  <button type="submit">add</button>
</form>

<hr class="rule">

<h2>Items (<?= count($items) ?>)</h2>
<?php if (!$items): ?>
  <p class="muted">Empty. Like that one drawer everyone has.</p>
<?php else: ?>
<table class="notebook">
  <tr>
    <th>title</th><th>detail</th><th>extra</th><th></th>
  </tr>
  <?php foreach ($items as $it): ?>
    <tr>
      <td><?= h($it['title']) ?></td>
      <td><?= h($it['subtitle'] ?? '') ?> <?= $it['year'] ? '· '.h($it['year']) : '' ?> <?= $it['status'] ? '· '.h($it['status']) : '' ?></td>
      <td><?= $it['rating']!==null ? '★ '.h($it['rating']) : '' ?> <?php if ($it['body']): ?><span class="small muted">— <?= h(mb_strimwidth($it['body'],0,80,'…')) ?></span><?php endif; ?></td>
      <td>
        <form method="post" onsubmit="return confirm('Delete this item?')" style="display:inline;">
          <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
          <input type="hidden" name="action" value="delete_item">
          <input type="hidden" name="item_id" value="<?= $it['id'] ?>">
          <button type="submit" class="btn-ghost btn" style="font-size:14px; padding:2px 8px; color:var(--accent); border-color:var(--accent);">delete</button>
        </form>
      </td>
    </tr>
  <?php endforeach; ?>
</table>
<?php endif; ?>

<?php include __DIR__ . '/includes/footer.php'; ?>
