<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/token_helper.php';
require_once __DIR__ . '/../includes/iscrizione_helper.php';
require_once __DIR__ . '/../includes/mailer.php';

$token = trim((string) ($_GET['token'] ?? ''));
$errors = [];
$success = '';
$pdo = db();

$tokenData = $token !== '' ? get_valid_token($pdo, $token) : null;

if (!$tokenData || ($tokenData['tipo'] ?? '') !== 'invito_giocatore') {
    $errors[] = 'Link non valido o scaduto.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = trim((string) ($_POST['token'] ?? ''));
    $tokenData = get_valid_token($pdo, $token);

    if (!$tokenData || ($tokenData['tipo'] ?? '') !== 'invito_giocatore') {
        $errors[] = 'Link non valido o scaduto.';
    } else {
        $nome = trim((string) ($_POST['nome'] ?? ''));
        $cognome = trim((string) ($_POST['cognome'] ?? ''));
        $email = trim(strtolower((string) ($_POST['email'] ?? '')));
        $telefono = trim((string) ($_POST['telefono'] ?? ''));
        $dataNascita = trim((string) ($_POST['data_nascita'] ?? ''));
        $indirizzo = trim((string) ($_POST['indirizzo'] ?? ''));

        if ($nome === '' || $cognome === '' || $email === '' || $telefono === '' || $dataNascita === '' || $indirizzo === '') {
            $errors[] = 'Tutti i campi obbligatori devono essere compilati.';
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Email non valida.';
        }

        if (!$errors) {
            $idGiocatore = (int) ($tokenData['id_giocatore'] ?? 0);
            $upd = $pdo->prepare('UPDATE giocatori SET nome = ?, cognome = ?, email = ?, telefono = ?, data_nascita = ?, indirizzo = ? WHERE id = ?');
            $upd->execute([$nome, $cognome, $email, $telefono, $dataNascita, $indirizzo, $idGiocatore]);

            $tokenVerify = create_verification_token($pdo, 'email_verifica', null, $idGiocatore, null, 72);
            if ($tokenVerify) {
                $link = SITE_URL . '/iscrizione/verifica-email.php?token=' . rawurlencode($tokenVerify);
                send_mail_message($email, 'Verifica la tua email', "Ciao {$nome},<br><br>verifica il tuo indirizzo email tramite questo link:<br><br><a href=\"{$link}\">{$link}</a>");
            }

            consume_token($pdo, $token);
            $success = 'Dati salvati. Controlla la tua email per completare la verifica.';
            $token = '';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="it">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Completa iscrizione giocatore — <?= htmlspecialchars(SITE_NAME) ?></title>
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
        <h1>Iscrizione</h1>
      </div>
      <p class="subtle">Completa i tuoi dati personali per proseguire con l'iscrizione.</p>

      <?php if ($errors): foreach ($errors as $err): ?>
        <div class="alert alert-error"><?= htmlspecialchars($err) ?></div>
      <?php endforeach; endif; ?>
      <?php if ($success): ?>
        <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
      <?php endif; ?>

      <?php if ($token !== '' && !$success): ?>
        <form method="post" class="admin-form" novalidate>
          <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>">
          <div class="form-row">
            <div class="form-group">
              <label>Nome</label>
              <input type="text" name="nome" value="<?= htmlspecialchars($_POST['nome'] ?? '') ?>" required>
            </div>
            <div class="form-group">
              <label>Cognome</label>
              <input type="text" name="cognome" value="<?= htmlspecialchars($_POST['cognome'] ?? '') ?>" required>
            </div>
          </div>
          <div class="form-row">
            <div class="form-group">
              <label>Email</label>
              <input type="email" name="email" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required>
            </div>
            <div class="form-group">
              <label>Telefono</label>
              <input type="text" name="telefono" value="<?= htmlspecialchars($_POST['telefono'] ?? '') ?>" required>
            </div>
          </div>
          <div class="form-group">
            <label>Data di nascita</label>
            <input type="date" name="data_nascita" value="<?= htmlspecialchars($_POST['data_nascita'] ?? '') ?>" required>
          </div>
          <div class="form-group">
            <label>Indirizzo</label>
            <input type="text" name="indirizzo" value="<?= htmlspecialchars($_POST['indirizzo'] ?? '') ?>" required>
          </div>
          <div class="inline-actions">
            <button type="submit" class="btn btn-gold">Salva dati</button>
          </div>
        </form>
      <?php endif; ?>
    </div>
  </div>
</body>
</html>
