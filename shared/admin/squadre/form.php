<?php
// admin/squadre/form.php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/db.php';
require_admin();

$pdo = db();
$id  = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT) ?: null;

$squadra  = null;
$capitano = null; // record giocatore con ruolo = 'Capitano'

if ($id) {
    $s = $pdo->prepare('SELECT * FROM squadre WHERE id = ?');
    $s->execute([$id]);
    $squadra = $s->fetch();
    if (!$squadra) { header('Location: index.php'); exit; }

    // Recupera il capitano attuale dalla rosa (se presente)
    if (!empty($squadra['capitano_giocatore_id'])) {
        $c = $pdo->prepare('SELECT * FROM giocatori WHERE id = ?');
        $c->execute([$squadra['capitano_giocatore_id']]);
        $capitano = $c->fetch() ?: null;
    }
    if (!$capitano) {
        $c = $pdo->prepare("SELECT * FROM giocatori WHERE id_squadra = ? AND ruolo = 'Capitano' LIMIT 1");
        $c->execute([$id]);
        $capitano = $c->fetch() ?: null;
    }
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify()) { $errors[] = 'CSRF non valido.'; goto render; }

    $nome           = trim($_POST['nome']            ?? '');
    $colore         = trim($_POST['colore']          ?? '#1a3a5c');
    $logo_url       = trim($_POST['logo_url']        ?? '');
    $capitano_nome    = trim($_POST['capitano_nome']    ?? '');
    $capitano_cognome = trim($_POST['capitano_cognome'] ?? '');
    $capitano_numero  = filter_input(INPUT_POST, 'capitano_numero_maglia', FILTER_VALIDATE_INT, ['options' => ['min_range' => 0, 'max_range' => 99]]) ?: null;

    if ($nome === '') $errors[] = 'Il nome della squadra è obbligatorio.';
    if (!preg_match('/^#[0-9a-fA-F]{6}$/', $colore)) $colore = '#1a3a5c';

    // Il capitano è obbligatorio: ogni squadra deve avere sempre almeno
    // un giocatore (il capitano) in rosa.
    if ($capitano_nome === '')    $errors[] = 'Il nome del capitano è obbligatorio.';
    if ($capitano_cognome === '') $errors[] = 'Il cognome del capitano è obbligatorio.';

    if (empty($errors)) {
        $nomeCompleto = trim($capitano_nome . ' ' . $capitano_cognome);

        try {
            $pdo->beginTransaction();

            if ($id) {
                // --- Aggiornamento squadra esistente -----------------------
                $pdo->prepare("UPDATE squadre SET nome=?, colore=?, logo_url=?, capitano=? WHERE id=?")
                    ->execute([$nome, $colore, $logo_url ?: null, $nomeCompleto, $id]);

                if ($capitano) {
                    // Aggiorna il giocatore-capitano esistente
                    $pdo->prepare("
                        UPDATE giocatori
                        SET nome=?, cognome=?, numero_maglia=?, ruolo='Capitano'
                        WHERE id=?
                    ")->execute([$capitano_nome, $capitano_cognome, $capitano_numero, $capitano['id']]);
                    $capitanoId = (int)$capitano['id'];
                } else {
                    // La squadra non aveva ancora un capitano in rosa: lo crea
                    $pdo->prepare("
                        INSERT INTO giocatori (id_squadra, nome, cognome, numero_maglia, ruolo)
                        VALUES (?, ?, ?, ?, 'Capitano')
                    ")->execute([$id, $capitano_nome, $capitano_cognome, $capitano_numero]);
                    $capitanoId = (int)$pdo->lastInsertId();
                }

                $pdo->prepare("UPDATE squadre SET capitano_giocatore_id = ? WHERE id = ?")
                    ->execute([$capitanoId, $id]);

            } else {
                // --- Nuova squadra ------------------------------------------
                $pdo->prepare("INSERT INTO squadre (nome, colore, logo_url, capitano) VALUES (?,?,?,?)")
                    ->execute([$nome, $colore, $logo_url ?: null, $nomeCompleto]);
                $newSquadraId = (int)$pdo->lastInsertId();

                // Il capitano viene automaticamente creato come giocatore attivo
                // della nuova squadra: ogni squadra ha quindi sempre almeno
                // un membro in rosa.
                $pdo->prepare("
                    INSERT INTO giocatori (id_squadra, nome, cognome, numero_maglia, ruolo)
                    VALUES (?, ?, ?, ?, 'Capitano')
                ")->execute([$newSquadraId, $capitano_nome, $capitano_cognome, $capitano_numero]);
                $capitanoId = (int)$pdo->lastInsertId();

                $pdo->prepare("UPDATE squadre SET capitano_giocatore_id = ? WHERE id = ?")
                    ->execute([$capitanoId, $newSquadraId]);
            }

            $pdo->commit();
        } catch (Throwable $e) {
            $pdo->rollBack();
            $errors[] = 'Errore durante il salvataggio: ' . $e->getMessage();
            goto render;
        }

        $_SESSION['flash'] = ['type' => 'success', 'text' => $id ? 'Squadra aggiornata.' : 'Squadra aggiunta (capitano inserito automaticamente in rosa).'];
        header('Location: index.php');
        exit;
    }
}

render:
$pageTitle = $id ? 'Modifica Squadra' : 'Nuova Squadra';
require_once __DIR__ . '/../_header.php';

// Valori di default per i campi capitano (precompilazione)
$defCapNome    = $capitano['nome']          ?? ($_POST['capitano_nome']    ?? '');
$defCapCognome = $capitano['cognome']       ?? ($_POST['capitano_cognome'] ?? '');
$defCapNumero  = $capitano['numero_maglia'] ?? ($_POST['capitano_numero_maglia'] ?? '');
?>

<div class="admin-page-header">
  <h1><?= $id ? '✏️ Modifica Squadra' : '➕ Nuova Squadra' ?></h1>
  <p><a href="index.php" style="color:var(--muted);font-size:.85rem;">← Torna alle squadre</a></p>
</div>

<?php foreach ($errors as $e): ?>
  <div class="alert alert-error"><?= htmlspecialchars($e) ?></div>
<?php endforeach; ?>

<form method="post" class="admin-form">
  <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">

  <div class="form-group">
    <label for="nome">Nome Squadra *</label>
    <input type="text" id="nome" name="nome" maxlength="80" required
           placeholder="es. FC Baone"
           value="<?= htmlspecialchars($squadra['nome'] ?? ($_POST['nome'] ?? '')) ?>">
  </div>

  <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;">
    <div class="form-group">
      <label for="colore">Colore Maglia (hex)</label>
      <div style="display:flex;gap:.5rem;align-items:center;">
        <input type="color" id="colorPicker" style="width:42px;height:38px;border:none;background:none;cursor:pointer;padding:0;"
               value="<?= htmlspecialchars($squadra['colore'] ?? '#1a3a5c') ?>">
        <input type="text" id="colore" name="colore" maxlength="7" pattern="^#[0-9a-fA-F]{6}$"
               value="<?= htmlspecialchars($squadra['colore'] ?? ($_POST['colore'] ?? '#1a3a5c')) ?>">
      </div>
    </div>

    <div class="form-group">
      <label for="logo_url">URL Logo (opzionale)</label>
      <input type="url" id="logo_url" name="logo_url" maxlength="255"
             placeholder="https://..."
             value="<?= htmlspecialchars($squadra['logo_url'] ?? ($_POST['logo_url'] ?? '')) ?>">
    </div>
  </div>

  <h3 style="margin-top:2rem;border-top:1px solid rgba(201,168,76,.15);padding-top:1.25rem;">
    👑 Capitano
  </h3>
  <p style="color:var(--muted);font-size:.85rem;margin-top:-.5rem;margin-bottom:1rem;">
    Il capitano viene automaticamente inserito (e mantenuto sincronizzato) come giocatore attivo
    nella rosa di questa squadra. Ogni squadra ha sempre almeno un membro: il capitano.
  </p>

  <div style="display:grid;grid-template-columns:1fr 1fr 120px;gap:1rem;">
    <div class="form-group">
      <label for="capitano_nome">Nome Capitano *</label>
      <input type="text" id="capitano_nome" name="capitano_nome" maxlength="100" required
             placeholder="es. Mario"
             value="<?= htmlspecialchars($defCapNome) ?>">
    </div>

    <div class="form-group">
      <label for="capitano_cognome">Cognome Capitano *</label>
      <input type="text" id="capitano_cognome" name="capitano_cognome" maxlength="100" required
             placeholder="es. Rossi"
             value="<?= htmlspecialchars($defCapCognome) ?>">
    </div>

    <div class="form-group">
      <label for="capitano_numero_maglia">N° Maglia</label>
      <input type="number" id="capitano_numero_maglia" name="capitano_numero_maglia" min="1" max="99"
             value="<?= htmlspecialchars((string)$defCapNumero) ?>">
    </div>
  </div>

  <div class="form-actions">
    <button type="submit" class="btn btn-gold">
      <?= $id ? '💾 Salva Modifiche' : '➕ Aggiungi Squadra' ?>
    </button>
    <a href="index.php" class="btn btn-outline">Annulla</a>
  </div>
</form>

<script>
const picker = document.getElementById('colorPicker');
const text   = document.getElementById('colore');
picker.addEventListener('input', () => text.value = picker.value);
text.addEventListener('input',   () => { if (/^#[0-9a-fA-F]{6}$/.test(text.value)) picker.value = text.value; });
</script>

<?php require_once __DIR__ . '/../_footer.php'; ?>
