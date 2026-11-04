<?php
// admin/index.php — Dashboard
declare(strict_types=1);
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';
require_tournament_capability('admin.dashboard');

$canTeams = staff_has_capability('torneo.teams');
$canPlayers = staff_has_capability('torneo.players');
$canMatches = staff_has_capability('partite.manage') || staff_has_capability('partite.limited');
$canPayments = staff_has_capability('torneo.payments');
$canRegistrations = staff_has_capability('torneo.registrations');
$pdo = db();
$numSquadre = $canTeams ? (int) $pdo->query('SELECT COUNT(*) FROM squadre')->fetchColumn() : 0;
$numGioc = $canPlayers ? (int) $pdo->query('SELECT COUNT(*) FROM giocatori')->fetchColumn() : 0;
$numPartite = $canMatches ? (int) $pdo->query('SELECT COUNT(*) FROM partite')->fetchColumn() : 0;
$numGiocate = $canMatches ? (int) $pdo->query('SELECT COUNT(*) FROM partite WHERE giocata=1')->fetchColumn() : 0;
$numDaGiocare = $numPartite - $numGiocate;

$pageTitle = 'Dashboard Admin';
require_once __DIR__ . '/_header.php';
?>

<div class="admin-page-header">
  <h1>Dashboard</h1>
  <p>Pannello di controllo — <?= htmlspecialchars(SITE_NAME) ?></p>
</div>

<div class="stat-grid">
  <?php if ($canTeams): ?>
  <div class="stat-card stat-card--highlight">
    <div class="stat-num"><?= $numSquadre ?></div>
    <div class="stat-lbl">Squadre</div>
    <a href="<?= ADMIN_PATH ?>/squadre/index.php" class="stat-link">Gestisci →</a>
  </div>
  <?php endif; ?>
  <?php if ($canPlayers): ?>
  <div class="stat-card">
    <div class="stat-num"><?= $numGioc ?></div>
    <div class="stat-lbl">Giocatori</div>
    <a href="<?= ADMIN_PATH ?>/giocatori/index.php" class="stat-link">Gestisci →</a>
  </div>
  <?php endif; ?>
  <?php if ($canMatches): ?>
  <div class="stat-card">
    <div class="stat-num"><?= $numPartite ?></div>
    <div class="stat-lbl">Partite totali</div>
    <a href="<?= ADMIN_PATH ?>/partite/index.php" class="stat-link">Calendario →</a>
  </div>
  <?php endif; ?>
  <?php if ($canMatches): ?>
  <div class="stat-card">
    <div class="stat-num"><?= $numGiocate ?></div>
    <div class="stat-lbl">Giocate</div>
  </div>
  <?php endif; ?>
  <?php if ($canMatches): ?>
  <div class="stat-card">
    <div class="stat-num"><?= $numDaGiocare ?></div>
    <div class="stat-lbl">Da giocare</div>
  </div>
  <?php endif; ?>
  <?php if ($canRegistrations || $canPayments): ?>
  <div class="stat-card">
    <div class="stat-lbl">Segreteria</div>
    <?php if ($canRegistrations): ?><a href="<?= ADMIN_PATH ?>/iscrizioni/" class="stat-link">Iscrizioni →</a><?php endif; ?>
    <?php if ($canPayments): ?><a href="<?= ADMIN_PATH ?>/pagamenti/transazioni" class="stat-link">Pagamenti →</a><?php endif; ?>
  </div>
  <?php endif; ?>
</div>

<?php if ($canMatches || $canTeams || $canPlayers || $canPayments || $canRegistrations): ?>
<div class="quick-actions">
  <h2>Azioni rapide</h2>
  <div class="qa-grid">
    <?php if ($canMatches): ?><a href="<?= staff_role('partite') === 'telecronista' ? TELECRONISTA_URL : ADMIN_PATH ?>/partite/form.php" class="qa-btn">+ Nuova Partita</a><?php endif; ?>
    <?php if ($canTeams): ?><a href="<?= ADMIN_PATH ?>/squadre/form.php" class="qa-btn">+ Nuova Squadra</a><?php endif; ?>
    <?php if ($canPlayers): ?><a href="<?= ADMIN_PATH ?>/giocatori/form.php" class="qa-btn">+ Nuovo Giocatore</a><?php endif; ?>
    <?php if ($canPayments): ?><a href="<?= ADMIN_PATH ?>/pagamenti/inserisci.php" class="qa-btn">+ Registra Pagamento</a><?php endif; ?>
    <?php if (staff_has_capability('archivio.admin') || staff_has_capability('archivio.editor') || staff_has_capability('archivio.uploader')): ?><a href="<?= ARCHIVIO_URL ?>/index.php" class="qa-btn qa-btn--outline">📷 Archivio media</a><?php endif; ?>
    <?php if ($canMatches): ?>
    <a href="<?= SITE_URL ?>/classifica.php"            class="qa-btn qa-btn--outline" target="_blank">🏆 Vedi Classifica</a>
    <a href="<?= SITE_URL ?>/calendario.php"            class="qa-btn qa-btn--outline" target="_blank">📅 Vedi Calendario</a>
    <?php endif; ?>
  </div>
</div>
<?php endif; ?>

<?php require_once __DIR__ . '/_footer.php'; ?>
