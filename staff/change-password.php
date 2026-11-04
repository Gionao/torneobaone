<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/config.php';
require_once dirname(__DIR__) . '/includes/auth.php';

$principal = staff_require_authenticated(true);
$errore = '';
$successo = false;
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    if (!staff_csrf_verify()) {
        $errore = 'La sessione del modulo è scaduta. Ricarica la pagina e riprova.';
    } elseif (!staff_change_password((string) ($_POST['password'] ?? ''), (string) ($_POST['password_confirmation'] ?? ''))) {
        $errore = 'La password deve contenere almeno 12 caratteri, una lettera maiuscola, una minuscola e un numero; la conferma deve coincidere.';
    } else {
        $successo = true;
        header('Location: ' . STAFF_URL . '/index.php?password=aggiornata');
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="it">
<head>
  <link rel="icon" type="image/x-icon" href="<?= SITE_URL ?>/assets/brand/02_favicon/favicon.ico">
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Cambia password — <?= htmlspecialchars(SITE_NAME, ENT_QUOTES, 'UTF-8') ?></title>
  <link rel="stylesheet" href="<?= SITE_URL ?>/assets/css/style.css">
  <link rel="stylesheet" href="<?= SITE_URL ?>/assets/css/admin.css">
  <link rel="stylesheet" href="<?= SITE_URL ?>/assets/css/baone-ui.css?v=phase04">
</head>
<body class="admin-body">
<main class="admin-main"><div class="admin-container">
  <div class="admin-page-header">
    <h1>Cambia password</h1>
    <p>Per continuare è necessario scegliere una nuova password.</p>
  </div>
  <?php if ($errore !== ''): ?><div class="alert alert-error" role="alert"><?= htmlspecialchars($errore, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
  <form method="post" class="admin-form" autocomplete="off">
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(staff_csrf_token(), ENT_QUOTES, 'UTF-8') ?>">
    <div class="form-group"><label for="password">Nuova password</label><input type="password" id="password" name="password" required autocomplete="new-password"></div>
    <div class="form-group"><label for="password_confirmation">Conferma nuova password</label><input type="password" id="password_confirmation" name="password_confirmation" required autocomplete="new-password"></div>
    <button type="submit" class="btn btn-gold">Salva password</button>
  </form>
</div></main>
</body>
</html>
