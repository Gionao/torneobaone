<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/db.php';
require_admin();

$pdo = db();
$methods = $pdo->query('SELECT * FROM metodi_pagamento ORDER BY ordine, id')->fetchAll();
$pageTitle = 'Metodi di pagamento';
require_once __DIR__ . '/../_header.php';
?>

<div class="admin-page-header">
  <h1>Metodi di pagamento</h1>
  <p>Configurazione dei canali di pagamento disponibili.</p>
</div>

<div class="admin-table-wrap">
  <table class="admin-table">
    <thead>
      <tr>
        <th>Nome</th>
        <th>Descrizione</th>
        <th>IBAN</th>
        <th>Attivo</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($methods as $m): ?>
        <tr>
          <td><?= htmlspecialchars($m['nome']) ?></td>
          <td><?= htmlspecialchars($m['descrizione'] ?? '') ?></td>
          <td><?= htmlspecialchars($m['iban'] ?? '—') ?></td>
          <td><?= !empty($m['attivo']) ? 'Sì' : 'No' ?></td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>

<?php require_once __DIR__ . '/../_footer.php'; ?>
