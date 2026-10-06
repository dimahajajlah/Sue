<?php
require_once __DIR__ . '/db.php';

function current_user(): ?array {
    if (empty($_SESSION['user_id'])) return null;
    static $user = null;
    if ($user === null) {
        $user = fetch_one('SELECT id, username, email, display_name, bio, created_at FROM users WHERE id = ?', [$_SESSION['user_id']], 'i');
    }
    return $user;
}

function require_login(): array {
    $u = current_user();
    if (!$u) {
        header('Location: ' . BASE_URL . '/login.php');
        exit;
    }
    return $u;
}

function login_user(int $user_id): void {
    session_regenerate_id(true);
    $_SESSION['user_id'] = $user_id;
}

function logout_user(): void {
    $_SESSION = [];
    session_destroy();
}

function csrf_token(): string {
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(16));
    }
    return $_SESSION['csrf'];
}

function check_csrf(): void {
    $token = $_POST['csrf'] ?? '';
    if (!hash_equals($_SESSION['csrf'] ?? '', $token)) {
        http_response_code(400);
        die('Bad CSRF token. Go back and try again.');
    }
}

function valid_username(string $u): bool {
    return (bool)preg_match('/^[a-z0-9_]{3,20}$/', $u);
}
