<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/helpers.php';

if (current_user()) { redirect('/dashboard.php'); }

$page_title = 'Sue — share your interests';
include __DIR__ . '/includes/header.php';
?>

<section class="fade-up">
  <h1>Sue.</h1>
  <p class="tagline">share your interests</p>
  <p class="muted" style="max-width:620px; margin-top: 14px;">
    Like social media but without the socialing. A CV but less professional.
    A portfolio but funnier. Sue is a corner of the internet for the stuff you actually
    care about — the books, the movies, the weirdly specific opinions, the party tricks
    nobody asked for.
  </p>

  <div class="row-flex" style="margin-top: 22px;">
    <a class="btn" href="<?= url('/signup.php') ?>">make your Sue</a>
    <a class="btn btn-ghost" href="<?= url('/login.php') ?>">already have one</a>
  </div>
</section>

<hr class="rule">

<section class="grid">
  <div class="card"><span class="tape"></span>
    <h3 class="tilt-l">Cool things about me</h3>
    <p class="small muted">A list of things nobody asked for. The whole point of Sue.</p>
  </div>
  <div class="card"><span class="tape"></span>
    <h3 class="tilt-r">Movies & shows</h3>
    <p class="small muted">Import a CSV from Letterboxd or your spreadsheet. We'll sort it like notebook clippings.</p>
  </div>
  <div class="card"><span class="tape"></span>
    <h3 class="tilt-l">Books</h3>
    <p class="small muted">Goodreads CSV in, ratings and your favourite quotes out.</p>
  </div>
  <div class="card"><span class="tape"></span>
    <h3 class="tilt-r">Hot takes, quotes, party tricks</h3>
    <p class="small muted">Skills you'll never put on LinkedIn. Opinions that get you uninvited from dinner.</p>
  </div>
  <div class="card"><span class="tape"></span>
    <h3 class="tilt-l">AMA &amp; guestbook</h3>
    <p class="small muted">Visitors can leave a question or sign your page. No accounts needed.</p>
  </div>
  <div class="card"><span class="tape"></span>
    <h3 class="tilt-r">Add more later</h3>
    <p class="small muted">A "+ add a page" button in your dashboard. Sue grows with you.</p>
  </div>
</section>

<hr class="rule">

<section class="center">
  <p class="tagline">stop performing. start sharing.</p>
  <p><a class="btn" href="<?= url('/signup.php') ?>">make your Sue</a></p>
</section>

<?php include __DIR__ . '/includes/footer.php'; ?>
