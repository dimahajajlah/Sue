<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/helpers.php';

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    $username = strtolower(trim($_POST['username'] ?? ''));
    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $display  = trim($_POST['display_name'] ?? '') ?: $username;

    if (!valid_username($username)) $errors[] = 'Usernames are 3-20 chars, lowercase letters/numbers/underscore.';
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'That email looks suspicious.';
    if (strlen($password) < 6) $errors[] = 'Password should be at least 6 characters.';
    if (!$errors) {
        $exists = fetch_one('SELECT id FROM users WHERE username=? OR email=?', [$username, $email], 'ss');
        if ($exists) $errors[] = 'Username or email already taken.';
    }
    if (!$errors) {
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $id = insert(
            'INSERT INTO users (username, email, password_hash, display_name) VALUES (?,?,?,?)',
            [$username, $email, $hash, $display], 'ssss'
        );
        default_sections_for_user($id);
        login_user($id);
        flash("Welcome to Sue, @$username. Your notebook is ready.");
        redirect('/dashboard.php');
    }
}

$page_title = 'Make your Sue';
include __DIR__ . '/includes/header.php';
?>
<h1>Make your Sue.</h1>
<p class="tagline">a notebook of your own</p>

<?php foreach ($errors as $e): ?>
  <div class="sticky-note" style="background:#ffd5d5;"><?= h($e) ?></div>
<?php endforeach; ?>

<form method="post" class="notebook" style="max-width:520px;">
  <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
  <div class="row">
    <label>username (your URL: /u/yourname)</label>
    <input type="text" name="username" maxlength="20" required value="<?= h($_POST['username'] ?? '') ?>">
  </div>
  <div class="row">
    <label>display name</label>
    <input type="text" name="display_name" maxlength="80" value="<?= h($_POST['display_name'] ?? '') ?>">
  </div>
  <div class="row">
    <label>email</label>
    <input type="email" name="email" required value="<?= h($_POST['email'] ?? '') ?>">
  </div>
  <div class="row">
    <label>password</label>
    <input type="password" name="password" required minlength="6">
  </div>
  <button type="submit">make it</button>
  <a href="<?= url('/login.php') ?>" class="muted" style="margin-left:14px;">already have one?</a>
</form>

<?php include __DIR__ . '/includes/footer.php'; ?>
