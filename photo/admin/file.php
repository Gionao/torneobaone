<?php
// photo/admin/file.php — Dettaglio di un file
declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';
require_media_login();

$id = media_get_int('id');
$file = media_file($id);
if (!$file) {
    http_response_code(404);
    $pageTitle = 'File non trovato';
    $paginaAttiva = 'galleria';
    require_once __DIR__ . '/_header.php';
    echo '<div class="archivio-empty"><strong>File non trovato</strong>Il file richiesto non esiste o è stato rimosso.</div>';
    echo '<p><a class="btn btn-outline" href="' . media_h(ARCHIVIO_URL) . '/galleria.php">Torna alla galleria</a></p>';
    require_once __DIR__ . '/_footer.php';
    exit;
}
if (!empty($file['eliminato_il'])) {
    media_flash('error', 'Il file si trova nel cestino e non è disponibile nella galleria.');
    media_redirect_interno('/galleria.php');
}
if (!media_puo_vedere_file($file)) {
    http_response_code(403);
    require __DIR__ . '/_403.php';
    exit;
}

$puoScaricare = media_puo_scaricare($file);
$puoEliminare = media_puo_eliminare($file);
$estensione = strtolower((string) $file['estensione']);
$isVideo = ($file['tipo'] ?? '') === 'video';
$anteprimabile = $puoScaricare && in_array($estensione, ['jpg', 'jpeg', 'png', 'mp4', 'mov'], true);
$downloadUrl = MEDIA_API_URL . '/download.php?id=' . (int) $file['id'];
$previewUrl = $downloadUrl . '&inline=1';
$thumbUrl = !empty($file['miniatura']) ? MEDIA_API_URL . '/thumb.php?id=' . (int) $file['id'] : '';
$duplicati = media_is_editor() ? media_file_duplicati((string) $file['sha256'], (int) $file['id']) : [];

$pageTitle = 'Dettaglio file';
$paginaAttiva = 'galleria';
require_once __DIR__ . '/_header.php';
?>

<nav class="archivio-briciole" aria-label="Percorso">
  <a href="<?= media_h(ARCHIVIO_URL) ?>/galleria.php">Galleria</a>
  <span class="sep">/</span>
  <a href="<?= media_h(media_url('galleria.php', ['edizione' => (int) $file['id_edizione']])) ?>"><?= media_h($file['nome_edizione']) ?></a>
  <span class="sep">/</span>
  <a href="<?= media_h(media_url('galleria.php', ['giornata' => (int) $file['id_giornata']])) ?>"><?= media_h($file['nome_giornata']) ?></a>
  <span class="sep">/</span>
  <a href="<?= media_h(media_url('galleria.php', ['album' => (int) $file['id_album']])) ?>"><?= media_h($file['nome_album']) ?></a>
  <span class="sep">/</span>
  <strong>Dettaglio</strong>
</nav>

<div class="admin-page-header">
  <h1 style="font-size:1.5rem;overflow-wrap:anywhere;"><?= media_h($file['nome_originale']) ?></h1>
  <p><?= media_h(strtoupper($estensione)) ?> · <?= media_h(media_bytes_leggibili((int) $file['dimensione'])) ?> · caricato il <?= media_h(date('d/m/Y H:i', strtotime((string) $file['created_at']))) ?></p>
</div>

<div class="archivio-dettaglio">
  <div class="archivio-dettaglio__media">
    <?php if ($isVideo && $anteprimabile): ?>
      <video controls preload="metadata" src="<?= media_h($previewUrl) ?>"></video>
    <?php elseif (!$isVideo && $anteprimabile): ?>
      <img src="<?= media_h($previewUrl) ?>" alt="<?= media_h($file['nome_originale']) ?>">
    <?php elseif ($thumbUrl !== ''): ?>
      <img src="<?= media_h($thumbUrl) ?>" alt="<?= media_h($file['nome_originale']) ?>">
    <?php else: ?>
      <div class="archivio-dettaglio__segnaposto">
        <strong><?= media_h(strtoupper($estensione)) ?></strong>
        Anteprima non disponibile per questo formato.<br>
        Il file originale è conservato e può essere scaricato<?= $puoScaricare ? '' : ' dagli utenti autorizzati' ?>.
      </div>
    <?php endif; ?>
  </div>

  <aside class="archivio-pannello">
    <h2>Informazioni</h2>
    <dl class="archivio-meta-lista">
      <div><dt>Nome originale</dt><dd><?= media_h($file['nome_originale']) ?></dd></div>
      <div><dt>Caricato da</dt><dd><?= media_h($file['username_uploader'] ?: 'Utente rimosso') ?></dd></div>
      <div><dt>Data</dt><dd><?= media_h(date('d/m/Y H:i', strtotime((string) $file['created_at']))) ?></dd></div>
      <div><dt>Dimensione</dt><dd><?= media_h(media_bytes_leggibili((int) $file['dimensione'])) ?> (<?= (int) $file['dimensione'] ?> byte)</dd></div>
      <div><dt>Tipo</dt><dd><?= $isVideo ? 'Video' : 'Foto' ?> · <?= media_h(strtoupper($estensione)) ?></dd></div>
      <?php if (!empty($file['larghezza']) && !empty($file['altezza'])): ?>
        <div><dt>Dimensioni</dt><dd><?= (int) $file['larghezza'] ?> × <?= (int) $file['altezza'] ?> pixel</dd></div>
      <?php endif; ?>
      <div><dt>Edizione</dt><dd><?= media_h($file['nome_edizione']) ?></dd></div>
      <div><dt>Giornata</dt><dd><?= media_h($file['nome_giornata']) ?></dd></div>
      <div><dt>Album</dt><dd><?= media_h($file['nome_album']) ?></dd></div>
      <div><dt>Stato</dt><dd><?= $file['stato'] === 'pronto' ? 'Pronto' : ($file['stato'] === 'senza_miniatura' ? 'Originale conservato, senza miniatura' : 'Errore di elaborazione') ?></dd></div>
      <div><dt>SHA-256</dt><dd class="archivio-hash"><?= media_h($file['sha256']) ?></dd></div>
    </dl>

    <div class="form-actions">
      <?php if ($puoScaricare): ?>
        <a class="btn btn-gold" href="<?= media_h($downloadUrl) ?>">Scarica originale</a>
      <?php endif; ?>
      <?php if ($anteprimabile): ?>
        <a class="btn btn-outline" href="<?= media_h($previewUrl) ?>" target="_blank" rel="noopener">Apri anteprima</a>
      <?php endif; ?>
      <a class="btn btn-outline" href="<?= media_h(media_url('galleria.php', ['album' => (int) $file['id_album']])) ?>">Torna all'album</a>
    </div>

    <?php if ($puoEliminare): ?>
      <form method="post" action="<?= media_h(ARCHIVIO_URL) ?>/file_delete.php" style="margin-top:1rem;"
            onsubmit="return confirm('Spostare questo file nel cestino? Potrà essere ripristinato da un amministratore.');">
        <input type="hidden" name="csrf_token" value="<?= media_h(csrf_token()) ?>">
        <input type="hidden" name="id" value="<?= (int) $file['id'] ?>">
        <input type="hidden" name="ritorna" value="<?= media_h(media_url('galleria.php', ['album' => (int) $file['id_album']])) ?>">
        <button type="submit" class="btn btn-red">Sposta nel cestino</button>
      </form>
    <?php endif; ?>
  </aside>
</div>

<?php if ($duplicati !== []): ?>
  <section class="archivio-warning" style="margin-top:1.5rem;">
    <strong>Possibili duplicati</strong>
    Questo file ha lo stesso contenuto (SHA-256) di altri file nell'archivio:
    <ul style="margin:.6rem 0 0 1.1rem;">
      <?php foreach ($duplicati as $d): ?>
        <li><?= media_h($d['nome_originale']) ?> — <?= media_h($d['nome_album']) ?> — <?= media_h(date('d/m/Y H:i', strtotime((string) $d['created_at']))) ?></li>
      <?php endforeach; ?>
    </ul>
  </section>
<?php endif; ?>

<?php require_once __DIR__ . '/_footer.php'; ?>
