<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth_capitano.php';
require_once __DIR__ . '/../includes/token_helper.php';

require_capitano();

$pdo = db();
$idCapitano = (int) ($_SESSION['capitano_id'] ?? 0);

$squadraRow = null;
if (table_has_column($pdo, 'squadre', 'id_capitano')) {
    $squadra = $pdo->prepare('SELECT id FROM squadre WHERE id_capitano = ? LIMIT 1');
    $squadra->execute([$idCapitano]);
    $squadraRow = $squadra->fetch();
}
if (!$squadraRow) {
    header('Location: ' . SITE_URL . '/capitano/dashboard.php');
    exit;
}

$errors = [];
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['capitano_csrf_token'] ?? '', $_POST['csrf_token'])) {
        $errors[] = 'Token di sicurezza non valido.';
    } else {
        $nome = trim((string) ($_POST['nome'] ?? ''));
        $cognome = trim((string) ($_POST['cognome'] ?? ''));
        if ($nome === '' || $cognome === '') {
            $errors[] = 'Nome e cognome sono obbligatori.';
        }

        if (!$errors) {
            $stmt = $pdo->prepare('INSERT INTO giocatori (id_squadra, nome, cognome, stato_iscrizione) VALUES (?, ?, ?, "invitato")');
            $stmt->execute([$squadraRow['id'], $nome, $cognome]);
            $idGiocatore = (int) $pdo->lastInsertId();

            $token = create_verification_token($pdo, 'invito_giocatore', $idCapitano, $idGiocatore, null, 168);
            if ($token !== null) {
                $url = SITE_URL . '/iscrizione/giocatore.php?token=' . rawurlencode($token);
                $body = "Ciao {$nome},<br><br>ti è stato invitato a completare la tua iscrizione al torneo. Apri questo link:<br><br><a href=\"{$url}\">{$url}</a>";
                send_mail_message(($nome . '@example.com'), 'Invito iscrizione', $body);
                $success = 'Giocatore creato e invito generato.';
            } else {
                $errors[] = 'Impossibile generare il link di invito.';
            }
        }
    }
}

$giocatori = $pdo->prepare('SELECT * FROM giocatori WHERE id_squadra = ? ORDER BY cognome, nome');
$giocatori->execute([$squadraRow['id']]);
$rows = $giocatori->fetchAll();

$pageTitle = 'Giocatori';
require_once __DIR__ . '/_header.php';
?>

<div class="admin-page-header">
  <h1>Giocatori</h1>
  <p>Aggiungi i giocatori iniziali e genera i link di invito.</p>
</div>

<?php if ($errors): foreach ($errors as $error): ?>
  <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
<?php endforeach; endif; ?>
<?php if ($success): ?>
  <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
<?php endif; ?>

<form method="post" class="admin-form" style="margin-bottom:2rem;">
  <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['capitano_csrf_token'] ?? capitano_csrf_token()) ?>">
  <div class="form-group">
    <label>Nome</label>
    <input type="text" name="nome" value="<?= htmlspecialchars($_POST['nome'] ?? '') ?>" required>
  </div>
  <div class="form-group">
    <label>Cognome</label>
    <input type="text" name="cognome" value="<?= htmlspecialchars($_POST['cognome'] ?? '') ?>" required>
  </div>
  <button type="submit" class="btn btn-gold">Aggiungi giocatore</button>
</form>

<div class="admin-form">
  <h2 style="margin-bottom:1rem; font-family:'Barlow Condensed',sans-serif; text-transform:uppercase; letter-spacing:.08em; color:var(--gold);">Elenco</h2>
  <?php if (!$rows): ?>
    <p class="empty-msg">Nessun giocatore inserito.</p>
  <?php else: ?>
    <table class="admin-table">
      <thead>
        <tr><th>Giocatore</th><th>Stato</th><th>Invito</th></tr>
      </thead>
      <tbody>
        <?php foreach ($rows as $row): ?>
          <tr>
            <td><?= htmlspecialchars(($row['nome'] ?? '') . ' ' . ($row['cognome'] ?? '')) ?></td>
            <td><span class="state-pill"><?= htmlspecialchars($row['stato_iscrizione'] ?? 'invitato') ?></span></td>
            <td>
              <?php
                $tokenRow = $pdo->prepare('SELECT token FROM token_verifica WHERE tipo = "invito_giocatore" AND id_giocatore = ? AND usato_at IS NULL AND invalidato_at IS NULL AND scadenza > NOW() ORDER BY created_at DESC LIMIT 1');
                $tokenRow->execute([$row['id']]);
                $tokenVal = $tokenRow->fetchColumn();
              ?>
              <?php if ($tokenVal): ?>
                <a href="<?= SITE_URL ?>/iscrizione/giocatore.php?token=<?= rawurlencode((string) $tokenVal) ?>" target="_blank">Apri link</a>
              <?php else: ?>
                <span class="empty-msg">Nessun link attivo</span>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>
</div>

<?php require_once __DIR__ . '/_footer.php'; ?>
