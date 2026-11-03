<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/db.php';
require_admin();

$pdo = db();
$rows = $pdo->query('SELECT t.*, s.nome AS squadra, a.username AS organizzatore, mp.nome AS metodo FROM transazioni t INNER JOIN squadre s ON s.id = t.id_squadra INNER JOIN admin a ON a.id = t.id_organizzatore LEFT JOIN metodi_pagamento mp ON mp.id = t.id_metodo_pagamento ORDER BY t.registrato_at DESC')->fetchAll();
$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);

$pageTitle = 'Transazioni';
require_once __DIR__ . '/../_header.php';
?>

<div class="admin-page-header">
  <h1>Transazioni</h1>
  <p>Storico dei pagamenti registrati dagli organizzatori.</p>
  <a class="btn btn-gold btn-sm" href="<?= ADMIN_PATH ?>/pagamenti/inserisci.php">+ Registra pagamento</a>
</div>

<?php if (($_GET['inserito'] ?? '') === '1'): ?>
  <div class="alert alert-success">Pagamento registrato correttamente.</div>
<?php endif; ?>
<?php if ($flash): ?>
  <div class="alert alert-<?= htmlspecialchars($flash['type']) ?>"><?= htmlspecialchars($flash['text']) ?></div>
<?php endif; ?>

<div class="admin-table-wrap tb-table-wrap">
  <table class="admin-table tb-table">
    <thead>
      <tr>
        <th>Squadra</th>
        <th>Importo</th>
        <th>Metodo</th>
        <th>Data</th>
        <th>Stato</th>
        <th>Organizzatore</th>
        <th>Azioni</th>
      </tr>
    </thead>
    <tbody>
      <?php if (!$rows): ?><tr><td colspan="7" class="admin-table-empty">Non sono ancora state registrate transazioni.</td></tr><?php endif; ?>
      <?php foreach ($rows as $row): ?>
        <tr>
          <td><?= htmlspecialchars($row['squadra']) ?></td>
          <td>€<?= number_format((float) $row['importo'], 2, ',', '.') ?></td>
          <td><?= htmlspecialchars($row['metodo'] ?? '—') ?></td>
          <td><?= htmlspecialchars($row['data_pagamento']) ?></td>
          <td><?= htmlspecialchars($row['stato']) ?></td>
          <td><?= htmlspecialchars($row['organizzatore']) ?></td>
          <td><div class="actions">
            <a class="btn btn-sm btn-outline" href="<?= ADMIN_PATH ?>/pagamenti/modifica.php?id=<?= (int) $row['id'] ?>">Modifica</a>
            <form method="post" action="<?= ADMIN_PATH ?>/pagamenti/elimina.php" onsubmit="return confirm('Eliminare questo pagamento? Il totale versato verrà aggiornato.');" style="display:inline">
              <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">
              <input type="hidden" name="id" value="<?= (int) $row['id'] ?>">
              <button type="submit" class="btn btn-sm btn-red">Elimina</button>
            </form>
          </div></td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>

<?php require_once __DIR__ . '/../_footer.php'; ?>
