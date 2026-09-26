<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/token_helper.php';
require_once __DIR__ . '/../includes/iscrizione_helper.php';

$pdo = db();
$token = trim((string) ($_GET['token'] ?? ''));
$errors = [];
$success = '';

if ($token === '') {
    $errors[] = 'Token mancante.';
} else {
    $row = get_valid_token($pdo, $token);
    if (!$row || ($row['tipo'] ?? '') !== 'email_verifica') {
        $errors[] = 'Token non valido o scaduto.';
    } else {
        $idGiocatore = (int) ($row['id_giocatore'] ?? 0);
        $pdo->prepare('UPDATE capitani SET email_verificata_at = NOW() WHERE id = ?')->execute([ (int) ($row['id_capitano'] ?? 0) ]);
        $pdo->prepare('UPDATE giocatori SET stato_iscrizione = "in_attesa_genitore" WHERE id = ? AND data_nascita IS NOT NULL AND data_nascita <> "0000-00-00"')->execute([$idGiocatore]);
        consume_token($pdo, $token);
        $success = 'Email verificata con successo.';
    }
}
?>
<!DOCTYPE html>
<html lang="it">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Verifica email — <?= htmlspecialchars(SITE_NAME) ?></title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Barlow+Condensed:wght@600;700;800&family=Barlow:wght@400;500;600&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?= SITE_URL ?>/assets/css/style.css">
  <link rel="stylesheet" href="<?= SITE_URL ?>/assets/css/iscrizioni.css">
</head>
<body>
  <div class="app-shell">
    <div class="card-shell">
      <div class="brand-row">
        <img src="<?= SITE_URL ?>/assets/img/logo_torneo.png" alt="Logo">
        <h1>Verifica</h1>
      </div>
      <?php if ($errors): foreach ($errors as $err): ?><div class="alert alert-error"><?= htmlspecialchars($err) ?></div><?php endforeach; endif; ?>
      <?php if ($success): ?><div class="alert alert-success"><?= htmlspecialchars($success) ?></div><?php endif; ?>
      <div class="inline-actions" style="justify-content:center;">
        <a href="<?= SITE_URL ?>" class="btn btn-gold">Torna al sito</a>
      </div>
    </div>
  </div>
</body>
</html>
