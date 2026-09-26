<?php
// capitano/_header.php

declare(strict_types=1);

require_once __DIR__ . '/../includes/config.php';

if (!isset($pageTitle)) {
    $pageTitle = 'Area Capitano';
}
?>
<!DOCTYPE html>
<html lang="it">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= htmlspecialchars($pageTitle) ?> — <?= htmlspecialchars(SITE_NAME) ?></title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Barlow+Condensed:wght@600;700;800&family=Barlow:wght@400;500;600&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?= SITE_URL ?>/assets/css/style.css">
  <link rel="stylesheet" href="<?= SITE_URL ?>/assets/css/admin.css">
</head>
<body class="admin-body">

<nav class="admin-navbar">
  <div class="admin-nav-inner">
    <div class="admin-brand">
      <img src="<?= SITE_URL ?>/assets/img/logo_torneo.png" alt="Logo" class="admin-logo">
      <span>Capitano <em>Baone</em></span>
    </div>
    <div class="admin-nav-links">
      <a href="<?= SITE_URL ?>/capitano/dashboard.php">Dashboard</a>
      <a href="<?= SITE_URL ?>/capitano/squadra.php">Squadra</a>
      <a href="<?= SITE_URL ?>/capitano/giocatori.php">Giocatori</a>
      <a href="<?= SITE_URL ?>/capitano/logout.php" class="admin-logout">Esci</a>
    </div>
  </div>
</nav>

<main class="admin-main">
  <div class="admin-container">
