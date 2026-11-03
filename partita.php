<?php
// partita.php
// ============================================================
//  Dettaglio partita + tabellino (gol, cartellini, MVP)
// ============================================================

declare(strict_types=1);

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';

$pdo = db();

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$id) {
    header('Location: ' . SITE_URL . '/calendario.php');
    exit;
}

// Partita + squadre + fase
$stmt = $pdo->prepare("
    SELECT
        p.*,
        f.nome            AS fase_nome,
        sc.nome           AS nome_casa,
        sc.colore         AS colore_casa,
        sc.logo_url       AS logo_casa,
        so.nome           AS nome_osp,
        so.colore         AS colore_osp,
        so.logo_url       AS logo_osp
    FROM partite p
    JOIN fasi    f  ON f.id  = p.id_fase
    JOIN squadre sc ON sc.id = p.id_squadra_casa
    JOIN squadre so ON so.id = p.id_squadra_osp
    WHERE p.id = ?
    LIMIT 1
");
$stmt->execute([$id]);
$partita = $stmt->fetch();

if (!$partita) {
    header('Location: ' . SITE_URL . '/calendario.php');
    exit;
}

// Eventi della partita
$evStmt = $pdo->prepare("
    SELECT
        e.*,
        COALESCE(CONCAT(g.nome, ' ', g.cognome), e.nome_libero) AS giocatore_display,
        s.nome   AS nome_squadra,
        s.colore AS colore_squadra
    FROM eventi_partita e
    LEFT JOIN giocatori g ON g.id = e.id_giocatore
    JOIN      squadre   s ON s.id = e.id_squadra
    WHERE e.id_partita = ?
    ORDER BY e.minuto ASC, e.id ASC
");
$evStmt->execute([$id]);
$eventi = $evStmt->fetchAll();

// Dividi eventi per tipo
$gol_casa   = array_filter($eventi, fn($e) => in_array($e['tipo'], ['gol','gol_autorete']) && $e['id_squadra'] == $partita['id_squadra_casa']);
$gol_osp    = array_filter($eventi, fn($e) => in_array($e['tipo'], ['gol','gol_autorete']) && $e['id_squadra'] == $partita['id_squadra_osp']);
$amm        = array_filter($eventi, fn($e) => in_array($e['tipo'], ['ammonizione','doppia_ammonizione']));
$esp        = array_filter($eventi, fn($e) => $e['tipo'] === 'espulsione');
$mvp        = array_filter($eventi, fn($e) => $e['tipo'] === 'mvp');

$hasScore   = $partita['giocata'] && $partita['gol_casa'] !== null;
$dataFmt    = $partita['data_ora'] ? date('d/m/Y \a\l\l\e H:i', strtotime($partita['data_ora'])) : 'Data da definire';

// Determina vincitore
$winner = null;
if ($hasScore) {
    if ($partita['gol_casa'] > $partita['gol_osp'])     $winner = 'casa';
    elseif ($partita['gol_osp'] > $partita['gol_casa']) $winner = 'osp';
}
?>
<!DOCTYPE html>
<html lang="it">
<head>
  <link rel="icon" type="image/x-icon" href="<?= SITE_URL ?>/assets/brand/02_favicon/favicon.ico">
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= htmlspecialchars($partita['nome_casa']) ?> vs <?= htmlspecialchars($partita['nome_osp']) ?> — <?= htmlspecialchars(SITE_NAME) ?></title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Barlow+Condensed:ital,wght@0,400;0,600;0,700;0,800;1,700&family=Barlow:wght@400;500;600&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?= SITE_URL ?>/assets/css/style.css?v=public-menu-4">
  <link rel="stylesheet" href="<?= SITE_URL ?>/assets/css/public-redesign.css?v=1">
</head>
<body class="public-site public-match">

<?php include 'shared/header.php'; ?>

<!-- BREADCRUMB -->
<div class="container" style="padding-top:1.5rem;">
  <nav class="breadcrumb">
    <a href="<?= SITE_URL ?>/calendario.php">← Calendario</a>
    <span>/ <?= htmlspecialchars($partita['fase_nome']) ?></span>
  </nav>
</div>

<!-- HERO SCORE -->
<section class="match-hero">
  <div class="container">
    <div class="match-hero-badge">
      <?php
        $badgeClass = match(strtolower($partita['fase_nome'])) {
          'girone' => 'fase-girone', 'playoff' => 'fase-playoff',
          'semifinale' => 'fase-semifinal', default => 'fase-final',
        };
      ?>
      <span class="fase-badge <?= $badgeClass ?>"><?= htmlspecialchars($partita['fase_nome']) ?></span>
      <span class="match-hero-date"><?= $dataFmt ?></span>
      <?php if (!empty($partita['campo'])): ?>
        <span class="match-hero-campo">📍 <?= htmlspecialchars($partita['campo']) ?></span>
      <?php endif; ?>
    </div>

    <div class="match-scoreboard">
      <!-- Casa -->
      <div class="msb-team msb-team--home <?= $winner === 'casa' ? 'msb-team--winner' : '' ?>">
        <?php if (!empty($partita['logo_casa'])): ?>
          <img src="<?= htmlspecialchars($partita['logo_casa']) ?>" alt="" class="msb-logo">
        <?php else: ?>
          <div class="msb-dot" style="background:<?= htmlspecialchars($partita['colore_casa']) ?>"></div>
        <?php endif; ?>
        <span class="msb-name"><?= htmlspecialchars($partita['nome_casa']) ?></span>
        <?php if ($winner === 'casa'): ?>
          <span class="msb-winner-badge">VINCITORE</span>
        <?php endif; ?>
        <!-- Marcatori casa -->
        <ul class="msb-scorers">
          <?php foreach ($gol_casa as $g): ?>
            <li>
              <?= $g['tipo'] === 'gol_autorete' ? '<span class="own-goal">(Aut)</span>' : '⚽' ?>
              <?= htmlspecialchars($g['giocatore_display'] ?? '—') ?>
              <?php if ($g['minuto']): ?><small class="min"><?= $g['minuto'] ?>'</small><?php endif; ?>
            </li>
          <?php endforeach; ?>
        </ul>
      </div>

      <!-- Score -->
      <div class="msb-center">
        <?php if ($hasScore): ?>
          <div class="msb-score">
            <span><?= $partita['gol_casa'] ?></span>
            <span class="msb-dash">:</span>
            <span><?= $partita['gol_osp'] ?></span>
          </div>
          <?php if ($partita['shootout_casa'] !== null): ?>
            <div class="msb-shootout">
              Rigori: <?= $partita['shootout_casa'] ?>&ndash;<?= $partita['shootout_osp'] ?>
            </div>
          <?php endif; ?>
          <div class="msb-ft">Risultato Finale</div>
        <?php else: ?>
          <div class="msb-vs">VS</div>
          <div class="msb-ft"><?= $partita['giocata'] ? 'N/D' : 'In programma' ?></div>
        <?php endif; ?>
      </div>

      <!-- Ospite -->
      <div class="msb-team msb-team--away <?= $winner === 'osp' ? 'msb-team--winner' : '' ?>">
        <?php if (!empty($partita['logo_osp'])): ?>
          <img src="<?= htmlspecialchars($partita['logo_osp']) ?>" alt="" class="msb-logo">
        <?php else: ?>
          <div class="msb-dot" style="background:<?= htmlspecialchars($partita['colore_osp']) ?>"></div>
        <?php endif; ?>
        <span class="msb-name"><?= htmlspecialchars($partita['nome_osp']) ?></span>
        <?php if ($winner === 'osp'): ?>
          <span class="msb-winner-badge">VINCITORE</span>
        <?php endif; ?>
        <ul class="msb-scorers msb-scorers--away">
          <?php foreach ($gol_osp as $g): ?>
            <li>
              <?= $g['tipo'] === 'gol_autorete' ? '<span class="own-goal">(Aut)</span>' : '⚽' ?>
              <?= htmlspecialchars($g['giocatore_display'] ?? '—') ?>
              <?php if ($g['minuto']): ?><small class="min"><?= $g['minuto'] ?>'</small><?php endif; ?>
            </li>
          <?php endforeach; ?>
        </ul>
      </div>
    </div>
  </div>
</section>

<!-- TABELLINO DETTAGLIO -->
<?php if (!empty($eventi)): ?>
<section class="section" style="padding-top:2.5rem;">
  <div class="container">
    <div class="two-col" style="gap:1.5rem;">

      <!-- Cartellini -->
      <?php if (!empty($amm) || !empty($esp)): ?>
      <div class="tab-block">
        <h3 class="tab-block-title">🟨 Disciplinare</h3>
        <ul class="event-list">
          <?php foreach (array_merge(array_values($amm), array_values($esp)) as $e): ?>
          <li class="event-item">
            <span class="event-icon">
              <?= $e['tipo'] === 'espulsione' ? '🟥' : '🟨' ?>
            </span>
            <span class="event-body">
              <strong><?= htmlspecialchars($e['giocatore_display'] ?? '—') ?></strong>
              <small style="color:var(--muted)"><?= htmlspecialchars($e['nome_squadra']) ?></small>
            </span>
            <?php if ($e['minuto']): ?>
              <span class="event-min"><?= $e['minuto'] ?>'</span>
            <?php endif; ?>
          </li>
          <?php endforeach; ?>
        </ul>
      </div>
      <?php endif; ?>

      <!-- MVP -->
      <?php if (!empty($mvp)): ?>
      <div class="tab-block">
        <h3 class="tab-block-title">⭐ MVP della Partita</h3>
        <?php foreach ($mvp as $m): ?>
        <div class="mvp-card">
          <div class="mvp-star">⭐</div>
          <div>
            <strong class="mvp-name"><?= htmlspecialchars($m['giocatore_display'] ?? '—') ?></strong>
            <span class="mvp-team" style="color:<?= htmlspecialchars($m['colore_squadra']) ?>">
              <?= htmlspecialchars($m['nome_squadra']) ?>
            </span>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>

    </div>

    <!-- Note partita -->
    <?php if (!empty($partita['note'])): ?>
    <div class="match-note">
      <strong>📝 Note:</strong> <?= nl2br(htmlspecialchars($partita['note'])) ?>
    </div>
    <?php endif; ?>
  </div>
</section>
<?php elseif (!$partita['giocata']): ?>
<section class="section" style="padding-top:1.5rem;">
  <div class="container centered">
    <p class="empty-msg">Questa partita non è ancora stata disputata.</p>
    <a href="<?= SITE_URL ?>/calendario.php" class="btn btn-outline btn-sm" style="margin-top:1rem;">
      ← Torna al calendario
    </a>
  </div>
</section>
<?php endif; ?>

<?php include 'shared/footer.php'; ?>

</body>
</html>
