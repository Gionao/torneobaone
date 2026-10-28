<?php
// photo/admin/upload.php — Caricamento multiplo e riprendibile
declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';
require_media_login();

if (!in_array(media_ruolo(), ['uploader', 'admin'], true)) {
    http_response_code(403);
    require __DIR__ . '/_403.php';
    exit;
}

$consentite = media_edizioni_consentite();
$albumDisponibili = media_album_tutti($consentite);
$idAlbumPreselezionato = media_get_int('album');

$albumPerGruppo = [];
foreach ($albumDisponibili as $a) {
    $gruppo = $a['nome_edizione'] . ' (' . (int) $a['anno'] . ') — ' . $a['nome_giornata'];
    $albumPerGruppo[$gruppo][] = $a;
}

$uploadAttivi = media_upload_attivi(media_admin_id());
$limiteByte = media_max_upload_bytes();
$chunkByte = media_chunk_bytes();
$limiti = media_limiti_php();

$pageTitle = 'Carica file';
$paginaAttiva = 'upload';
require_once __DIR__ . '/_header.php';
?>

<div class="admin-page-header">
  <h1>Carica file</h1>
  <p>Foto e video vengono salvati nella raccolta scelta, senza modificare l'originale.</p>
</div>

<?php if ($albumDisponibili === []): ?>
  <div class="archivio-empty">
    <strong>Nessun album disponibile</strong>
    <?php if (media_is_admin()): ?>
      Crea prima un'edizione, una giornata e un album dalla gestione della struttura.
      <div style="margin-top:1rem;"><a class="btn btn-gold" href="<?= media_h(ARCHIVIO_URL) ?>/edizioni/index.php">Gestisci struttura</a></div>
    <?php else: ?>
      Il tuo account non è ancora collegato a un'edizione. Chiedi a un amministratore di abilitarti.
    <?php endif; ?>
  </div>
<?php else: ?>
  <div class="archivio-upload-layout">
    <section class="archivio-pannello">
      <h2>Nuovo caricamento</h2>
      <p>Seleziona la raccolta di destinazione e uno o più file. Puoi caricare più file in sequenza; se la connessione si interrompe, potrai riprendere il caricamento.</p>

      <div class="form-group">
        <label for="albumDestinazione">Raccolta di destinazione</label>
        <select id="albumDestinazione" required>
          <option value="">— Scegli edizione, giornata e album —</option>
          <?php foreach ($albumPerGruppo as $gruppo => $albumGruppo): ?>
            <optgroup label="<?= media_h($gruppo) ?>">
              <?php foreach ($albumGruppo as $a): ?>
                <option value="<?= (int) $a['id'] ?>" <?= (int) $a['id'] === $idAlbumPreselezionato ? 'selected' : '' ?>>
                  <?= media_h($a['nome']) ?>
                </option>
              <?php endforeach; ?>
            </optgroup>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="archivio-drop" id="zonaDrop">
        <input type="file" id="fileInput" multiple accept=".jpg,.jpeg,.png,.heic,.mp4,.mov,image/jpeg,image/png,image/heic,video/mp4,video/quicktime">
        <span class="archivio-drop-icona" aria-hidden="true">＋</span>
        <strong>Tocca qui o trascina i file</strong>
        <span>JPG, JPEG, PNG, HEIC, MP4, MOV · massimo <?= media_h(media_bytes_leggibili($limiteByte)) ?> per file</span>
      </div>

      <div class="archivio-limiti">
        <span>Blocco di invio: <strong><?= media_h(media_bytes_leggibili($chunkByte)) ?></strong></span>
        <span>Formati: <strong>JPG · PNG · HEIC · MP4 · MOV</strong></span>
        <span>Limite PHP per blocco: <strong><?= media_h(media_bytes_leggibili((int) $limiti['upload_max_filesize'])) ?></strong></span>
      </div>

      <div class="form-actions">
        <button type="button" class="btn btn-gold" id="avviaUpload" disabled>Avvia caricamento</button>
        <button type="button" class="btn btn-outline" id="svuotaCoda" disabled>Svuota elenco</button>
      </div>

      <div class="archivio-progresso-generale" id="progressoGenerale" hidden>
        <div class="archivio-progresso-generale__testo">
          <span id="progressoTesto">Preparazione…</span>
          <span id="progressoPercento">0%</span>
        </div>
        <div class="archivio-barra-progresso"><span id="progressoBarra"></span></div>
      </div>

      <div class="archivio-coda" id="codaUpload" aria-live="polite"></div>
      <noscript><div class="archivio-warning">Per caricare i file è necessario abilitare JavaScript nel browser.</div></noscript>
    </section>

    <aside class="archivio-pannello">
      <h2>Caricamenti sospesi</h2>
      <p>Se un caricamento è stato interrotto, qui trovi i file incompleti. Per riprendere devi selezionare di nuovo lo stesso file dal dispositivo.</p>
      <div class="archivio-sospesi" id="caricamentiSospesi">
        <?php if ($uploadAttivi === []): ?>
          <span class="archivio-muted archivio-testo-piccolo">Nessun caricamento in sospeso.</span>
        <?php else: ?>
          <?php foreach ($uploadAttivi as $u): ?>
            <?php
              $totaleU = max(1, (int) $u['dimensione_totale']);
              $percentoU = min(100, (int) floor((int) $u['dimensione_ricevuta'] * 100 / $totaleU));
            ?>
            <div class="archivio-sospeso" data-upload="<?= media_h($u['uuid']) ?>"
                 data-nome="<?= media_h($u['nome_originale']) ?>"
                 data-dimensione="<?= (int) $u['dimensione_totale'] ?>"
                 data-album="<?= (int) $u['id_album'] ?>">
              <div class="archivio-sospeso__nome"><?= media_h($u['nome_originale']) ?></div>
              <div class="archivio-sospeso__meta">
                <?= media_h($u['nome_edizione']) ?> · <?= media_h($u['nome_album']) ?> ·
                <?= media_h(media_bytes_leggibili((int) $u['dimensione_ricevuta'])) ?> di <?= media_h(media_bytes_leggibili((int) $u['dimensione_totale'])) ?> (<?= $percentoU ?>%)
              </div>
              <div class="archivio-barra-progresso" style="margin-bottom:.55rem;"><span style="width:<?= $percentoU ?>%"></span></div>
              <div class="archivio-sospeso__azioni">
                <button type="button" class="btn btn-sm btn-gold riprendi" data-upload="<?= media_h($u['uuid']) ?>">Riprendi</button>
                <button type="button" class="btn btn-sm btn-outline annulla-sospeso" data-upload="<?= media_h($u['uuid']) ?>">Annulla</button>
                <input type="file" class="file-ripresa" data-upload="<?= media_h($u['uuid']) ?>" accept=".jpg,.jpeg,.png,.heic,.mp4,.mov" hidden>
              </div>
            </div>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>

      <div class="archivio-istruzioni">
        <strong>Come funziona la ripresa</strong><br>
        Il file viene inviato a blocchi. Se la connessione cade, il server conserva i blocchi già ricevuti per 48 ore. Seleziona lo stesso file per continuare dal punto interrotto.
      </div>
    </aside>
  </div>

  <script>
    window.ARCHIVIO_UPLOAD = {
      initUrl: <?= json_encode(MEDIA_UPLOAD_URL . '/init.php', JSON_UNESCAPED_SLASHES) ?>,
      chunkUrl: <?= json_encode(MEDIA_UPLOAD_URL . '/chunk.php', JSON_UNESCAPED_SLASHES) ?>,
      completeUrl: <?= json_encode(MEDIA_UPLOAD_URL . '/complete.php', JSON_UNESCAPED_SLASHES) ?>,
      abortUrl: <?= json_encode(MEDIA_UPLOAD_URL . '/abort.php', JSON_UNESCAPED_SLASHES) ?>,
      statusUrl: <?= json_encode(MEDIA_API_URL . '/upload_status.php', JSON_UNESCAPED_SLASHES) ?>,
      csrf: <?= json_encode(csrf_token(), JSON_UNESCAPED_SLASHES) ?>,
      maxBytes: <?= (int) $limiteByte ?>,
      chunkBytes: <?= (int) $chunkByte ?>,
      formati: ['jpg', 'jpeg', 'png', 'heic', 'mp4', 'mov']
    };
  </script>
  <script src="<?= media_h(MEDIA_ROOT_URL) ?>/assets/js/upload.js?v=<?= (int) (@filemtime(dirname(__DIR__) . '/assets/js/upload.js') ?: time()) ?>"></script>
<?php endif; ?>

<?php require_once __DIR__ . '/_footer.php'; ?>
