<?php require_once __DIR__ . '/includes/config.php'; ?>
<nav class="navbar" id="navbar">
  <div class="nav-container">
    <!-- Link alla Home usando la URL assoluta -->
    <a href="../sito_torneo/index.php" class="nav-brand">
      <img src="assets/img/logo_torneo.png" alt="Logo" class="nav-logo">
      <span>Torneo Baone <em>2026</em></span>
    </a>
    <button class="nav-toggle" id="navToggle" aria-label="Menu">
      <span></span><span></span><span></span>
    </button>
    <ul class="nav-links" id="navLinks">
      <li><a href="<?php echo $baseUrl; ?>/index.php" class="active">Home</a></li>
      <li><a href="<?php echo $baseUrl; ?>/calendario.php">Calendario</a></li>
      <li><a href="<?php echo $baseUrl; ?>/classifica.php">Classifica</a></li>
      <li><a href="<?php echo $baseUrl; ?>/regolamento.php">Iscrizione</a></li>
    </ul>
  </div>
</nav>
