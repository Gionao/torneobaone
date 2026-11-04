<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/token_helper.php';

$token = trim((string) ($_GET['token'] ?? ''));
$errors = [];
$success = '';

if ($token === '') {
    $errors[] = 'Link di verifica mancante.';
} else {
    $pdo = db();
    $row = get_valid_token($pdo, $token);
    if (!$row || ($row['tipo'] ?? '') !== 'email_verifica') {
        $errors[] = 'Il link di verifica non è valido o è scaduto.';
    } else {
        $idCapitano = (int) ($row['id_capitano'] ?? 0);
        if ($idCapitano > 0) {
            $pdo->prepare('UPDATE capitani SET email_verificata_at = NOW() WHERE id = ?')->execute([$idCapitano]);
            if (table_has_column($pdo, 'squadre', 'attiva')) {
                $pdo->prepare('UPDATE squadre SET attiva = 1 WHERE id_capitano = ?')->execute([$idCapitano]);
            }
            consume_token($pdo, $token);
            $success = 'La tua email è stata verificata con successo. La squadra è ora attiva e puoi accedere all’area capitano.';
        } else {
            $errors[] = 'Impossibile associare il token a un account valido.';
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
  <title>Verifica account — <?= htmlspecialchars(SITE_NAME) ?></title>
  <link rel="stylesheet" href="<?= SITE_URL ?>/assets/css/style.css">
  <style>
    body { min-height: 100vh; display: flex; align-items: center; justify-content: center; background: var(--navy); }
    .card { width: min(100%, 520px); background: var(--navy-mid); border: 1px solid rgba(201,168,76,.18); border-radius: 12px; padding: 2rem; }
    h1 { font-family: 'Barlow Condensed', sans-serif; letter-spacing: .08em; color: var(--gold); text-transform: uppercase; }
  </style>
  <link rel="stylesheet" href="<?= SITE_URL ?>/assets/css/captain-portal.css?v=phase05">
</head>
<body class="captain-auth-flow">
  <main class="card">
    <h1>Verifica account</h1>
    <?php if ($errors): foreach ($errors as $error): ?>
      <div class="alert alert-error" role="alert"><?= htmlspecialchars($error) ?></div>
    <?php endforeach; endif; ?>
    <?php if ($success): ?>
      <div class="alert alert-success" role="status"><?= htmlspecialchars($success) ?></div>
    <?php endif; ?>
    <div style="margin-top: 1.5rem; text-align: center;">
      <a href="<?= SITE_URL ?>/capitano/login.php" class="btn btn-gold">Vai al login</a>
    </div>
  </main>
</body>
</html>
