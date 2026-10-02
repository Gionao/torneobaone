<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';
session_start_secure();
if (!is_admin_logged()) { header('Location: login.php'); exit; }
if (!is_telecronista()) { header('Location: ' . SITE_URL . '/admin/index.php'); exit; }
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
