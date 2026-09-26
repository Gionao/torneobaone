<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/db.php';
require_admin();

$pdo = db();
$rows = $pdo->query('SELECT t.*, s.nome AS squadra, a.username AS organizzatore, mp.nome AS metodo FROM transazioni t INNER JOIN squadre s ON s.id = t.id_squadra INNER JOIN admin a ON a.id = t.id_organizzatore LEFT JOIN metodi_pagamento mp ON mp.id = t.id_metodo_pagamento ORDER BY t.registrato_at DESC')->fetchAll();

$pageTitle = 'Transazioni';
require_once __DIR__ . '/../_header.php';
?>

<div class="admin-page-header">
  <h1>Transazioni</h1>
  <p>Storico dei pagamenti registrati dagli organizzatori.</p>
</div>

<div class="admin-table-wrap">
  <table class="admin-table">
    <thead>
      <tr>
        <th>Squadra</th>
        <th>Importo</th>
        <th>Metodo</th>
        <th>Data</th>
        <th>Stato</th>
        <th>Organizzatore</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($rows as $row): ?>
        <tr>
          <td><?= htmlspecialchars($row['squadra']) ?></td>
          <td>€<?= number_format((float) $row['importo'], 2, ',', '.') ?></td>
          <td><?= htmlspecialchars($row['metodo'] ?? '—') ?></td>
          <td><?= htmlspecialchars($row['data_pagamento']) ?></td>
          <td><?= htmlspecialchars($row['stato']) ?></td>
          <td><?= htmlspecialchars($row['organizzatore']) ?></td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>

<?php require_once __DIR__ . '/../_footer.php'; ?>
