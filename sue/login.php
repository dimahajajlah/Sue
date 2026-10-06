<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/helpers.php';

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    $id_or_email = trim($_POST['id'] ?? '');
    $password = $_POST['password'] ?? '';
    $row = fetch_one('SELECT id, password_hash FROM users WHERE username=? OR email=?', [$id_or_email, $id_or_email], 'ss');
    if ($row && password_verify($password, $row['password_hash'])) {
        login_user((int)$row['id']);
        redirect('/dashboard.php');
    }
    $errors[] = 'Wrong username/email or password.';
}

$page_title = 'Log in';
include __DIR__ . '/includes/header.php';
?>
<h1>Log in.</h1>
<p class="tagline">welcome back</p>

<?php foreach ($errors as $e): ?>
  <div class="sticky-note" style="background:#ffd5d5;"><?= h($e) ?></div>
<?php endforeach; ?>

<form method="post" class="notebook" style="max-width:480px;">
  <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
  <div class="row">
    <label>username or email</label>
    <input type="text" name="id" required>
  </div>
  <div class="row">
    <label>password</label>
    <input type="password" name="password" required>
  </div>
  <button type="submit">let me in</button>
  <a href="<?= url('/signup.php') ?>" class="muted" style="margin-left:14px;">no account yet?</a>
</form>

<?php include __DIR__ . '/includes/footer.php'; ?>
