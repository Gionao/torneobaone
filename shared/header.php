<?php require_once __DIR__ . '/../includes/config.php'; ?>
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
      <li><a href="<?php echo SITE_URL; ?>/index.php" class="active">Home</a></li>
      <li><a href="<?php echo SITE_URL; ?>/calendario.php">Calendario</a></li>
      <li><a href="<?php echo SITE_URL; ?>/classifica.php">Classifica</a></li>
      <li><a href="<?php echo SITE_URL; ?>/iscrizione.php">Iscrizione</a></li>
      <li><a href="<?php echo SITE_URL; ?>/capitano/login.php">Login</a></li>
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