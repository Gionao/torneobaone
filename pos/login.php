<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once dirname(__DIR__) . '/includes/auth.php';

session_start_secure();

if (isset($_SESSION['pos_admin_id'])) {
    $stmt = $pdo->prepare('SELECT id, username, role, station_name FROM admin WHERE id = ? LIMIT 1');
    $stmt->execute([(int) $_SESSION['pos_admin_id']]);
    $account = $stmt->fetch();
    $ruoloPos = (string) ($account['role'] ?? '');
    if ($account && in_array($ruoloPos, ['admin', 'operatore', 'bar', 'cucina'], true)) {
        $_SESSION['pos_admin_user'] = (string) $account['username'];
        $_SESSION['pos_admin_role'] = $ruoloPos;
        $_SESSION['pos_admin_station'] = $account['station_name'];
        header('Location: ' . POS_URL . '/index.php');
        exit;
    }
    unset($_SESSION['pos_admin_id'], $_SESSION['pos_admin_user'], $_SESSION['pos_admin_role'], $_SESSION['pos_admin_station']);
    session_regenerate_id(true);
}

$errore = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($username === '' || $password === '') {
        $errore = 'Credenziali non valide';
    } else {
        $account = admin_authenticate_credentials($username, $password);
        if ($account && in_array($account['role'], ['admin', 'operatore', 'bar', 'cucina'], true)) {
            session_regenerate_id(true);
            unset($_SESSION['admin_id'], $_SESSION['admin_username'], $_SESSION['admin_role'], $_SESSION['admin_token']);
            $_SESSION['pos_admin_id']      = $account['id'];
            $_SESSION['pos_admin_user']    = $account['username'];
            $_SESSION['pos_admin_role']    = $account['role'];
            $_SESSION['pos_admin_station'] = $account['station_name'];
            header('Location: ' . POS_URL . '/index.php');
            exit;
        }
        $errore = 'Credenziali non valide';
    }
}
?>
<!DOCTYPE html>
<html lang="it">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Area gestione — POS</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Barlow+Condensed:wght@600;700;800&family=Barlow:wght@400;500;600&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?= SITE_URL ?>/assets/css/style.css">
  <link rel="stylesheet" href="<?= SITE_URL ?>/assets/css/admin.css">
  <link rel="stylesheet" href="<?= POS_URL ?>/assets/css/pos.css">
</head>
<body class="pos-login-body">

<div class="pos-login-wrap">
  <div class="pos-login-header">
    <img src="<?= SITE_URL ?>/assets/img/logo_torneo.png" alt="Logo torneo" class="pos-login-logo">
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
