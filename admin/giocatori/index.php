<?php
// admin/giocatori/index.php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/db.php';
require_admin();

$pdo = db();
$msg = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);

// Filtro per squadra
$sqFiltro = filter_input(INPUT_GET, 'squadra', FILTER_VALIDATE_INT) ?: null;

// Lista squadre per select filtro
$squadre = $pdo->query('SELECT id, nome FROM squadre ORDER BY nome')->fetchAll();

// Query giocatori
if ($sqFiltro) {
    $stmt = $pdo->prepare("
        SELECT g.*, s.nome AS nome_squadra, s.colore AS colore_squadra
        FROM giocatori g
        JOIN squadre s ON s.id = g.id_squadra
        WHERE g.id_squadra = ?
        ORDER BY g.cognome, g.nome
    ");
    $stmt->execute([$sqFiltro]);
} else {
    $stmt = $pdo->query("
        SELECT g.*, s.nome AS nome_squadra, s.colore AS colore_squadra
        FROM giocatori g
        JOIN squadre s ON s.id = g.id_squadra
        ORDER BY s.nome, g.cognome, g.nome
    ");
}
$giocatori = $stmt->fetchAll();

$pageTitle = 'Gestione Giocatori';
require_once __DIR__ . '/../_header.php';
?>

<div class="admin-page-header">
  <h1>👤 Gestione Giocatori</h1>
  <p>Rose delle squadre</p>
</div>

<?php if ($msg): ?>
  <div class="alert alert-<?= htmlspecialchars($msg['type']) ?>"><?= htmlspecialchars($msg['text']) ?></div>
<?php endif; ?>

<div style="display:flex;gap:1rem;align-items:center;margin-bottom:1.5rem;flex-wrap:wrap;">
  <a href="form.php<?= $sqFiltro ? '?squadra='.$sqFiltro : '' ?>" class="qa-btn">+ Nuovo Giocatore</a>

  <!-- Filtro squadra -->
  <form method="get" style="display:flex;gap:.5rem;align-items:center;">
    <select name="squadra" onchange="this.form.submit()"
            style="background:rgba(255,255,255,.06);border:1px solid rgba(201,168,76,.2);
                   border-radius:6px;color:var(--white);padding:.4rem .8rem;font-family:'Barlow',sans-serif;">
      <option value="">Tutte le squadre</option>
      <?php foreach ($squadre as $s): ?>
        <option value="<?= $s['id'] ?>" <?= $sqFiltro == $s['id'] ? 'selected' : '' ?>>
          <?= htmlspecialchars($s['nome']) ?>
        </option>
      <?php endforeach; ?>
    </select>
    <?php if ($sqFiltro): ?>
      <a href="index.php" class="btn btn-sm btn-outline">✕ Reset</a>
    <?php endif; ?>
  </form>
</div>

<?php if (empty($giocatori)): ?>
  <p class="empty-msg">Nessun giocatore trovato.</p>
<?php else: ?>
<div class="admin-table-wrap">
  <table class="admin-table">
    <thead>
      <tr>
        <th>N°</th>
        <th>Cognome</th>
        <th>Nome</th>
        <th>Squadra</th>
        <th>Ruolo</th>
        <th>Azioni</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($giocatori as $g): ?>
      <tr>
        <td style="color:var(--muted)"><?= $g['numero_maglia'] ?? '—' ?></td>
        <td><strong><?= htmlspecialchars($g['cognome']) ?></strong></td>
        <td><?= htmlspecialchars($g['nome']) ?></td>
        <td>
          <span class="team-color-dot" style="background:<?= htmlspecialchars($g['colore_squadra']) ?>"></span>
          <?= htmlspecialchars($g['nome_squadra']) ?>
        </td>
        <td>
          <?php
            $ruolo = $g['ruolo'] ?? 'Giocatore';
            $icon  = $ruolo === 'Portiere' ? '🧤 ' : ($ruolo === 'Capitano' ? '👑 ' : '');
          ?>
          <?= $icon . htmlspecialchars($ruolo) ?>
        </td>
        <td>
          <div class="actions">
            <a href="form.php?id=<?= $g['id'] ?>" class="btn btn-sm btn-outline">Modifica</a>
            <a href="delete.php?id=<?= $g['id'] ?>&csrf=<?= csrf_token() ?>"
               class="btn btn-sm btn-red"
               onclick="return confirm('Eliminare questo giocatore?')">Elimina</a>
          </div>
        </td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>
<?php endif; ?>

<style>
.team-color-dot { display:inline-block;width:10px;height:10px;border-radius:50%;margin-right:.3rem; }
</style>

<?php require_once __DIR__ . '/../_footer.php'; ?>