<?php
// admin/partite/form.php
// ============================================================
//  Admin — Inserimento / Modifica partita
// ============================================================

declare(strict_types=1);

require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/db.php';

require_partite_access();

$pdo = db();

// ── Modalità: modifica se ?id= presente ─────────────────────
$id      = filter_input(INPUT_GET,  'id', FILTER_VALIDATE_INT)
        ?: filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
$editing = (bool) $id;
$partita = null;

if ($editing) {
    $stmt = $pdo->prepare("SELECT * FROM partite WHERE id = ? LIMIT 1");
    $stmt->execute([$id]);
    $partita = $stmt->fetch();
    if (!$partita) { header('Location: index.php'); exit; }
}

// ── Lookup: fasi e squadre ───────────────────────────────────
$fasi    = $pdo->query("SELECT id, nome FROM fasi    ORDER BY ordine ASC, nome ASC")->fetchAll();
$squadre = $pdo->query("SELECT id, nome FROM squadre ORDER BY nome ASC")->fetchAll();

$errors = [];

// ── Gestione POST ────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (!csrf_verify()) {
        $errors[] = 'Token CSRF non valido. Riprova.';
        goto render;
    }

    // Campi obbligatori
    $id_fase         = filter_input(INPUT_POST, 'id_fase',         FILTER_VALIDATE_INT);
    $id_squadra_casa = filter_input(INPUT_POST, 'id_squadra_casa', FILTER_VALIDATE_INT);
    $id_squadra_osp  = filter_input(INPUT_POST, 'id_squadra_osp',  FILTER_VALIDATE_INT);

    // Campi facoltativi
    $data_ora_raw = trim($_POST['data_ora'] ?? '');
    $campo        = trim($_POST['campo']    ?? '');
    $note         = trim($_POST['note']     ?? '');

    // Normalizza data da <input type="datetime-local"> → MySQL DATETIME
    $data_ora = null;
    if ($data_ora_raw !== '') {
        $dt = DateTime::createFromFormat('Y-m-d\TH:i', $data_ora_raw);
        $data_ora = $dt ? $dt->format('Y-m-d H:i:s') : null;
        if (!$dt) $errors[] = 'Formato data/ora non valido.';
    }

    // Validazione
    if (!$id_fase)         $errors[] = 'Seleziona la fase del torneo.';
    if (!$id_squadra_casa) $errors[] = 'Seleziona la squadra di casa.';
    if (!$id_squadra_osp)  $errors[] = 'Seleziona la squadra ospite.';
    if ($id_squadra_casa && $id_squadra_osp && $id_squadra_casa === $id_squadra_osp)
        $errors[] = 'La squadra di casa e quella ospite non possono coincidere.';

    // Verifica che la coppia di squadre non esista già nella stessa fase
    // (solo in inserimento, o in modifica escludendo sé stessa)
    if (empty($errors)) {
        $dupSql = "
            SELECT id FROM partite
            WHERE id_fase = ?
              AND id_squadra_casa = ?
              AND id_squadra_osp  = ?
        ";
        $dupParams = [$id_fase, $id_squadra_casa, $id_squadra_osp];
        if ($editing) {
            $dupSql   .= ' AND id <> ?';
            $dupParams[] = $id;
        }
        $dupStmt = $pdo->prepare($dupSql);
        $dupStmt->execute($dupParams);
        if ($dupStmt->fetch()) {
            $errors[] = 'Esiste già una partita tra queste squadre in questa fase.';
        }
    }

    if (empty($errors)) {
        if ($editing) {
            $sql = "
                UPDATE partite
                SET id_fase = ?, id_squadra_casa = ?, id_squadra_osp = ?,
                    data_ora = ?, campo = ?, note = ?, updated_at = NOW()
                WHERE id = ?
            ";
            $pdo->prepare($sql)->execute([
                $id_fase, $id_squadra_casa, $id_squadra_osp,
                $data_ora, $campo ?: null, $note ?: null,
                $id,
            ]);
            $_SESSION['flash'] = ['type' => 'success', 'text' => 'Partita aggiornata con successo!'];
        } else {
            $sql = "
                INSERT INTO partite
                    (id_fase, id_squadra_casa, id_squadra_osp, data_ora, campo, note, giocata, created_at, updated_at)
                VALUES (?, ?, ?, ?, ?, ?, 0, NOW(), NOW())
            ";
            $pdo->prepare($sql)->execute([
                $id_fase, $id_squadra_casa, $id_squadra_osp,
                $data_ora, $campo ?: null, $note ?: null,
            ]);
            $_SESSION['flash'] = ['type' => 'success', 'text' => 'Partita aggiunta al calendario!'];
        }

        header('Location: index.php');
        exit;
    }
}

// ── Ripopola i valori del form dopo un errore POST ───────────
$val = [
    'id_fase'         => $_POST['id_fase']         ?? $partita['id_fase']         ?? '',
    'id_squadra_casa' => $_POST['id_squadra_casa']  ?? $partita['id_squadra_casa'] ?? '',
    'id_squadra_osp'  => $_POST['id_squadra_osp']   ?? $partita['id_squadra_osp']  ?? '',
    'data_ora'        => '',
    'campo'           => $_POST['campo']            ?? $partita['campo']           ?? '',
    'note'            => $_POST['note']             ?? $partita['note']            ?? '',
];

// Converti data DB → formato datetime-local per l'input HTML
$rawDataOra = $_POST['data_ora'] ?? ($partita['data_ora'] ?? '');
if ($rawDataOra) {
    $dt = new DateTime($rawDataOra);
    $val['data_ora'] = $dt->format('Y-m-d\TH:i');
}

render:
$pageTitle = $editing ? 'Modifica Partita' : 'Nuova Partita';
require_once __DIR__ . '/../_header.php';
?>

<div class="admin-page-header">
  <h1><?= $editing ? '✏️ Modifica Partita' : '➕ Nuova Partita' ?></h1>
  <p>
    <?= $editing ? 'Modifica i dati della partita selezionata.' : 'Aggiungi una nuova partita al calendario del torneo.' ?>
    &ensp;<a href="index.php" style="color:var(--muted);font-size:.85rem;">← Torna al calendario</a>
  </p>
</div>

<?php foreach ($errors as $e): ?>
  <div class="alert alert-error"><?= htmlspecialchars($e) ?></div>
<?php endforeach; ?>

<form method="post" class="admin-form" style="max-width:700px;" novalidate>
  <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
  <?php if ($editing): ?>
    <input type="hidden" name="id" value="<?= $id ?>">
  <?php endif; ?>

  <!-- ── Fase ────────────────────────────────────────────── -->
  <div class="form-group">
    <label for="id_fase">Fase del torneo *</label>
    <select id="id_fase" name="id_fase" required>
      <option value="">— Seleziona fase —</option>
      <?php foreach ($fasi as $f): ?>
        <option value="<?= $f['id'] ?>"
          <?= (string)$val['id_fase'] === (string)$f['id'] ? 'selected' : '' ?>>
          <?= htmlspecialchars($f['nome']) ?>
        </option>
      <?php endforeach; ?>
    </select>
  </div>

  <!-- ── Squadre ─────────────────────────────────────────── -->
  <div style="display:grid;grid-template-columns:1fr auto 1fr;gap:.8rem;align-items:end;margin-bottom:1.2rem;">

    <div class="form-group" style="margin-bottom:0;">
      <label for="id_squadra_casa">Squadra Casa *</label>
      <select id="id_squadra_casa" name="id_squadra_casa" required>
        <option value="">— Seleziona —</option>
        <?php foreach ($squadre as $s): ?>
          <option value="<?= $s['id'] ?>"
            <?= (string)$val['id_squadra_casa'] === (string)$s['id'] ? 'selected' : '' ?>>
            <?= htmlspecialchars($s['nome']) ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>

    <div style="padding-bottom:.5rem;text-align:center;">
      <span style="
        font-family:'Barlow Condensed',sans-serif;
        font-size:1rem;font-weight:700;
        color:var(--muted);letter-spacing:.08em;
      ">VS</span>
    </div>

    <div class="form-group" style="margin-bottom:0;">
      <label for="id_squadra_osp">Squadra Ospite *</label>
      <select id="id_squadra_osp" name="id_squadra_osp" required>
        <option value="">— Seleziona —</option>
        <?php foreach ($squadre as $s): ?>
          <option value="<?= $s['id'] ?>"
            <?= (string)$val['id_squadra_osp'] === (string)$s['id'] ? 'selected' : '' ?>>
            <?= htmlspecialchars($s['nome']) ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>

  </div>

  <!-- Avviso squadre uguali (JS) -->
  <div id="sameTeamWarn" class="alert alert-error" style="display:none;margin-top:-.6rem;margin-bottom:1.2rem;">
    ⚠️ La squadra di casa e quella ospite non possono coincidere.
  </div>

  <!-- ── Data / Ora ───────────────────────────────────────── -->
  <div style="display:grid;grid-template-columns:1fr 1fr;gap:1.2rem;">

    <div class="form-group">
      <label for="data_ora">Data e Ora</label>
      <input type="datetime-local" id="data_ora" name="data_ora"
             value="<?= htmlspecialchars($val['data_ora']) ?>">
    </div>

    <div class="form-group">
      <label for="campo">Campo / Luogo</label>
      <input type="text" id="campo" name="campo" maxlength="120"
             placeholder="es. Campo Sportivo Baone"
             value="<?= htmlspecialchars($val['campo']) ?>">
    </div>

  </div>

  <!-- ── Note ────────────────────────────────────────────── -->
  <div class="form-group">
    <label for="note">Note</label>
    <textarea id="note" name="note" rows="3" maxlength="500"
              placeholder="Note aggiuntive (facoltativo)…"><?= htmlspecialchars($val['note']) ?></textarea>
  </div>

  <!-- ── Anteprima matchup ────────────────────────────────── -->
  <div id="matchupPreview" style="
    display:none;
    background:rgba(201,168,76,.06);
    border:1px solid rgba(201,168,76,.15);
    border-radius:var(--radius);
    padding:.9rem 1.2rem;
    margin-bottom:1.4rem;
    display:flex;align-items:center;gap:.8rem;flex-wrap:wrap;
  ">
    <span id="prevCasa" style="font-family:'Barlow Condensed',sans-serif;font-size:1.15rem;font-weight:700;"></span>
    <span style="font-family:'Barlow Condensed',sans-serif;font-size:.85rem;color:var(--muted);">VS</span>
    <span id="prevOsp"  style="font-family:'Barlow Condensed',sans-serif;font-size:1.15rem;font-weight:700;"></span>
  </div>

  <!-- ── Azioni ───────────────────────────────────────────── -->
  <div class="form-actions">
    <button type="submit" class="btn btn-gold" id="submitBtn">
      <?= $editing ? '💾 Salva Modifiche' : '➕ Aggiungi Partita' ?>
    </button>
    <a href="index.php" class="btn btn-outline">Annulla</a>
    <?php if ($editing): ?>
      <a href="risultato.php?id=<?= $id ?>" class="btn btn-outline">⚽ Risultato</a>
      <a href="eventi.php?id=<?= $id ?>"   class="btn btn-outline">🎯 Eventi</a>
    <?php endif; ?>
  </div>

</form>

<?php if ($editing): ?>
<?php if (!is_telecronista()): ?>
<!-- ── Zona pericolosa: elimina partita ─────────────────── -->
<div style="
  margin-top:2.5rem;
  padding:1.4rem 1.6rem;
  background:rgba(217,79,79,.06);
  border:1px solid rgba(217,79,79,.2);
  border-radius:var(--radius);
  max-width:700px;
">
  <h3 style="
    font-family:'Barlow Condensed',sans-serif;
    font-size:1rem;font-weight:700;
    color:var(--red);text-transform:uppercase;
    letter-spacing:.06em;margin-bottom:.5rem;
  ">⚠️ Zona Pericolosa</h3>
  <p style="font-size:.85rem;color:var(--muted);margin-bottom:1rem;">
    L'eliminazione è permanente: verranno cancellati anche tutti gli eventi e il risultato legati a questa partita.
    La classifica verrà ricalcolata automaticamente.
  </p>
  <a href="delete.php?id=<?= $id ?>&csrf=<?= csrf_token() ?>"
     class="btn btn-red btn-sm"
     onclick="return confirm('Sei sicuro di voler eliminare questa partita? L\'operazione è irreversibile.')">
    🗑️ Elimina Partita
  </a>
</div>
<?php endif; ?>
<?php endif; ?>

<script>
(function () {
  const selCasa  = document.getElementById('id_squadra_casa');
  const selOsp   = document.getElementById('id_squadra_osp');
  const warn     = document.getElementById('sameTeamWarn');
  const preview  = document.getElementById('matchupPreview');
  const prevCasa = document.getElementById('prevCasa');
  const prevOsp  = document.getElementById('prevOsp');
  const submit   = document.getElementById('submitBtn');

  function teamName(sel) {
    const opt = sel.options[sel.selectedIndex];
    return opt && opt.value ? opt.text.trim() : '';
  }

  function update() {
    const casa = teamName(selCasa);
    const osp  = teamName(selOsp);
    const same = casa && osp && selCasa.value === selOsp.value;

    // Avviso squadre uguali
    warn.style.display = same ? 'block' : 'none';
    submit.disabled    = same;

    // Anteprima matchup
    if (casa && osp && !same) {
      prevCasa.textContent = casa;
      prevOsp.textContent  = osp;
      preview.style.display = 'flex';
    } else {
      preview.style.display = 'none';
    }
  }

  selCasa.addEventListener('change', update);
  selOsp.addEventListener('change', update);
  update(); // esegui al caricamento (in modifica)
})();
</script>

<?php require_once __DIR__ . '/../_footer.php'; ?>
