<?php
// photo/admin/index.php — Panoramica dell'archivio
declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';
require_media_login();

$consentite = media_edizioni_consentite();
$edizioni = media_edizioni(false, $consentite);
$stat = media_statistiche($consentite);
$numGiornate = 0;
$numAlbum = 0;
foreach ($edizioni as $edizione) {
    $numGiornate += (int) $edizione['numero_giornate'];
}

if ($consentite === null) {
    $numAlbum = (int) db()->query('SELECT COUNT(*) FROM media_album')->fetchColumn();
} elseif ($consentite !== []) {
    $segnaposto = implode(',', array_fill(0, count($consentite), '?'));
    $stmt = db()->prepare('
        SELECT COUNT(*) FROM media_album a
        JOIN media_giornate g ON g.id = a.id_giornata
        WHERE g.id_edizione IN (' . $segnaposto . ')
    ');
    $stmt->execute($consentite);
    $numAlbum = (int) $stmt->fetchColumn();
}

$archivioOk = null;
$archivioMsg = '';
if (media_is_admin()) {
    $check = media_storage_check();
    $archivioOk = $check['ok'];
    $archivioMsg = $check['messaggio'];
}

$pageTitle = 'Panoramica archivio';
$paginaAttiva = 'dashboard';
require_once __DIR__ . '/_header.php';
?>

<div class="admin-page-header">
  <h1>Archivio multimediale</h1>
  <p>Foto e video privati del torneo — accesso riservato allo staff autorizzato</p>
</div>

<?php if ($archivioOk === false): ?>
  <div class="archivio-warning" role="alert">
    <strong>Attenzione:</strong> <?= media_h($archivioMsg) ?>
    Controlla le impostazioni dell'archivio prima di caricare file.
  </div>
<?php endif; ?>

<div class="archivio-stat-grid">
  <div class="archivio-stat"><strong><?= (int) $stat['file_totali'] ?></strong><span>File totali</span></div>
  <div class="archivio-stat"><strong><?= (int) $stat['foto'] ?></strong><span>Foto</span></div>
  <div class="archivio-stat"><strong><?= (int) $stat['video'] ?></strong><span>Video</span></div>
  <div class="archivio-stat"><strong><?= media_h(media_bytes_leggibili($stat['byte_totali'])) ?></strong><span>Spazio occupato</span></div>
  <?php if (media_is_admin()): ?>
  <div class="archivio-stat"><strong><?= (int) $stat['nel_cestino'] ?></strong><span>Nel cestino</span></div>
  <?php endif; ?>
</div>

<div class="quick-actions" style="margin-bottom:2rem;">
  <h2>Azioni rapide</h2>
  <div class="qa-grid">
    <?php if (in_array(media_ruolo(), ['uploader', 'admin'], true)): ?>
      <a href="<?= media_h(ARCHIVIO_URL) ?>/upload.php" class="qa-btn">+ Carica file</a>
    <?php endif; ?>
    <a href="<?= media_h(ARCHIVIO_URL) ?>/galleria.php" class="qa-btn qa-btn--outline">Sfoglia la galleria</a>
    <?php if (media_is_editor()): ?>
      <a href="<?= media_h(ARCHIVIO_URL) ?>/edizioni/form.php" class="qa-btn qa-btn--outline">+ Nuova edizione</a>
    <?php endif; ?>
  </div>
</div>

<div class="archivio-pagina-testata">
  <div class="admin-page-header" style="margin-top:1rem;">
    <h1 style="font-size:1.45rem;">Edizioni</h1>
    <p><?= count($edizioni) ?> edizioni · <?= $numGiornate ?> giornate · <?= $numAlbum ?> album</p>
  </div>
  <?php if (media_is_editor()): ?>
    <div class="archivio-pagina-azioni">
      <a class="btn btn-sm btn-outline" href="<?= media_h(ARCHIVIO_URL) ?>/edizioni/index.php">Gestisci la struttura</a>
    </div>
  <?php endif; ?>
</div>

<?php if ($edizioni === []): ?>
  <div class="archivio-empty">
    <?php if (media_is_editor()): ?>
      <strong>Nessuna edizione presente</strong>
      Crea la prima edizione per iniziare a organizzare foto e video.
      <div style="margin-top:1rem;"><a class="btn btn-gold" href="<?= media_h(ARCHIVIO_URL) ?>/edizioni/form.php">Crea edizione</a></div>
    <?php else: ?>
      <strong>Nessuna edizione assegnata</strong>
      Il tuo account non è ancora collegato a un'edizione. Chiedi a un amministratore di abilitarti.
    <?php endif; ?>
  </div>
<?php else: ?>
  <div class="archivio-griglia">
    <?php foreach ($edizioni as $edizione): ?>
      <article class="archivio-scheda">
        <div class="archivio-scheda__corpo">
          <h3><?= media_h($edizione['nome']) ?><?= (int) $edizione['attiva'] === 1 ? '' : ' <span class="archivio-muted">· archiviata</span>' ?></h3>
          <p>Anno <?= (int) $edizione['anno'] ?></p>
          <?php if (!empty($edizione['descrizione'])): ?>
            <p class="archivio-testo-piccolo"><?= media_h($edizione['descrizione']) ?></p>
          <?php endif; ?>
          <div class="archivio-scheda__meta">
            <span><?= (int) $edizione['numero_giornate'] ?> giornate</span>
            <span><?= (int) $edizione['numero_file'] ?> file</span>
          </div>
          <div class="archivio-scheda__azioni">
            <a class="btn btn-sm btn-gold" href="<?= media_h(ARCHIVIO_URL) ?>/galleria.php?edizione=<?= (int) $edizione['id'] ?>">Apri</a>
            <?php if (media_is_editor()): ?>
              <a class="btn btn-sm btn-outline" href="<?= media_h(ARCHIVIO_URL) ?>/edizioni/form.php?id=<?= (int) $edizione['id'] ?>">Modifica</a>
            <?php endif; ?>
          </div>
        </div>
      </article>
    <?php endforeach; ?>
  </div>
<?php endif; ?>

<?php require_once __DIR__ . '/_footer.php'; ?>
