<?php
// photo/admin/login.php — Accesso all'archivio privato
declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';

session_start_secure();
$cssVersione = @filemtime(dirname(__DIR__) . '/assets/css/archivio.css') ?: time();

if (media_is_logged()) {
    header('Location: ' . ARCHIVIO_URL . '/index.php');
    exit;
}

$errore = '';
$username = '';
$ritorna = (string) ($_GET['ritorna'] ?? $_POST['ritorna'] ?? '');
$destinazione = ARCHIVIO_URL . '/index.php';
if (preg_match('~\A/photo/admin/(?!/)(?!.*(?:^|/)\.\.(?:/|$))[A-Za-z0-9_./-]*(?:\?[A-Za-z0-9_=&%-]*)?\z~', $ritorna) === 1) {
    $destinazione = SITE_URL . $ritorna;
}

if (is_admin_logged() && !media_is_logged()) {
    $errore = 'Questo account non ha accesso all\'archivio. Usa un account autorizzato.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify()) {
        $errore = 'Sessione scaduta. Ricarica la pagina e riprova.';
    } else {
        $username = trim((string) ($_POST['username'] ?? ''));
        $password = (string) ($_POST['password'] ?? '');

        if ($username === '' || $password === '') {
            $errore = 'Inserisci nome utente e password.';
        } elseif (!staff_login($username, $password)) {
            $errore = 'Credenziali non valide. Riprova.';
        } elseif (!media_is_logged()) {
            $errore = 'Questo account non ha accesso all\'archivio.';
        } else {
            if (staff_password_change_required()) {
                header('Location: ' . STAFF_URL . '/change-password.php');
                exit;
            }
            if (count(staff_current_user()['assignments']) > 1 && !staff_is_super_admin()) {
                header('Location: ' . STAFF_URL . '/index.php');
                exit;
            }
            header('Location: ' . $destinazione);
            exit;
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
  <meta name="robots" content="noindex, nofollow">
  <title>Accedi all'archivio — <?= media_h(SITE_NAME) ?></title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Barlow+Condensed:wght@600;700;800&family=Barlow:wght@400;500;600&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?= media_h(SITE_URL) ?>/assets/css/style.css">
  <link rel="stylesheet" href="<?= media_h(MEDIA_ROOT_URL) ?>/assets/css/archivio.css?v=<?= (int) $cssVersione ?>">
</head>
<body class="archivio-login-body">
  <main class="archivio-login-card">
    <div class="archivio-login-logo">
      <img src="<?= media_h(SITE_URL) ?>/assets/img/logo_torneo.png" alt="Logo Torneo">
    </div>
    <p class="archivio-eyebrow">Area riservata</p>
    <h1>Archivio multimediale</h1>
    <p class="archivio-login-sub">Foto e video privati del Torneo di Baone. Accesso consentito solo allo staff autorizzato.</p>

    <?php if ($errore): ?>
      <div class="alert alert-error" role="alert"><?= media_h($errore) ?></div>
    <?php endif; ?>

    <form method="post" autocomplete="off" novalidate>
      <input type="hidden" name="csrf_token" value="<?= media_h(csrf_token()) ?>">
      <input type="hidden" name="ritorna" value="<?= media_h($ritorna) ?>">
      <div class="form-group">
        <label for="username">Nome utente</label>
        <input type="text" id="username" name="username" required autofocus maxlength="60"
               value="<?= media_h($username) ?>" autocomplete="username">
      </div>
      <div class="form-group">
        <label for="password">Password</label>
        <input type="password" id="password" name="password" required autocomplete="current-password">
      </div>
      <button type="submit" class="btn btn-gold archivio-login-btn">Accedi</button>
    </form>

    <p class="archivio-login-footer">
      Archivio privato: nessun contenuto è pubblico. Non esiste registrazione autonoma.
    </p>
    <p class="archivio-login-footer">
      <a href="<?= media_h(SITE_URL) ?>">Torna al sito del torneo</a>
    </p>
  </main>
</body>
</html>
