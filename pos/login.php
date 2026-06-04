<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (isset($_SESSION['pos_admin_id'])) {
    header('Location: ' . POS_URL . '/operator_panel.php');
    exit;
}

$errore = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($username === '' || $password === '') {
        $errore = 'Credenziali non valide';
    } else {
        $stmt = $pdo->prepare('SELECT id, username, password_hash FROM admin WHERE username = ? LIMIT 1');
        $stmt->execute([$username]);
        $row = $stmt->fetch();

        if ($row && password_verify($password, $row['password_hash'])) {
            session_regenerate_id(true);
            $_SESSION['pos_admin_id']   = (int) $row['id'];
            $_SESSION['pos_admin_user'] = $row['username'];
            header('Location: ' . POS_URL . '/operator_panel.php');
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
  <link rel="stylesheet" href="<?= SITE_URL ?>/pos/assets/css/pos.css">
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
