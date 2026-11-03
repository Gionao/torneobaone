<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth_capitano.php';
require_once __DIR__ . '/../includes/iscrizione_helper.php';

require_capitano();

$pdo = db();
$idCapitano = (int) ($_SESSION['capitano_id'] ?? 0);

$squadraRow = null;
if (table_has_column($pdo, 'squadre', 'id_capitano')) {
    $squadra = $pdo->prepare('SELECT * FROM squadre WHERE id_capitano = ? LIMIT 1');
    $squadra->execute([$idCapitano]);
    $squadraRow = $squadra->fetch();
}

$giocatori = [];
$giocatoriCompletati = 0;
$giocatoriInAttesaVerifica = 0;
if ($squadraRow) {
    $stmt = $pdo->prepare('SELECT * FROM giocatori WHERE id_squadra = ? ORDER BY cognome, nome');
    $stmt->execute([$squadraRow['id']]);
    $giocatori = $stmt->fetchAll();
    $giocatoriCompletati = count(array_filter($giocatori, static fn(array $g): bool => ($g['stato_iscrizione'] ?? '') === 'completo'));
    $giocatoriInAttesaVerifica = count($giocatori) - $giocatoriCompletati;
}

$pagamentiRecenti = [];
$totalePagato = 0.0;
$quotaResidua = 0.0;
if ($squadraRow) {
    $paidStmt = $pdo->prepare('SELECT COALESCE(SUM(CASE WHEN stato = "confermata" THEN importo ELSE 0 END), 0) FROM transazioni WHERE id_squadra = ?');
    $paidStmt->execute([(int) $squadraRow['id']]);
    $totalePagato = (float) $paidStmt->fetchColumn();
    $quotaResidua = max(0, (float) ($squadraRow['quota_iscrizione'] ?? 0) - $totalePagato);
    $statoPagamentoCorrente = $quotaResidua <= 0 ? 'pagato' : ($totalePagato > 0 ? 'parziale' : 'non_pagato');

    $recentPaymentsStmt = $pdo->prepare('SELECT t.importo, t.data_pagamento, t.stato, mp.nome AS metodo FROM transazioni t LEFT JOIN metodi_pagamento mp ON mp.id = t.id_metodo_pagamento WHERE t.id_squadra = ? ORDER BY t.data_pagamento DESC, t.registrato_at DESC, t.id DESC LIMIT 5');
    $recentPaymentsStmt->execute([(int) $squadraRow['id']]);
    $pagamentiRecenti = $recentPaymentsStmt->fetchAll();
}

$pageTitle = 'Dashboard capitano';
require_once __DIR__ . '/_header.php';
?>

<div class="admin-page-header">
  <h1>Dashboard Capitano</h1>
  <p>Gestisci la tua squadra, i giocatori e i pagamenti.</p>
</div>

<?php if (!empty($_GET['nuova_squadra'])): ?>
  <div class="alert alert-success">Squadra creata correttamente. Ora puoi aggiungere i giocatori iniziali.</div>
<?php endif; ?>

<?php if (!$squadraRow): ?>
  <div class="alert alert-info">Nessuna squadra associata al tuo account. Completa la registrazione per iniziare.</div>
<?php else: ?>
  <div class="stat-grid captain-overview-grid">
    <div class="stat-card">
      <div class="stat-num"><?= htmlspecialchars((string) ($squadraRow['numero_giocatori_previsto'] ?? 0)) ?></div>
      <div class="stat-lbl">Giocatori previsti</div>
    </div>
    <div class="stat-card">
      <div class="stat-num"><?= count($giocatori) ?></div>
      <div class="stat-lbl">Giocatori in rosa</div>
    </div>
    <div class="stat-card">
      <div class="stat-num"><?= $giocatoriCompletati ?></div>
      <div class="stat-lbl">Iscrizioni completate</div>
    </div>
    <div class="stat-card stat-card--highlight">
      <div class="stat-num"><?= $giocatoriInAttesaVerifica ?></div>
      <div class="stat-lbl">In attesa di verifica</div>
      <a class="stat-link" href="<?= SITE_URL ?>/capitano/giocatori.php">Controlla giocatori →</a>
    </div>
    <div class="stat-card">
      <div class="stat-num"><?= count(array_filter($giocatori, fn($g) => ($g['stato_iscrizione'] ?? '') === 'in_attesa_genitore')) ?></div>
      <div class="stat-lbl">Minorenni in attesa</div>
    </div>
    <div class="stat-card">
      <div class="stat-num">€<?= number_format($quotaResidua, 2, ',', '.') ?></div>
      <div class="stat-lbl">Quota da pagare · versati €<?= number_format($totalePagato, 2, ',', '.') ?></div>
    </div>
  </div>

  <div class="admin-form captain-panel" style="margin-bottom: 2rem;">
    <h2 style="margin-bottom:1rem; font-family:'Barlow Condensed',sans-serif; text-transform:uppercase; letter-spacing:.08em; color:var(--gold);">Squadra</h2>
    <p><strong>Nome:</strong> <?= htmlspecialchars($squadraRow['nome']) ?></p>
    <p><strong>Stato iscrizione:</strong> <span class="state-pill <?= $squadraRow['stato_iscrizione'] === 'completa' ? 'ok' : 'warn' ?>"><?= htmlspecialchars(['bozza' => 'Da completare', 'in_corso' => 'Iscrizione in corso', 'completa' => 'Iscrizione confermata'][$squadraRow['stato_iscrizione']] ?? ucfirst(str_replace('_', ' ', $squadraRow['stato_iscrizione']))) ?></span></p>
    <p><strong>Stato pagamento:</strong> <span class="state-pill <?= $statoPagamentoCorrente === 'pagato' ? 'ok' : 'bad' ?>"><?= htmlspecialchars(etichetta_stato_pagamento($statoPagamentoCorrente)) ?></span></p>
    <p><strong>Numero previsti:</strong> <?= htmlspecialchars((string) ($squadraRow['numero_giocatori_previsto'] ?? '')) ?></p>
  </div>

  <div class="admin-form captain-panel" style="margin-bottom: 2rem;">
    <div style="display:flex; justify-content:space-between; align-items:center; gap:1rem; flex-wrap:wrap; margin-bottom:1rem;">
      <h2 style="margin:0; font-family:'Barlow Condensed',sans-serif; text-transform:uppercase; letter-spacing:.08em; color:var(--gold);">Pagamenti recenti</h2>
      <a class="btn btn-outline btn-sm" href="<?= SITE_URL ?>/capitano/pagamenti.php">Tutti i pagamenti</a>
    </div>
    <?php if (!$pagamentiRecenti): ?>
      <p class="empty-msg">Non risultano pagamenti registrati.</p>
    <?php else: ?>
      <div class="admin-table-wrap"><table class="admin-table">
        <thead><tr><th>Data</th><th>Importo</th><th>Metodo</th><th>Stato</th></tr></thead>
        <tbody>
          <?php foreach ($pagamentiRecenti as $pagamento):
            $pagamentoStato = (string) $pagamento['stato'];
            $pagamentoLabel = ['confermata' => 'Confermato', 'in_attesa' => 'In attesa', 'annullata' => 'Annullato'][$pagamentoStato] ?? $pagamentoStato;
            $pagamentoClass = ['confermata' => 'ok', 'in_attesa' => 'warn', 'annullata' => 'bad'][$pagamentoStato] ?? '';
          ?>
            <tr>
              <td><?= htmlspecialchars(date('d/m/Y', strtotime((string) $pagamento['data_pagamento']))) ?></td>
              <td>€<?= number_format((float) $pagamento['importo'], 2, ',', '.') ?></td>
              <td><?= htmlspecialchars($pagamento['metodo'] ?? '—') ?></td>
              <td><span class="state-pill <?= htmlspecialchars($pagamentoClass) ?>"><?= htmlspecialchars($pagamentoLabel) ?></span></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table></div>
    <?php endif; ?>
  </div>

  

  <div class="admin-form captain-panel" style="margin-bottom: 2rem;">
    <h2 style="margin-bottom:1rem; font-family:'Barlow Condensed',sans-serif; text-transform:uppercase; letter-spacing:.08em; color:var(--gold);">Giocatori</h2>
    <?php if (!$giocatori): ?>
      <div class="alert alert-info">Nessun giocatore inserito. Aggiungine uno per generare l'invito.</div>
    <?php else: ?>
      <div class="admin-table-wrap"><table class="admin-table">
        <thead>
          <tr><th>Nome</th><th>Email</th><th>Stato</th></tr>
        </thead>
        <tbody>
          <?php foreach ($giocatori as $g): ?>
            <tr>
              <td><?= htmlspecialchars(($g['nome'] ?? '') . ' ' . ($g['cognome'] ?? '')) ?></td>
              <td><?= htmlspecialchars($g['email'] ?? '—') ?></td>
              <td><span class="state-pill <?= ($g['stato_iscrizione'] ?? '') === 'completo' ? 'ok' : (($g['stato_iscrizione'] ?? '') === 'in_attesa_genitore' ? 'warn' : 'bad') ?>"><?= htmlspecialchars(etichetta_stato_iscrizione((string) ($g['stato_iscrizione'] ?? 'invitato'))) ?></span></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table></div>
    <?php endif; ?>
  </div>

<?php endif; ?>

<?php require_once __DIR__ . '/_footer.php'; ?>
