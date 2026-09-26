<?php
// partials/admin_header.php
// Incluso da tutte le pagine admin (dopo require_admin())
?>
<header class="admin-navbar">
  <div class="admin-nav-inner">
    <a href="<?= ADMIN_PATH ?>" class="admin-brand">
      <img src="<?= SITE_URL ?>/assets/img/logo_torneo.png" alt="Logo" class="admin-logo">
      <span>Admin <em>Panel</em></span>
    </a>

    <nav class="admin-nav-links">
      <a href="<?= ADMIN_PATH ?>">Dashboard</a>
      <a href="<?= ADMIN_PATH ?>/squadre/">Squadre</a>
      <a href="<?= ADMIN_PATH ?>/giocatori/">Giocatori</a>
      <a href="<?= ADMIN_PATH ?>/partite/">Partite</a>
      <a href="<?= ADMIN_PATH ?>/impostazioni/password.php">⚙</a>
      <a href="<?= ADMIN_PATH ?>/logout.php" class="admin-logout">Esci</a>
    </nav>
  </div>
</header>
