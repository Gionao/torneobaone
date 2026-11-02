<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/token_helper.php';
require_once __DIR__ . '/../includes/iscrizione_helper.php';

$pdo = db();
$token = trim((string) ($_POST['token'] ?? $_GET['token'] ?? ''));
$row = $token !== '' ? get_valid_token($pdo, $token) : null;
$errors = [];
$success = '';
if (!$row || ($row['tipo'] ?? '') !== 'consenso_genitore' || empty($row['id_genitore']) || empty($row['id_giocatore'])) {
    $errors[] = 'Il link non è valido o è scaduto. Chiedi al capitano di inviarne uno nuovo.';
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (empty($_POST['accetta_privacy']) || empty($_POST['accetta_regolamento'])) {
        $errors[] = 'Per confermare devi accettare privacy e regolamento.';
    } else {
        $idGenitore = (int) $row['id_genitore'];
        $idGiocatore = (int) $row['id_giocatore'];
        $meta = [(string) ($_SERVER['REMOTE_ADDR'] ?? ''), (string) ($_SERVER['HTTP_USER_AGENT'] ?? '')];
        registra_consenso($pdo, 'privacy', 'id_genitore', $idGenitore, $meta[0], $meta[1]);
        registra_consenso($pdo, 'regolamento', 'id_genitore', $idGenitore, $meta[0], $meta[1]);
        if (!empty($_POST['accetta_foto'])) {
            registra_consenso($pdo, 'liberatoria_foto', 'id_genitore', $idGenitore, $meta[0], $meta[1]);
        }
        consume_token($pdo, $token);
        avanza_stato_giocatore($pdo, $idGiocatore);
        $success = 'Iscrizione del minorenne confermata. Grazie.';
    }
}
?>
<!doctype html><html lang="it"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Conferma iscrizione — <?= htmlspecialchars(SITE_NAME) ?></title><link rel="stylesheet" href="<?= SITE_URL ?>/assets/css/tokens.css?v=redesign-1"><link rel="stylesheet" href="<?= SITE_URL ?>/assets/css/iscrizioni.css"></head><body><main class="app-shell"><section class="card-shell"><h1>Conferma iscrizione</h1>
<?php foreach ($errors as $error): ?><div class="alert alert-error"><?= htmlspecialchars($error) ?></div><?php endforeach; ?>
<?php if ($success): ?><div class="alert alert-success"><?= htmlspecialchars($success) ?></div><?php elseif ($row): ?>
<p class="subtle">Conferma i consensi per completare l’iscrizione del minorenne.</p><form method="post" class="player-form"><input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>"><label class="check-item"><input type="checkbox" name="accetta_privacy" value="1" required> Accetto l’informativa privacy.</label><label class="check-item"><input type="checkbox" name="accetta_regolamento" value="1" required> Accetto il regolamento del torneo.</label><label class="check-item"><input type="checkbox" name="accetta_foto" value="1"> Acconsento all’uso di foto e video (facoltativo).</label><button class="btn btn-gold" type="submit">Conferma iscrizione</button></form>
<?php endif; ?></section></main></body></html>
