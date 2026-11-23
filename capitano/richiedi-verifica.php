<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/mailer.php';
require_once __DIR__ . '/../includes/token_helper.php';

$errors = [];
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim(strtolower((string) ($_POST['email'] ?? '')));

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Inserisci un indirizzo email valido.';
    } else {
        $pdo = db();
        $stmt = $pdo->prepare('SELECT id, nome, cognome, email_verificata_at FROM capitani WHERE email = ? LIMIT 1');
        $stmt->execute([$email]);
        $capitano = $stmt->fetch();

        if ($capitano && empty($capitano['email_verificata_at'])) {
            $token = create_verification_token($pdo, 'email_verifica', (int) $capitano['id'], null, null, 72);
            if ($token) {
                $verificationLink = build_site_link('/capitano/verifica-email.php', $token);
                $body = build_email_template(
                    'Verifica il tuo account',
                    "Ciao {$capitano['nome']} {$capitano['cognome']},\n\nRichiedi un nuovo link per verificare il tuo account e completare l'accesso all'area capitano.",
                    'Verifica il tuo account',
                    $verificationLink,
                    'Il link scade tra 72 ore.',
                    'Se non hai richiesto questa verifica, puoi ignorare questa email.'
                );
                $failureReason = null;
                if (!send_email($email, 'Verifica il tuo account', $body, $failureReason)) {
                    $errors[] = $failureReason ?: 'Non è stato possibile inviare la mail di verifica. Riprova più tardi.';
                }
            } else {
                $errors[] = 'Non è stato possibile creare il link di verifica. Riprova più tardi.';
            }
            if (!$errors) {
                $success = 'Se l’indirizzo è corretto, ti abbiamo inviato un nuovo link di verifica.';
            }
        } else {
            $success = 'Se l’indirizzo è corretto, ti abbiamo inviato un nuovo link di verifica.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="it">
<head>
  <link rel="icon" type="image/x-icon" href="<?= SITE_URL ?>/assets/brand/02_favicon/favicon.ico">
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Richiedi verifica — <?= htmlspecialchars(SITE_NAME) ?></title>
  <link rel="stylesheet" href="<?= SITE_URL ?>/assets/css/style.css">
  <style>
    body { min-height: 100vh; display: flex; align-items: center; justify-content: center; background: var(--navy); }
    .card { width: min(100%, 500px); background: var(--navy-mid); border: 1px solid rgba(201,168,76,.18); border-radius: 12px; padding: 2rem; }
    h1 { font-family: 'Barlow Condensed', sans-serif; letter-spacing: .08em; color: var(--gold); text-transform: uppercase; }
  </style>
  <link rel="stylesheet" href="<?= SITE_URL ?>/assets/css/captain-portal.css?v=phase05">
</head>
<body class="captain-auth-flow">
  <main class="card">
    <h1>Richiedi verifica</h1>
    <?php if ($errors): foreach ($errors as $error): ?><div class="alert alert-error" role="alert"><?= htmlspecialchars($error) ?></div><?php endforeach; endif; ?>
    <?php if ($success): ?><div class="alert alert-success" role="status"><?= htmlspecialchars($success) ?></div><?php endif; ?>
    <form method="post" class="admin-form" style="margin-top: 1rem;">
      <p class="required-note">Inserisci l’indirizzo email usato per registrare l’account.</p>
      <div class="form-group">
        <label for="email">Email <span class="required-mark" aria-hidden="true">*</span></label>
        <input type="email" id="email" name="email" required>
      </div>
      <button type="submit" class="btn btn-gold" style="width:100%; justify-content:center;">Invia nuovo link</button>
    </form>
    <div style="margin-top:1rem; text-align:center;"><a href="<?= SITE_URL ?>/capitano/login.php">Torna al login</a></div>
  </main>
</body>
</html>
