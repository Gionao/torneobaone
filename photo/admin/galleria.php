<?php
// photo/admin/galleria.php — Navigazione edizioni, giornate, album e file
declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';
require_media_login();

$consentite = media_edizioni_consentite();
$idEdizione = media_get_int('edizione');
$idGiornata = media_get_int('giornata');
$idAlbum = media_get_int('album');
$pagina = max(1, media_get_int('pagina', 1));

$edizione = null;
$giornata = null;
$album = null;
$vista = 'radice';

if ($idAlbum > 0) {
    $album = media_album($idAlbum);
    if (!$album) {
        media_flash('error', 'Album non trovato.');
        media_redirect_interno('/galleria.php');
    }
    if (!media_puo_vedere_album($album)) {
        http_response_code(403);
        require __DIR__ . '/_403.php';
        exit;
    }
    $idEdizione = (int) $album['id_edizione'];
    $idGiornata = (int) $album['id_giornata'];
    $edizione = media_edizione($idEdizione);
    $giornata = media_giornata($idGiornata);
    $vista = 'album';
} elseif ($idGiornata > 0) {
    $giornata = media_giornata($idGiornata);
    if (!$giornata) {
        media_flash('error', 'Giornata non trovata.');
        media_redirect_interno('/galleria.php');
    }
    if (!media_puo_vedere_edizione((int) $giornata['id_edizione'])) {
        http_response_code(403);
        require __DIR__ . '/_403.php';
        exit;
    }
    $idEdizione = (int) $giornata['id_edizione'];
    $edizione = media_edizione($idEdizione);
    $vista = 'giornata';
} elseif ($idEdizione > 0) {
    $edizione = media_edizione($idEdizione);
    if (!$edizione) {
        media_flash('error', 'Edizione non trovata.');
        media_redirect_interno('/galleria.php');
    }
    if (!media_puo_vedere_edizione($idEdizione)) {
        http_response_code(403);
        require __DIR__ . '/_403.php';
        exit;
    }
    $vista = 'edizione';
}

if ($vista === 'album') {
    $perPagina = 60;
    $totale = media_file_conteggio_album($idAlbum);
    $pagine = max(1, (int) ceil($totale / $perPagina));
    if ($pagina > $pagine) {
        $pagina = $pagine;
    }
    $file = media_file_per_album($idAlbum, $perPagina, ($pagina - 1) * $perPagina);
} elseif ($vista === 'giornata') {
    $albumGiornata = media_album_per_giornata($idGiornata);
} elseif ($vista === 'edizione') {
    $giornate = media_giornate($idEdizione);
} else {
    $edizioni = media_edizioni(false, $consentite);
}

$pageTitle = 'Galleria';
$paginaAttiva = 'galleria';
require_once __DIR__ . '/_header.php';

/** Etichetta tipo file. */
$etichettaTipo = static function (array $f): string {
    return ($f['tipo'] ?? '') === 'video' ? 'Video' : strtoupper((string) ($f['estensione'] ?? 'Immagine'));
};
$anteprimabile = static function (array $f): bool {
    return in_array(strtolower((string) ($f['estensione'] ?? '')), ['jpg', 'jpeg', 'png', 'mp4', 'mov'], true);
};
?>

<div class="archivio-pagina-testata">
  <div class="admin-page-header">
    <h1>Galleria</h1>
    <p>Sfoglia le raccolte autorizzate e scarica i contenuti</p>
  </div>
  <div class="archivio-pagina-azioni">
    <?php if (media_is_editor()): ?>
      <a class="btn btn-sm btn-outline" href="<?= media_h(ARCHIVIO_URL) ?>/edizioni/index.php">Gestisci struttura</a>
    <?php endif; ?>
    <?php if (in_array(media_ruolo(), ['uploader', 'admin'], true)): ?>
      <a class="btn btn-sm btn-gold" href="<?= media_h(ARCHIVIO_URL) ?>/upload.php">+ Carica file</a>
    <?php endif; ?>
  </div>
</div>

<nav class="archivio-briciole" aria-label="Percorso">
  <a href="<?= media_h(ARCHIVIO_URL) ?>/galleria.php">Galleria</a>
  <?php if ($edizione): ?>
    <span class="sep">/</span>
    <?php if ($vista === 'edizione'): ?>
      <strong><?= media_h($edizione['nome']) ?></strong>
    <?php else: ?>
      <a href="<?= media_h(media_url('galleria.php', ['edizione' => (int) $edizione['id']])) ?>"><?= media_h($edizione['nome']) ?></a>
    <?php endif; ?>
  <?php endif; ?>
  <?php if ($giornata): ?>
    <span class="sep">/</span>
    <?php if ($vista === 'giornata'): ?>
      <strong><?= media_h($giornata['nome']) ?></strong>
    <?php else: ?>
      <a href="<?= media_h(media_url('galleria.php', ['giornata' => (int) $giornata['id']])) ?>"><?= media_h($giornata['nome']) ?></a>
    <?php endif; ?>
  <?php endif; ?>
  <?php if ($album): ?>
    <span class="sep">/</span>
    <strong><?= media_h($album['nome']) ?></strong>
  <?php endif; ?>
</nav>

<?php if ($vista === 'radice'): ?>

  <?php if ($edizioni === []): ?>
    <div class="archivio-empty">
      <strong>Nessuna edizione disponibile</strong>
      <?= $consentite === null
          ? 'Crea la prima edizione dalla gestione della struttura.'
          : 'Il tuo account non è ancora collegato a un\'edizione. Chiedi a un amministratore di abilitarti.' ?>
    </div>
  <?php else: ?>
    <div class="archivio-griglia">
      <?php foreach ($edizioni as $e): ?>
        <article class="archivio-scheda">
          <div class="archivio-scheda__corpo">
            <h3><?= media_h($e['nome']) ?><?= (int) $e['attiva'] === 1 ? '' : ' <span class="archivio-muted">· archiviata</span>' ?></h3>
            <p>Anno <?= (int) $e['anno'] ?></p>
            <div class="archivio-scheda__meta">
              <span><?= (int) $e['numero_giornate'] ?> giornate</span>
              <span><?= (int) $e['numero_file'] ?> file</span>
            </div>
            <div class="archivio-scheda__azioni">
              <a class="btn btn-sm btn-gold" href="<?= media_h(media_url('galleria.php', ['edizione' => (int) $e['id']])) ?>">Apri edizione</a>
            </div>
          </div>
        </article>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>

<?php elseif ($vista === 'edizione'): ?>

  <div class="archivio-empty" style="margin-bottom:1.2rem;">
    <strong><?= media_h($edizione['nome']) ?> — <?= (int) $edizione['anno'] ?></strong>
    <?= !empty($edizione['descrizione']) ? media_h($edizione['descrizione']) : 'Seleziona una giornata per consultare gli album.' ?>
  </div>

  <?php if (media_is_editor()): ?>
    <div class="archivio-pagina-azioni">
      <a class="btn btn-sm btn-gold" href="<?= media_h(media_url('giornate/form.php', ['edizione' => $idEdizione])) ?>">+ Nuova giornata</a>
      <a class="btn btn-sm btn-outline" href="<?= media_h(media_url('edizioni/form.php', ['id' => $idEdizione])) ?>">Modifica edizione</a>
    </div>
  <?php endif; ?>

  <?php if ($giornate === []): ?>
    <div class="archivio-empty"><strong>Nessuna giornata</strong>Non sono ancora state create giornate per questa edizione.</div>
  <?php else: ?>
    <div class="archivio-griglia">
      <?php foreach ($giornate as $g): ?>
        <article class="archivio-scheda">
          <div class="archivio-scheda__corpo">
            <h3><?= media_h($g['nome']) ?></h3>
            <p><?= !empty($g['data_evento']) ? media_h(date('d/m/Y', strtotime((string) $g['data_evento']))) : 'Data non indicata' ?></p>
            <div class="archivio-scheda__meta">
              <span><?= (int) $g['numero_album'] ?> album</span>
              <span><?= (int) $g['numero_file'] ?> file</span>
            </div>
            <div class="archivio-scheda__azioni">
              <a class="btn btn-sm btn-gold" href="<?= media_h(media_url('galleria.php', ['giornata' => (int) $g['id']])) ?>">Apri giornata</a>
              <?php if (media_is_editor()): ?>
                <a class="btn btn-sm btn-outline" href="<?= media_h(media_url('giornate/form.php', ['id' => (int) $g['id']])) ?>">Modifica</a>
              <?php endif; ?>
            </div>
          </div>
        </article>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>

<?php elseif ($vista === 'giornata'): ?>

  <div class="archivio-empty" style="margin-bottom:1.2rem;">
    <strong><?= media_h($giornata['nome']) ?></strong>
    <?= media_h($edizione['nome'] ?? '') ?><?= !empty($giornata['data_evento']) ? ' · ' . media_h(date('d/m/Y', strtotime((string) $giornata['data_evento']))) : '' ?>
    <?= !empty($giornata['note']) ? '<br>' . media_h($giornata['note']) : '' ?>
  </div>

  <?php if (media_is_editor()): ?>
    <div class="archivio-pagina-azioni">
      <a class="btn btn-sm btn-gold" href="<?= media_h(media_url('album/form.php', ['giornata' => $idGiornata])) ?>">+ Nuovo album</a>
      <a class="btn btn-sm btn-outline" href="<?= media_h(media_url('giornate/form.php', ['id' => $idGiornata])) ?>">Modifica giornata</a>
    </div>
  <?php endif; ?>

  <?php if ($albumGiornata === []): ?>
    <div class="archivio-empty"><strong>Nessun album</strong>Crea un album per iniziare a caricare foto e video.</div>
  <?php else: ?>
    <div class="archivio-griglia">
      <?php foreach ($albumGiornata as $a): ?>
        <article class="archivio-scheda">
          <?php if ((int) $a['prima_foto'] > 0): ?>
            <a href="<?= media_h(media_url('galleria.php', ['album' => (int) $a['id']])) ?>" style="display:block;aspect-ratio:16/9;overflow:hidden;background:#08111b;">
              <img src="<?= media_h(MEDIA_API_URL) ?>/thumb.php?id=<?= (int) $a['prima_foto'] ?>" alt="" loading="lazy" style="width:100%;height:100%;object-fit:cover;">
            </a>
          <?php endif; ?>
          <div class="archivio-scheda__corpo">
            <h3><?= media_h($a['nome']) ?></h3>
            <p><?= !empty($a['descrizione']) ? media_h($a['descrizione']) : 'Album della giornata' ?></p>
            <div class="archivio-scheda__meta"><span><?= (int) $a['numero_file'] ?> file</span></div>
            <div class="archivio-scheda__azioni">
              <a class="btn btn-sm btn-gold" href="<?= media_h(media_url('galleria.php', ['album' => (int) $a['id']])) ?>">Apri album</a>
              <?php if (media_is_editor()): ?>
                <a class="btn btn-sm btn-outline" href="<?= media_h(media_url('album/form.php', ['id' => (int) $a['id']])) ?>">Modifica</a>
              <?php endif; ?>
            </div>
          </div>
        </article>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>

<?php else: ?>

  <div class="archivio-pagina-testata">
    <div class="admin-page-header" style="margin-bottom:.9rem;">
      <h1 style="font-size:1.45rem;"><?= media_h($album['nome']) ?></h1>
      <p><?= media_h($album['nome_edizione']) ?> · <?= media_h($album['nome_giornata']) ?> · <?= (int) $totale ?> file</p>
      <?php if (!empty($album['descrizione'])): ?>
        <p><?= media_h($album['descrizione']) ?></p>
      <?php endif; ?>
    </div>
    <?php if (media_is_editor()): ?>
      <div class="archivio-pagina-azioni">
        <a class="btn btn-sm btn-gold" href="<?= media_h(media_url('upload.php', ['album' => $idAlbum])) ?>">+ Carica qui</a>
        <a class="btn btn-sm btn-outline" href="<?= media_h(media_url('album/form.php', ['id' => $idAlbum])) ?>">Modifica album</a>
      </div>
    <?php endif; ?>
  </div>

  <?php if ($totale === 0): ?>
    <div class="archivio-empty">
      <strong>Album vuoto</strong>
      Non ci sono ancora file in questo album.
      <div style="margin-top:1rem;"><a class="btn btn-gold" href="<?= media_h(media_url('upload.php', ['album' => $idAlbum])) ?>">Carica i primi file</a></div>
    </div>
  <?php else: ?>
    <div class="archivio-barra">
      <div class="archivio-barra__azioni">
        <?php if (media_is_editor()): ?>
          <button type="button" class="btn btn-sm btn-outline" id="selezionaTutti">Seleziona tutti</button>
          <span class="archivio-selezione-info"><span id="numeroSelezionati">0</span> selezionati</span>
        <?php endif; ?>
        <a class="btn btn-sm btn-outline" href="<?= media_h(media_url('galleria.php', ['giornata' => (int) $album['id_giornata']])) ?>">Torna agli album</a>
      </div>
      <div class="archivio-barra__azioni">
        <?php if (media_is_editor()): ?>
          <button type="button" class="btn btn-sm btn-gold" id="scaricaAlbum"
                  data-ambito="album" data-id="<?= (int) $idAlbum ?>"
                  data-url-create="<?= media_h(MEDIA_API_URL) ?>/zip_create.php"
                  data-url-status="<?= media_h(MEDIA_API_URL) ?>/zip_status.php">Scarica album (ZIP)</button>
          <button type="button" class="btn btn-sm btn-outline" id="scaricaSelezionati" disabled>Scarica selezionati (ZIP)</button>
        <?php endif; ?>
      </div>
    </div>

    <div id="zipEsito" class="archivio-info" style="display:none;" role="status"></div>

    <div class="archivio-file-griglia" id="grigliaFile">
      <?php foreach ($file as $f): ?>
        <?php
          $idFile = (int) $f['id'];
          $thumbUrl = !empty($f['miniatura']) ? MEDIA_API_URL . '/thumb.php?id=' . $idFile : '';
          $downloadUrl = MEDIA_API_URL . '/download.php?id=' . $idFile;
          $previewUrl = $downloadUrl . '&inline=1';
          $puoScaricare = media_puo_scaricare($f);
          $puoEliminare = media_puo_eliminare($f);
          $puoAnteprima = $puoScaricare && $anteprimabile($f);
        ?>
        <article class="archivio-file" data-file-id="<?= $idFile ?>">
          <?php if (media_is_editor()): ?>
            <input type="checkbox" class="archivio-file__check seleziona-file" value="<?= $idFile ?>"
                   aria-label="Seleziona <?= media_h($f['nome_originale']) ?>">
          <?php endif; ?>
          <?php if ($puoAnteprima): ?>
            <a class="archivio-file__anteprima" href="<?= media_h($previewUrl) ?>" target="_blank" rel="noopener">
          <?php else: ?>
            <span class="archivio-file__anteprima">
          <?php endif; ?>
              <span class="archivio-file__tipo"><?= media_h($etichettaTipo($f)) ?></span>
              <?php if ($thumbUrl !== ''): ?>
                <img src="<?= media_h($thumbUrl) ?>" alt="<?= media_h($f['nome_originale']) ?>" loading="lazy">
              <?php else: ?>
                <span class="archivio-file__segnaposto">
                  <span><?= ($f['tipo'] ?? '') === 'video' ? '▶' : '▣' ?></span>
                  <span><?= media_h(strtoupper((string) $f['estensione'])) ?></span>
                </span>
              <?php endif; ?>
          <?php if ($puoAnteprima): ?></a><?php else: ?></span><?php endif; ?>
          <div class="archivio-file__corpo">
            <span class="archivio-file__nome" title="<?= media_h($f['nome_originale']) ?>"><?= media_h($f['nome_originale']) ?></span>
            <span class="archivio-file__meta">
              <span><?= media_h(media_bytes_leggibili((int) $f['dimensione'])) ?></span>
              <span><?= media_h(date('d/m/Y', strtotime((string) $f['created_at']))) ?></span>
            </span>
            <div class="archivio-file__azioni">
              <a class="btn btn-sm btn-outline" href="<?= media_h(media_url('file.php', ['id' => $idFile])) ?>">Dettagli</a>
              <?php if ($puoScaricare): ?>
                <a class="btn btn-sm btn-gold" href="<?= media_h($downloadUrl) ?>">Scarica</a>
              <?php endif; ?>
              <?php if ($puoEliminare): ?>
                <form method="post" action="<?= media_h(ARCHIVIO_URL) ?>/file_delete.php" style="display:inline;"
                      onsubmit="return confirm('Spostare questo file nel cestino?');">
                  <input type="hidden" name="csrf_token" value="<?= media_h(csrf_token()) ?>">
                  <input type="hidden" name="id" value="<?= $idFile ?>">
                  <input type="hidden" name="ritorna" value="<?= media_h(media_url('galleria.php', ['album' => $idAlbum])) ?>">
                  <button type="submit" class="btn btn-sm btn-red">Elimina</button>
                </form>
              <?php endif; ?>
            </div>
          </div>
        </article>
      <?php endforeach; ?>
    </div>

    <?php if ($pagine > 1): ?>
      <nav class="archivio-paginazione" aria-label="Paginazione">
        <?php for ($p = 1; $p <= $pagine; $p++): ?>
          <?php if ($p === $pagina): ?>
            <span class="corrente" aria-current="page"><?= $p ?></span>
          <?php else: ?>
            <a href="<?= media_h(media_url('galleria.php', ['album' => $idAlbum, 'pagina' => $p])) ?>"><?= $p ?></a>
          <?php endif; ?>
        <?php endfor; ?>
      </nav>
    <?php endif; ?>
  <?php endif; ?>

<?php endif; ?>

<?php if ($vista === 'album' && media_is_editor()): ?>
<script src="<?= media_h(MEDIA_ROOT_URL) ?>/assets/js/galleria.js?v=<?= (int) (@filemtime(dirname(__DIR__) . '/assets/js/galleria.js') ?: time()) ?>"></script>
<?php endif; ?>
<?php require_once __DIR__ . '/_footer.php'; ?>
