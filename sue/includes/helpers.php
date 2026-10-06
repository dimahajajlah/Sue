<?php
require_once __DIR__ . '/db.php';

function h(?string $s): string {
    return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8');
}

// Only allow http(s) image/cover URLs — blocks javascript:, data:, etc.
function safe_image_url(?string $u): ?string {
    if (!$u) return null;
    $u = trim($u);
    if (!preg_match('#^https?://#i', $u)) return null;
    return $u;
}

function url(string $path = ''): string {
    return BASE_URL . $path;
}

function redirect(string $path): void {
    header('Location: ' . url($path));
    exit;
}

function flash(string $msg = null): ?string {
    if ($msg !== null) {
        $_SESSION['flash'] = $msg;
        return null;
    }
    $f = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);
    return $f;
}

// Section types — the system "types" each user gets, plus custom_list for user-added.
const SECTION_TYPES = [
    'cool_things'  => ['label' => 'Cool things about me that nobody cares about', 'fields' => ['title']],
    'movies'       => ['label' => 'Movies',       'fields' => ['title','year','rating','status','review','poster']],
    'shows'        => ['label' => 'Shows',        'fields' => ['title','year','rating','status','review','poster']],
    'books'        => ['label' => 'Books',        'fields' => ['title','author','rating','status','quote','cover']],
    'music'        => ['label' => 'Music',        'fields' => ['title','artist','minutes','note']],
    'takes'        => ['label' => 'Hot takes',    'fields' => ['title','body']],
    'skills'       => ['label' => 'Skills & party tricks', 'fields' => ['title','level','body']],
    'quotes'       => ['label' => 'Quotes',       'fields' => ['title','body','attribution']],
    'now'          => ['label' => 'Now',          'fields' => ['title','body']],
    'places'       => ['label' => 'Places',       'fields' => ['title','status','note']],
    'stats'        => ['label' => 'Stats',        'fields' => ['title','body']],
    'ama'          => ['label' => 'Ask me anything', 'fields' => []],
    'guestbook'    => ['label' => 'Guestbook',    'fields' => []],
    'custom_list'  => ['label' => 'Custom list',  'fields' => ['title','body']],
];

function default_sections_for_user(int $user_id): void {
    $defaults = [
        ['cool_things', 'cool-things',  'Cool things about me that nobody cares about'],
        ['movies',      'movies',       'Movies'],
        ['shows',       'shows',        'Shows'],
        ['books',       'books',        'Books'],
        ['music',       'music',        'Music'],
        ['takes',       'takes',        'Hot takes'],
        ['skills',      'skills',       'Skills & party tricks'],
        ['quotes',      'quotes',       'Quotes'],
        ['now',         'now',          'Now'],
        ['places',      'places',       'Places'],
        ['stats',       'stats',        'Stats'],
        ['ama',         'ama',          'Ask me anything'],
        ['guestbook',   'guestbook',    'Guestbook'],
    ];
    $i = 0;
    foreach ($defaults as [$type, $slug, $title]) {
        q('INSERT INTO sections (user_id, type, slug, title, is_public, sort_order) VALUES (?,?,?,?,?,?)',
          [$user_id, $type, $slug, $title, 1, $i++], 'isssii');
    }
}

function get_section(int $user_id, string $slug): ?array {
    return fetch_one('SELECT * FROM sections WHERE user_id=? AND slug=?', [$user_id, $slug], 'is');
}

function get_user_by_username(string $username): ?array {
    return fetch_one('SELECT id, username, display_name, bio, created_at FROM users WHERE username=?', [$username], 's');
}

function profile_url(string $username, string $slug = ''): string {
    return url('/u.php?u=' . urlencode($username) . ($slug ? '&s=' . urlencode($slug) : ''));
}

function nice_date(?string $datetime): string {
    if (!$datetime) return '';
    $ts = strtotime($datetime);
    return $ts ? date('M j, Y', $ts) : '';
}

function unique_slug(int $user_id, string $base): string {
    $base = strtolower(preg_replace('/[^a-z0-9]+/i', '-', $base));
    $base = trim($base, '-');
    if ($base === '') $base = 'section';
    $slug = $base;
    $i = 2;
    while (fetch_one('SELECT id FROM sections WHERE user_id=? AND slug=?', [$user_id, $slug], 'is')) {
        $slug = $base . '-' . $i++;
    }
    return $slug;
}
