<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth_capitano.php';

capitano_session_start_secure();

if (is_capitano_logged()) {
    header('Location: ' . SITE_URL . '/capitano/dashboard.php');
    exit;
}

$errore = '';
if (!empty($_SESSION['capitano_notice'])) {
    $notice = $_SESSION['capitano_notice'];
    unset($_SESSION['capitano_notice']);
} else {
    $notice = '';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim((string) ($_POST['email'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');

    if ($email === '' || $password === '') {
        $errore = 'Inserisci email e password.';
    } elseif (!capitano_login($email, $password)) {
      $loginError = $_SESSION['capitano_login_error'] ?? '';
      unset($_SESSION['capitano_login_error']);
      if ($loginError === 'email_non_verificata') {
            $errore = 'L’account non è ancora verificato. Controlla la tua email o richiedi un nuovo link.';
      } elseif ($loginError === 'squadra_non_attiva') {
        $errore = 'La password è corretta, ma la squadra non risulta attiva. Contatta l’organizzazione.';
        } else {
            $errore = 'Credenziali non valide.';
        }
    } else {
        header('Location: ' . SITE_URL . '/capitano/dashboard.php');
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="it">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Login capitano — <?= htmlspecialchars(SITE_NAME) ?></title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Barlow+Condensed:wght@600;700;800&family=Barlow:wght@400;500;600&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?= SITE_URL ?>/assets/css/style.css">
  <link rel="stylesheet" href="<?= SITE_URL ?>/assets/css/admin.css">
  <style>
    body { background: var(--navy); }
    .auth-card { max-width: 430px; margin: 4rem auto; background: var(--navy-mid); border:1px solid rgba(201,168,76,.18); border-radius: var(--radius); padding: 2rem; }
    .brand { text-align:center; margin-bottom: 1.2rem; }
    .brand img { width: 52px; margin: 0 auto 0.7rem; }
    .brand h1 { font-family:'Barlow Condensed',sans-serif; text-transform:uppercase; letter-spacing:.08em; color:var(--gold); }
    .helper { color: var(--muted); text-align:center; margin-bottom: 1.2rem; }
    .auth-form .form-group { margin-bottom: 1rem; }
    .muted-links { display:flex; justify-content:space-between; gap:1rem; margin-top: 1rem; font-size: .8rem; }
  </style>
</head>
<body>
<div class="auth-card">
  <div class="brand">
    <img src="<?= SITE_URL ?>/assets/img/logo_torneo.png" alt="Logo">
    <h1>Area Capitano</h1>
  </div>
  <div class="helper">Accedi per gestire la squadra e gli inviti.</div>

  <?php if ($notice): ?>
    <div class="alert alert-success"><?= htmlspecialchars($notice) ?></div>
  <?php endif; ?>

  <?php if ($errore): ?>
    <div class="alert alert-error"><?= htmlspecialchars($errore) ?></div>
  <?php endif; ?>

  <form method="post" class="auth-form">
    <div class="form-group">
      <label for="email">Email</label>
      <input type="email" id="email" name="email" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required>
    </div>
    <div class="form-group">
      <label for="password">Password</label>
      <input type="password" id="password" name="password" required>
    </div>
    <button type="submit" class="btn btn-gold" style="width:100%; justify-content:center;">Accedi</button>
  </form>

  <div class="muted-links">
    <a href="<?= SITE_URL ?>/capitano/password-dimenticata.php">Password dimenticata</a>
    <a href="<?= SITE_URL ?>/capitano/richiedi-verifica.php">Verifica account</a>
    <a href="<?= SITE_URL ?>/capitano/registrati.php">Registrati</a>
  </div>
  <div class="muted-links">
    <a href="<?= SITE_URL ?>">Torna al sito</a>
  </div>
</div>
</body>
</html>
