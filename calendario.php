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
$totalePartite = count($partite);

// Filtri rapidi per stato, combinabili con il filtro fase
$faseRichiesta = $_GET['fase'] ?? 'tutte';
$filtro = is_string($faseRichiesta) ? $faseRichiesta : 'tutte';
$fasiDisponibili = array_values(array_unique(array_column($partite, 'fase_nome')));
$statoRichiesto = $_GET['stato'] ?? 'tutte';
$stato = is_string($statoRichiesto) && in_array($statoRichiesto, ['tutte', 'programmate', 'giocate'], true)
    ? $statoRichiesto
    : 'tutte';

if ($stato !== 'tutte') {
    $partite = array_values(array_filter($partite, static function (array $partita) use ($stato): bool {
        return $stato === 'giocate'
            ? (bool)$partita['giocata']
            : !(bool)$partita['giocata'];
    }));
}

// Raggruppa per fase
$per_fase = [];
foreach ($partite as $p) {
    $per_fase[$p['fase_nome']][] = $p;
}
$fasiMostrate = $filtro === 'tutte'
    ? $per_fase
    : (isset($per_fase[$filtro]) ? [$filtro => $per_fase[$filtro]] : []);

?>
<!DOCTYPE html>
<html lang="it">
<head>
  <link rel="icon" type="image/x-icon" href="<?= SITE_URL ?>/assets/brand/02_favicon/favicon.ico">
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Calendario — <?= htmlspecialchars(SITE_NAME) ?></title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Barlow+Condensed:ital,wght@0,400;0,600;0,700;0,800;1,700&family=Barlow:wght@400;500;600&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="assets/css/style.css?v=public-menu-5">
  <link rel="stylesheet" href="assets/css/public-redesign.css?v=1">
</head>
<body class="public-site public-calendar">

<?php include __DIR__ . '/shared/header.php'; ?>

<!-- HEADER -->
<section class="section" style="padding-top:3rem;padding-bottom:1rem;">
  <div class="container">
    <h1 class="section-title">Calendario partite</h1>

    <!-- Filtri fase -->
    <?php if ($totalePartite > 0): ?>
    <div class="fase-filter">
      <a href="?fase=tutte&amp;stato=<?= urlencode($stato) ?>" class="fase-filter-btn <?= $filtro === 'tutte' ? 'active' : '' ?>" <?= $filtro === 'tutte' ? 'aria-current="page"' : '' ?>>Tutte</a>
      <?php foreach ($fasiDisponibili as $faseNome): ?>
        <a href="?fase=<?= urlencode($faseNome) ?>&amp;stato=<?= urlencode($stato) ?>"
           class="fase-filter-btn <?= $filtro === $faseNome ? 'active' : '' ?>" <?= $filtro === $faseNome ? 'aria-current="page"' : '' ?>>
          <?= htmlspecialchars($faseNome) ?>
        </a>
      <?php endforeach; ?>
    </div>
    <nav class="status-filter" aria-label="Filtra le partite per stato">
      <?php foreach (['tutte' => 'Tutte le partite', 'programmate' => 'In programma', 'giocate' => 'Risultati'] as $statoKey => $statoLabel): ?>
        <a href="?fase=<?= urlencode($filtro) ?>&amp;stato=<?= urlencode($statoKey) ?>"
           class="fase-filter-btn <?= $stato === $statoKey ? 'active' : '' ?>" <?= $stato === $statoKey ? 'aria-current="page"' : '' ?>>
          <?= htmlspecialchars($statoLabel) ?>
        </a>
      <?php endforeach; ?>
    </nav>
    <?php endif; ?>
  </div>
</section>

<!-- PARTITE -->
<section class="section" style="padding-top:1rem;">
  <div class="container">

    <?php if ($totalePartite === 0): ?>
      <p class="empty-msg centered">Il calendario non è ancora stato pubblicato. Torna presto!</p>
    <?php elseif (empty($partite) || empty($fasiMostrate)): ?>
      <p class="empty-msg centered">Nessuna partita corrisponde ai filtri selezionati.</p>
    <?php else: ?>

    <?php foreach ($fasiMostrate as $faseNome => $matches):
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

</body>
</html>
