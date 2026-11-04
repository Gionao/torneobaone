<?php
// admin/partite/eventi.php
// ============================================================
//  Admin — Gestione eventi partita (gol, cartellini, MVP)
// ============================================================

declare(strict_types=1);

require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/classifica_helper.php';

require_partite_access();

$pdo = db();

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT)
   ?: filter_input(INPUT_POST, 'partita_id', FILTER_VALIDATE_INT);
if (!$id) { header('Location: index.php'); exit; }

// Dati partita
$pStmt = $pdo->prepare("
    SELECT p.*, sc.nome AS nome_casa, sc.id AS id_casa,
                so.nome AS nome_osp,  so.id AS id_osp
    FROM partite p
    JOIN squadre sc ON sc.id = p.id_squadra_casa
    JOIN squadre so ON so.id = p.id_squadra_osp
    WHERE p.id = ? LIMIT 1
");
$pStmt->execute([$id]);
$partita = $pStmt->fetch();
if (!$partita) { header('Location: index.php'); exit; }

// Elimina evento
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['del'])) {
    $delId = filter_input(INPUT_POST, 'del', FILTER_VALIDATE_INT);
    if (!csrf_verify()) {
        http_response_code(400);
        exit('Richiesta non valida.');
    }
    if ($delId) {
        $pdo->prepare('DELETE FROM eventi_partita WHERE id = ? AND id_partita = ?')
            ->execute([$delId, $id]);
        ricalcola_classifica($pdo);
    }
    header('Location: eventi.php?id=' . $id);
    exit;
}

// Giocatori delle due squadre per autocomplete
$giocatori = $pdo->prepare("
    SELECT id, CONCAT(nome,' ',cognome) AS display, id_squadra
    FROM giocatori
    WHERE id_squadra IN (?, ?)
    ORDER BY cognome, nome
");
$giocatori->execute([$partita['id_squadra_casa'], $partita['id_squadra_osp']]);
$giocatori = $giocatori->fetchAll();

$gByCasa = array_filter($giocatori, fn($g) => $g['id_squadra'] == $partita['id_squadra_casa']);
$gByOsp  = array_filter($giocatori, fn($g) => $g['id_squadra'] == $partita['id_squadra_osp']);

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_event'])) {
    if (!csrf_verify()) { $errors[] = 'CSRF non valido.'; goto render; }

    $tipo        = $_POST['tipo']        ?? '';
    $id_squadra  = filter_input(INPUT_POST, 'id_squadra',  FILTER_VALIDATE_INT);
    $id_gioc     = filter_input(INPUT_POST, 'id_giocatore', FILTER_VALIDATE_INT) ?: null;
    $nome_libero = trim($_POST['nome_libero'] ?? '');
    $minuto      = filter_input(INPUT_POST, 'minuto', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 120]]) ?: null;
    $note        = trim($_POST['note_ev'] ?? '');

    $tipi_validi = ['gol','gol_autorete','ammonizione','espulsione','doppia_ammonizione','mvp'];
    if (!in_array($tipo, $tipi_validi, true)) $errors[] = 'Tipo evento non valido.';
    if (!$id_squadra) $errors[] = 'Seleziona la squadra.';
    if (!$id_gioc && $nome_libero === '') $errors[] = 'Seleziona un giocatore o inserisci il nome.';

    if (empty($errors)) {
        $ins = $pdo->prepare("
            INSERT INTO eventi_partita
                (id_partita, id_giocatore, nome_libero, id_squadra, tipo, minuto, note)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        $ins->execute([
            $id,
            $id_gioc,
            $id_gioc ? null : ($nome_libero ?: null),
            $id_squadra,
            $tipo,
            $minuto,
            $note ?: null,
        ]);

        // Se è un gol, aggiorna anche il conteggio nel risultato partita e ricalcola
        ricalcola_classifica($pdo);

        $_SESSION['flash_ev'] = 'Evento aggiunto.';
        header('Location: eventi.php?id=' . $id);
        exit;
    }
}

render:
// Lista eventi attuali
$evList = $pdo->prepare("
    SELECT e.*,
           COALESCE(CONCAT(g.nome,' ',g.cognome), e.nome_libero) AS giocatore_display,
           s.nome AS nome_squadra
    FROM eventi_partita e
    LEFT JOIN giocatori g ON g.id = e.id_giocatore
    JOIN      squadre   s ON s.id = e.id_squadra
    WHERE e.id_partita = ?
    ORDER BY e.minuto ASC, e.id ASC
");
$evList->execute([$id]);
$eventi = $evList->fetchAll();

$flashEv = $_SESSION['flash_ev'] ?? null;
unset($_SESSION['flash_ev']);

$pageTitle = 'Gestione Eventi';
require_once __DIR__ . '/../_header.php';
?>

<div class="admin-page-header">
  <h1>🎯 Gestione Eventi</h1>
  <p>
    <strong><?= htmlspecialchars($partita['nome_casa']) ?></strong>
    <?php if ($partita['gol_casa'] !== null): ?>
      <span style="color:var(--gold);margin:0 .3rem;">
        <?= $partita['gol_casa'] ?> — <?= $partita['gol_osp'] ?>
      </span>
    <?php else: ?>
      <span style="color:var(--muted);margin:0 .3rem;">vs</span>
    <?php endif; ?>
    <strong><?= htmlspecialchars($partita['nome_osp']) ?></strong>
    &nbsp;
    <a href="index.php" style="color:var(--muted);font-size:.85rem;">← Calendario</a>
  </p>
</div>

<?php if ($flashEv): ?>
  <div class="alert alert-success"><?= htmlspecialchars($flashEv) ?></div>
<?php endif; ?>
<?php foreach ($errors as $e): ?>
  <div class="alert alert-error"><?= htmlspecialchars($e) ?></div>
<?php endforeach; ?>

<div style="display:grid;grid-template-columns:1fr 1.5fr;gap:2rem;align-items:start;">

  <!-- FORM AGGIUNTA EVENTO -->
  <form method="post" class="admin-form" style="max-width:none;">
    <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
    <input type="hidden" name="partita_id" value="<?= $id ?>">
    <input type="hidden" name="add_event" value="1">

    <h3 style="font-family:'Barlow Condensed',sans-serif;color:var(--gold);font-size:1.1rem;margin-bottom:1.2rem;text-transform:uppercase;letter-spacing:.06em;">
      + Aggiungi Evento
    </h3>

    <div class="form-group">
      <label>Tipo Evento *</label>
      <select name="tipo" required>
        <option value="">— Seleziona —</option>
        <option value="gol">⚽ Gol</option>
        <option value="gol_autorete">⚽ Autorete</option>
        <option value="ammonizione">🟨 Ammonizione</option>
        <option value="doppia_ammonizione">🟨🟨 Doppia Ammonizione</option>
        <option value="espulsione">🟥 Espulsione</option>
        <option value="mvp">⭐ MVP</option>
      </select>
    </div>

    <div class="form-group">
      <label>Squadra *</label>
      <select name="id_squadra" id="squadraSelect" required>
        <option value="">— Seleziona —</option>
        <option value="<?= $partita['id_squadra_casa'] ?>"><?= htmlspecialchars($partita['nome_casa']) ?></option>
        <option value="<?= $partita['id_squadra_osp'] ?>"><?= htmlspecialchars($partita['nome_osp']) ?></option>
      </select>
    </div>

    <div class="form-group">
      <label>Giocatore (dalla rosa)</label>
      <select name="id_giocatore" id="giocatoreSelect">
        <option value="">— Seleziona dalla lista —</option>
        <optgroup label="<?= htmlspecialchars($partita['nome_casa']) ?>" id="optCasa">
          <?php foreach ($gByCasa as $g): ?>
            <option value="<?= $g['id'] ?>" data-squadra="<?= $partita['id_squadra_casa'] ?>">
              <?= htmlspecialchars($g['display']) ?>
            </option>
          <?php endforeach; ?>
        </optgroup>
        <optgroup label="<?= htmlspecialchars($partita['nome_osp']) ?>" id="optOsp">
          <?php foreach ($gByOsp as $g): ?>
            <option value="<?= $g['id'] ?>" data-squadra="<?= $partita['id_squadra_osp'] ?>">
              <?= htmlspecialchars($g['display']) ?>
            </option>
          <?php endforeach; ?>
        </optgroup>
      </select>
    </div>

    <div class="form-group">
      <label>Oppure nome libero</label>
      <input type="text" name="nome_libero" maxlength="100"
             placeholder="Se il giocatore non è in rosa">
    </div>

    <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;">
      <div class="form-group">
        <label>Minuto</label>
        <input type="number" name="minuto" min="1" max="120" placeholder="es. 23">
      </div>
      <div class="form-group">
        <label>Note</label>
        <input type="text" name="note_ev" maxlength="200">
      </div>
    </div>

    <div class="form-actions">
      <button type="submit" class="btn btn-gold">➕ Aggiungi</button>
    </div>
  </form>

  <!-- LISTA EVENTI -->
  <div>
    <h3 style="font-family:'Barlow Condensed',sans-serif;color:var(--gold);font-size:1.1rem;margin-bottom:1rem;text-transform:uppercase;letter-spacing:.06em;">
      Storico Eventi (<?= count($eventi) ?>)
    </h3>
    <?php if (empty($eventi)): ?>
      <p class="empty-msg">Nessun evento ancora aggiunto.</p>
    <?php else: ?>
    <div class="admin-table-wrap tb-table-wrap">
      <table class="admin-table tb-table">
        <thead>
          <tr>
            <th>Tipo</th>
            <th>Giocatore</th>
            <th>Squadra</th>
            <th>Min</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($eventi as $ev): ?>
          <tr>
            <td>
              <?php $icons = [
                'gol' => '⚽', 'gol_autorete' => '⚽(Aut)',
                'ammonizione' => '🟨', 'doppia_ammonizione' => '🟨🟨',
                'espulsione' => '🟥', 'mvp' => '⭐',
              ]; ?>
              <?= $icons[$ev['tipo']] ?? $ev['tipo'] ?>
            </td>
            <td><?= htmlspecialchars($ev['giocatore_display']) ?></td>
            <td style="font-size:.82rem;"><?= htmlspecialchars($ev['nome_squadra']) ?></td>
            <td style="color:var(--muted)"><?= $ev['minuto'] ? $ev['minuto']."'" : '—' ?></td>
            <td>
              <form method="post" style="display:inline" onsubmit="return confirm('Eliminare questo evento?')"><input type="hidden" name="csrf_token" value="<?= csrf_token() ?>"><input type="hidden" name="partita_id" value="<?= (int) $id ?>"><input type="hidden" name="del" value="<?= (int) $ev['id'] ?>"><button class="btn btn-sm btn-red" type="submit">✕</button></form>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php endif; ?>
  </div>

</div>

<script>
// Quando si seleziona un giocatore dalla lista, auto-popola la squadra
document.getElementById('giocatoreSelect')?.addEventListener('change', function() {
  const opt = this.options[this.selectedIndex];
  const squadra = opt?.dataset?.squadra;
  if (squadra) document.getElementById('squadraSelect').value = squadra;
});
</script>

<?php require_once __DIR__ . '/../_footer.php'; ?>
