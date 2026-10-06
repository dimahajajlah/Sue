<?php
// ============================================
// Sue — site configuration
// ============================================

// Site identity
$SITE_TITLE = 'Sue';
$SITE_TAGLINE = 'Share your interests';

// ---------------------------------------------------------------
// Database driver
//   'sqlite' = use a local file (great for local preview, zero setup).
//   'mysql'  = use MySQL — fill in DB_HOST/NAME/USER/PASS below.
// On InfinityFree set DB_DRIVER='mysql' and use the credentials
// from your control panel → MySQL Databases.
// ---------------------------------------------------------------
define('DB_DRIVER', 'sqlite');

// MySQL settings (only used when DB_DRIVER === 'mysql')
define('DB_HOST', 'sql.infinityfree.com');
define('DB_NAME', 'if0_xxxxxxxx_sue');
define('DB_USER', 'if0_xxxxxxxx');
define('DB_PASS', 'your-db-password');

// SQLite file path (only used when DB_DRIVER === 'sqlite')
define('DB_SQLITE_PATH', __DIR__ . '/../data/sue.sqlite');

// Base URL path of the site relative to domain root.
// If uploaded to /htdocs/sue, set to '/sue'. If uploaded to root, leave ''.
define('BASE_URL', '');

// Session — harden cookie flags
if (session_status() === PHP_SESSION_NONE) {
    $is_https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
             || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'secure'   => $is_https,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

// Timezone
date_default_timezone_set('UTC');

// Error display — turn OFF in production
ini_set('display_errors', '1');
error_reporting(E_ALL);

// PHP 7.4 polyfills (InfinityFree usually runs 8.x but just in case)
if (!function_exists('str_ends_with')) {
    function str_ends_with(string $haystack, string $needle): bool {
        return $needle === '' || (strlen($haystack) >= strlen($needle) && substr($haystack, -strlen($needle)) === $needle);
    }
}
if (!function_exists('str_starts_with')) {
    function str_starts_with(string $haystack, string $needle): bool {
        return strncmp($haystack, $needle, strlen($needle)) === 0;
    }
}
