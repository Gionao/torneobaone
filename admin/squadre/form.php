<?php
// admin/squadre/form.php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/db.php';
require_admin();

$pdo = db();
$id  = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT) ?: null;

$squadra = null;
if ($id) {
    $s = $pdo->prepare('SELECT * FROM squadre WHERE id = ?');
    $s->execute([$id]);
    $squadra = $s->fetch();
    if (!$squadra) { header('Location: index.php'); exit; }
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify()) { $errors[] = 'CSRF non valido.'; goto render; }

    $nome     = trim($_POST['nome']     ?? '');
    $colore   = trim($_POST['colore']   ?? '#1a3a5c');
    $logo_url = trim($_POST['logo_url'] ?? '');

    if ($nome === '') $errors[] = 'Il nome della squadra è obbligatorio.';
    if (!preg_match('/^#[0-9a-fA-F]{6}$/', $colore)) $colore = '#1a3a5c';

    if (empty($errors)) {
        if ($id) {
            $pdo->prepare("UPDATE squadre SET nome=?, colore=?, logo_url=? WHERE id=?")
                ->execute([$nome, $colore, $logo_url ?: null, $id]);
        } else {
            $pdo->prepare("INSERT INTO squadre (nome, colore, logo_url) VALUES (?,?,?)")
                ->execute([$nome, $colore, $logo_url ?: null]);
        }
        $_SESSION['flash'] = ['type' => 'success', 'text' => $id ? 'Squadra aggiornata.' : 'Squadra aggiunta.'];
        header('Location: index.php');
        exit;
    }
}

render:
$pageTitle = $id ? 'Modifica Squadra' : 'Nuova Squadra';
require_once __DIR__ . '/../_header.php';
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

  <div class="form-group">
    <label>Capitano dell'iscrizione</label>
    <?php
      $capitanoLabel = 'Non assegnato';
      if (!empty($squadra['id_capitano'])) {
          $capitanoStmt = $pdo->prepare('SELECT CONCAT(nome, " ", cognome) AS nome_completo FROM capitani WHERE id = ? LIMIT 1');
          $capitanoStmt->execute([$squadra['id_capitano']]);
          $capitanoNome = trim((string) $capitanoStmt->fetchColumn());
          if ($capitanoNome !== '') {
              $capitanoLabel = $capitanoNome;
          }
      }
    ?>
    <div class="form-note" style="padding:.75rem .9rem;border:1px solid rgba(231,169,50,.25);border-radius:8px;background:rgba(255,255,255,.02);color:var(--muted);">
      <?= htmlspecialchars($capitanoLabel) ?>
    </div>
    <small style="display:block;margin-top:.45rem;color:var(--muted);">Il capitano ufficiale è collegato al profilo account in <strong>capitani</strong>.</small>
  </div>

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