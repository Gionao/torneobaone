<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';
session_start_secure();
if (!is_admin_logged()) { header('Location: ' . TELECRONISTA_URL . '/login.php'); exit; }
staff_require_authenticated();
if (!staff_has_capability('partite.manage') && !staff_has_capability('partite.limited')) { staff_forbidden(); }
if (!is_telecronista()) { header('Location: ' . staff_landing_url('partite')); exit; }
$pdo = db();
$numPartite = (int) $pdo->query('SELECT COUNT(*) FROM partite')->fetchColumn();
$pageTitle = 'Area telecronista';
require_once __DIR__ . '/../admin/_header.php';
?>
<div class="admin-page-header"><h1>Area telecronista</h1><p>Gestione calendario, risultati ed eventi delle partite.</p></div>
<div class="quick-actions"><div class="qa-grid">
<a href="partite/index.php" class="qa-btn">Apri partite (<?= $numPartite ?>)</a>
<a href="partite/form.php" class="qa-btn">+ Crea partita</a>
</div></div>
<?php require_once __DIR__ . '/../admin/_footer.php'; ?>
