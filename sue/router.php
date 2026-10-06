<?php
// Router for PHP's built-in dev server (php -S).
// On real shared hosting, .htaccess does this job. This file is dev-only.

$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

// Serve existing static files directly (CSS, images, etc.)
$file = __DIR__ . $uri;
if ($uri !== '/' && file_exists($file) && !is_dir($file)) {
    return false;
}

// Pretty profile URLs: /u/username[/section]
if (preg_match('#^/u/([a-z0-9_]+)(?:/([a-z0-9-]+))?/?$#', $uri, $m)) {
    $_GET['u'] = $m[1];
    if (!empty($m[2])) $_GET['s'] = $m[2];
    require __DIR__ . '/u.php';
    return true;
}

// Default doc
if ($uri === '/' || $uri === '') {
    require __DIR__ . '/index.php';
    return true;
}

// Allow direct .php access (e.g., /signup.php)
$php = __DIR__ . $uri;
if (str_ends_with($uri, '.php') && file_exists($php)) {
    require $php;
    return true;
}

http_response_code(404);
echo 'Not found';
return true;
