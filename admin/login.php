<?php
// admin/login.php
// ============================================================
//  Login amministratore
// ============================================================

declare(strict_types=1);

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';

session_start_secure();

// Se già loggato, vai alla dashboard corretta
if (is_admin_logged()) {
    $destinazione = in_array((string) ($_SESSION['admin_role'] ?? ''), ['uploader', 'editor'], true)
        ? ARCHIVIO_URL . '/index.php'
        : 'index.php';
    header('Location: ' . $destinazione);
    exit;
}

$errore = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($username === '' || $password === '') {
        $errore = 'Inserisci username e password.';
    } elseif (!admin_login($username, $password)) {
        // Messaggio vago per non rivelare se l'username esiste
        $errore = 'Credenziali non valide. Riprova.';
    } else {
        $destinazione = in_array((string) ($_SESSION['admin_role'] ?? ''), ['uploader', 'editor'], true)
            ? ARCHIVIO_URL . '/index.php'
            : 'index.php';
        header('Location: ' . $destinazione);
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="it">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Admin Login — <?= SITE_NAME ?></title>
  <link rel="icon" type="image/png" href="<?= SITE_URL ?>/assets/brand/01_loghi/png/logo_principale.png">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Barlow+Condensed:wght@600;700&family=Barlow:wght@400;500&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?= SITE_URL ?>/assets/css/style.css">
  <link rel="stylesheet" href="<?= SITE_URL ?>/assets/css/public-redesign.css?v=1">
  <style>
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

    :root {
      --navy:  #0d1b2a;
      --gold:  #c9a84c;
      --white: #f0f4f8;
      --red:   #d94f4f;
    }

    body {
      min-height: 100vh;
      display: flex;
      flex-direction: column;
      align-items: stretch;
      justify-content: flex-start;
      background: var(--navy);
      background-image:
        radial-gradient(ellipse at 20% 50%, rgba(201,168,76,.08) 0%, transparent 60%),
        radial-gradient(ellipse at 80% 20%, rgba(201,168,76,.06) 0%, transparent 50%);
      font-family: 'Barlow', sans-serif;
    }

    .login-card {
      width: 100%;
      max-width: 380px;
      margin: auto;
      padding: 2.5rem 2rem;
      background: rgba(255,255,255,.04);
      border: 1px solid rgba(201,168,76,.25);
      border-radius: 12px;
      box-shadow: 0 24px 60px rgba(0,0,0,.5);
    }

    .login-logo {
      text-align: center;
      margin-bottom: 1.8rem;
    }

    .login-logo img {
      width: 90px;
      filter: drop-shadow(0 4px 12px rgba(201,168,76,.3));
    }

    h1 {
      font-family: 'Barlow Condensed', sans-serif;
      font-size: 1.5rem;
      color: var(--gold);
      letter-spacing: .08em;
      text-transform: uppercase;
      text-align: center;
      margin-bottom: .3rem;
    }

    .subtitle {
      text-align: center;
      color: rgba(240,244,248,.45);
      font-size: .8rem;
      margin-bottom: 2rem;
    }

    label {
      display: block;
      font-size: .78rem;
      font-weight: 500;
      color: rgba(240,244,248,.6);
      text-transform: uppercase;
      letter-spacing: .06em;
      margin-bottom: .4rem;
    }

    input[type="text"],
    input[type="password"] {
      width: 100%;
      padding: .7rem 1rem;
      background: rgba(255,255,255,.06);
      border: 1px solid rgba(201,168,76,.2);
      border-radius: 6px;
      color: var(--white);
      font-family: 'Barlow', sans-serif;
      font-size: .95rem;
      margin-bottom: 1.2rem;
      transition: border-color .2s;
      outline: none;
    }

    input:focus {
      border-color: var(--gold);
      background: rgba(201,168,76,.06);
    }

    .btn-login {
      width: 100%;
      padding: .85rem;
      background: var(--gold);
      color: var(--navy);
      border: none;
      border-radius: 6px;
      font-family: 'Barlow Condensed', sans-serif;
      font-size: 1.1rem;
      font-weight: 700;
      letter-spacing: .1em;
      text-transform: uppercase;
      cursor: pointer;
      transition: opacity .2s, transform .15s;
    }

    .btn-login:hover  { opacity: .9; transform: translateY(-1px); }
    .btn-login:active { transform: translateY(0); }

    .errore {
      background: rgba(217,79,79,.15);
      border: 1px solid rgba(217,79,79,.4);
      border-radius: 6px;
      color: #f08080;
      font-size: .85rem;
      padding: .7rem 1rem;
      margin-bottom: 1.2rem;
      text-align: center;
    }

    .back-link {
      display: block;
      text-align: center;
      margin-top: 1.4rem;
      color: rgba(240,244,248,.35);
      font-size: .8rem;
      text-decoration: none;
      transition: color .2s;
    }
    .back-link:hover { color: var(--gold); }
  </style>
</head>
<body class="public-site admin-login-page">

<?php include __DIR__ . '/../shared/header.php'; ?>

<div class="login-card">
  <div class="login-logo">
    <img src="<?= SITE_URL ?>/assets/img/logo_torneo.png" alt="Logo Torneo">
  </div>

  <h1>Pannello Admin</h1>
  <p class="subtitle">Accesso riservato agli organizzatori</p>

  <?php if ($errore): ?>
    <div class="errore"><?= htmlspecialchars($errore) ?></div>
  <?php endif; ?>

  <form method="post" autocomplete="off" novalidate>
    <label for="username">Username</label>
    <input type="text" id="username" name="username"
           value="<?= htmlspecialchars($_POST['username'] ?? '') ?>"
           required autofocus>

    <label for="password">Password</label>
    <input type="password" id="password" name="password" required>

    <button type="submit" class="btn-login">Accedi</button>
  </form>

  <a href="<?= SITE_URL ?>" class="back-link">← Torna al sito</a>
</div>

<?php include __DIR__ . '/../shared/footer.php'; ?>
