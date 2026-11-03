<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth_capitano.php';
require_once __DIR__ . '/../includes/iscrizione_helper.php';

require_capitano();

$pdo = db();
$idCapitano = (int) ($_SESSION['capitano_id'] ?? 0);
$squadraStmt = $pdo->prepare('SELECT id, nome, quota_iscrizione, stato_pagamento FROM squadre WHERE id_capitano = ? LIMIT 1');
$squadraStmt->execute([$idCapitano]);
$squadra = $squadraStmt->fetch();
$pagamenti = [];
$totalePagato = 0.0;
$quotaResidua = 0.0;

if ($squadra) {
    $importiStmt = $pdo->prepare('SELECT COALESCE(SUM(CASE WHEN stato = "confermata" THEN importo ELSE 0 END), 0) AS pagato FROM transazioni WHERE id_squadra = ?');
    $importiStmt->execute([(int) $squadra['id']]);
    $totalePagato = (float) $importiStmt->fetchColumn();
    $quotaResidua = max(0, (float) $squadra['quota_iscrizione'] - $totalePagato);

    $paymentsStmt = $pdo->prepare('SELECT t.importo, t.data_pagamento, t.stato, t.note, t.registrato_at, mp.nome AS metodo FROM transazioni t LEFT JOIN metodi_pagamento mp ON mp.id = t.id_metodo_pagamento WHERE t.id_squadra = ? ORDER BY t.data_pagamento DESC, t.registrato_at DESC, t.id DESC');
    $paymentsStmt->execute([(int) $squadra['id']]);
    $pagamenti = $paymentsStmt->fetchAll();
}

$pageTitle = 'Pagamenti';
require_once __DIR__ . '/_header.php';
?>

<div class="admin-page-header">
  <h1>Pagamenti</h1>
  <p>Storico dei pagamenti registrati per <?= htmlspecialchars($squadra['nome'] ?? 'la tua squadra') ?>.</p>
</div>

<?php if (!$squadra): ?>
  <div class="alert alert-info">Non risulta una squadra associata al tuo account.</div>
<?php else: ?>
  <div class="stat-grid captain-overview-grid">
    <div class="stat-card"><div class="stat-num">€<?= number_format((float) $squadra['quota_iscrizione'], 2, ',', '.') ?></div><div class="stat-lbl">Quota totale</div></div>
    <div class="stat-card"><div class="stat-num">€<?= number_format($totalePagato, 2, ',', '.') ?></div><div class="stat-lbl">Pagato</div></div>
    <div class="stat-card"><div class="stat-num">€<?= number_format($quotaResidua, 2, ',', '.') ?></div><div class="stat-lbl">Quota da pagare</div></div>
  </div>

  <div class="admin-form captain-panel">
    <h2 style="margin-bottom:1rem; font-family:'Barlow Condensed',sans-serif; text-transform:uppercase; letter-spacing:.08em; color:var(--gold);">Storico pagamenti</h2>
    <?php if (!$pagamenti): ?>
      <p class="empty-msg">Non risultano pagamenti registrati.</p>
    <?php else: ?>
      <div class="admin-table-wrap"><table class="admin-table">
        <thead><tr><th>Data</th><th>Importo</th><th>Metodo</th><th>Stato</th><th>Note</th></tr></thead>
        <tbody>
          <?php foreach ($pagamenti as $pagamento):
            $stato = (string) $pagamento['stato'];
            $statoLabel = ['confermata' => 'Confermato', 'in_attesa' => 'In attesa', 'annullata' => 'Annullato'][$stato] ?? $stato;
            $statoClass = ['confermata' => 'ok', 'in_attesa' => 'warn', 'annullata' => 'bad'][$stato] ?? '';
          ?>
            <tr>
              <td><?= htmlspecialchars(date('d/m/Y', strtotime((string) $pagamento['data_pagamento']))) ?></td>
              <td>€<?= number_format((float) $pagamento['importo'], 2, ',', '.') ?></td>
              <td><?= htmlspecialchars($pagamento['metodo'] ?? '—') ?></td>
              <td><span class="state-pill <?= htmlspecialchars($statoClass) ?>"><?= htmlspecialchars($statoLabel) ?></span></td>
              <td><?= nl2br(htmlspecialchars((string) ($pagamento['note'] ?? ''))) ?: '—' ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table></div>
    <?php endif; ?>
  </div>
<?php endif; ?>

<?php require_once __DIR__ . '/_footer.php'; ?>
