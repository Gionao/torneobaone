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
$rows = $pdo->query('SELECT * FROM v_classifica')->fetchAll();

// Fetch capocannonieri (top 10)
$cannonieri = $pdo->query(
    'SELECT * FROM v_capocannonieri LIMIT 10'
)->fetchAll();
?>
<!DOCTYPE html>
<html lang="it">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Classifica — <?= htmlspecialchars(SITE_NAME) ?></title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Barlow+Condensed:ital,wght@0,400;0,600;0,700;0,800;1,700&family=Barlow:wght@400;500;600&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>

<!-- NAVBAR -->
<nav class="navbar">
  <div class="nav-container">
    <a href="<?= SITE_URL ?>" class="nav-brand">
      <img src="<?= SITE_URL ?>/assets/img/logo_torneo.png" alt="Logo" class="nav-logo">
      <span>Torneo <em>Baone</em> 2026</span>
    </a>
    <button class="nav-toggle" id="navToggle" aria-label="Apri menu">
      <span></span><span></span><span></span>
    </button>
    <ul class="nav-links" id="navLinks">
      <li><a href="<?= SITE_URL ?>">Home</a></li>
      <li><a href="<?= SITE_URL ?>/calendario.php">Calendario</a></li>
      <li><a href="<?= SITE_URL ?>/classifica.php" class="active">Classifica</a></li>
      <li><a href="<?= SITE_URL ?>/iscrizione.php">Iscrizione</a></li>
    </ul>
  </div>
</nav>

<!-- CLASSIFICA HERO -->
<section class="section" style="padding-top:3rem;padding-bottom:1.5rem;">
  <div class="container">
    <div class="section-title centered">🏆 Classifica Girone</div>
    <p class="centered empty-msg" style="margin-bottom:2.5rem;">
      Aggiornata in tempo reale dopo ogni partita
    </p>

    <?php if (empty($rows)): ?>
      <p class="empty-msg centered">Nessuna squadra in classifica ancora. Torna dopo la prima giornata!</p>
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

<script>
// Mobile nav toggle
const toggle = document.getElementById('navToggle');
const links  = document.getElementById('navLinks');
toggle?.addEventListener('click', () => links.classList.toggle('open'));
</script>

</body>
</html>