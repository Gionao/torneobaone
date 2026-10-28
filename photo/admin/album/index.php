<?php
// photo/admin/album/index.php — Elenco album di una giornata o edizione
declare(strict_types=1);

require_once dirname(__DIR__) . '/_bootstrap.php';
require_media_editor();

$idGiornata = media_get_int('giornata');
$idEdizione = media_get_int('edizione');
$giornata = null;
$edizione = null;
$album = [];
$titolo = 'Album';
$sottotitolo = '';

if ($idGiornata > 0) {
    $giornata = media_giornata($idGiornata);
    if (!$giornata) {
        media_flash('error', 'Giornata non trovata.');
        media_redirect_interno('/edizioni/index.php');
    }
    if (!media_puo_vedere_edizione((int) $giornata['id_edizione'])) {
        http_response_code(403);
        require dirname(__DIR__) . '/_403.php';
        exit;
    }
    $idEdizione = (int) $giornata['id_edizione'];
    $edizione = media_edizione($idEdizione);
    $album = media_album_per_giornata($idGiornata);
    $sottotitolo = (string) $edizione['nome'] . ' · ' . $giornata['nome'];
} elseif ($idEdizione > 0) {
    $edizione = media_edizione($idEdizione);
    if (!$edizione) {
        media_flash('error', 'Edizione non trovata.');
        media_redirect_interno('/edizioni/index.php');
    }
    if (!media_puo_vedere_edizione($idEdizione)) {
        http_response_code(403);
        require dirname(__DIR__) . '/_403.php';
        exit;
    }
    $album = media_album_per_edizione($idEdizione);
    $titolo = 'Album dell\'edizione';
    $sottotitolo = (string) $edizione['nome'] . ' — ' . (int) $edizione['anno'];
} else {
    media_redirect_interno('/edizioni/index.php');
}

$pageTitle = 'Album';
$paginaAttiva = 'edizioni';
require_once dirname(__DIR__) . '/_header.php';
?>

<nav class="archivio-briciole" aria-label="Percorso">
  <a href="<?= media_h(ARCHIVIO_URL) ?>/edizioni/index.php">Edizioni</a>
  <?php if ($edizione): ?>
    <span class="sep">/</span>
    <a href="<?= media_h(media_url('giornate/index.php', ['edizione' => $idEdizione])) ?>"><?= media_h($edizione['nome']) ?></a>
  <?php endif; ?>
  <?php if ($giornata): ?>
    <span class="sep">/</span>
    <strong><?= media_h($giornata['nome']) ?></strong>
  <?php endif; ?>
</nav>

<div class="archivio-pagina-testata">
  <div class="admin-page-header">
    <h1><?= media_h($titolo) ?></h1>
    <p><?= media_h($sottotitolo) ?></p>
  </div>
  <div class="archivio-pagina-azioni">
    <?php if ($giornata): ?>
      <a class="btn btn-sm btn-gold" href="<?= media_h(media_url('album/form.php', ['giornata' => $idGiornata])) ?>">+ Nuovo album</a>
      <a class="btn btn-sm btn-outline" href="<?= media_h(media_url('galleria.php', ['giornata' => $idGiornata])) ?>">Apri galleria</a>
    <?php else: ?>
      <a class="btn btn-sm btn-outline" href="<?= media_h(media_url('giornate/index.php', ['edizione' => $idEdizione])) ?>">Gestisci giornate</a>
    <?php endif; ?>
  </div>
</div>

<?php if ($album === []): ?>
  <div class="archivio-empty">
    <strong>Nessun album</strong>
    <?= $giornata ? 'Crea un album per iniziare a caricare foto e video.' : 'Questa edizione non contiene ancora album.' ?>
  </div>
<?php else: ?>
  <div class="admin-table-wrap">
    <table class="admin-table archivio-tabella">
      <thead>
        <tr>
          <?php if (!$giornata): ?><th>Giornata</th><?php endif; ?>
          <th>Album</th>
          <th>File</th>
          <th>Ordine</th>
          <th>Azioni</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($album as $a): ?>
          <tr>
            <?php if (!$giornata): ?><td><?= media_h($a['nome_giornata']) ?></td><?php endif; ?>
            <td>
              <strong><?= media_h($a['nome']) ?></strong>
              <?php if (!empty($a['descrizione'])): ?>
                <br><span class="archivio-muted archivio-testo-piccolo"><?= media_h($a['descrizione']) ?></span>
              <?php endif; ?>
            </td>
            <td><?= (int) $a['numero_file'] ?></td>
            <td><?= (int) $a['ordine'] ?></td>
            <td class="actions">
              <a class="btn btn-sm btn-gold" href="<?= media_h(media_url('galleria.php', ['album' => (int) $a['id']])) ?>">Apri</a>
              <a class="btn btn-sm btn-outline" href="<?= media_h(media_url('upload.php', ['album' => (int) $a['id']])) ?>">Carica</a>
              <a class="btn btn-sm btn-outline" href="<?= media_h(media_url('album/form.php', ['id' => (int) $a['id']])) ?>">Modifica</a>
              <form method="post" action="<?= media_h(ARCHIVIO_URL) ?>/album/delete.php" style="display:inline;"
                    onsubmit="return confirm('Eliminare questo album? È possibile solo se non contiene file.');">
                <input type="hidden" name="csrf_token" value="<?= media_h(csrf_token()) ?>">
                <input type="hidden" name="id" value="<?= (int) $a['id'] ?>">
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
