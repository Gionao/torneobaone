<?php
require_once __DIR__ . '/../includes/config.php';

$currentPage = basename($_SERVER['PHP_SELF'] ?? 'index.php');
$navActive = match ($currentPage) {
    'index.php' => 'home',
    'calendario.php' => 'calendario',
    'classifica.php' => 'classifica',
    'iscrizione.php' => 'iscrizione',
    'login.php' => 'login',
    default => '',
};
?>
<nav class="navbar" id="navbar">
  <div class="nav-container">
    <a href="<?php echo SITE_URL; ?>/index.php" class="nav-brand">
      <img src="assets/img/logo_torneo.png" alt="Logo" class="nav-logo">
      <span>Torneo Baone <em>2026</em></span>
    </a>
    <button class="nav-toggle" id="navToggle" aria-label="Menu">
      <span></span><span></span><span></span>
    </button>
    <ul class="nav-links" id="navLinks">
      <li><a href="<?php echo SITE_URL; ?>/index.php" class="<?= $navActive === 'home' ? 'active' : '' ?>">Home</a></li>
      <li><a href="<?php echo SITE_URL; ?>/calendario.php" class="<?= $navActive === 'calendario' ? 'active' : '' ?>">Calendario</a></li>
      <li><a href="<?php echo SITE_URL; ?>/classifica.php" class="<?= $navActive === 'classifica' ? 'active' : '' ?>">Classifica</a></li>
      <li><a href="<?php echo SITE_URL; ?>/iscrizione.php" class="<?= $navActive === 'iscrizione' ? 'active' : '' ?>">Iscrizione</a></li>
      <li><a href="<?php echo SITE_URL; ?>/capitano/login.php" class="<?= $navActive === 'login' ? 'active' : '' ?>">Login</a></li>
    </ul>
  </div>
</nav>

<script>
document.addEventListener('DOMContentLoaded', function() {
  const navToggle = document.getElementById('navToggle');
  const navLinks = document.getElementById('navLinks');

  if (navToggle && navLinks) {
    navToggle.addEventListener('click', function(e) {
      e.stopPropagation();
      navLinks.classList.toggle('open');
    });

    // Chiude il menu se si clicca su un link o fuori
    document.addEventListener('click', function(e) {
      if (!navLinks.contains(e.target) && !navToggle.contains(e.target)) {
        navLinks.classList.remove('open');
      }
    });
  }
});
</script>