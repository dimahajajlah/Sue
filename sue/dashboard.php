<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/helpers.php';
$u = require_login();

// Add new section
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'add_section') {
    check_csrf();
    $type = $_POST['type'] ?? 'custom_list';
    if (!isset(SECTION_TYPES[$type])) $type = 'custom_list';
    $title = trim($_POST['title'] ?? '') ?: SECTION_TYPES[$type]['label'];
    $slug = unique_slug($u['id'], $title);
    $next = (int)(fetch_one('SELECT COALESCE(MAX(sort_order),0)+1 AS n FROM sections WHERE user_id=?', [$u['id']], 'i')['n'] ?? 1);
    insert('INSERT INTO sections (user_id, type, slug, title, is_public, sort_order) VALUES (?,?,?,?,1,?)',
        [$u['id'], $type, $slug, $title, $next], 'isssi');
    flash('Page added.');
    redirect('/dashboard.php');
}

// Toggle public/delete
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'toggle_public') {
    check_csrf();
    $sid = (int)($_POST['section_id'] ?? 0);
    q('UPDATE sections SET is_public = 1 - is_public WHERE id=? AND user_id=?', [$sid, $u['id']], 'ii');
    redirect('/dashboard.php');
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete_section') {
    check_csrf();
    $sid = (int)($_POST['section_id'] ?? 0);
    q('DELETE FROM sections WHERE id=? AND user_id=?', [$sid, $u['id']], 'ii');
    flash('Page deleted.');
    redirect('/dashboard.php');
}

// Update profile
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'profile') {
    check_csrf();
    q('UPDATE users SET display_name=?, bio=? WHERE id=?',
        [trim($_POST['display_name'] ?? ''), trim($_POST['bio'] ?? ''), $u['id']], 'ssi');
    flash('Profile saved.');
    redirect('/dashboard.php');
}

$sections = fetch_all('SELECT * FROM sections WHERE user_id=? ORDER BY sort_order', [$u['id']], 'i');

// Suggestions
$have = array_column($sections, 'type');
$catalog = [
    'cool_things'=>'A list of weirdly specific things about you',
    'movies'     =>'Track every movie you watched',
    'shows'      =>'TV shows, with seasons and a "currently obsessed with" shelf',
    'books'      =>'Books with covers, ratings, favourite quotes',
    'music'      =>'Top artists, minutes, weirdly specific playlists',
    'takes'      =>'Hot takes — opinions that get you uninvited',
    'skills'     =>'Skills & party tricks (Rubik\'s cube, Morse code, etc.)',
    'quotes'     =>'A wall of quotes — overheard, remembered, stolen',
    'now'        =>'A tiny "now" page: what you\'re into this week',
    'places'     =>'Places visited or want-to-visit',
    'stats'      =>'Fake-serious stats about you',
    'ama'        =>'Let visitors ask you a weird question',
    'guestbook'  =>'A guestbook visitors can sign',
];
$suggestions = [];
foreach ($catalog as $t => $why) {
    if (!in_array($t, $have, true)) $suggestions[$t] = $why;
}

$page_title = 'My notebook';
include __DIR__ . '/includes/header.php';
?>

<div class="between">
  <div>
    <h1>My notebook.</h1>
    <p class="tagline">@<?= h($u['username']) ?> &middot; <a href="<?= profile_url($u['username']) ?>">view public page →</a></p>
  </div>
</div>

<hr class="rule">

<h2>Profile</h2>
<form method="post" class="notebook" style="max-width:600px;">
  <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
  <input type="hidden" name="action" value="profile">
  <div class="row">
    <label>display name</label>
    <input type="text" name="display_name" value="<?= h($u['display_name']) ?>" maxlength="80">
  </div>
  <div class="row">
    <label>bio (a sentence or two — dry humour encouraged)</label>
    <textarea name="bio" maxlength="500"><?= h($u['bio']) ?></textarea>
  </div>
  <button type="submit">save</button>
</form>

<hr class="rule">

<h2>Pages</h2>
<p class="muted small">Each page has its own URL on your public profile. Toggle private to hide while you draft.</p>

<div class="grid">
<?php foreach ($sections as $s):
    $type_label = SECTION_TYPES[$s['type']]['label'] ?? 'Custom';
    $count_row = fetch_one('SELECT COUNT(*) AS n FROM items WHERE section_id=?', [$s['id']], 'i');
    $count = (int)($count_row['n'] ?? 0);
?>
  <div class="card"><span class="tape"></span>
    <h3 class="tilt-l"><?= h($s['title']) ?></h3>
    <p class="small muted"><?= h($type_label) ?> &middot; <?= $count ?> item<?= $count===1?'':'s' ?></p>
    <div class="row-flex" style="margin-top:8px;">
      <a class="btn btn-ghost" href="<?= url('/edit-section.php?id=' . $s['id']) ?>">edit</a>
      <a href="<?= profile_url($u['username'], $s['slug']) ?>">view →</a>
    </div>
    <form method="post" style="display:inline; margin-top:8px;">
      <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
      <input type="hidden" name="action" value="toggle_public">
      <input type="hidden" name="section_id" value="<?= $s['id'] ?>">
      <button type="submit" class="btn-ghost btn" style="font-size:16px; padding:4px 10px;">
        <?= $s['is_public'] ? 'public' : 'private' ?> — flip
      </button>
    </form>
    <form method="post" style="display:inline;" onsubmit="return confirm('Delete this page and everything in it?')">
      <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
      <input type="hidden" name="action" value="delete_section">
      <input type="hidden" name="section_id" value="<?= $s['id'] ?>">
      <button type="submit" class="btn-ghost btn" style="font-size:16px; padding:4px 10px; border-color:var(--accent); color:var(--accent);">delete</button>
    </form>
  </div>
<?php endforeach; ?>
</div>

<hr class="rule">

<h2>+ add a page</h2>
<form method="post" class="notebook" style="max-width:600px;">
  <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
  <input type="hidden" name="action" value="add_section">
  <div class="row">
    <label>type</label>
    <select name="type">
      <?php foreach (SECTION_TYPES as $key => $info): ?>
        <option value="<?= h($key) ?>"><?= h($info['label']) ?></option>
      <?php endforeach; ?>
    </select>
  </div>
  <div class="row">
    <label>title (optional — defaults to type name)</label>
    <input type="text" name="title" maxlength="120" placeholder="e.g. Comfort rewatches">
  </div>
  <button type="submit">add page</button>
</form>

<?php if ($suggestions): ?>
<hr class="rule">
<h2>Suggested pages</h2>
<p class="small muted">Things alike that fit Sue's vibe — based on what you don't have yet.</p>
<div class="grid">
  <?php foreach ($suggestions as $t => $why): ?>
    <div class="card">
      <h3 class="tilt-r"><?= h(SECTION_TYPES[$t]['label']) ?></h3>
      <p class="small muted"><?= h($why) ?></p>
      <form method="post" style="margin-top:6px;">
        <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
        <input type="hidden" name="action" value="add_section">
        <input type="hidden" name="type" value="<?= h($t) ?>">
        <button type="submit" class="btn-ghost btn" style="font-size:18px;">add this</button>
      </form>
    </div>
  <?php endforeach; ?>
</div>
<?php endif; ?>

<?php include __DIR__ . '/includes/footer.php'; ?>
