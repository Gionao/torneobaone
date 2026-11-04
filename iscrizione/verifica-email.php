<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/token_helper.php';
require_once __DIR__ . '/../includes/iscrizione_helper.php';
require_once __DIR__ . '/../includes/mailer.php';

$pdo = db();
$token = trim((string) ($_GET['token'] ?? ''));
$errors = [];
$success = '';
$warning = '';

if ($token === '') {
    $errors[] = 'Token mancante.';
} else {
    $row = get_valid_token($pdo, $token);
    if (!$row || ($row['tipo'] ?? '') !== 'email_verifica') {
        $errors[] = 'Token non valido o scaduto.';
    } else {
        $idGiocatore = (int) ($row['id_giocatore'] ?? 0);
        if (!empty($row['id_capitano'])) {
            $pdo->prepare('UPDATE capitani SET email_verificata_at = NOW() WHERE id = ?')->execute([(int) $row['id_capitano']]);
        }
        consume_token($pdo, $token);
        if ($idGiocatore > 0) {
            avanza_stato_giocatore($pdo, $idGiocatore);
            $parentStmt = $pdo->prepare('SELECT p.id, p.nome, p.cognome, p.email, g.nome AS nome_giocatore, g.cognome AS cognome_giocatore FROM genitori p JOIN giocatori g ON g.id = p.id_giocatore WHERE p.id_giocatore = ? LIMIT 1');
            $parentStmt->execute([$idGiocatore]);
            $parent = $parentStmt->fetch();

            if ($parent) {
                invalidate_previous_tokens($pdo, 'consenso_genitore', null, (int) $parent['id']);
                $parentToken = create_verification_token($pdo, 'consenso_genitore', null, $idGiocatore, (int) $parent['id'], 72);
                $parentSent = false;
                if ($parentToken) {
                    $parentLink = build_site_link('/iscrizione/conferma-genitore.php', $parentToken);
                    $parentBody = build_email_template(
                        'Conferma iscrizione minorenne',
                        "Buongiorno {$parent['nome']} {$parent['cognome']},\n\ncompleta l'iscrizione di {$parent['nome_giocatore']} {$parent['cognome_giocatore']} al torneo.",
                        'Completa iscrizione',
                        $parentLink,
                        'Il link scade tra 72 ore.'
                    );
                    $parentSent = send_email((string) $parent['email'], 'Conferma iscrizione minorenne', $parentBody);
                }

                if ($parentSent) {
                    $success = 'Email verificata. Il giocatore è in attesa del genitore: abbiamo inviato al genitore il link per approvare l’iscrizione.';
                } else {
                    $success = 'Email verificata. Il giocatore è in attesa del genitore.';
                    $warning = 'Non è stato possibile inviare il link al genitore. Il capitano può riprovare dalla pagina Giocatori.';
                }
            } else {
                $success = 'Email verificata con successo.';
            }
        } else {
            $success = 'Email verificata con successo.';
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
  <title>Verifica email — <?= htmlspecialchars(SITE_NAME) ?></title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Barlow+Condensed:wght@600;700;800&family=Barlow:wght@400;500;600&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?= SITE_URL ?>/assets/css/style.css">
  <link rel="stylesheet" href="<?= SITE_URL ?>/assets/css/iscrizioni.css">
  <link rel="stylesheet" href="<?= SITE_URL ?>/assets/css/registration-portal.css?v=phase05">
</head>
<body class="registration-form-page">
  <div class="app-shell">
    <div class="card-shell">
      <div class="brand-row">
        <img src="<?= SITE_URL ?>/assets/brand/01_loghi/png/logo_scudetto_compatto.png" alt="">
        <h1>Verifica</h1>
      </div>
      <?php if ($errors): foreach ($errors as $err): ?><div class="alert alert-error" role="alert"><?= htmlspecialchars($err) ?></div><?php endforeach; endif; ?>
      <?php if ($success): ?><div class="alert alert-success" role="status"><?= htmlspecialchars($success) ?></div><?php endif; ?>
      <?php if ($warning): ?><div class="alert alert-error" role="alert"><?= htmlspecialchars($warning) ?></div><?php endif; ?>
      <div class="inline-actions" style="justify-content:center;">
        <a href="<?= SITE_URL ?>" class="btn btn-gold">Torna al sito</a>
      </div>
    </div>
  </div>
</body>
</html>
