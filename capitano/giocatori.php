<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth_capitano.php';
require_once __DIR__ . '/../includes/token_helper.php';
require_once __DIR__ . '/../includes/mailer.php';
require_once __DIR__ . '/../includes/iscrizione_helper.php';

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
$shareUrl = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['capitano_csrf_token'] ?? '', $_POST['csrf_token'])) {
        $errors[] = 'Token di sicurezza non valido.';
    } else {
        $action = (string) ($_POST['action'] ?? 'add');
        if ($action === 'delete') {
            $id = (int) ($_POST['id_giocatore'] ?? 0);
            $del = $pdo->prepare('DELETE FROM giocatori WHERE id = ? AND id_squadra = ?');
            $del->execute([$id, (int) $squadraRow['id']]);
            $success = $del->rowCount() ? 'Giocatore eliminato.' : 'Giocatore non trovato.';
        } elseif ($action === 'copy_link') {
            $id = (int) ($_POST['id_giocatore'] ?? 0);
            $player = $pdo->prepare('SELECT id, nome, email FROM giocatori WHERE id = ? AND id_squadra = ? LIMIT 1');
            $player->execute([$id, (int) $squadraRow['id']]);
            $playerRow = $player->fetch();
            if ($playerRow) {
                $pdo->prepare('UPDATE token_verifica SET invalidato_at = NOW() WHERE tipo = "invito_giocatore" AND id_giocatore = ? AND usato_at IS NULL AND invalidato_at IS NULL')->execute([$id]);
                $newToken = create_verification_token($pdo, 'invito_giocatore', $idCapitano, $id, null, 168);
                if ($newToken) {
                    $shareUrl = build_site_link('/iscrizione/giocatore.php', $newToken);
                    $success = 'Link pronto da copiare e condividere.';
                }
            }
        } elseif ($action === 'resend_parent') {
            $id = (int) ($_POST['id_giocatore'] ?? 0);
            $parentStmt = $pdo->prepare('SELECT p.id, p.nome, p.cognome, p.email, g.nome AS nome_giocatore, g.cognome AS cognome_giocatore FROM genitori p JOIN giocatori g ON g.id = p.id_giocatore WHERE g.id = ? AND g.id_squadra = ? LIMIT 1');
            $parentStmt->execute([$id, (int) $squadraRow['id']]);
            $parent = $parentStmt->fetch();
            if (!$parent) {
                $errors[] = 'Non sono stati trovati i dati del genitore per questo giocatore.';
            } else {
                invalidate_previous_tokens($pdo, 'consenso_genitore', null, (int) $parent['id']);
                $parentToken = create_verification_token($pdo, 'consenso_genitore', null, $id, (int) $parent['id'], 72);
                if ($parentToken) {
                    $parentLink = build_site_link('/iscrizione/conferma-genitore.php', $parentToken);
                    $parentBody = build_email_template(
                        'Conferma iscrizione minorenne',
                        "Buongiorno {$parent['nome']} {$parent['cognome']},\n\ncompleta l'iscrizione di {$parent['nome_giocatore']} {$parent['cognome_giocatore']} al torneo.",
                        'Completa iscrizione',
                        $parentLink,
                        'Il link scade tra 72 ore.'
                    );
                    if (send_email((string) $parent['email'], 'Conferma iscrizione minorenne', $parentBody)) {
                        $success = 'Nuovo link di approvazione inviato al genitore.';
                    } else {
                        $errors[] = 'Non è stato possibile inviare la mail al genitore. Riprova più tardi.';
                    }
                } else {
                    $errors[] = 'Non è stato possibile creare un nuovo link per il genitore.';
                }
            }
        } else {
        $nome = trim((string) ($_POST['nome'] ?? ''));
        $cognome = trim((string) ($_POST['cognome'] ?? ''));
        $email = trim(strtolower((string) ($_POST['email'] ?? '')));
        if ($nome === '' || $cognome === '' || $email === '') {
          $errors[] = 'Nome, cognome ed email sono obbligatori.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
          $errors[] = 'Inserisci un indirizzo email valido.';
        }

        if (!$errors) {
          $duplicate = $pdo->prepare('SELECT id FROM giocatori WHERE LOWER(TRIM(nome)) = LOWER(TRIM(?)) AND LOWER(TRIM(cognome)) = LOWER(TRIM(?)) AND LOWER(TRIM(email)) = LOWER(TRIM(?)) LIMIT 1');
          $duplicate->execute([$nome, $cognome, $email]);
          if ($duplicate->fetch()) {
              $errors[] = 'Questo giocatore (nome, cognome ed email) è già presente nel sistema.';
          }
        }
        if (!$errors) {
          $count = $pdo->prepare('SELECT COUNT(*) FROM giocatori WHERE id_squadra = ?');
          $count->execute([$squadraRow['id']]);
          if ((int) $count->fetchColumn() >= 9) {
            $errors[] = 'La squadra ha già raggiunto il massimo di 9 giocatori.';
          } else {
            $stmt = $pdo->prepare('INSERT INTO giocatori (id_squadra, nome, cognome, email, stato_iscrizione) VALUES (?, ?, ?, ?, "non_registrato")');
            $stmt->execute([$squadraRow['id'], $nome, $cognome, $email]);
            $idGiocatore = (int) $pdo->lastInsertId();

            $token = create_verification_token($pdo, 'invito_giocatore', $idCapitano, $idGiocatore, null, 168);
            if ($token !== null) {
              $url = build_site_link('/iscrizione/giocatore.php', $token);
              $body = build_email_template(
                'Invito iscrizione',
                "Ciao {$nome},\n\nsei stato invitato a completare la tua iscrizione al torneo. Clicca sul pulsante qui sotto per aprire il modulo.",
                'Completa l’iscrizione',
                $url,
                'Il link scade tra 7 giorni.',
                'Se non hai richiesto questo invito, puoi ignorare questa email.'
              );
              $sent = send_email($email, 'Invito iscrizione', $body);
              $success = $sent ? 'Giocatore aggiunto e invito inviato.' : 'Giocatore aggiunto. Non è stato possibile inviare l’email: puoi copiare il link dalla lista.';
            } else {
              $errors[] = 'Impossibile generare il link di invito.';
            }
        }
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
  <div class="alert alert-error" role="alert"><?= htmlspecialchars($error) ?></div>
<?php endforeach; endif; ?>
<?php if ($success): ?>
  <div class="alert alert-success" role="status"><?= htmlspecialchars($success) ?></div>
<?php endif; ?>
<?php if ($shareUrl): ?><div class="share-link"><label for="share-link">Link da condividere</label><input id="share-link" readonly value="<?= htmlspecialchars($shareUrl) ?>"><button type="button" class="btn btn-outline" onclick="copyInviteLink(this)">Copia link</button></div><script>function copyInviteLink(button){const field=document.getElementById('share-link');field.select();if(navigator.clipboard&&window.isSecureContext){navigator.clipboard.writeText(field.value).then(()=>button.textContent='Copiato');}else{document.execCommand('copy');button.textContent='Copiato';}}</script><?php endif; ?>

<form method="post" class="admin-form player-add-form captain-panel" style="margin-bottom:2rem;">
  <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['capitano_csrf_token'] ?? capitano_csrf_token()) ?>">
  <p class="required-note">Tutti i campi sono obbligatori.</p>
  <div class="form-group">
    <label for="player_nome">Nome <span class="required-mark" aria-hidden="true">*</span></label>
  <input id="player_nome" type="text" name="nome" value="<?= $success ? '' : htmlspecialchars($_POST['nome'] ?? '') ?>" required>
  </div>
  <div class="form-group">
    <label for="player_cognome">Cognome <span class="required-mark" aria-hidden="true">*</span></label>
  <input id="player_cognome" type="text" name="cognome" value="<?= $success ? '' : htmlspecialchars($_POST['cognome'] ?? '') ?>" required>
  </div>
  <div class="form-group">
    <label for="player_email">Email <span class="required-mark" aria-hidden="true">*</span></label>
  <input id="player_email" type="email" name="email" value="<?= $success ? '' : htmlspecialchars($_POST['email'] ?? '') ?>" required>
  </div>
  <button type="submit" class="btn btn-gold">Aggiungi giocatore</button>
</form>

<div class="admin-form captain-panel">
  <h2 style="margin-bottom:1rem; font-family:'Barlow Condensed',sans-serif; text-transform:uppercase; letter-spacing:.08em; color:var(--gold);">Elenco</h2>
  <?php if (!$rows): ?>
    <p class="empty-msg">Nessun giocatore inserito.</p>
  <?php else: ?>
    <div class="admin-table-wrap player-table-wrap"><table class="admin-table player-table">
      <thead>
        <tr><th>Giocatore</th><th>Stato</th><th>Invito e azioni</th></tr>
      </thead>
      <tbody>
        <?php foreach ($rows as $row): ?>
          <tr>
            <td data-label="Giocatore"><?= htmlspecialchars(($row['nome'] ?? '') . ' ' . ($row['cognome'] ?? '')) ?></td>
            <td data-label="Stato"><span class="state-pill <?= ($row['stato_iscrizione'] ?? '') === 'completo' ? 'ok' : (($row['stato_iscrizione'] ?? '') === 'in_attesa_genitore' ? 'parent-wait' : '') ?>"><?= htmlspecialchars(etichetta_stato_iscrizione((string) ($row['stato_iscrizione'] ?? 'non_registrato'))) ?></span></td>
            <td data-label="Invito e azioni">
              <?php
                $tokenRow = $pdo->prepare('SELECT token FROM token_verifica WHERE tipo = "invito_giocatore" AND id_giocatore = ? AND usato_at IS NULL AND invalidato_at IS NULL AND scadenza > NOW() ORDER BY created_at DESC LIMIT 1');
                $tokenRow->execute([$row['id']]);
                $tokenVal = $tokenRow->fetchColumn();
                $parentRow = $pdo->prepare('SELECT id FROM genitori WHERE id_giocatore = ? LIMIT 1');
                $parentRow->execute([$row['id']]);
                $hasParent = (bool) $parentRow->fetchColumn();
              ?>
              <?php if ($tokenVal): ?>
                <span class="empty-msg">Link disponibile tramite “Copia link”.</span>
              <?php else: ?>
                <span class="empty-msg">Nessun link attivo</span>
              <?php endif; ?>
              <div class="row-actions">
                <form method="post"><input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['capitano_csrf_token'] ?? capitano_csrf_token()) ?>"><input type="hidden" name="action" value="copy_link"><input type="hidden" name="id_giocatore" value="<?= (int) $row['id'] ?>"><button class="btn btn-outline" type="submit">Copia link</button></form>
                <?php if ($hasParent && ($row['stato_iscrizione'] ?? '') === 'in_attesa_genitore'): ?><form method="post"><input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['capitano_csrf_token'] ?? capitano_csrf_token()) ?>"><input type="hidden" name="action" value="resend_parent"><input type="hidden" name="id_giocatore" value="<?= (int) $row['id'] ?>"><button class="btn btn-outline" type="submit">Reinvia al genitore</button></form><?php endif; ?>
                <form method="post" onsubmit="return confirm('Eliminare questo giocatore?')"><input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['capitano_csrf_token'] ?? capitano_csrf_token()) ?>"><input type="hidden" name="action" value="delete"><input type="hidden" name="id_giocatore" value="<?= (int) $row['id'] ?>"><button class="btn btn-danger" type="submit">Elimina</button></form>
              </div>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table></div>
  <?php endif; ?>
</div>

<?php require_once __DIR__ . '/_footer.php'; ?>
