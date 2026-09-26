<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth_capitano.php';

require_capitano();

$pdo = db();
$idCapitano = (int) ($_SESSION['capitano_id'] ?? 0);

$row = null;
if (table_has_column($pdo, 'squadre', 'id_capitano')) {
    $squadra = $pdo->prepare('SELECT * FROM squadre WHERE id_capitano = ? LIMIT 1');
    $squadra->execute([$idCapitano]);
    $row = $squadra->fetch();
}

if (!$row) {
    header('Location: ' . SITE_URL . '/capitano/dashboard.php');
    exit;
}

$pageTitle = 'Squadra';
require_once __DIR__ . '/_header.php';
?>

<div class="admin-page-header">
  <h1>Squadra</h1>
  <p>Dettagli della tua squadra e stato dell'iscrizione.</p>
</div>

<div class="admin-form">
  <div class="form-group">
    <label>Nome squadra</label>
    <input type="text" value="<?= htmlspecialchars($row['nome']) ?>" readonly>
  </div>
  <div class="form-group">
    <label>Quota iscrizione</label>
    <input type="text" value="€<?= number_format((float) ($row['quota_iscrizione'] ?? 0), 2, ',', '.') ?>" readonly>
  </div>
  <div class="form-group">
    <label>Stato iscrizione</label>
    <input type="text" value="<?= htmlspecialchars($row['stato_iscrizione']) ?>" readonly>
  </div>
  <div class="form-group">
    <label>Stato pagamento</label>
    <input type="text" value="<?= htmlspecialchars($row['stato_pagamento']) ?>" readonly>
  </div>
</div>

<?php require_once __DIR__ . '/_footer.php'; ?>
