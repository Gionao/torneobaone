<?php
// admin/index.php — Dashboard
declare(strict_types=1);
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';
require_admin();

$pdo = db();

$numSquadre  = $pdo->query('SELECT COUNT(*) FROM squadre')->fetchColumn();
$numGioc     = $pdo->query('SELECT COUNT(*) FROM giocatori')->fetchColumn();
$numPartite  = $pdo->query('SELECT COUNT(*) FROM partite')->fetchColumn();
$numGiocate  = $pdo->query('SELECT COUNT(*) FROM partite WHERE giocata=1')->fetchColumn();
$numDaGiocare = $numPartite - $numGiocate;

$pageTitle = 'Dashboard Admin';
require_once __DIR__ . '/_header.php';
?>

<div class="admin-page-header">
  <h1>Dashboard</h1>
  <p>Pannello di controllo — <?= htmlspecialchars(SITE_NAME) ?></p>
</div>

<div class="stat-grid">
  <div class="stat-card stat-card--highlight">
    <div class="stat-num"><?= $numSquadre ?></div>
    <div class="stat-lbl">Squadre</div>
    <a href="<?= ADMIN_PATH ?>/squadre/index.php" class="stat-link">Gestisci →</a>
  </div>
  <div class="stat-card">
    <div class="stat-num"><?= $numGioc ?></div>
    <div class="stat-lbl">Giocatori</div>
    <a href="<?= ADMIN_PATH ?>/giocatori/index.php" class="stat-link">Gestisci →</a>
  </div>
  <div class="stat-card">
    <div class="stat-num"><?= $numPartite ?></div>
    <div class="stat-lbl">Partite totali</div>
    <a href="<?= ADMIN_PATH ?>/partite/index.php" class="stat-link">Calendario →</a>
  </div>
  <div class="stat-card">
    <div class="stat-num"><?= $numGiocate ?></div>
    <div class="stat-lbl">Giocate</div>
  </div>
  <div class="stat-card">
    <div class="stat-num"><?= $numDaGiocare ?></div>
    <div class="stat-lbl">Da giocare</div>
  </div>
</div>

<div class="quick-actions">
  <h2>Azioni rapide</h2>
  <div class="qa-grid">
    <a href="<?= ADMIN_PATH ?>/partite/form.php"    class="qa-btn">+ Nuova Partita</a>
    <a href="<?= ADMIN_PATH ?>/squadre/form.php"    class="qa-btn">+ Nuova Squadra</a>
    <a href="<?= ADMIN_PATH ?>/giocatori/form.php"  class="qa-btn">+ Nuovo Giocatore</a>
    <a href="<?= ADMIN_PATH ?>/pagamenti/inserisci.php" class="qa-btn">+ Registra Pagamento</a>
    <a href="<?= ARCHIVIO_URL ?>/index.php"              class="qa-btn qa-btn--outline">📷 Archivio media</a>
    <a href="<?= SITE_URL ?>/classifica.php"            class="qa-btn qa-btn--outline" target="_blank">🏆 Vedi Classifica</a>
    <a href="<?= SITE_URL ?>/calendario.php"            class="qa-btn qa-btn--outline" target="_blank">📅 Vedi Calendario</a>
  </div>
</div>

<?php require_once __DIR__ . '/_footer.php'; ?>
