<?php
require_once __DIR__ . '/../includes/config.php';

$currentPage = basename($_SERVER['PHP_SELF'] ?? 'index.php');
$navActive = match ($currentPage) {
    'index.php' => 'home',
    'calendario.php' => 'calendario',
    'classifica.php' => 'classifica',
    'squadre.php', 'squadra.php' => 'squadre',
    'iscrizione.php' => 'iscrizione',
    'login.php' => 'login',
    default => '',
};
?>
<nav class="navbar public-navbar" id="navbar">
  <div class="nav-container">
    <a href="<?php echo SITE_URL; ?>/index.php" class="nav-brand">
      <img src="<?php echo SITE_URL; ?>/assets/img/logo_torneo.png" alt="Logo" class="nav-logo">
      <span>Torneo Baone <em>2026</em></span>
    </a>
    <button class="nav-toggle" id="navMenuToggle" type="button" aria-label="Apri il menu" aria-controls="navLinks" aria-expanded="false">
      <span></span><span></span><span></span>
    </button>
    <ul class="nav-links" id="navLinks">
      <li><a href="<?php echo SITE_URL; ?>/index.php" class="<?= $navActive === 'home' ? 'active' : '' ?>">Home</a></li>
      <li><a href="<?php echo SITE_URL; ?>/calendario.php" class="<?= $navActive === 'calendario' ? 'active' : '' ?>">Calendario</a></li>
      <li><a href="<?php echo SITE_URL; ?>/classifica.php" class="<?= $navActive === 'classifica' ? 'active' : '' ?>">Classifica</a></li>
      <li><a href="<?php echo SITE_URL; ?>/squadre" class="<?= $navActive === 'squadre' ? 'active' : '' ?>">Squadre</a></li>
      <li><a href="<?php echo SITE_URL; ?>/iscrizione.php" class="<?= $navActive === 'iscrizione' ? 'active' : '' ?>">Iscrizione</a></li>
      <li><a href="<?php echo SITE_URL; ?>/capitano/login.php" class="<?= $navActive === 'login' ? 'active' : '' ?>">Login</a></li>
    </ul>
  </div>
</nav>
<div class="nav-backdrop" id="navBackdrop" aria-hidden="true"></div>
