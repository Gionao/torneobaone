<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/iscrizione_helper.php';
require_admin();

$pdo = db();
$squadre = $pdo->query('SELECT s.id, s.nome, s.stato_iscrizione, s.stato_pagamento, s.quota_iscrizione, COUNT(g.id) AS num_giocatori, SUM(CASE WHEN g.stato_iscrizione = "completo" THEN 1 ELSE 0 END) AS completati FROM squadre s LEFT JOIN giocatori g ON g.id_squadra = s.id GROUP BY s.id ORDER BY s.nome')->fetchAll();

$pageTitle = 'Iscrizioni';
require_once __DIR__ . '/../_header.php';
?>

<div class="admin-page-header">
  <h1>Iscrizioni</h1>
  <p>Panoramica delle squadre, stati iscrizione e pagamenti.</p>
</div>

<div class="admin-table-wrap">
  <table class="admin-table">
    <thead>
      <tr>
        <th>Squadra</th>
        <th>Iscrizione</th>
        <th>Pagamento</th>
        <th>Giocatori</th>
        <th>Quota</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($squadre as $s): ?>
        <tr>
          <td><?= htmlspecialchars($s['nome']) ?></td>
          <td><span class="state-pill <?= $s['stato_iscrizione'] === 'completa' ? 'ok' : 'warn' ?>"><?= htmlspecialchars(['bozza' => 'Da completare', 'in_corso' => 'Iscrizione in corso', 'completa' => 'Iscrizione confermata'][$s['stato_iscrizione']] ?? ucfirst(str_replace('_', ' ', $s['stato_iscrizione']))) ?></span></td>
          <td><span class="state-pill <?= $s['stato_pagamento'] === 'pagato' ? 'ok' : 'bad' ?>"><?= htmlspecialchars(etichetta_stato_pagamento((string) $s['stato_pagamento'])) ?></span></td>
          <td><?= (int) ($s['num_giocatori'] ?? 0) ?></td>
          <td>€<?= number_format((float) ($s['quota_iscrizione'] ?? 0), 2, ',', '.') ?></td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>

<?php require_once __DIR__ . '/../_footer.php'; ?>
