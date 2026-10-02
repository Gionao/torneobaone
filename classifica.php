<?php
// classifica.php
// ============================================================
//  Classifica pubblica — Torneo Calcio a 5 Baone 2026
// ============================================================

declare(strict_types=1);

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';

// Fetch classifica completa dalla view
$pdo  = db();

// Query diretta (senza VIEW — Altervista non supporta CREATE VIEW)
try {
    $rows = $pdo->query("
        SELECT c.*, s.nome AS nome_squadra, s.colore AS colore_squadra, s.logo_url
        FROM classifica c
        JOIN squadre s ON s.id = c.id_squadra
        ORDER BY c.punti DESC, c.diff_reti DESC, c.gol_fatti DESC,
                 c.gol_subiti ASC, c.ammonizioni ASC, c.espulsioni ASC, s.nome ASC
    ")->fetchAll();
} catch (Throwable $e) {
    if (DEBUG_MODE) error_log('Errore classifica: ' . $e->getMessage());
    $rows = [];
}

// Capocannonieri top 10 — query diretta
try {
    $cannonieri = $pdo->query("
        SELECT COALESCE(CONCAT(g.nome, ' ', g.cognome), e.nome_libero) AS giocatore,
               s.nome AS squadra, s.colore AS colore_squadra, COUNT(*) AS gol
        FROM eventi_partita e
        LEFT JOIN giocatori g ON g.id = e.id_giocatore
        JOIN  squadre       s ON s.id = e.id_squadra
        WHERE e.tipo = 'gol'
        GROUP BY e.id_giocatore, e.nome_libero, e.id_squadra
        ORDER BY gol DESC
        LIMIT 10
    ")->fetchAll();
} catch (Throwable $e) {
    if (DEBUG_MODE) error_log('Errore capocannonieri: ' . $e->getMessage());
    $cannonieri = [];
}

// Messaggio mostrato quando non ci sono ancora dati sufficienti
$msgClassificaVuota = [
    'Partite non ancora giocate',
    'Non ci sono abbastanza dati per stilare una classifica',
    'Classifica in arrivo',
][array_rand([0, 1, 2])];
?>
<!DOCTYPE html>
<html lang="it">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Classifica — <?= htmlspecialchars(SITE_NAME) ?></title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Barlow+Condensed:ital,wght@0,400;0,600;0,700;0,800;1,700&family=Barlow:wght@400;500;600&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="assets/css/style.css?v=public-menu-2">
</head>
<body>

<?php include 'shared/header.php'; ?>

<!-- CLASSIFICA HERO -->
<section class="section" style="padding-top:3rem;padding-bottom:1.5rem;">
  <div class="container">
    <div class="section-title centered">🏆 Classifica Girone</div>
    <p class="centered empty-msg" style="margin-bottom:2.5rem;">
      Aggiornata in tempo reale dopo ogni partita
    </p>

    <?php if (empty($rows)): ?>
      <p class="empty-msg centered"><?= htmlspecialchars($msgClassificaVuota) ?></p>
    <?php else: ?>

    <div class="standings-wrap">
      <div class="standings-table-outer">
        <table class="standings-table">
          <thead>
            <tr>
              <th>#</th>
              <th style="text-align:left;">Squadra</th>
              <th title="Partite Giocate">PG</th>
              <th title="Vinte">V</th>
              <th title="Pareggiate">P</th>
              <th title="Perse">S</th>
              <th title="Gol Fatti">GF</th>
              <th title="Gol Subiti">GS</th>
              <th title="Differenza Reti">DR</th>
              <th title="Punti" class="pts-col">PTS</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($rows as $i => $r):
              $pos = $i + 1;
              $cls = match(true) {
                $pos <= 2  => 'standing-direct',
                $pos <= 4  => 'standing-playoff',
                default    => 'standing-out',
              };
              $dr = ($r['diff_reti'] > 0 ? '+' : '') . $r['diff_reti'];
            ?>
            <tr class="<?= $cls ?>">
              <td class="pos-num"><?= $pos ?></td>
              <td>
                <div class="team-name-cell">
                  <?php if (!empty($r['logo_url'])): ?>
                    <img src="<?= htmlspecialchars($r['logo_url']) ?>" alt="" class="team-mini-logo">
                  <?php else: ?>
                    <span class="team-color-dot" style="background:<?= htmlspecialchars($r['colore_squadra']) ?>;"></span>
                  <?php endif; ?>
                  <strong><?= htmlspecialchars($r['nome_squadra']) ?></strong>
                </div>
              </td>
              <td><?= $r['giocate'] ?></td>
              <td><?= $r['vinte'] ?></td>
              <td><?= $r['pareggiate'] ?></td>
              <td><?= $r['perse'] ?></td>
              <td><?= $r['gol_fatti'] ?></td>
              <td><?= $r['gol_subiti'] ?></td>
              <td><?= $dr ?></td>
              <td class="pts"><?= $r['punti'] ?></td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>

      <div class="table-legend">
        <span class="legend-item"><span class="legend-dot gold"></span> Passano direttamente</span>
        <span class="legend-item"><span class="legend-dot blue"></span> Playoff</span>
      </div>
    </div>

    <?php endif; ?>
  </div>
</section>

<!-- CAPOCANNONIERI -->
<?php if (!empty($cannonieri)): ?>
<section class="section partite-strip" style="padding-top:2.5rem;">
  <div class="container">
    <div class="section-title">⚽ Capocannonieri</div>
    <div class="standings-table-outer">
      <table class="standings-table">
        <thead>
          <tr>
            <th>#</th>
            <th style="text-align:left;">Giocatore</th>
            <th style="text-align:left;">Squadra</th>
            <th title="Gol">GOL</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($cannonieri as $i => $c): ?>
          <tr>
            <td class="pos-num"><?= $i + 1 ?></td>
            <td><strong><?= htmlspecialchars($c['giocatore']) ?></strong></td>
            <td>
              <span class="team-color-dot" style="background:<?= htmlspecialchars($c['colore_squadra']) ?>;"></span>
              <?= htmlspecialchars($c['squadra']) ?>
            </td>
            <td class="pts"><?= $c['gol'] ?></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</section>
<?php endif; ?>

<?php include 'shared/footer.php'; ?>

<style>
.standings-wrap { margin-bottom: 1rem; }
.standings-table-outer { overflow-x: auto; border-radius: var(--radius); border: 1px solid rgba(201,168,76,.12); }
.pts-col { font-size: .95rem !important; }
.team-name-cell { display: flex; align-items: center; gap: .5rem; }
.team-color-dot { display:inline-block; width:10px; height:10px; border-radius:50%; flex-shrink:0; }
.team-mini-logo { width:20px; height:20px; object-fit:contain; border-radius:3px; }
</style>

</body>
</html>
