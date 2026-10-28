<?php
// photo/admin/edizioni/index.php — Elenco edizioni
declare(strict_types=1);

require_once dirname(__DIR__) . '/_bootstrap.php';
require_media_editor();

$edizioni = media_edizioni();

$pageTitle = 'Edizioni';
$paginaAttiva = 'edizioni';
require_once dirname(__DIR__) . '/_header.php';
?>

<div class="archivio-pagina-testata">
  <div class="admin-page-header">
    <h1>Edizioni</h1>
    <p>Le edizioni restano archiviate e disponibili anche dopo la fine del torneo.</p>
  </div>
  <div class="archivio-pagina-azioni">
    <a class="btn btn-sm btn-gold" href="<?= media_h(ARCHIVIO_URL) ?>/edizioni/form.php">+ Nuova edizione</a>
  </div>
</div>

<?php if ($edizioni === []): ?>
  <div class="archivio-empty">
    <strong>Nessuna edizione</strong>
    Crea la prima edizione per organizzare giornate, album e file.
  </div>
<?php else: ?>
  <div class="admin-table-wrap">
    <table class="admin-table archivio-tabella">
      <thead>
        <tr>
          <th>Edizione</th>
          <th>Anno</th>
          <th>Periodo</th>
          <th>Giornate</th>
          <th>File</th>
          <th>Stato</th>
          <th>Azioni</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($edizioni as $e): ?>
          <tr>
            <td><strong><?= media_h($e['nome']) ?></strong><?= !empty($e['descrizione']) ? '<br><span class="archivio-muted archivio-testo-piccolo">' . media_h($e['descrizione']) . '</span>' : '' ?></td>
            <td><?= (int) $e['anno'] ?></td>
            <td class="archivio-muted">
              <?= !empty($e['data_inizio']) ? media_h(date('d/m/Y', strtotime((string) $e['data_inizio']))) : '—' ?>
              <?= !empty($e['data_fine']) ? ' → ' . media_h(date('d/m/Y', strtotime((string) $e['data_fine']))) : '' ?>
            </td>
            <td><?= (int) $e['numero_giornate'] ?></td>
            <td><?= (int) $e['numero_file'] ?></td>
            <td><span class="role-badge <?= (int) $e['attiva'] === 1 ? 'role-badge--uploader' : '' ?>"><?= (int) $e['attiva'] === 1 ? 'Attiva' : 'Archiviata' ?></span></td>
            <td class="actions">
              <a class="btn btn-sm btn-gold" href="<?= media_h(media_url('edizioni/index.php', ['id' => (int) $e['id']])) ?>" hidden>—</a>
              <a class="btn btn-sm btn-gold" href="<?= media_h(media_url('giornate/index.php', ['edizione' => (int) $e['id']])) ?>">Giornate</a>
              <a class="btn btn-sm btn-outline" href="<?= media_h(media_url('edizioni/form.php', ['id' => (int) $e['id']])) ?>">Modifica</a>
              <form method="post" action="<?= media_h(ARCHIVIO_URL) ?>/edizioni/delete.php" style="display:inline;"
                    onsubmit="return confirm('Eliminare questa edizione? L\'operazione è possibile solo se non contiene giornate.');">
                <input type="hidden" name="csrf_token" value="<?= media_h(csrf_token()) ?>">
                <input type="hidden" name="id" value="<?= (int) $e['id'] ?>">
                <button type="submit" class="btn btn-sm btn-red">Elimina</button>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
<?php endif; ?>

<?php require_once dirname(__DIR__) . '/_footer.php'; ?>
