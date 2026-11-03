<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';

$pdo = db();
$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
if (!$id) {
    header('Location: ' . SITE_URL . '/squadre');
    exit;
}

$requestPath = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);
if (is_string($requestPath) && basename($requestPath) === 'squadra.php') {
    header('Location: ' . SITE_URL . '/squadra/' . $id, true, 301);
    exit;
}

$stmt = $pdo->prepare('SELECT s.* FROM squadre s WHERE s.id = ? LIMIT 1');
$stmt->execute([$id]);
$squadra = $stmt->fetch();

if (!$squadra) {
    http_response_code(404);
}

$giocatori = [];
$partite = [];
$marcatori = [];
$posizione = null;
$statistiche = null;

if ($squadra) {
    $playersStmt = $pdo->prepare(<<<'SQL'
        SELECT id, nome, cognome, numero_maglia, ruolo
        FROM giocatori
        WHERE id_squadra = ? AND stato_iscrizione <> 'annullato'
        ORDER BY CASE ruolo WHEN 'Capitano' THEN 0 WHEN 'Portiere' THEN 1 ELSE 2 END,
                 numero_maglia IS NULL, numero_maglia, cognome, nome
    SQL);
    $playersStmt->execute([$id]);
    $giocatori = $playersStmt->fetchAll();

    $matchesStmt = $pdo->prepare(<<<'SQL'
        SELECT p.*, f.nome AS fase_nome,
               sc.nome AS nome_casa, sc.logo_url AS logo_casa,
               so.nome AS nome_osp, so.logo_url AS logo_osp
        FROM partite p
        JOIN fasi f ON f.id = p.id_fase
        JOIN squadre sc ON sc.id = p.id_squadra_casa
        JOIN squadre so ON so.id = p.id_squadra_osp
        WHERE p.id_squadra_casa = ? OR p.id_squadra_osp = ?
        ORDER BY p.data_ora IS NULL, p.data_ora ASC, p.id ASC
    SQL);
    $matchesStmt->execute([$id, $id]);
    $partite = $matchesStmt->fetchAll();

    $scorersStmt = $pdo->prepare(<<<'SQL'
        SELECT COALESCE(CONCAT(g.nome, ' ', g.cognome), e.nome_libero) AS giocatore,
               COUNT(*) AS gol
        FROM eventi_partita e
        JOIN partite p ON p.id = e.id_partita
        LEFT JOIN giocatori g ON g.id = e.id_giocatore
        WHERE e.id_squadra = ? AND e.tipo = 'gol' AND p.giocata = 1
        GROUP BY e.id_giocatore, e.nome_libero
        ORDER BY gol DESC, giocatore ASC
        LIMIT 5
    SQL);
    $scorersStmt->execute([$id]);
    $marcatori = $scorersStmt->fetchAll();

    $classifica = $pdo->query(<<<'SQL'
        SELECT c.id_squadra, c.punti, c.giocate, c.vinte, c.pareggiate, c.perse,
               c.gol_fatti, c.gol_subiti, c.diff_reti
        FROM classifica c
        JOIN squadre s ON s.id = c.id_squadra
        ORDER BY c.punti DESC, c.diff_reti DESC, c.gol_fatti DESC,
                 c.gol_subiti ASC, c.ammonizioni ASC, c.espulsioni ASC, s.nome ASC
    SQL)->fetchAll();

    foreach ($classifica as $i => $riga) {
        if ((int)$riga['id_squadra'] === (int)$id) {
            $posizione = $i + 1;
            $statistiche = $riga;
            break;
        }
    }
}

$coloreSquadra = $squadra && preg_match('/^#[0-9a-fA-F]{6}$/', (string)$squadra['colore'])
    ? $squadra['colore']
    : '#c9a84c';
$inizialeSquadra = '?';
if ($squadra && preg_match('/^./u', $squadra['nome'], $matchIniziale)) {
    $inizialeSquadra = $matchIniziale[0];
}
$logoSquadra = trim((string)($squadra['logo_url'] ?? ''));
if ($logoSquadra !== '' && !preg_match('~^(?:https?:)?//|^/~i', $logoSquadra)) {
    $logoSquadra = SITE_URL . '/' . ltrim($logoSquadra, '/');
}
?>
<!DOCTYPE html>
<html lang="it">
<head>
  <link rel="icon" type="image/x-icon" href="<?= SITE_URL ?>/assets/brand/02_favicon/favicon.ico">
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  /assets/brand/01_loghi/png/logo_principale.png">
  <link rel="apple-touch-icon" sizes="180x180" href="<?= SITE_URL ?>/assets/brand/02_favicon/favicon-180.png">
  <title><?= $squadra ? htmlspecialchars($squadra['nome']) . ' — ' : '' ?>Squadre — <?= htmlspecialchars(SITE_NAME) ?></title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Barlow+Condensed:ital,wght@0,400;0,600;0,700;0,800;1,700&family=Barlow:wght@400;500;600&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?= SITE_URL ?>/assets/css/style.css?v=public-squadre-2">
  <link rel="stylesheet" href="<?= SITE_URL ?>/assets/css/public-redesign.css?v=1">
</head>
<body class="public-site public-team-profile">
<?php include __DIR__ . '/shared/header.php'; ?>

<?php if (!$squadra): ?>
<main class="section">
  <div class="container centered">
    <h1 class="section-title">Squadra non trovata</h1>
    <p class="empty-msg">La squadra richiesta non è disponibile.</p>
    <a class="btn btn-gold" href="<?= SITE_URL ?>/squadre">Tutte le squadre</a>
  </div>
</main>
<?php else: ?>
<main class="team-page" style="--team-color: <?= htmlspecialchars($coloreSquadra) ?>">
  <div class="container team-page-container">
    <nav class="breadcrumb" aria-label="Percorso pagina">
      <a href="<?= SITE_URL ?>/squadre">Squadre</a><span>/ <?= htmlspecialchars($squadra['nome']) ?></span>
    </nav>

    <header class="team-profile-hero">
      <div class="team-profile-mark">
        <?php if ($logoSquadra !== ''): ?>
          <img src="<?= htmlspecialchars($logoSquadra) ?>" alt="Stemma <?= htmlspecialchars($squadra['nome']) ?>">
        <?php else: ?>
          <span aria-hidden="true"><?= htmlspecialchars($inizialeSquadra) ?></span>
        <?php endif; ?>
      </div>
      <div class="team-profile-copy">
        <p class="team-profile-kicker">Scheda squadra</p>
        <h1><?= htmlspecialchars($squadra['nome']) ?></h1>
        <p>Rosa, classifica e percorso nel torneo</p>
      </div>
      <div class="team-rank-badge">
        <span><?= $posizione !== null ? '#' . $posizione : '—' ?></span>
        <small><?= $posizione !== null ? 'in classifica' : 'classifica in aggiornamento' ?></small>
      </div>
    </header>

    <section class="team-stat-grid" aria-label="Statistiche squadra">
      <div class="team-stat"><span class="team-stat-value"><?= (int)($statistiche['punti'] ?? 0) ?></span><span>Punti</span></div>
      <div class="team-stat"><span class="team-stat-value"><?= (int)($statistiche['giocate'] ?? 0) ?></span><span>Giocate</span></div>
      <div class="team-stat"><span class="team-stat-value"><?= (int)($statistiche['vinte'] ?? 0) ?></span><span>Vinte</span></div>
      <div class="team-stat"><span class="team-stat-value"><?= (int)($statistiche['pareggiate'] ?? 0) ?></span><span>Pareggiate</span></div>
      <div class="team-stat"><span class="team-stat-value"><?= (int)($statistiche['perse'] ?? 0) ?></span><span>Perse</span></div>
      <div class="team-stat"><span class="team-stat-value"><?= (int)($statistiche['diff_reti'] ?? 0) > 0 ? '+' : '' ?><?= (int)($statistiche['diff_reti'] ?? 0) ?></span><span>Differenza reti</span></div>
    </section>

    <div class="team-content-grid">
      <section class="team-panel" aria-labelledby="team-roster-title">
        <div class="team-panel-heading">
          <div><p class="team-panel-kicker">La squadra</p><h2 id="team-roster-title">Rosa <span><?= count($giocatori) ?></span></h2></div>
        </div>
        <?php if (!$giocatori): ?>
          <p class="empty-msg">La rosa non è ancora disponibile.</p>
        <?php else: ?>
          <ul class="team-roster">
            <?php foreach ($giocatori as $giocatore): ?>
              <li class="team-player">
                <span class="team-player-number"><?= $giocatore['numero_maglia'] !== null ? (int)$giocatore['numero_maglia'] : '—' ?></span>
                <span class="team-player-name"><?= htmlspecialchars($giocatore['nome'] . ' ' . $giocatore['cognome']) ?></span>
                <span class="team-player-role"><?= htmlspecialchars($giocatore['ruolo']) ?></span>
              </li>
            <?php endforeach; ?>
          </ul>
        <?php endif; ?>
      </section>

      <section class="team-panel" aria-labelledby="team-scorers-title">
        <div class="team-panel-heading"><div><p class="team-panel-kicker">Rendimento</p><h2 id="team-scorers-title">Marcatori</h2></div></div>
        <?php if (!$marcatori): ?>
          <p class="empty-msg">I marcatori compariranno dopo le prime partite.</p>
        <?php else: ?>
          <ol class="team-scorers">
            <?php foreach ($marcatori as $i => $marcatore): ?>
              <li><span class="team-scorer-rank"><?= $i + 1 ?></span><span><?= htmlspecialchars($marcatore['giocatore'] ?: 'Giocatore') ?></span><strong><?= (int)$marcatore['gol'] ?> gol</strong></li>
            <?php endforeach; ?>
          </ol>
        <?php endif; ?>
      </section>
    </div>

    <section class="team-panel team-fixtures-panel" aria-labelledby="team-fixtures-title">
      <div class="team-panel-heading">
        <div><p class="team-panel-kicker">Il percorso</p><h2 id="team-fixtures-title">Partite</h2></div>
        <a href="<?= SITE_URL ?>/calendario.php">Calendario completo <span aria-hidden="true">→</span></a>
      </div>
      <?php if (!$partite): ?>
        <p class="empty-msg">Non ci sono ancora partite associate a questa squadra.</p>
      <?php else: ?>
        <div class="team-match-list">
          <?php foreach ($partite as $partita):
            $squadraCasa = (int)$partita['id_squadra_casa'] === (int)$id;
            $avversariaId = $squadraCasa ? (int)$partita['id_squadra_osp'] : (int)$partita['id_squadra_casa'];
            $avversaria = $squadraCasa ? $partita['nome_osp'] : $partita['nome_casa'];
            $giocata = (bool)$partita['giocata'] && $partita['gol_casa'] !== null;
          ?>
            <article class="team-match-row">
              <div class="team-match-meta"><span><?= htmlspecialchars($partita['fase_nome']) ?></span><span><?= $partita['data_ora'] ? date('d/m/Y · H:i', strtotime($partita['data_ora'])) : 'Data da definire' ?></span></div>
              <div class="team-match-main">
                <span class="team-match-opponent"><a href="<?= SITE_URL ?>/squadra/<?= $avversariaId ?>"><?= htmlspecialchars($avversaria) ?></a><small><?= $squadraCasa ? 'In casa' : 'In trasferta' ?></small></span>
                <strong class="team-match-score <?= $giocata ? 'is-played' : '' ?>"><?= $giocata ? (int)$partita['gol_casa'] . ' – ' . (int)$partita['gol_osp'] : 'VS' ?></strong>
                <a class="team-match-link" href="<?= SITE_URL ?>/partita.php?id=<?= (int)$partita['id'] ?>"><?= $giocata ? 'Tabellino' : 'Dettagli' ?> <span aria-hidden="true">→</span></a>
              </div>
            </article>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </section>
  </div>
</main>
<?php endif; ?>

<?php include __DIR__ . '/shared/footer.php'; ?>
