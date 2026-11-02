<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';

$requestPath = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);
if (is_string($requestPath) && basename($requestPath) === 'squadre.php') {
    header('Location: ' . SITE_URL . '/squadre', true, 301);
    exit;
}

$squadre = db()->query(<<<'SQL'
    SELECT s.id, s.nome, s.colore, s.logo_url,
           (SELECT COUNT(*) FROM giocatori g WHERE g.id_squadra = s.id AND g.stato_iscrizione <> 'annullato') AS numero_giocatori,
           c.punti, c.giocate, c.vinte, c.pareggiate, c.perse, c.diff_reti,
           c.gol_fatti, c.gol_subiti, c.ammonizioni, c.espulsioni
    FROM squadre s
    LEFT JOIN classifica c ON c.id_squadra = s.id
    ORDER BY c.punti IS NULL, c.punti DESC, c.diff_reti DESC, c.gol_fatti DESC,
             c.gol_subiti ASC, c.ammonizioni ASC, c.espulsioni ASC, s.nome ASC
SQL)->fetchAll();

$posizione = 0;
foreach ($squadre as &$squadra) {
    $squadra['posizione'] = $squadra['punti'] !== null ? ++$posizione : null;
    $squadra['colore'] = preg_match('/^#[0-9a-fA-F]{6}$/', (string)$squadra['colore'])
        ? $squadra['colore']
        : '#c9a84c';
}
unset($squadra);
?>
<!DOCTYPE html>
<html lang="it">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Squadre — <?= htmlspecialchars(SITE_NAME) ?></title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Barlow+Condensed:ital,wght@0,400;0,600;0,700;0,800;1,700&family=Barlow:wght@400;500;600&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?= SITE_URL ?>/assets/css/style.css?v=public-squadre-1">
</head>
<body>
<?php include __DIR__ . '/shared/header.php'; ?>
<main class="team-directory-page">
  <div class="container">
    <header class="team-directory-heading">
      <p class="team-profile-kicker">Torneo Baone</p>
      <h1 class="section-title">Le squadre</h1>
      <p class="empty-msg">Apri una scheda per scoprire rosa, classifica e partite.</p>
    </header>

    <?php if (!$squadre): ?>
      <p class="empty-msg centered">Le squadre non sono ancora state pubblicate.</p>
    <?php else: ?>
      <div class="team-directory-grid">
        <?php foreach ($squadre as $squadra): ?>
          <a class="team-directory-card" href="<?= SITE_URL ?>/squadra/<?= (int)$squadra['id'] ?>" style="--team-color: <?= htmlspecialchars($squadra['colore']) ?>">
            <div class="team-directory-mark">
              <?php if (!empty($squadra['logo_url'])): ?>
                <img src="<?= htmlspecialchars($squadra['logo_url']) ?>" alt="">
              <?php else: ?>
                <?php preg_match('/^./u', $squadra['nome'], $matchIniziale); ?>
                <span aria-hidden="true"><?= htmlspecialchars($matchIniziale[0] ?? '?') ?></span>
              <?php endif; ?>
            </div>
            <div class="team-directory-info">
              <h2><?= htmlspecialchars($squadra['nome']) ?></h2>
              <p><?= (int)$squadra['numero_giocatori'] ?> giocatori<?= $squadra['posizione'] !== null ? ' · posizione #' . (int)$squadra['posizione'] . ' · ' . (int)$squadra['punti'] . ' punti' : ' · classifica in aggiornamento' ?></p>
            </div>
            <span class="team-directory-arrow" aria-hidden="true">→</span>
          </a>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>
</main>
<?php include __DIR__ . '/shared/footer.php'; ?>
