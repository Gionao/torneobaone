<?php
// admin/partite/risultato.php
// ============================================================
//  Admin — Inserimento/modifica risultato + ricalcolo classifica
// ============================================================

declare(strict_types=1);

require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/classifica_helper.php';

require_partite_access();

$pdo = db();
$id  = filter_input(INPUT_GET,  'id', FILTER_VALIDATE_INT)
    ?: filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);

if (!$id) { header('Location: index.php'); exit; }

$stmt = $pdo->prepare("
    SELECT p.*, f.nome AS fase_nome,
           sc.nome AS nome_casa, so.nome AS nome_osp
    FROM partite p
    JOIN fasi    f  ON f.id  = p.id_fase
    JOIN squadre sc ON sc.id = p.id_squadra_casa
    JOIN squadre so ON so.id = p.id_squadra_osp
    WHERE p.id = ? LIMIT 1
");
$stmt->execute([$id]);
$partita = $stmt->fetch();
if (!$partita) { header('Location: index.php'); exit; }

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify()) { $errors[] = 'Token CSRF non valido.'; goto render; }

    $gol_casa      = filter_input(INPUT_POST, 'gol_casa',      FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]);
    $gol_osp       = filter_input(INPUT_POST, 'gol_osp',       FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]);
    $shootout_casa = filter_input(INPUT_POST, 'shootout_casa', FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]);
    $shootout_osp  = filter_input(INPUT_POST, 'shootout_osp',  FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]);
    $giocata       = isset($_POST['giocata']) ? 1 : 0;

    if ($giocata) {
        if ($gol_casa === false || $gol_casa === null) $errors[] = 'Inserisci gol squadra casa (≥0).';
        if ($gol_osp  === false || $gol_osp  === null) $errors[] = 'Inserisci gol squadra ospite (≥0).';
    }

    if (empty($errors)) {
        $upd = $pdo->prepare("
            UPDATE partite
            SET gol_casa=?, gol_osp=?,
                shootout_casa=?, shootout_osp=?,
                giocata=?
            WHERE id=?
        ");
        $upd->execute([
            $giocata ? $gol_casa : null,
            $giocata ? $gol_osp  : null,
            ($giocata && $shootout_casa !== false && $shootout_casa !== null) ? $shootout_casa : null,
            ($giocata && $shootout_osp  !== false && $shootout_osp  !== null) ? $shootout_osp  : null,
            $giocata,
            $id,
        ]);

        // Ricalcola classifica dopo ogni modifica del risultato
        ricalcola_classifica($pdo);

        $_SESSION['flash'] = ['type' => 'success', 'text' => 'Risultato salvato e classifica aggiornata!'];
        header('Location: index.php');
        exit;
    }
}

render:
$pageTitle = 'Inserisci Risultato';
require_once __DIR__ . '/../_header.php';
?>

<div class="admin-page-header">
  <h1>⚽ Inserisci Risultato</h1>
  <p><a href="index.php" style="color:var(--muted);font-size:.85rem;">← Torna al calendario</a></p>
</div>

<!-- Matchup recap -->
<div class="matchup-recap">
  <span class="mr-team"><?= htmlspecialchars($partita['nome_casa']) ?></span>
  <span class="mr-vs">VS</span>
  <span class="mr-team"><?= htmlspecialchars($partita['nome_osp']) ?></span>
  <span class="mr-fase fase-badge fase-girone"><?= htmlspecialchars($partita['fase_nome']) ?></span>
</div>

<?php foreach ($errors as $e): ?>
  <div class="alert alert-error"><?= htmlspecialchars($e) ?></div>
<?php endforeach; ?>

<form method="post" class="admin-form">
  <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
  <input type="hidden" name="id" value="<?= $id ?>">

  <div class="form-group">
    <label>
      <input type="checkbox" name="giocata" value="1" id="cbGiocata"
             <?= $partita['giocata'] ? 'checked' : '' ?>>
      &nbsp;Partita giocata (spunta per inserire il risultato)
    </label>
  </div>

  <div id="scoreFields" <?= !$partita['giocata'] ? 'style="display:none"' : '' ?>>
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:1.5rem;margin-bottom:1.2rem;">
      <div class="form-group">
        <label for="gol_casa">Gol <?= htmlspecialchars($partita['nome_casa']) ?></label>
        <input type="number" id="gol_casa" name="gol_casa" min="0" max="99"
               value="<?= htmlspecialchars((string)($partita['gol_casa'] ?? '')) ?>">
      </div>
      <div class="form-group">
        <label for="gol_osp">Gol <?= htmlspecialchars($partita['nome_osp']) ?></label>
        <input type="number" id="gol_osp" name="gol_osp" min="0" max="99"
               value="<?= htmlspecialchars((string)($partita['gol_osp'] ?? '')) ?>">
      </div>
    </div>

    <!-- Shootout (solo fasi eliminatorie) -->
    <p style="font-size:.82rem;color:var(--muted);margin-bottom:.6rem;">
      Rigori (DCR) — compila solo se la partita è andata ai tiri di rigore:
    </p>
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:1.5rem;">
      <div class="form-group">
        <label for="shootout_casa">Rigori <?= htmlspecialchars($partita['nome_casa']) ?></label>
        <input type="number" id="shootout_casa" name="shootout_casa" min="0" max="99"
               value="<?= htmlspecialchars((string)($partita['shootout_casa'] ?? '')) ?>">
      </div>
      <div class="form-group">
        <label for="shootout_osp">Rigori <?= htmlspecialchars($partita['nome_osp']) ?></label>
        <input type="number" id="shootout_osp" name="shootout_osp" min="0" max="99"
               value="<?= htmlspecialchars((string)($partita['shootout_osp'] ?? '')) ?>">
      </div>
    </div>
  </div>

  <div class="form-actions" style="margin-top:1.5rem;">
    <button type="submit" class="btn btn-gold">💾 Salva Risultato</button>
    <a href="index.php" class="btn btn-outline">Annulla</a>
    <a href="eventi.php?id=<?= $id ?>" class="btn btn-outline">Gestisci Eventi →</a>
  </div>
</form>

<style>
.matchup-recap {
  display:flex; align-items:center; gap:1rem; flex-wrap:wrap;
  background:rgba(231,169,50,.06); border:1px solid rgba(231,169,50,.15);
  border-radius:var(--radius); padding:1rem 1.5rem; margin-bottom:1.5rem;
}
.mr-team { font-family:'Barlow Condensed',sans-serif; font-size:1.3rem; font-weight:700; }
.mr-vs   { font-family:'Barlow Condensed',sans-serif; font-size:1rem; color:var(--muted); }
.mr-fase { margin-left:auto; }
</style>

<script>
const cb = document.getElementById('cbGiocata');
const sf = document.getElementById('scoreFields');
cb.addEventListener('change', () => {
  sf.style.display = cb.checked ? '' : 'none';
});
</script>

<?php require_once __DIR__ . '/../_footer.php'; ?>
