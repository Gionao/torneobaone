<?php
// calendario.php
// ============================================================
//  Calendario partite pubblico — Torneo Calcio a 5 Baone 2026
// ============================================================

declare(strict_types=1);

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db.php';

$pdo = db();
$isTelecronista = is_telecronista();

// Tutte le partite con dati squadre e fase, ordinate per data poi fase
$stmt = $pdo->query("
    SELECT
        p.*,
        f.nome            AS fase_nome,
        f.ordine          AS fase_ordine,
        sc.nome           AS nome_casa,
        sc.colore         AS colore_casa,
        sc.logo_url       AS logo_casa,
        sc.autorizzata    AS autorizzata_casa,
        so.nome           AS nome_osp,
        so.colore         AS colore_osp,
        so.logo_url       AS logo_osp,
        so.autorizzata    AS autorizzata_osp
    FROM partite p
    JOIN fasi    f  ON f.id  = p.id_fase
    JOIN squadre sc ON sc.id = p.id_squadra_casa
    JOIN squadre so ON so.id = p.id_squadra_osp
    ORDER BY f.ordine ASC, p.data_ora ASC, p.id ASC
");
$partite = $stmt->fetchAll();

// Raggruppa per fase
$per_fase = [];
foreach ($partite as $p) {
    $per_fase[$p['fase_nome']][] = $p;
}

// Filtro fase attivo (query string)
$filtro = $_GET['fase'] ?? 'tutte';
?>
<!DOCTYPE html>
<html lang="it">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Calendario — <?= htmlspecialchars(SITE_NAME) ?></title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Barlow+Condensed:ital,wght@0,400;0,600;0,700;0,800;1,700&family=Barlow:wght@400;500;600&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="assets/css/tokens.css?v=redesign-1">
  <link rel="stylesheet" href="assets/css/style.css?v=public-menu-4">
</head>
<body>

<?php include __DIR__ . '/shared/header.php'; ?>

<!-- HEADER -->
<section class="section" style="padding-top:3rem;padding-bottom:1rem;">
  <div class="container">
    <div class="section-title centered">📅 Calendario Partite</div>

    <!-- Filtri fase -->
    <?php if (!empty($per_fase)): ?>
    <div class="fase-filter">
      <a href="?fase=tutte" class="fase-filter-btn <?= $filtro === 'tutte' ? 'active' : '' ?>">Tutte</a>
      <?php foreach (array_keys($per_fase) as $faseNome): ?>
        <a href="?fase=<?= urlencode($faseNome) ?>"
           class="fase-filter-btn <?= $filtro === $faseNome ? 'active' : '' ?>">
          <?= htmlspecialchars($faseNome) ?>
        </a>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>
  </div>
</section>

<!-- PARTITE -->
<section class="section" style="padding-top:1rem;">
  <div class="container">

    <?php if (empty($partite)): ?>
      <p class="empty-msg centered">Il calendario non è ancora stato pubblicato. Torna presto!</p>
    <?php else: ?>

    <?php foreach ($per_fase as $faseNome => $matches):
      // Rispetta il filtro
      if ($filtro !== 'tutte' && $filtro !== $faseNome) continue;

      // Badge css class
      $badgeClass = match(strtolower($faseNome)) {
          'girone'      => 'fase-girone',
          'playoff'     => 'fase-playoff',
          'semifinale'  => 'fase-semifinal',
          default       => 'fase-final',
      };
    ?>
    <div class="cal-fase-block">
      <h2 class="cal-fase-title">
        <span class="fase-badge <?= $badgeClass ?>"><?= htmlspecialchars($faseNome) ?></span>
      </h2>

      <div class="cal-cards">
        <?php foreach ($matches as $m):
          $giocata  = (bool)$m['giocata'];
          $squadreNonAutorizzate = empty($m['autorizzata_casa']) || empty($m['autorizzata_osp']);
          $partitaDisabilitata = $isTelecronista && $squadreNonAutorizzate;
          $hasScore = $giocata && $m['gol_casa'] !== null;
          $dataFmt  = $m['data_ora'] ? date('d/m/Y H:i', strtotime($m['data_ora'])) : 'Da definire';
          $vincitore = null;
          if ($hasScore) {
              if ($m['gol_casa'] > $m['gol_osp'])      $vincitore = 'casa';
              elseif ($m['gol_osp'] > $m['gol_casa'])  $vincitore = 'osp';
          }
        ?>
        <?php if ($partitaDisabilitata): ?>
        <div class="cal-card cal-card--disabled" aria-disabled="true">
        <?php else: ?>
        <a href="<?= SITE_URL ?>/partita.php?id=<?= $m['id'] ?>" class="cal-card <?= $giocata ? 'cal-card--played' : '' ?>">
        <?php endif; ?>
          <div class="cal-card-meta">
            <span class="cal-date"><?= $dataFmt ?></span>
            <?php if (!empty($m['campo'])): ?>
              <span class="cal-campo">📍 <?= htmlspecialchars($m['campo']) ?></span>
            <?php endif; ?>
            <?php if ($partitaDisabilitata): ?>
              <span class="cal-status cal-status--disabled">Squadra non autorizzata</span>
            <?php elseif ($giocata): ?>
              <span class="cal-status cal-status--done">✓ Giocata</span>
            <?php else: ?>
              <span class="cal-status cal-status--pending">In programma</span>
            <?php endif; ?>
          </div>

          <div class="cal-matchup">
            <!-- Squadra Casa -->
            <div class="cal-team cal-team--home <?= $vincitore === 'casa' ? 'cal-team--winner' : '' ?>">
              <?php if (!empty($m['logo_casa'])): ?>
                <img src="<?= htmlspecialchars($m['logo_casa']) ?>" alt="" class="cal-team-logo">
              <?php else: ?>
                <span class="cal-team-dot" style="background:<?= htmlspecialchars($m['colore_casa']) ?>"></span>
              <?php endif; ?>
              <span class="cal-team-name"><?= htmlspecialchars($m['nome_casa']) ?></span>
            </div>

            <!-- Score -->
            <div class="cal-score">
              <?php if ($hasScore): ?>
                <span class="score-badge">
                  <?= $m['gol_casa'] ?> &ndash; <?= $m['gol_osp'] ?>
                </span>
                <?php if ($m['shootout_casa'] !== null): ?>
                  <span class="cal-shootout">
                    (<?= $m['shootout_casa'] ?>&ndash;<?= $m['shootout_osp'] ?> dcr)
                  </span>
                <?php endif; ?>
              <?php else: ?>
                <span class="cal-vs">VS</span>
              <?php endif; ?>
            </div>

            <!-- Squadra Ospite -->
            <div class="cal-team cal-team--away <?= $vincitore === 'osp' ? 'cal-team--winner' : '' ?>">
              <span class="cal-team-name"><?= htmlspecialchars($m['nome_osp']) ?></span>
              <?php if (!empty($m['logo_osp'])): ?>
                <img src="<?= htmlspecialchars($m['logo_osp']) ?>" alt="" class="cal-team-logo">
              <?php else: ?>
                <span class="cal-team-dot" style="background:<?= htmlspecialchars($m['colore_osp']) ?>"></span>
              <?php endif; ?>
            </div>
          </div>

          <?php if ($giocata): ?>
            <div class="cal-detail-hint">Vedi tabellino →</div>
          <?php endif; ?>
        <?php if ($partitaDisabilitata): ?></div><?php else: ?></a><?php endif; ?>
        <?php endforeach; ?>
      </div>
    </div>
    <?php endforeach; ?>

    <?php endif; ?>
  </div>
</section>

<!-- FOOTER -->
<?php include 'shared/footer.php'; ?>

<style>
/* ── Fase filter tabs ─────────────────── */
.fase-filter {
  display: flex;
  gap: .5rem;
  flex-wrap: wrap;
  justify-content: center;
  margin-top: 1rem;
  margin-bottom: .5rem;
}
.fase-filter-btn {
  padding: .35rem 1rem;
  border-radius: 20px;
  border: 1px solid rgba(231,169,50,.25);
  color: var(--muted);
  font-family: 'Barlow Condensed', sans-serif;
  font-size: .88rem;
  font-weight: 600;
  letter-spacing: .06em;
  text-transform: uppercase;
  transition: var(--transition);
}
.fase-filter-btn:hover, .fase-filter-btn.active {
  background: var(--gold);
  color: var(--navy);
  border-color: var(--gold);
}

/* ── Fase block ────────────────────────── */
.cal-fase-block { margin-bottom: 3rem; }
.cal-fase-title { margin-bottom: 1.2rem; }

/* ── Match cards ───────────────────────── */
.cal-cards { display: flex; flex-direction: column; gap: .7rem; }

.cal-card {
  display: block;
  background: rgba(255,255,255,.04);
  border: 1px solid rgba(231,169,50,.12);
  border-radius: var(--radius);
  padding: 1rem 1.2rem;
  transition: var(--transition);
  color: var(--white);
  text-decoration: none;
}
.cal-card:hover {
  border-color: rgba(231,169,50,.4);
  background: rgba(231,169,50,.05);
  transform: translateY(-1px);
  color: var(--white);
}
.cal-card--played { border-left: 3px solid var(--gold); }

.cal-card-meta {
  display: flex;
  gap: .8rem;
  font-size: .73rem;
  color: var(--muted);
  margin-bottom: .6rem;
  flex-wrap: wrap;
  align-items: center;
  text-transform: uppercase;
  letter-spacing: .05em;
}
.cal-date { font-weight: 600; }
.cal-status { border-radius: 4px; padding: .1rem .4rem; font-size: .7rem; }
.cal-status--done    { background: rgba(76,175,125,.15); color: #6ddfab; }
.cal-status--pending { background: rgba(231,169,50,.1);  color: var(--gold-dim); }

.cal-matchup {
  display: grid;
  grid-template-columns: 1fr auto 1fr;
  align-items: center;
  gap: 1rem;
}

.cal-team { display: flex; align-items: center; gap: .5rem; }
.cal-team--home { justify-content: flex-end; text-align: right; }
.cal-team--away { justify-content: flex-start; text-align: left; }
.cal-team--winner .cal-team-name { color: var(--gold); font-weight: 700; }

.cal-team-name {
  font-family: 'Barlow Condensed', sans-serif;
  font-size: 1.05rem;
  font-weight: 600;
}
.cal-team-dot {
  display: inline-block;
  width: 10px; height: 10px;
  border-radius: 50%;
  flex-shrink: 0;
}
.cal-team-logo { width: 24px; height: 24px; object-fit: contain; }

.cal-score { text-align: center; }
.cal-vs {
  font-family: 'Barlow Condensed', sans-serif;
  font-size: .8rem;
  font-weight: 700;
  color: var(--gold-dim);
  letter-spacing: .1em;
}
.cal-shootout { display: block; font-size: .72rem; color: var(--muted); margin-top: .2rem; }

.cal-detail-hint {
  margin-top: .5rem;
  font-size: .75rem;
  color: var(--gold-dim);
  text-align: right;
}
</style>

</body>
</html>
