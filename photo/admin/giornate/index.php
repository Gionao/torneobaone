<?php
// photo/admin/giornate/index.php — Elenco giornate di una edizione
declare(strict_types=1);

require_once dirname(__DIR__) . '/_bootstrap.php';
require_media_editor();

$idEdizione = media_get_int('edizione');
$edizione = $idEdizione > 0 ? media_edizione($idEdizione) : null;
if (!$edizione) {
    media_flash('error', 'Edizione non trovata.');
    media_redirect_interno('/edizioni/index.php');
}
if (!media_puo_vedere_edizione($idEdizione)) {
    http_response_code(403);
    require dirname(__DIR__) . '/_403.php';
    exit;
}

$giornate = media_giornate($idEdizione);

$pageTitle = 'Giornate';
$paginaAttiva = 'edizioni';
require_once dirname(__DIR__) . '/_header.php';
?>

<nav class="archivio-briciole" aria-label="Percorso">
  <a href="<?= media_h(ARCHIVIO_URL) ?>/edizioni/index.php">Edizioni</a>
  <span class="sep">/</span>
  <strong><?= media_h($edizione['nome']) ?> <?= (int) $edizione['anno'] ?></strong>
</nav>

<div class="archivio-pagina-testata">
  <div class="admin-page-header">
    <h1>Giornate</h1>
    <p><?= media_h($edizione['nome']) ?> — <?= (int) $edizione['anno'] ?></p>
  </div>
  <div class="archivio-pagina-azioni">
    <a class="btn btn-sm btn-gold" href="<?= media_h(media_url('giornate/form.php', ['edizione' => $idEdizione])) ?>">+ Nuova giornata</a>
    <a class="btn btn-sm btn-outline" href="<?= media_h(media_url('album/index.php', ['edizione' => $idEdizione])) ?>">Tutti gli album</a>
    <a class="btn btn-sm btn-outline" href="<?= media_h(media_url('edizioni/form.php', ['id' => $idEdizione])) ?>">Modifica edizione</a>
  </div>
</div>

<?php if ($giornate === []): ?>
  <div class="archivio-empty">
    <strong>Nessuna giornata</strong>
    Crea la prima giornata per organizzare gli album di questa edizione.
  </div>
<?php else: ?>
  <div class="admin-table-wrap">
    <table class="admin-table archivio-tabella">
      <thead>
        <tr>
          <th>Giornata</th>
          <th>Data</th>
          <th>Ordine</th>
          <th>Album</th>
          <th>File</th>
          <th>Azioni</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($giornate as $g): ?>
          <tr>
            <td>
              <strong><?= media_h($g['nome']) ?></strong>
              <?php if (!empty($g['note'])): ?>
                <br><span class="archivio-muted archivio-testo-piccolo"><?= media_h($g['note']) ?></span>
              <?php endif; ?>
            </td>
            <td class="archivio-muted"><?= !empty($g['data_evento']) ? media_h(date('d/m/Y', strtotime((string) $g['data_evento']))) : '—' ?></td>
            <td><?= (int) $g['ordine'] ?></td>
            <td><?= (int) $g['numero_album'] ?></td>
            <td><?= (int) $g['numero_file'] ?></td>
            <td class="actions">
              <a class="btn btn-sm btn-gold" href="<?= media_h(media_url('galleria.php', ['giornata' => (int) $g['id']])) ?>">Apri</a>
              <a class="btn btn-sm btn-outline" href="<?= media_h(media_url('album/index.php', ['giornata' => (int) $g['id']])) ?>">Album</a>
              <a class="btn btn-sm btn-outline" href="<?= media_h(media_url('album/form.php', ['giornata' => (int) $g['id']])) ?>">+ Album</a>
              <a class="btn btn-sm btn-outline" href="<?= media_h(media_url('giornate/form.php', ['id' => (int) $g['id']])) ?>">Modifica</a>
              <form method="post" action="<?= media_h(ARCHIVIO_URL) ?>/giornate/delete.php" style="display:inline;"
                    onsubmit="return confirm('Eliminare questa giornata? È possibile solo se non contiene album.');">
                <input type="hidden" name="csrf_token" value="<?= media_h(csrf_token()) ?>">
                <input type="hidden" name="id" value="<?= (int) $g['id'] ?>">
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
