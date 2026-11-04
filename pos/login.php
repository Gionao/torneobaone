<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/config.php';
require_once dirname(__DIR__) . '/includes/auth.php';

session_start_secure();

if (is_admin_logged()) {
    staff_continue_after_login();
}

$errore = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($username === '' || $password === '') {
        $errore = 'Credenziali non valide';
    } else {
        if (staff_login($username, $password)) {
            staff_continue_after_login();
        }
        $errore = 'Credenziali non valide';
    }
}

$siteNavConfig = [
    'id' => 'pos-login-navigation',
    'brand_href' => SITE_URL . '/',
    'brand_name' => 'Torneo Baone',
    'brand_year' => '2026',
    'brand_label' => 'Torneo Baone 2026',
    'label' => 'Navigazione principale',
    'primary' => [
        ['label' => 'Home', 'href' => SITE_URL . '/'],
        ['label' => 'Calendario', 'href' => SITE_URL . '/calendario'],
        ['label' => 'Classifica', 'href' => SITE_URL . '/classifica'],
        ['label' => 'Squadre', 'href' => SITE_URL . '/squadre'],
    ],
    'actions' => [['label' => 'Area staff', 'href' => '#accesso', 'primary' => true, 'current' => true]],
];
?>
<!DOCTYPE html>
<html lang="it">
<head>
  <link rel="icon" type="image/x-icon" href="<?= SITE_URL ?>/assets/brand/02_favicon/favicon.ico">
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Area gestione — POS</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Barlow+Condensed:wght@600;700;800&family=Barlow:wght@400;500;600&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?= SITE_URL ?>/assets/css/style.css">
  <link rel="stylesheet" href="<?= SITE_URL ?>/assets/css/admin.css">
  <link rel="stylesheet" href="<?= SITE_URL ?>/assets/css/public-redesign.css">
  <link rel="stylesheet" href="<?= POS_URL ?>/assets/css/pos.css">
</head>
<body class="pos-login-body public-site">
<?php require dirname(__DIR__) . '/shared/site_nav.php'; ?>

<main class="pos-login-main" id="accesso">
<div class="pos-login-wrap">
  <div class="pos-login-header">
    <h1 class="pos-login-title">Area gestione</h1>
    <p class="pos-login-sub">Accesso riservato allo staff</p>
  </div>

  <div class="pos-login-card">
    <?php if ($errore): ?>
      <div class="alert alert-error"><?= htmlspecialchars($errore) ?></div>
    <?php endif; ?>

    <form method="post" class="pos-login-form" autocomplete="off" novalidate>
      <div class="form-group">
        <label for="username">Username</label>
        <input type="text" id="username" name="username" required autofocus
               value="<?= htmlspecialchars($_POST['username'] ?? '') ?>">
      </div>
      <div class="form-group">
        <label for="password">Password</label>
        <div class="pos-password-wrap">
          <input type="password" id="password" name="password" required>
          <button type="button" class="pos-password-toggle" id="togglePassword" aria-label="Mostra password">👁</button>
        </div>
      </div>
      <button type="submit" class="btn btn-gold pos-login-btn">Accedi</button>
    </form>
  </div>

  <footer class="pos-login-footer">
    Torneo Calcio a 5 — Baone 2026
  </footer>
</div>
</main>

<script>
document.getElementById('togglePassword').addEventListener('click', function () {
  const inp = document.getElementById('password');
  const show = inp.type === 'password';
  inp.type = show ? 'text' : 'password';
  this.setAttribute('aria-label', show ? 'Nascondi password' : 'Mostra password');
});
</script>
</body>
</html>
