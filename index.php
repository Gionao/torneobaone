<?php
// index.php — Home Page pubblica
declare(strict_types=1);

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';

// Prossima partita in evidenza e successive (max 4)
$prossime = db()->query(
    "SELECT p.*, sc.nome AS casa, so.nome AS ospite
     FROM partite p
     JOIN squadre sc ON sc.id = p.id_squadra_casa
     JOIN squadre so ON so.id = p.id_squadra_osp
     WHERE p.giocata = 0 AND p.data_ora >= NOW()
     ORDER BY p.data_ora ASC LIMIT 4"
)->fetchAll();

// Ultimi risultati (max 3)
$ultimi = db()->query(
    "SELECT p.*, sc.nome AS casa, so.nome AS ospite
     FROM partite p
     JOIN squadre sc ON sc.id = p.id_squadra_casa
     JOIN squadre so ON so.id = p.id_squadra_osp
     WHERE p.giocata = 1
     ORDER BY p.data_ora DESC LIMIT 3"
)->fetchAll();

// Prossima partita con orario ancora futuro, se disponibile
$partitaInEvidenza = null;
$adesso = time();
foreach ($prossime as $partita) {
    if (strtotime($partita['data_ora']) >= $adesso) {
        $partitaInEvidenza = $partita;
        break;
    }
}
$altreProssime = array_values(array_filter($prossime, static function (array $partita) use ($partitaInEvidenza): bool {
    return !$partitaInEvidenza || (int)$partita['id'] !== (int)$partitaInEvidenza['id'];
}));

// Data fine torneo per countdown (modifica se necessario)
$data_torneo = '2026-07-05 21:00:00';
?>
<!DOCTYPE html>
<html lang="it">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="description" content="Torneo di Calcio a 5 - Baone 2026. Segui gironi, classifiche e risultati in tempo reale.">
  <title><?= SITE_NAME ?></title>

  <link rel="icon" type="image/png" href="assets/img/logo_torneo.png">
  <link rel="icon" type="image/x-icon" href="assets/img/logo_torneo.png">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Barlow+Condensed:ital,wght@0,400;0,600;0,700;0,800;1,700&family=Barlow:wght@300;400;500;600&display=swap" rel="stylesheet">

  <link rel="stylesheet" href="assets/css/style.css?v=public-menu-5">

</head>
<body>

<?php include __DIR__ . '/shared/header.php'; ?>

<!-- ═══════════════════════════════ HERO ════════════════════════════════ -->
<header class="hero">
  <div class="hero-bg"></div>
  <div class="hero-content">
    <img src="assets/img/logo_torneo.png" alt="Torneo Calcio a 5 Baone" class="hero-logo animate-drop">
    <h1 class="hero-title animate-fade">
      TORNEO<br><span class="gold">CALCIO A 5</span><br>BAONE
    </h1>
    <p class="hero-sub animate-fade delay-1">Campetto di Baone &bull; Estate 2026</p>

    <!-- COUNTDOWN -->
    <div class="countdown animate-fade delay-2" id="countdown"
         data-target="<?= $data_torneo ?>">
      <div class="cd-box"><span class="cd-num" id="cd-days">--</span><span class="cd-lbl">Giorni</span></div>
      <div class="cd-sep">:</div>
      <div class="cd-box"><span class="cd-num" id="cd-hours">--</span><span class="cd-lbl">Ore</span></div>
      <div class="cd-sep">:</div>
      <div class="cd-box"><span class="cd-num" id="cd-mins">--</span><span class="cd-lbl">Minuti</span></div>
      <div class="cd-sep">:</div>
      <div class="cd-box"><span class="cd-num" id="cd-secs">--</span><span class="cd-lbl">Secondi</span></div>
    </div>

    <div class="hero-actions animate-fade delay-3">
      <a href="classifica.php" class="btn btn-gold">Classifica</a>
      <a href="calendario.php" class="btn btn-outline">Calendario</a>
    </div>
  </div>
  <div class="hero-scroll-hint">
    <span>Scorri</span>
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M7 10l5 5 5-5"/></svg>
  </div>
</header>

<?php if ($partitaInEvidenza): ?>
<section class="featured-match-section" aria-labelledby="featured-match-title">
  <div class="container">
    <div class="featured-match-card" data-reveal="from-left">
      <div class="featured-match-heading">
        <span class="featured-match-kicker">La prossima sfida</span>
        <h2 id="featured-match-title">Prossima partita</h2>
      </div>
      <div class="featured-matchup">
        <span><?= htmlspecialchars($partitaInEvidenza['casa']) ?></span>
        <span class="featured-match-vs">VS</span>
        <span><?= htmlspecialchars($partitaInEvidenza['ospite']) ?></span>
      </div>
      <div class="featured-match-details">
        <span>📅 <?= date('d/m/Y · H:i', strtotime($partitaInEvidenza['data_ora'])) ?></span>
        <?php if (!empty($partitaInEvidenza['campo'])): ?>
          <span>📍 <?= htmlspecialchars($partitaInEvidenza['campo']) ?></span>
        <?php endif; ?>
      </div>
      <p class="featured-countdown" id="next-match-countdown"
         data-target="<?= htmlspecialchars(date(DATE_ATOM, strtotime($partitaInEvidenza['data_ora'])), ENT_QUOTES, 'UTF-8') ?>"
         aria-live="off">Calcolo del conto alla rovescia…</p>
      <a class="featured-match-link" href="calendario.php">Apri il calendario <span aria-hidden="true">→</span></a>
    </div>
  </div>
</section>
<?php endif; ?>

<!-- ═══════════════ PROSSIME PARTITE + ULTIMI RISULTATI ══════════════════ -->
<section class="section partite-strip">
  <div class="container two-col">

    <!-- Prossime -->
    <div class="col-block">
      <h2 class="section-title">⚽ Prossime Partite</h2>
      <?php if (empty($altreProssime)): ?>
        <p class="empty-msg">Nessun'altra partita in programma al momento.</p>
      <?php else: ?>
        <?php foreach ($altreProssime as $p): ?>
          <div class="match-card">
            <div class="match-meta">
              <?php if ($p['data_ora']): ?>
                <span class="match-date"><?= date('d/m H:i', strtotime($p['data_ora'])) ?></span>
              <?php endif; ?>
              <?php if ($p['campo']): ?>
                <span class="match-field">📍 <?= htmlspecialchars($p['campo']) ?></span>
              <?php endif; ?>
            </div>
            <div class="match-teams">
              <span class="team home"><?= htmlspecialchars($p['casa']) ?></span>
              <span class="match-vs">VS</span>
              <span class="team away"><?= htmlspecialchars($p['ospite']) ?></span>
            </div>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>

    <!-- Ultimi risultati -->
    <div class="col-block">
      <h2 class="section-title">🏆 Ultimi Risultati</h2>
      <?php if (empty($ultimi)): ?>
        <p class="empty-msg">Nessun risultato ancora disponibile.</p>
      <?php else: ?>
        <?php foreach ($ultimi as $p): ?>
          <a href="partita.php?id=<?= $p['id'] ?>" class="match-card match-card--result">
            <div class="match-teams">
              <span class="team home"><?= htmlspecialchars($p['casa']) ?></span>
              <span class="score-badge"><?= $p['gol_casa'] ?> – <?= $p['gol_osp'] ?></span>
              <span class="team away"><?= htmlspecialchars($p['ospite']) ?></span>
            </div>
            <div class="match-meta">
              <span class="match-date"><?= $p['data_ora'] ? date('d/m/Y', strtotime($p['data_ora'])) : '' ?></span>
              <span class="match-detail-link">Tabellino →</span>
            </div>
          </a>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>

  </div>
</section>

<!-- ═══════════════════════════ INFO TORNEO ══════════════════════════════ -->
<section class="section info-section">
  <div class="container">
    <h2 class="section-title centered">Il Torneo</h2>
    <div class="info-grid">
      <div class="info-card">
        <div class="info-icon">🏟️</div>
        <h3>10 Squadre</h3>
        <p>Un girone unico formato Champions League. Ogni squadra disputa 4 partite nel girone.</p>
      </div>
      <div class="info-card">
        <div class="info-icon">⏱️</div>
        <h3>20 Minuti</h3>
        <p>Partite da 2 tempi da 10 minuti, cambi infiniti, 5 giocatori per lato.</p>
      </div>
      <div class="info-card">
        <div class="info-icon">🥇</div>
        <h3>Playoff & Finali</h3>
        <p>Le prime 7 accedono ai playoff. Semifinali, finale 3°/4° e finale 1°/2°.</p>
      </div>
      <!--<div class="info-card">
        <div class="info-icon">🍔</div>
        <h3>2 Coupon</h3>
        <p>Ogni giocatore ha 2 coupon gratuiti per lo stand panini al Patronato San Lorenzo.</p>
      </div>
    </div>-->
  </div>
</section>

<!-- ════════════════════════ TRACCIA ORDINE ════════════════════════ 
<section class="section track-order-section">
  <div class="container centered">
    <h2 class="section-title">🍔 Traccia il tuo Ordine</h2>
    <p class="social-sub">
      Hai ordinato allo stand gastronomico? Controlla in tempo reale lo stato del tuo ordine.
    </p>

    <div class="track-order-actions">
      <a href="pos/stato-ordine.php" class="btn btn-gold btn-large">
        📋 Controlla Ordine
      </a>
    </div>
  </div>
</section>-->

<!-- ═══════════════════════════════ SOCIAL ═══════════════════════════════ -->
<section class="section social-section">
  <div class="container centered">
    <h2 class="section-title">Seguici sui Social</h2>
    <p class="social-sub">Aggiornamenti in tempo reale, foto e highlights del torneo</p>
    <div class="social-icons">
      <!-- Sostituisci gli href con i link reali -->
      <a href="https://www.instagram.com/torneodibaone" class="social-btn instagram" aria-label="Instagram">
        <svg viewBox="0 0 24 24" fill="currentColor">
          <path d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zM12 0C8.741 0 8.333.014 7.053.072 2.695.272.273 2.69.073 7.052.014 8.333 0 8.741 0 12c0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98C8.333 23.986 8.741 24 12 24c3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98C15.668.014 15.259 0 12 0zm0 5.838a6.162 6.162 0 100 12.324 6.162 6.162 0 000-12.324zM12 16a4 4 0 110-8 4 4 0 010 8zm6.406-11.845a1.44 1.44 0 100 2.881 1.44 1.44 0 000-2.881z"/>
        </svg>
        Instagram
      </a>
      <a href="https://www.facebook.com/share/17aeXbMUpy/?mibextid=wwXIfr" class="social-btn facebook" aria-label="Facebook">
        <svg viewBox="0 0 24 24" fill="currentColor">
          <path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/>
        </svg>
        Facebook
      </a>
    </div>
  </div>
</section>

<?php include __DIR__ . '/shared/footer.php' ?>
