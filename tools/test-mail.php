<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/mailer.php';

require_admin();

$errors = [];
$success = '';
$recipient = trim((string) ($_POST['recipient'] ?? ''));
$subject = trim((string) ($_POST['subject'] ?? 'Test email Torneo di Baone'));
$message = trim((string) ($_POST['message'] ?? 'Questo è un messaggio di test.'));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!filter_var($recipient, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Inserisci un indirizzo email valido.';
    } elseif ($subject === '') {
        $errors[] = 'Inserisci un oggetto.';
    } elseif ($message === '') {
        $errors[] = 'Inserisci un messaggio.';
    } else {
        $html = build_email_template(
            $subject,
            $message,
            'Apri messaggio',
            SITE_URL,
            'Questa email è stata generata dal test di invio della posta del sito.',
            'Se non hai richiesto questo messaggio, puoi ignorare questa email.'
        );

        if (send_email($recipient, $subject, $html)) {
            $success = 'Email inviata correttamente.';
        } else {
            $errors[] = 'Invio email fallito. Controlla la configurazione SMTP e riprova.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="it">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Test email — <?= htmlspecialchars(SITE_NAME) ?></title>
  <link rel="stylesheet" href="<?= SITE_URL ?>/assets/css/admin.css">
  <style>
    body { background: #0d1b2a; color: #e5e7eb; font-family: Arial, sans-serif; }
    .wrap { max-width: 680px; margin: 48px auto; padding: 24px; background: #132238; border: 1px solid rgba(212,175,55,.18); border-radius: 14px; }
    h1 { color: #d4af37; }
  </style>
</head>
<body>
  <div class="wrap">
    <h1>Test invio email</h1>
    <?php if ($errors): foreach ($errors as $error): ?><div class="alert alert-error"><?= htmlspecialchars($error) ?></div><?php endforeach; endif; ?>
    <?php if ($success): ?><div class="alert alert-success"><?= htmlspecialchars($success) ?></div><?php endif; ?>

    <form method="post" class="admin-form" style="margin-top: 1.5rem;">
      <div class="form-group">
        <label for="recipient">Destinatario</label>
        <input id="recipient" type="email" name="recipient" value="<?= htmlspecialchars($recipient) ?>" required>
      </div>
      <div class="form-group">
        <label for="subject">Oggetto</label>
        <input id="subject" type="text" name="subject" value="<?= htmlspecialchars($subject) ?>" required>
      </div>
      <div class="form-group">
        <label for="message">Messaggio</label>
        <textarea id="message" name="message" rows="8" required><?= htmlspecialchars($message) ?></textarea>
      </div>
      <button type="submit" class="btn btn-gold">Invia test</button>
    </form>
  </div>
</body>
</html>
