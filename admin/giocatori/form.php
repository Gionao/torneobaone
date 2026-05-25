<?php
// admin/giocatori/form.php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/db.php';
require_admin();

$pdo = db();
$id  = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT) ?: null;

// Pre-seleziona squadra se arriva dalla pagina rosa
$sqDefault = filter_input(INPUT_GET, 'squadra', FILTER_VALIDATE_INT) ?: null;

$giocatore = null;
if ($id) {
    $s = $pdo->prepare('SELECT * FROM giocatori WHERE id = ?');
    $s->execute([$id]);
    $giocatore = $s->fetch();
    if (!$giocatore) { header('Location: index.php'); exit; }
}

$squadre = $pdo->query('SELECT id, nome FROM squadre ORDER BY nome')->fetchAll();
$errors  = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify()) { $errors[] = 'CSRF non valido.'; goto render; }

    $id_squadra    = filter_input(INPUT_POST, 'id_squadra',     FILTER_VALIDATE_INT);
    $nome          = trim($_POST['nome']     ?? '');
    $cognome       = trim($_POST['cognome']  ?? '');
    $numero_maglia = filter_input(INPUT_POST, 'numero_maglia',  FILTER_VALIDATE_INT, ['options' => ['min_range' => 0, 'max_range' => 99]]) ?: null;
    $e_portiere    = isset($_POST['e_portiere']) ? 1 : 0;

    if (!$id_squadra) $errors[] = 'Seleziona la squadra.';
    if ($nome === '')    $errors[] = 'Il nome è obbligatorio.';
    if ($cognome === '') $errors[] = 'Il cognome è obbligatorio.';

    if (empty($errors)) {
        if ($id) {
            $pdo->prepare("
                UPDATE giocatori SET id_squadra=?, nome=?, cognome=?, numero_maglia=?, e_portiere=?
                WHERE id=?
            ")->execute([$id_squadra, $nome, $cognome, $numero_maglia, $e_portiere, $id]);
        } else {
            $pdo->prepare("
                INSERT INTO giocatori (id_squadra, nome, cognome, numero_maglia, e_portiere)
                VALUES (?,?,?,?,?)
            ")->execute([$id_squadra, $nome, $cognome, $numero_maglia, $e_portiere]);
        }
        $_SESSION['flash'] = ['type' => 'success', 'text' => $id ? 'Giocatore aggiornato.' : 'Giocatore aggiunto.'];
        $backSquadra = $_POST['id_squadra'] ?? '';
        header('Location: index.php' . ($backSquadra ? '?squadra='.$backSquadra : ''));
        exit;
    }
}

render:
$pageTitle = $id ? 'Modifica Giocatore' : 'Nuovo Giocatore';
require_once __DIR__ . '/../_header.php';
?>

<div class="admin-page-header">
  <h1><?= $id ? '✏️ Modifica Giocatore' : '➕ Nuovo Giocatore' ?></h1>
  <p><a href="index.php" style="color:var(--muted);font-size:.85rem;">← Torna ai giocatori</a></p>
</div>

<?php foreach ($errors as $e): ?>
  <div class="alert alert-error"><?= htmlspecialchars($e) ?></div>
<?php endforeach; ?>

<form method="post" class="admin-form">
  <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">

  <div class="form-group">
    <label for="id_squadra">Squadra *</label>
    <select id="id_squadra" name="id_squadra" required>
      <option value="">— Seleziona —</option>
      <?php
        $selSq = $giocatore['id_squadra'] ?? ($_POST['id_squadra'] ?? $sqDefault);
        foreach ($squadre as $s):
      ?>
        <option value="<?= $s['id'] ?>" <?= $selSq == $s['id'] ? 'selected' : '' ?>>
          <?= htmlspecialchars($s['nome']) ?>
        </option>
      <?php endforeach; ?>
    </select>
  </div>

  <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;">
    <div class="form-group">
      <label for="nome">Nome *</label>
      <input type="text" id="nome" name="nome" maxlength="100" required
             placeholder="es. Mario"
             value="<?= htmlspecialchars($giocatore['nome'] ?? ($_POST['nome'] ?? '')) ?>">
    </div>

    <div class="form-group">
      <label for="cognome">Cognome *</label>
      <input type="text" id="cognome" name="cognome" maxlength="100" required
             placeholder="es. Rossi"
             value="<?= htmlspecialchars($giocatore['cognome'] ?? ($_POST['cognome'] ?? '')) ?>">
    </div>
  </div>

  <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;">
    <div class="form-group">
      <label for="numero_maglia">Numero Maglia</label>
      <input type="number" id="numero_maglia" name="numero_maglia" min="1" max="99"
             value="<?= htmlspecialchars((string)($giocatore['numero_maglia'] ?? ($_POST['numero_maglia'] ?? ''))) ?>">
    </div>

    <div class="form-group" style="display:flex;align-items:center;padding-top:1.5rem;">
      <label style="display:flex;align-items:center;gap:.5rem;cursor:pointer;text-transform:none;font-size:.9rem;">
        <input type="checkbox" name="e_portiere" value="1"
               <?= ($giocatore['e_portiere'] ?? 0) ? 'checked' : '' ?>>
        🧤 È portiere
      </label>
    </div>
  </div>

  <div class="form-actions">
    <button type="submit" class="btn btn-gold">
      <?= $id ? '💾 Salva Modifiche' : '➕ Aggiungi Giocatore' ?>
    </button>
    <a href="index.php" class="btn btn-outline">Annulla</a>
  </div>
</form>

<?php require_once __DIR__ . '/../_footer.php'; ?>