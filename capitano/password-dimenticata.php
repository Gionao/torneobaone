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
        $stmt = $pdo->prepare('SELECT id FROM capitani WHERE email = ? LIMIT 1');
        $stmt->execute([$email]);
        $capitano = $stmt->fetch();

        if ($capitano) {
            $token = create_verification_token($pdo, 'reset_password', (int) $capitano['id'], null, null, 48);
            if ($token) {
                $link = build_site_link('/capitano/reset-password.php', $token);
                $body = build_email_template(
                    'Reimposta la password',
                    "Ciao,\n\nabbiamo ricevuto una richiesta per reimpostare la password del tuo account. Per continuare, clicca sul pulsante qui sotto.",
                    'Reimposta password',
                    $link,
                    'Il link di reimpostazione scade tra 48 ore.',
                    'Se non hai richiesto questo reset, puoi ignorare questa email.'
                );
                send_email($email, 'Reimposta la password', $body);
            }
        }

        $success = 'Se l’email è corretta, ti invieremo il link per reimpostare la password.';
    }
}
?>
<!DOCTYPE html>
<html lang="it">
<head>
  <link rel="icon" type="image/x-icon" href="<?= SITE_URL ?>/assets/brand/02_favicon/favicon.ico">
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Password dimenticata — <?= htmlspecialchars(SITE_NAME) ?></title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Barlow+Condensed:wght@600;700;800&family=Barlow:wght@400;500;600&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?= SITE_URL ?>/assets/css/style.css">
  <style>
    body { min-height:100vh; display:flex; align-items:center; justify-content:center; background:var(--navy); }
    .card { width:min(100%, 440px); background:var(--navy-mid); border:1px solid rgba(201,168,76,.18); border-radius:10px; padding:2rem; }
    h1 { font-family:'Barlow Condensed',sans-serif; text-transform:uppercase; color:var(--gold); letter-spacing:.08em; }
  </style>
  <link rel="stylesheet" href="<?= SITE_URL ?>/assets/css/captain-portal.css?v=1">
</head>
<body class="captain-auth-flow">
  <main class="card">
    <h1>Password dimenticata</h1>
    <?php if ($errors): foreach ($errors as $error): ?><div class="alert alert-error" role="alert"><?= htmlspecialchars($error) ?></div><?php endforeach; endif; ?>
    <?php if ($success): ?><div class="alert alert-success" role="status"><?= htmlspecialchars($success) ?></div><?php endif; ?>
    <form method="post" class="admin-form" style="margin-top: 1rem;">
      <p class="required-note">Inserisci l’indirizzo email associato all’account.</p>
      <div class="form-group">
        <label for="email">Email <span class="required-mark" aria-hidden="true">*</span></label>
        <input type="email" id="email" name="email" required>
      </div>
      <button type="submit" class="btn btn-gold" style="width:100%; justify-content:center;">Invia link</button>
    </form>
    <div style="margin-top:1rem; text-align:center;"><a href="<?= SITE_URL ?>/capitano/login.php">Torna al login</a></div>
  </main>
</body>
</html>
