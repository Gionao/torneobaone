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
if ($squadraRow) {
    $stmt = $pdo->prepare('SELECT * FROM giocatori WHERE id_squadra = ? ORDER BY cognome, nome');
    $stmt->execute([$squadraRow['id']]);
    $giocatori = $stmt->fetchAll();
}

$invitations = [];
if ($squadraRow) {
    $invStmt = $pdo->prepare('SELECT * FROM token_verifica WHERE tipo = "invito_giocatore" AND id_capitano = ? ORDER BY created_at DESC');
    $invStmt->execute([$idCapitano]);
    $invitations = $invStmt->fetchAll();
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
  <div class="stat-grid">
    <div class="stat-card">
      <div class="stat-num"><?= htmlspecialchars((string) ($squadraRow['numero_giocatori_previsto'] ?? 0)) ?></div>
      <div class="stat-lbl">Previsti</div>
    </div>
    <div class="stat-card">
      <div class="stat-num"><?= count($giocatori) ?></div>
      <div class="stat-lbl">Giocatori</div>
    </div>
    <div class="stat-card">
      <div class="stat-num"><?= count(array_filter($giocatori, fn($g) => ($g['stato_iscrizione'] ?? '') === 'completo')) ?></div>
      <div class="stat-lbl">Completi</div>
    </div>
    <div class="stat-card">
      <div class="stat-num"><?= count(array_filter($giocatori, fn($g) => ($g['stato_iscrizione'] ?? '') === 'in_attesa_genitore')) ?></div>
      <div class="stat-lbl">Minorenni in attesa</div>
    </div>
    <div class="stat-card">
      <div class="stat-num">€<?= number_format((float) ($squadraRow['quota_iscrizione'] ?? 0), 2, ',', '.') ?></div>
      <div class="stat-lbl">Quota</div>
    </div>
  </div>

  <div class="admin-form" style="margin-bottom: 2rem;">
    <h2 style="margin-bottom:1rem; font-family:'Barlow Condensed',sans-serif; text-transform:uppercase; letter-spacing:.08em; color:var(--gold);">Squadra</h2>
    <p><strong>Nome:</strong> <?= htmlspecialchars($squadraRow['nome']) ?></p>
    <p><strong>Stato iscrizione:</strong> <?= htmlspecialchars($squadraRow['stato_iscrizione']) ?></p>
    <p><strong>Stato pagamento:</strong> <?= htmlspecialchars($squadraRow['stato_pagamento']) ?></p>
    <p><strong>Numero previsti:</strong> <?= htmlspecialchars((string) ($squadraRow['numero_giocatori_previsto'] ?? '')) ?></p>
  </div>

  <div class="admin-form" style="margin-bottom: 2rem;">
    <h2 style="margin-bottom:1rem; font-family:'Barlow Condensed',sans-serif; text-transform:uppercase; letter-spacing:.08em; color:var(--gold);">Giocatori</h2>
    <?php if (!$giocatori): ?>
      <div class="alert alert-info">Nessun giocatore inserito. Aggiungine uno per generare l'invito.</div>
    <?php else: ?>
      <table class="admin-table">
        <thead>
          <tr><th>Nome</th><th>Email</th><th>Stato</th></tr>
        </thead>
        <tbody>
          <?php foreach ($giocatori as $g): ?>
            <tr>
              <td><?= htmlspecialchars(($g['nome'] ?? '') . ' ' . ($g['cognome'] ?? '')) ?></td>
              <td><?= htmlspecialchars($g['email'] ?? '—') ?></td>
              <td><span class="state-pill"><?= htmlspecialchars((string) ($g['stato_iscrizione'] ?? 'invitato')) ?></span></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>
  </div>

  <div class="admin-form" style="margin-bottom: 2rem;">
    <h2 style="margin-bottom:1rem; font-family:'Barlow Condensed',sans-serif; text-transform:uppercase; letter-spacing:.08em; color:var(--gold);">Link di invito</h2>
    <?php if (!$invitations): ?>
      <div class="alert alert-info">Non ci sono inviti attivi.</div>
    <?php else: ?>
      <ul style="list-style:none; display:grid; gap:.7rem;">
        <?php foreach ($invitations as $token): ?>
          <li>
            <div class="token-box"><?= htmlspecialchars(SITE_URL . '/iscrizione/giocatore.php?token=' . rawurlencode($token['token'])) ?></div>
          </li>
        <?php endforeach; ?>
      </ul>
    <?php endif; ?>
  </div>
<?php endif; ?>

<?php require_once __DIR__ . '/_footer.php'; ?>
