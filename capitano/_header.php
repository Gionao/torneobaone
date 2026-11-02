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
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-sRIl4kxILFvY47J16cr9ZwB07vP4J8+LH7qKQnuqkuIAvNWLzeN8tE5YBujZqJLB" crossorigin="anonymous">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Barlow+Condensed:wght@600;700;800&family=Barlow:wght@400;500;600&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?= SITE_URL ?>/assets/css/tokens.css?v=redesign-1">
  <link rel="stylesheet" href="<?= SITE_URL ?>/assets/css/style.css">
  <link rel="stylesheet" href="<?= SITE_URL ?>/assets/css/admin.css">
</head>
<body class="admin-body">

<nav class="navbar navbar-expand-lg navbar-dark captain-navbar">
  <div class="container-fluid captain-nav-inner">
    <a class="navbar-brand admin-brand" href="<?= SITE_URL ?>/capitano/dashboard.php">
      <img src="<?= SITE_URL ?>/assets/img/logo_torneo.png" alt="Logo" class="admin-logo">
      <span>Capitano <em>Baone</em></span>
    </a>
    <button class="navbar-toggler captain-nav-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#captainNavigation" aria-controls="captainNavigation" aria-expanded="false" aria-label="Apri o chiudi il menu">
      <span class="navbar-toggler-icon"></span>
    </button>
    <div class="collapse navbar-collapse captain-nav-collapse" id="captainNavigation">
      <div class="navbar-nav captain-nav-links">
        <a class="nav-link" href="<?= SITE_URL ?>/capitano/dashboard.php">Dashboard</a>
        <a class="nav-link" href="<?= SITE_URL ?>/capitano/giocatori.php">Giocatori</a>
        <a class="nav-link" href="<?= SITE_URL ?>/capitano/pagamenti.php">Pagamenti</a>
        <a class="nav-link captain-nav-logout" href="<?= SITE_URL ?>/capitano/logout.php">Esci</a>
      </div>
    </div>
  </div>
</nav>

<main class="admin-main">
  <div class="admin-container">
