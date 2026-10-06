<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/helpers.php';

$username = strtolower(trim($_GET['u'] ?? ''));
$slug     = trim($_GET['s'] ?? '');
$owner = get_user_by_username($username);
if (!$owner) { http_response_code(404); $page_title='Not found'; include __DIR__.'/includes/header.php'; ?>
<h1>Not here.</h1><p class="tagline">no Sue by that name</p>
<p><a href="<?= url('/') ?>">go home</a></p>
<?php include __DIR__.'/includes/footer.php'; exit; }

$me = current_user();
$is_owner = $me && (int)$me['id'] === (int)$owner['id'];

$sections = fetch_all(
    'SELECT * FROM sections WHERE user_id=? ' . ($is_owner ? '' : 'AND is_public=1 ') . 'ORDER BY sort_order',
    [$owner['id']], 'i'
);

$active = null;
if ($slug) {
    foreach ($sections as $s) if ($s['slug'] === $slug) { $active = $s; break; }
}

// AMA submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'ama_ask') {
    check_csrf();
    $name = trim($_POST['asker_name'] ?? '') ?: 'anonymous';
    $question = trim($_POST['question'] ?? '');
    if ($question !== '') {
        insert('INSERT INTO ama_questions (user_id, asker_name, question, is_public) VALUES (?,?,?,0)',
            [$owner['id'], $name, $question], 'iss');
        flash('Question sent. They\'ll get to it. Or won\'t. That\'s the deal.');
    }
    redirect('/u.php?u=' . urlencode($username) . '&s=ama');
}
// AMA answer (owner only)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'ama_answer' && $is_owner) {
    check_csrf();
    $qid = (int)($_POST['qid'] ?? 0);
    $ans = trim($_POST['answer'] ?? '');
    q('UPDATE ama_questions SET answer=?, is_public=1 WHERE id=? AND user_id=?',
        [$ans, $qid, $owner['id']], 'sii');
    redirect('/u.php?u=' . urlencode($username) . '&s=ama');
}
// Guestbook signing
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'gb_sign') {
    check_csrf();
    $name = trim($_POST['signer_name'] ?? '') ?: 'a stranger';
    $msg = trim($_POST['message'] ?? '');
    if ($msg !== '') {
        insert('INSERT INTO guestbook (user_id, signer_name, message) VALUES (?,?,?)',
            [$owner['id'], $name, $msg], 'iss');
        flash('Signed. Thanks for stopping by.');
    }
    redirect('/u.php?u=' . urlencode($username) . '&s=guestbook');
}

$page_title = '@' . $owner['username'];
include __DIR__ . '/includes/header.php';
?>

<header style="margin-bottom: 18px;">
  <h1><?= h($owner['display_name'] ?: $owner['username']) ?>.</h1>
  <p class="tagline">@<?= h($owner['username']) ?></p>
  <?php if ($owner['bio']): ?>
    <p style="max-width: 640px; margin-top: 10px;"><?= nl2br(h($owner['bio'])) ?></p>
  <?php endif; ?>
</header>

<nav class="section-tabs">
  <a class="<?= !$active ? 'active' : '' ?>" style="--tilt:-1deg" href="<?= profile_url($owner['username']) ?>">about</a>
  <?php $i = 0; foreach ($sections as $s):
      $tilt = ((($i++) % 2) ? 1 : -1) * (1 + ($i % 3));
  ?>
    <a style="--tilt: <?= $tilt ?>deg" class="<?= $active && $active['id']==$s['id'] ? 'active' : '' ?>"
       href="<?= profile_url($owner['username'], $s['slug']) ?>"><?= h($s['title']) ?></a>
  <?php endforeach; ?>
</nav>

<?php if (!$active): ?>
  <div class="grid">
    <?php foreach ($sections as $s):
      $count_row = fetch_one('SELECT COUNT(*) AS n FROM items WHERE section_id=?', [$s['id']], 'i');
      $count = (int)($count_row['n'] ?? 0);
    ?>
      <a class="card" href="<?= profile_url($owner['username'], $s['slug']) ?>" style="border:1px solid #d8c89a;">
        <span class="tape"></span>
        <h3 class="tilt-l"><?= h($s['title']) ?></h3>
        <p class="small muted"><?= h(SECTION_TYPES[$s['type']]['label'] ?? 'Custom') ?> · <?= $count ?></p>
      </a>
    <?php endforeach; ?>
  </div>
  <?php if (!$sections): ?>
    <p class="muted">This Sue hasn't filled anything in yet. Check back later, or don't.</p>
  <?php endif; ?>

<?php else:
  $sid = (int)$active['id'];
  $type = $active['type'];
  ?>

  <div class="between">
    <h2><?= h($active['title']) ?></h2>
    <?php if ($is_owner): ?>
      <a class="btn btn-ghost" href="<?= url('/edit-section.php?id=' . $sid) ?>">edit this page</a>
    <?php endif; ?>
  </div>

  <?php
  // Render by type
  if ($type === 'ama') {
      $public_qs = fetch_all('SELECT * FROM ama_questions WHERE user_id=? AND is_public=1 ORDER BY created_at DESC', [$owner['id']], 'i');
      $unanswered = $is_owner ? fetch_all('SELECT * FROM ama_questions WHERE user_id=? AND (answer IS NULL OR answer="") ORDER BY created_at DESC', [$owner['id']], 'i') : [];
  ?>
    <p class="muted small">Drop a weird question. They'll get to it. Or won't.</p>
    <form method="post" class="notebook" style="max-width:560px;">
      <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
      <input type="hidden" name="action" value="ama_ask">
      <div class="row"><label>your name (optional)</label><input type="text" name="asker_name" maxlength="80"></div>
      <div class="row"><label>your question</label><textarea name="question" required></textarea></div>
      <button type="submit">send it</button>
    </form>

    <?php if ($unanswered): ?>
      <hr class="rule">
      <h3>Inbox (only you see this)</h3>
      <?php foreach ($unanswered as $aq): ?>
        <div class="card" style="margin-bottom: 12px;">
          <p class="small muted">from <?= h($aq['asker_name']) ?> · <?= nice_date($aq['created_at']) ?></p>
          <p><strong><?= h($aq['question']) ?></strong></p>
          <form method="post">
            <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
            <input type="hidden" name="action" value="ama_answer">
            <input type="hidden" name="qid" value="<?= $aq['id'] ?>">
            <textarea name="answer" placeholder="answer (saving makes it public)"></textarea>
            <button type="submit" style="margin-top:6px;">answer publicly</button>
          </form>
        </div>
      <?php endforeach; ?>
    <?php endif; ?>

    <?php if ($public_qs): ?>
      <hr class="rule">
      <h3>Answered</h3>
      <?php foreach ($public_qs as $aq): ?>
        <div class="card" style="margin-bottom: 12px;"><span class="tape"></span>
          <p class="small muted">from <?= h($aq['asker_name']) ?> · <?= nice_date($aq['created_at']) ?></p>
          <p><strong>Q.</strong> <?= h($aq['question']) ?></p>
          <p><strong>A.</strong> <?= nl2br(h($aq['answer'])) ?></p>
        </div>
      <?php endforeach; ?>
    <?php endif; ?>

  <?php } elseif ($type === 'guestbook') {
      $signs = fetch_all('SELECT * FROM guestbook WHERE user_id=? ORDER BY created_at DESC', [$owner['id']], 'i');
  ?>
    <p class="muted small">Sign the page. No accounts, no rules.</p>
    <form method="post" class="notebook" style="max-width:560px;">
      <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
      <input type="hidden" name="action" value="gb_sign">
      <div class="row"><label>your name</label><input type="text" name="signer_name" maxlength="80"></div>
      <div class="row"><label>your note</label><textarea name="message" required></textarea></div>
      <button type="submit">sign</button>
    </form>
    <hr class="rule">
    <div class="grid">
      <?php foreach ($signs as $g): ?>
        <div class="card"><span class="tape"></span>
          <p class="small muted">— <?= h($g['signer_name']) ?> · <?= nice_date($g['created_at']) ?></p>
          <p><?= nl2br(h($g['message'])) ?></p>
        </div>
      <?php endforeach; ?>
      <?php if (!$signs): ?><p class="muted">No signatures yet. Be the first.</p><?php endif; ?>
    </div>

  <?php } else {
      $items = fetch_all('SELECT * FROM items WHERE section_id=? ORDER BY sort_order, id DESC', [$sid], 'i');
      if ($type === 'cool_things') { ?>
        <ul class="scribble">
          <?php foreach ($items as $it): ?>
            <li><?= h($it['title']) ?></li>
          <?php endforeach; ?>
        </ul>
        <?php if (!$items): ?><p class="muted">No things yet.</p><?php endif; ?>

      <?php } elseif ($type === 'movies' || $type === 'shows' || $type === 'books') { ?>
        <div class="poster-grid">
          <?php $i=0; foreach ($items as $it): $tilt = (($i++)%2 ? 1 : -1) * (1 + ($i%2)); ?>
            <div class="poster" style="--tilt: <?= $tilt ?>deg;">
              <?php $img = safe_image_url($it['image_url']); if ($img): ?>
                <img src="<?= h($img) ?>" alt="<?= h($it['title']) ?>" loading="lazy">
              <?php else: ?>
                <div class="ph"><?= h(mb_substr($it['title'] ?? '?', 0, 1)) ?></div>
              <?php endif; ?>
              <h4><?= h($it['title']) ?></h4>
              <div class="meta">
                <?= $it['year'] ? h($it['year']) : '' ?>
                <?= $it['rating']!==null ? ' · ★'.h($it['rating']) : '' ?>
                <?= $it['status'] ? ' · '.h($it['status']) : '' ?>
              </div>
              <?php if ($it['subtitle']): ?><div class="meta"><?= h($it['subtitle']) ?></div><?php endif; ?>
              <?php if ($it['body']): ?><p class="small" style="margin-top:6px;">"<?= h($it['body']) ?>"</p><?php endif; ?>
            </div>
          <?php endforeach; ?>
        </div>
        <?php if (!$items): ?><p class="muted">Empty shelf. <?= $is_owner ? 'Add some, or import a CSV.' : '' ?></p><?php endif; ?>

      <?php } elseif ($type === 'quotes') { ?>
        <div class="grid">
          <?php $i=0; foreach ($items as $it): $tilt = (($i++)%2 ? 1.4 : -1.4); ?>
            <div class="card" style="background:#fff5a8; transform: rotate(<?= $tilt ?>deg);">
              <p>"<?= nl2br(h($it['body'])) ?>"</p>
              <?php if ($it['subtitle']): ?><p class="small muted">— <?= h($it['subtitle']) ?></p><?php endif; ?>
            </div>
          <?php endforeach; ?>
        </div>
        <?php if (!$items): ?><p class="muted">No quotes yet.</p><?php endif; ?>

      <?php } elseif ($type === 'takes') { ?>
        <ul class="scribble">
          <?php foreach ($items as $it): ?>
            <li><strong><?= h($it['title']) ?></strong>
              <?php if ($it['body']): ?><div class="small muted"><?= nl2br(h($it['body'])) ?></div><?php endif; ?>
            </li>
          <?php endforeach; ?>
        </ul>
        <?php if (!$items): ?><p class="muted">No opinions on the record. Yet.</p><?php endif; ?>

      <?php } elseif ($type === 'skills') { ?>
        <div class="grid">
          <?php foreach ($items as $it): ?>
            <div class="card"><span class="tape"></span>
              <h3 class="tilt-l"><?= h($it['title']) ?></h3>
              <?php if ($it['status']): ?><p class="small muted"><?= h($it['status']) ?></p><?php endif; ?>
              <?php if ($it['body']): ?><p><?= nl2br(h($it['body'])) ?></p><?php endif; ?>
            </div>
          <?php endforeach; ?>
        </div>
        <?php if (!$items): ?><p class="muted">No tricks listed.</p><?php endif; ?>

      <?php } elseif ($type === 'now') { ?>
        <ul class="scribble">
          <?php foreach ($items as $it): ?>
            <li><strong><?= h($it['title'] ?: 'currently') ?>:</strong> <?= nl2br(h($it['body'])) ?></li>
          <?php endforeach; ?>
        </ul>
        <p class="small muted">Last updated: <?= $items ? nice_date($items[0]['created_at']) : 'never' ?></p>

      <?php } elseif ($type === 'places') {
        $visited = array_filter($items, fn($x)=>$x['status']==='visited');
        $want    = array_filter($items, fn($x)=>$x['status']==='want to');
        $lived   = array_filter($items, fn($x)=>$x['status']==='lived in');
      ?>
        <?php foreach (['lived in'=>$lived,'visited'=>$visited,'want to'=>$want] as $label=>$grp): if(!$grp) continue; ?>
          <h3><?= h($label) ?></h3>
          <ul class="scribble">
            <?php foreach ($grp as $it): ?>
              <li><?= h($it['title']) ?><?php if ($it['body']): ?> — <span class="small muted"><?= h($it['body']) ?></span><?php endif; ?></li>
            <?php endforeach; ?>
          </ul>
        <?php endforeach; ?>
        <?php if (!$items): ?><p class="muted">No places logged.</p><?php endif; ?>

      <?php } elseif ($type === 'music') { ?>
        <div class="grid">
          <?php foreach ($items as $it): ?>
            <div class="card"><span class="tape"></span>
              <h3 class="tilt-r"><?= h($it['title']) ?></h3>
              <?php if ($it['subtitle']): ?><p class="small muted"><?= h($it['subtitle']) ?></p><?php endif; ?>
              <?php if ($it['year']): ?><p><strong><?= number_format((int)$it['year']) ?></strong> minutes</p><?php endif; ?>
              <?php if ($it['body']): ?><p><?= nl2br(h($it['body'])) ?></p><?php endif; ?>
            </div>
          <?php endforeach; ?>
        </div>
        <?php if (!$items): ?><p class="muted">No music data yet.</p><?php endif; ?>

      <?php } elseif ($type === 'stats') { ?>
        <div class="grid">
          <?php foreach ($items as $it): ?>
            <div class="card">
              <h3 class="tilt-l"><?= h($it['title']) ?></h3>
              <p style="font-size:22px;"><?= nl2br(h($it['body'])) ?></p>
            </div>
          <?php endforeach; ?>
        </div>

      <?php } else { /* custom_list */ ?>
        <ul class="scribble">
          <?php foreach ($items as $it): ?>
            <li><strong><?= h($it['title']) ?></strong><?php if ($it['body']): ?> — <?= nl2br(h($it['body'])) ?><?php endif; ?></li>
          <?php endforeach; ?>
        </ul>
      <?php }
  }
endif; ?>

<?php include __DIR__ . '/includes/footer.php'; ?>
