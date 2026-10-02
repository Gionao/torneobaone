<?php
// admin/_header.php
// Partial incluso da tutte le pagine admin
// $pageTitle deve essere definito prima dell'include
?>
<!DOCTYPE html>
<html lang="it">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= htmlspecialchars($pageTitle ?? 'Admin') ?> — <?= htmlspecialchars(SITE_NAME) ?></title>
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
      <span><?= is_telecronista() ? 'Telecronista' : 'Admin' ?> <em>Baone</em></span>
    </div>
    <div class="admin-nav-links">
      <?php if (is_telecronista()): ?>
      <a href="<?= SITE_URL ?>/telecronista/index.php">Partite</a>
      <?php else: ?>
      <a href="<?= SITE_URL ?>/admin/index.php">Dashboard</a>
      <a href="<?= SITE_URL ?>/admin/partite/index.php">Partite</a>
      <a href="<?= SITE_URL ?>/admin/squadre/index.php">Squadre</a>
      <a href="<?= SITE_URL ?>/admin/giocatori/index.php">Giocatori</a>
      <a href="<?= SITE_URL ?>/admin/iscrizioni/index.php"> Iscrizioni</a>
      <a href="<?= SITE_URL ?>/admin/pagamenti/transazioni.php">Pagamenti</a>
      <?php endif; ?>
      <a href="<?= SITE_URL ?>/admin/logout.php" class="admin-logout">Esci</a>
    </div>
  </div>
</nav>

<main class="admin-main">
<div class="admin-container">
