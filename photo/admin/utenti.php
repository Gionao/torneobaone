<?php
// photo/admin/utenti.php — Utenti e permessi dell'archivio (solo amministratori)
declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';
require_media_admin();

$utenti = media_utenti_archivio();
$edizioni = media_edizioni();
$idModifica = media_get_int('modifica');
$utenteModifica = null;

if ($idModifica > 0) {
    foreach ($utenti as $u) {
        if ((int) $u['id'] === $idModifica) {
            $utenteModifica = $u;
            break;
        }
    }
    if (!$utenteModifica) {
        media_flash('error', 'Utente non trovato.');
        media_redirect_interno('/utenti.php');
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify()) {
        media_flash('error', 'Sessione scaduta. Ricarica la pagina e riprova.');
        media_redirect_interno('/utenti.php');
    }

    $azione = (string) ($_POST['azione'] ?? '');
    $id = media_post_int('id');

    try {
        if ($azione === 'crea') {
            $username = trim((string) ($_POST['username'] ?? ''));
            $password = (string) ($_POST['password'] ?? '');
            $conferma = (string) ($_POST['password_confirm'] ?? '');
            $ruolo = (string) ($_POST['role'] ?? '');

            if (!preg_match('/^[A-Za-z0-9._-]{3,60}$/', $username)) {
                throw new RuntimeException('Il nome utente deve avere da 3 a 60 caratteri: lettere, numeri, punto, trattino o underscore.');
            }
            if (strlen($password) < 8) {
                throw new RuntimeException('La password deve contenere almeno 8 caratteri.');
            }
            if ($password !== $conferma) {
                throw new RuntimeException('Le password non coincidono.');
            }
            if (!media_ruolo_valido($ruolo)) {
                throw new RuntimeException('Ruolo non valido.');
            }
            $check = db()->prepare('SELECT COUNT(*) FROM admin WHERE username = ?');
            $check->execute([$username]);
            if ((int) $check->fetchColumn() > 0) {
                throw new RuntimeException('Esiste già un utente con questo nome.');
            }

            $nuovoId = media_utente_crea($username, $password, $ruolo);
            if ($ruolo === 'uploader') {
                $accessi = isset($_POST['edizioni']) && is_array($_POST['edizioni'])
                    ? array_map('intval', $_POST['edizioni'])
                    : [];
                media_accesso_imposta($nuovoId, $accessi, media_admin_id());
            }
            media_log('utente_creato', 'admin', $nuovoId, ['username' => $username, 'ruolo' => $ruolo]);
            media_flash('success', 'Utente creato.');
            media_redirect_interno('/utenti.php');
        }

        if ($azione === 'ruolo') {
            $utente = media_utente($id);
            if (!$utente || !in_array((string) $utente['role'], media_ruoli(), true)) {
                throw new RuntimeException('Utente non trovato.');
            }
            $ruolo = (string) ($_POST['role'] ?? '');
            if (!media_ruolo_valido($ruolo)) {
                throw new RuntimeException('Ruolo non valido.');
            }
            if ($id === media_admin_id() && $ruolo !== 'admin') {
                throw new RuntimeException('Non puoi togliere il ruolo amministratore al tuo account.');
            }
            if ($utente['role'] === 'admin' && $ruolo !== 'admin') {
                $check = db()->prepare('SELECT COUNT(*) FROM admin WHERE role = \'admin\' AND id <> ?');
                $check->execute([$id]);
                if ((int) $check->fetchColumn() < 1) {
                    throw new RuntimeException('Deve restare almeno un amministratore.');
                }
            }

            media_utente_aggiorna_ruolo($id, $ruolo);
            if ($ruolo === 'uploader') {
                $accessi = isset($_POST['edizioni']) && is_array($_POST['edizioni'])
                    ? array_map('intval', $_POST['edizioni'])
                    : [];
                media_accesso_imposta($id, $accessi, media_admin_id());
            } else {
                media_accesso_imposta($id, [], media_admin_id());
            }
            media_log('utente_ruolo_modificato', 'admin', $id, [
                'username' => $utente['username'],
                'ruolo' => $ruolo,
            ]);
            media_flash('success', 'Ruolo e accessi aggiornati.');
            media_redirect_interno('/utenti.php?modifica=' . $id);
        }

        if ($azione === 'password') {
            $utente = media_utente($id);
            if (!$utente || !in_array((string) $utente['role'], media_ruoli(), true)) {
                throw new RuntimeException('Utente non trovato.');
            }
            $password = (string) ($_POST['password'] ?? '');
            $conferma = (string) ($_POST['password_confirm'] ?? '');
            if (strlen($password) < 8) {
                throw new RuntimeException('La password deve contenere almeno 8 caratteri.');
            }
            if ($password !== $conferma) {
                throw new RuntimeException('Le password non coincidono.');
            }
            media_utente_password($id, $password);
            media_log('utente_password_modificata', 'admin', $id, ['username' => $utente['username']]);
            media_flash('success', 'Password aggiornata.');
            media_redirect_interno('/utenti.php?modifica=' . $id);
        }

        if ($azione === 'elimina') {
            $utente = media_utente($id);
            if (!$utente) {
                throw new RuntimeException('Utente non trovato.');
            }
            media_utente_elimina($id);
            media_log('utente_eliminato', 'admin', $id, ['username' => $utente['username']]);
            media_flash('success', 'Utente eliminato. I suoi file restano nell\'archivio.');
            media_redirect_interno('/utenti.php');
        }

        throw new RuntimeException('Azione non riconosciuta.');
    } catch (Throwable $e) {
        media_flash('error', $e->getMessage());
        media_redirect_interno('/utenti.php' . ($idModifica > 0 ? '?modifica=' . $idModifica : ''));
    }
}

$accessiUtente = $utenteModifica ? media_accesso_edizioni_admin((int) $utenteModifica['id']) : [];
$ruoliLabel = [
    'uploader' => 'Collaboratore (carica e gestisce i propri file)',
    'editor'   => 'Editor (consulta, scarica e organizza)',
    'admin'    => 'Amministratore (tutti i permessi)',
];

$pageTitle = 'Utenti archivio';
$paginaAttiva = 'utenti';
require_once __DIR__ . '/_header.php';
?>

<div class="admin-page-header">
  <h1>Utenti dell'archivio</h1>
  <p>Gli account sono condivisi con il pannello del torneo. Anche editor e amministratori usano la stessa password.</p>
</div>

<div class="admin-table-wrap" style="margin-bottom:2rem;">
  <table class="admin-table archivio-tabella">
    <thead>
      <tr>
        <th>Utente</th>
        <th>Ruolo</th>
        <th>File caricati</th>
        <th>Edizioni assegnate</th>
        <th>Creato</th>
        <th>Azioni</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($utenti as $u): ?>
        <tr>
          <td><strong><?= media_h($u['username']) ?></strong><?= (int) $u['id'] === media_admin_id() ? ' <span class="archivio-muted">(tu)</span>' : '' ?></td>
          <td><span class="role-badge role-badge--<?= media_h($u['role']) ?>"><?= media_h($u['role']) ?></span></td>
          <td><?= (int) $u['numero_file'] ?></td>
          <td><?= $u['role'] === 'uploader' ? (int) $u['numero_accessi'] : 'Tutte' ?></td>
          <td class="archivio-muted"><?= media_h($u['created_at'] ? date('d/m/Y', strtotime((string) $u['created_at'])) : '—') ?></td>
          <td class="actions">
            <a class="btn btn-sm btn-outline" href="<?= media_h(media_url('utenti.php', ['modifica' => (int) $u['id']])) ?>">Gestisci</a>
          </td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>

<?php if ($utenteModifica): ?>
  <section class="admin-form" style="margin-bottom:2rem;">
    <h2 style="font-family:'Barlow Condensed',sans-serif;color:var(--gold);text-transform:uppercase;margin-bottom:1rem;">
      Ruolo e accessi — <?= media_h($utenteModifica['username']) ?>
    </h2>
    <form method="post">
      <input type="hidden" name="csrf_token" value="<?= media_h(csrf_token()) ?>">
      <input type="hidden" name="azione" value="ruolo">
      <input type="hidden" name="id" value="<?= (int) $utenteModifica['id'] ?>">
      <div class="form-group">
        <label for="ruolo">Ruolo</label>
        <?php if ((int) $utenteModifica['id'] === media_admin_id()): ?>
          <input type="text" value="Amministratore" disabled>
        <?php else: ?>
          <select id="ruolo" name="role" required>
            <?php foreach ($ruoliLabel as $valore => $etichetta): ?>
              <option value="<?= media_h($valore) ?>" <?= $utenteModifica['role'] === $valore ? 'selected' : '' ?>>
                <?= media_h($etichetta) ?>
              </option>
            <?php endforeach; ?>
          </select>
        <?php endif; ?>
      </div>
      <div class="form-group">
        <label>Edizioni consentite (solo collaboratori)</label>
        <?php if ($edizioni === []): ?>
          <p class="archivio-muted archivio-testo-piccolo">Nessuna edizione disponibile.</p>
        <?php else: ?>
          <div class="archivio-accessi">
            <?php foreach ($edizioni as $e): ?>
              <label>
                <input type="checkbox" name="edizioni[]" value="<?= (int) $e['id'] ?>"
                  <?= in_array((int) $e['id'], $accessiUtente, true) ? 'checked' : '' ?>>
                <span><?= media_h($e['nome']) ?> <small>Anno <?= (int) $e['anno'] ?></small></span>
              </label>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
        <small class="archivio-muted">Editor e amministratori vedono tutte le edizioni e ignorano questa selezione.</small>
      </div>
      <div class="form-actions">
        <button type="submit" class="btn btn-gold">Salva ruolo e accessi</button>
        <a class="btn btn-outline" href="<?= media_h(ARCHIVIO_URL) ?>/utenti.php">Chiudi</a>
      </div>
    </form>

    <h2 style="font-family:'Barlow Condensed',sans-serif;color:var(--gold);text-transform:uppercase;margin:1.6rem 0 1rem;">
      Reimposta password
    </h2>
    <form method="post">
      <input type="hidden" name="csrf_token" value="<?= media_h(csrf_token()) ?>">
      <input type="hidden" name="azione" value="password">
      <input type="hidden" name="id" value="<?= (int) $utenteModifica['id'] ?>">
      <div class="form-group">
        <label for="password_utente">Nuova password</label>
        <input type="password" id="password_utente" name="password" required minlength="8" autocomplete="new-password">
      </div>
      <div class="form-group">
        <label for="password_utente_confirm">Conferma password</label>
        <input type="password" id="password_utente_confirm" name="password_confirm" required minlength="8" autocomplete="new-password">
      </div>
      <div class="form-actions">
        <button type="submit" class="btn btn-gold">Aggiorna password</button>
      </div>
    </form>

    <?php if ((int) $utenteModifica['id'] !== media_admin_id()): ?>
      <form method="post" style="margin-top:1.6rem;"
            onsubmit="return confirm('Eliminare questo account? I file caricati restano nell\'archivio.');">
        <input type="hidden" name="csrf_token" value="<?= media_h(csrf_token()) ?>">
        <input type="hidden" name="azione" value="elimina">
        <input type="hidden" name="id" value="<?= (int) $utenteModifica['id'] ?>">
        <button type="submit" class="btn btn-red">Elimina account</button>
      </form>
    <?php endif; ?>
  </section>
<?php endif; ?>

<section class="admin-form">
  <h2 style="font-family:'Barlow Condensed',sans-serif;color:var(--gold);text-transform:uppercase;margin-bottom:1rem;">Nuovo utente</h2>
  <form method="post">
    <input type="hidden" name="csrf_token" value="<?= media_h(csrf_token()) ?>">
    <input type="hidden" name="azione" value="crea">
    <div class="form-group">
      <label for="username">Nome utente *</label>
      <input type="text" id="username" name="username" required minlength="3" maxlength="60" autocomplete="off" pattern="[A-Za-z0-9._-]+">
    </div>
    <div class="form-group">
      <label for="password">Password *</label>
      <input type="password" id="password" name="password" required minlength="8" autocomplete="new-password">
    </div>
    <div class="form-group">
      <label for="password_confirm">Conferma password *</label>
      <input type="password" id="password_confirm" name="password_confirm" required minlength="8" autocomplete="new-password">
    </div>
    <div class="form-group">
      <label for="role">Ruolo *</label>
      <select id="role" name="role" required>
        <option value="uploader">Collaboratore (carica e gestisce i propri file)</option>
        <option value="editor">Editor (consulta, scarica e organizza)</option>
        <option value="admin">Amministratore (tutti i permessi)</option>
      </select>
    </div>
    <div class="form-group">
      <label>Edizioni consentite (per i collaboratori)</label>
      <?php if ($edizioni === []): ?>
        <p class="archivio-muted archivio-testo-piccolo">Nessuna edizione disponibile: potrai assegnarle in seguito.</p>
      <?php else: ?>
        <div class="archivio-accessi">
          <?php foreach ($edizioni as $e): ?>
            <label>
              <input type="checkbox" name="edizioni[]" value="<?= (int) $e['id'] ?>">
              <span><?= media_h($e['nome']) ?> <small>Anno <?= (int) $e['anno'] ?></small></span>
            </label>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>
    <div class="form-actions">
      <button type="submit" class="btn btn-gold">Crea utente</button>
    </div>
  </form>
</section>

<?php require_once __DIR__ . '/_footer.php'; ?>
