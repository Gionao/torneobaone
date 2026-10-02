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
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= htmlspecialchars($partita['nome_casa']) ?> vs <?= htmlspecialchars($partita['nome_osp']) ?> — <?= htmlspecialchars(SITE_NAME) ?></title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Barlow+Condensed:ital,wght@0,400;0,600;0,700;0,800;1,700&family=Barlow:wght@400;500;600&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?= SITE_URL ?>/assets/css/style.css?v=public-menu-2">
</head>
<body>

<!-- NAVBAR -->
<nav class="navbar">
  <div class="nav-container">
    <a href="<?= SITE_URL ?>" class="nav-brand">
      <img src="<?= SITE_URL ?>/assets/img/logo_torneo.png" alt="Logo" class="nav-logo">
      <span>Torneo <em>Baone</em> 2026</span>
    </a>
    <input type="checkbox" class="nav-menu-check" id="matchNavMenuToggle" aria-label="Apri o chiudi il menu" aria-controls="matchNavLinks">
    <label class="nav-toggle" for="matchNavMenuToggle" aria-hidden="true">
      <span></span><span></span><span></span>
    </label>
    <ul class="nav-links" id="matchNavLinks">
      <li><a href="<?= SITE_URL ?>">Home</a></li>
      <li><a href="<?= SITE_URL ?>/calendario.php" class="active">Calendario</a></li>
      <li><a href="<?= SITE_URL ?>/classifica.php">Classifica</a></li>
      <li><a href="<?= SITE_URL ?>/iscrizione.php">Iscrizione</a></li>
    </ul>
  </div>
</nav>

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

<style>
/* Breadcrumb */
.breadcrumb { font-size:.82rem; color:var(--muted); margin-bottom:.5rem; }
.breadcrumb a { color:var(--muted); } .breadcrumb a:hover { color:var(--gold); }

/* Match hero */
.match-hero { padding: 2rem 0 3rem; background: var(--navy-mid); border-bottom: 1px solid rgba(201,168,76,.1); }
.match-hero-badge { display:flex; gap:.7rem; align-items:center; flex-wrap:wrap; margin-bottom:1.5rem; font-size:.8rem; color:var(--muted); }

/* Scoreboard */
.match-scoreboard {
  display: grid;
  grid-template-columns: 1fr auto 1fr;
  gap: 1.5rem;
  align-items: start;
}
.msb-team { display:flex; flex-direction:column; align-items:center; gap:.5rem; }
.msb-team--home { align-items:flex-end; text-align:right; }
.msb-team--away { align-items:flex-start; text-align:left; }
.msb-team--winner .msb-name { color:var(--gold); }
.msb-name { font-family:'Barlow Condensed',sans-serif; font-size:clamp(1.1rem,3vw,1.8rem); font-weight:700; }
.msb-logo { width:60px; height:60px; object-fit:contain; }
.msb-dot  { width:50px; height:50px; border-radius:50%; }
.msb-winner-badge { font-family:'Barlow Condensed',sans-serif; font-size:.7rem; letter-spacing:.1em; background:rgba(201,168,76,.15); color:var(--gold); border-radius:4px; padding:.1rem .5rem; }

.msb-scorers { list-style:none; font-size:.82rem; color:var(--muted); margin-top:.3rem; }
.msb-scorers li { display:flex; align-items:center; gap:.3rem; justify-content:flex-end; }
.msb-scorers--away li { justify-content:flex-start; }
.min { opacity:.6; }
.own-goal { color:var(--red); font-size:.72rem; }

.msb-center { text-align:center; padding-top:.5rem; }
.msb-score { font-family:'Barlow Condensed',sans-serif; font-size:clamp(2.5rem,8vw,5rem); font-weight:800; color:var(--gold); line-height:1; display:flex; align-items:center; gap:.3rem; }
.msb-dash { color:var(--gold-dim); }
.msb-vs   { font-family:'Barlow Condensed',sans-serif; font-size:2rem; font-weight:700; color:var(--gold-dim); }
.msb-ft   { font-size:.72rem; text-transform:uppercase; letter-spacing:.1em; color:var(--muted); margin-top:.3rem; }
.msb-shootout { font-size:.8rem; color:var(--muted); margin-top:.2rem; }

/* Events */
.tab-block { margin-bottom:2rem; }
.tab-block-title { font-family:'Barlow Condensed',sans-serif; font-size:1.15rem; font-weight:700; color:var(--white); text-transform:uppercase; letter-spacing:.06em; margin-bottom:1rem; }

.event-list { list-style:none; display:flex; flex-direction:column; gap:.5rem; }
.event-item { display:flex; align-items:center; gap:.7rem; background:rgba(255,255,255,.04); border-radius:8px; padding:.6rem .9rem; }
.event-icon { font-size:1.1rem; }
.event-body { flex:1; display:flex; flex-direction:column; gap:.1rem; font-size:.88rem; }
.event-min  { font-family:'Barlow Condensed',sans-serif; font-weight:700; color:var(--muted); font-size:.85rem; white-space:nowrap; }

/* MVP */
.mvp-card { display:flex; align-items:center; gap:1rem; background:rgba(201,168,76,.08); border:1px solid rgba(201,168,76,.2); border-radius:10px; padding:1rem 1.2rem; }
.mvp-star { font-size:2rem; }
.mvp-name { font-family:'Barlow Condensed',sans-serif; font-size:1.3rem; font-weight:700; color:var(--gold); display:block; }
.mvp-team { font-size:.82rem; }

/* Note */
.match-note { margin-top:2rem; background:rgba(76,143,201,.06); border:1px solid rgba(76,143,201,.2); border-radius:8px; padding:1rem 1.2rem; font-size:.88rem; color:var(--muted); }

@media(max-width:600px) {
  .match-scoreboard { grid-template-columns:1fr; }
  .msb-team--home, .msb-team--away { align-items:center; text-align:center; }
  .msb-scorers li, .msb-scorers--away li { justify-content:center; }
  .msb-center { order:-1; }
}
</style>

</body>
</html>
