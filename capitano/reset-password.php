<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/token_helper.php';

$token = trim((string) ($_GET['token'] ?? ''));
$errors = [];
$success = '';

if ($token !== '') {
    $pdo = db();
    $valid = get_valid_token($pdo, $token);
    if (!$valid || ($valid['tipo'] ?? '') !== 'reset_password') {
        $errors[] = 'Token non valido o scaduto.';
        $token = '';
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = trim((string) ($_POST['token'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');
    if ($password === '' || strlen($password) < 8) {
        $errors[] = 'La password deve avere almeno 8 caratteri.';
    }
    if (!$errors) {
        $pdo = db();
        $valid = get_valid_token($pdo, $token);
        if (!$valid || ($valid['tipo'] ?? '') !== 'reset_password') {
            $errors[] = 'Token non valido o scaduto.';
        } else {
            $hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
            $upd = $pdo->prepare('UPDATE capitani SET password_hash = ? WHERE id = ?');
            $upd->execute([$hash, (int) $valid['id_capitano']]);
            consume_token($pdo, $token);
            $success = 'Password aggiornata con successo. Ora puoi accedere.';
            $token = '';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="it">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Reset password — <?= htmlspecialchars(SITE_NAME) ?></title>
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
    <h1>Reimposta password</h1>
    <?php if ($errors): foreach ($errors as $error): ?><div class="alert alert-error" role="alert"><?= htmlspecialchars($error) ?></div><?php endforeach; endif; ?>
    <?php if ($success): ?><div class="alert alert-success" role="status"><?= htmlspecialchars($success) ?></div><?php endif; ?>
    <?php if ($token !== ''): ?>
      <form method="post" class="admin-form" style="margin-top:1rem;">
        <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>">
        <div class="form-group">
          <label for="nuova_password">Nuova password <span class="required-mark" aria-hidden="true">*</span></label>
          <input id="nuova_password" type="password" name="password" required>
        </div>
        <button type="submit" class="btn btn-gold" style="width:100%; justify-content:center;">Salva</button>
      </form>
    <?php else: ?>
      <div style="margin-top:1rem; text-align:center;"><a href="<?= SITE_URL ?>/capitano/login.php">Vai al login</a></div>
    <?php endif; ?>
  </main>
</body>
</html>
