<?php
// pos/includes/navbar.php — Navbar condivisa modulo POS
declare(strict_types=1);

$posNavActive = $posNavActive ?? '';
$posUser = $_SESSION['pos_admin_user'] ?? 'Staff';
?>
<nav class="pos-navbar">
  <div class="pos-nav-inner">
    <a href="<?= POS_URL ?>/operator_panel.php" class="pos-nav-brand">
      <img src="<?= SITE_URL ?>/assets/img/logo_torneo.png" alt="Logo" class="pos-nav-logo">
      <span>POS · <em>BAONE 2026</em></span>
    </a>
    <button type="button" class="pos-nav-toggle" id="posNavToggle" aria-label="Menu">
      <span></span><span></span><span></span>
    </button>
    <div class="pos-nav-links" id="posNavLinks">
      <a href="<?= POS_URL ?>/operator_panel.php" class="<?= $posNavActive === 'operator' ? 'active' : '' ?>">Operatore</a>
      <a href="<?= POS_URL ?>/kitchen_display.php" target="_blank" class="<?= $posNavActive === 'kitchen' ? 'active' : '' ?>">Cucina</a>
      <a href="<?= POS_URL ?>/bar_display.php" target="_blank" class="<?= $posNavActive === 'bar' ? 'active' : '' ?>">Bar</a>
      <a href="<?= POS_URL ?>/admin/menu_management.php" class="<?= $posNavActive === 'menu' ? 'active' : '' ?>">Menu</a>
      <a href="<?= POS_URL ?>/admin/coupons.php" class="<?= $posNavActive === 'coupons' ? 'active' : '' ?>">Coupon</a>
      <a href="<?= POS_URL ?>/admin/orders_history.php" class="<?= $posNavActive === 'history' ? 'active' : '' ?>">Storico</a>
      <span class="pos-nav-user"><?= htmlspecialchars($posUser) ?></span>
      <a href="<?= POS_URL ?>/logout.php" class="pos-nav-logout">Logout</a>
    </div>
  </div>
</nav>
