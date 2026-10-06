<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/helpers.php';
$u = current_user();
$page_title = $page_title ?? $SITE_TITLE;
?><!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= h($page_title) ?> — <?= h($SITE_TITLE) ?></title>
<link rel="icon" href="<?= url('/assets/logo.png') ?>">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Caveat:wght@400;600;700&family=Patrick+Hand&family=Special+Elite&family=Shadows+Into+Light&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= url('/assets/style.css') ?>">
</head>
<body>
<div class="paper">
  <header class="topbar">
    <a class="brand" href="<?= url('/') ?>">
      <img src="<?= url('/assets/logo.png') ?>" alt="Sue logo">
      <span class="brand-tag"><?= h($SITE_TAGLINE) ?></span>
    </a>
    <nav class="nav">
      <?php if ($u): ?>
        <a href="<?= url('/dashboard.php') ?>">my notebook</a>
        <a href="<?= profile_url($u['username']) ?>">@<?= h($u['username']) ?></a>
        <a href="<?= url('/logout.php') ?>" class="muted">log out</a>
      <?php else: ?>
        <a href="<?= url('/login.php') ?>">log in</a>
        <a href="<?= url('/signup.php') ?>" class="btn-ink">make your Sue</a>
      <?php endif; ?>
    </nav>
  </header>

  <?php if ($f = flash()): ?>
    <div class="sticky-note"><?= h($f) ?></div>
  <?php endif; ?>

  <main class="page">
