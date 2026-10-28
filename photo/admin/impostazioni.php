<?php
// photo/admin/impostazioni.php — Configurazione e manutenzione (solo amministratori)
declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';
require_once dirname(__DIR__) . '/includes/media_maintenance.php';
require_media_admin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify()) {
        media_flash('error', 'Sessione scaduta. Ricarica la pagina e riprova.');
        media_redirect_interno('/impostazioni.php');
    }

    $azione = (string) ($_POST['azione'] ?? '');

    try {
        if ($azione === 'salva') {
            $valori = [
                'media_storage_path'     => trim((string) ($_POST['media_storage_path'] ?? '')),
                'media_max_upload_mb'    => (string) max(1, min(10240, media_post_int('media_max_upload_mb', 1024))),
                'media_chunk_mb'         => (string) max(1, min(32, media_post_int('media_chunk_mb', 4))),
                'media_thumb_max_px'     => (string) max(96, min(1600, media_post_int('media_thumb_max_px', 480))),
                'media_retention_giorni' => (string) max(1, min(3650, media_post_int('media_retention_giorni', 30))),
                'media_zip_max_file'     => (string) max(1, min(20000, media_post_int('media_zip_max_file', 2000))),
                'media_zip_max_mb'       => (string) max(10, min(20480, media_post_int('media_zip_max_mb', 5000))),
                'media_zip_parte_mb'     => (string) max(10, min(4096, media_post_int('media_zip_parte_mb', 1500))),
                'media_zip_scadenza_ore' => (string) max(1, min(168, media_post_int('media_zip_scadenza_ore', 24))),
            ];

            if ($valori['media_zip_parte_mb'] > $valori['media_zip_max_mb']) {
                throw new RuntimeException('La dimensione della singola parte ZIP non può superare il limite totale dello ZIP.');
            }

            if ($valori['media_storage_path'] !== '') {
                $verifica = media_storage_valida_percorso($valori['media_storage_path']);
                if (!$verifica['ok']) {
                    throw new RuntimeException('Percorso archivio non valido: ' . $verifica['messaggio']);
                }
            }

            media_impostazioni_salva($valori);
            media_log('impostazioni_aggiornate', 'impostazioni', null, [
                'storage' => $valori['media_storage_path'] !== '' ? 'personalizzato' : 'predefinito',
            ]);
            media_flash('success', 'Impostazioni salvate.');
            media_redirect_interno('/impostazioni.php');
        }

        if ($azione === 'pulizie') {
            $esiti = media_pulizie_periodiche();
            media_log('pulizie_eseguite', 'sistema', null, $esiti);
            media_flash('success', 'Pulizie completate: '
                . $esiti['upload'] . ' caricamenti scaduti, '
                . $esiti['cestino'] . ' file dal cestino, '
                . $esiti['zip'] . ' archivi ZIP scaduti, '
                . $esiti['zip_bloccati'] . ' ZIP ripristinati, '
                . $esiti['quarantena'] . ' file in quarantena.');
            media_redirect_interno('/impostazioni.php');
        }

        throw new RuntimeException('Azione non riconosciuta.');
    } catch (Throwable $e) {
        media_flash('error', $e->getMessage());
        media_redirect_interno('/impostazioni.php');
    }
}

$valori = media_impostazioni_default();
$salvate = media_impostazioni_salvate();
foreach ($salvate as $chiave => $valore) {
    if (array_key_exists($chiave, $valori)) {
        $valori[$chiave] = $valore;
    }
}

$limiti = media_limiti_php();
$storageCheck = media_storage_check();
$storageRoot = '';
try {
    $storageRoot = media_storage_root(false);
} catch (Throwable $e) {
    $storageRoot = '';
}
$spazioLibero = media_spazio_libero();
$numeroUpload = (int) db()->query('SELECT COUNT(*) FROM media_upload WHERE stato = \'in_corso\'')->fetchColumn();
$numeroZip = (int) db()->query('SELECT COUNT(*) FROM media_zip_job WHERE stato IN (\'in_attesa\',\'in_corso\')')->fetchColumn();

$pageTitle = 'Impostazioni archivio';
$paginaAttiva = 'impostazioni';
require_once __DIR__ . '/_header.php';
?>

<div class="admin-page-header">
  <h1>Impostazioni dell'archivio</h1>
  <p>Configurazione dei limiti, dello spazio di archiviazione e delle pulizie automatiche.</p>
</div>

<h2 class="archivio-sezione-titolo">Stato dell'ambiente</h2>
<dl class="archivio-ambiente">
  <div><dt>Ambiente</dt><dd><?= media_h(ENV) ?></dd></div>
  <div><dt>PHP</dt><dd><?= media_h(PHP_VERSION) ?></dd></div>
  <div><dt>Percorso archivio</dt><dd><?= media_h($storageRoot !== '' ? $storageRoot : 'non disponibile') ?></dd></div>
  <div><dt>Archivio scrivibile</dt><dd class="<?= $storageCheck['ok'] ? 'archivio-ok' : 'archivio-ko' ?>"><?= media_h($storageCheck['messaggio']) ?></dd></div>
  <div><dt>Spazio libero</dt><dd><?= $spazioLibero === false ? 'non rilevato' : media_h(media_bytes_leggibili($spazioLibero)) ?></dd></div>
  <div><dt>upload_max_filesize</dt><dd><?= media_h(media_bytes_leggibili((int) $limiti['upload_max_filesize'])) ?></dd></div>
  <div><dt>post_max_size</dt><dd><?= media_h(media_bytes_leggibili((int) $limiti['post_max_size'])) ?></dd></div>
  <div><dt>memory_limit</dt><dd><?= (int) $limiti['memory_limit'] > 0 ? media_h(media_bytes_leggibili((int) $limiti['memory_limit'])) : 'illimitato' ?></dd></div>
  <div><dt>max_execution_time</dt><dd><?= (int) $limiti['max_execution_time'] === 0 ? 'illimitato' : (int) $limiti['max_execution_time'] . ' s' ?></dd></div>
  <div><dt>Blocco di invio effettivo</dt><dd><?= media_h(media_bytes_leggibili((int) $limiti['chunk_size'])) ?></dd></div>
  <div><dt>Estensioni</dt><dd>
    ZIP <?= $limiti['zip'] ? '<span class="archivio-ok">sì</span>' : '<span class="archivio-ko">no</span>' ?> ·
    GD <?= $limiti['gd'] ? '<span class="archivio-ok">sì</span>' : '<span class="archivio-ko">no</span>' ?> ·
    finfo <?= $limiti['fileinfo'] ? '<span class="archivio-ok">sì</span>' : '<span class="archivio-ko">no</span>' ?> ·
    EXIF <?= $limiti['exif'] ? '<span class="archivio-ok">sì</span>' : '<span class="archivio-ko">no</span>' ?>
  </dd></div>
  <div><dt>Caricamenti in corso</dt><dd><?= $numeroUpload ?></dd></div>
  <div><dt>ZIP in preparazione</dt><dd><?= $numeroZip ?></dd></div>
</dl>

<?php if (!$storageCheck['ok']): ?>
  <div class="archivio-warning" role="alert">
    <strong>Archivio non utilizzabile:</strong> <?= media_h($storageCheck['messaggio']) ?>
    Correggi il percorso o i permessi della cartella prima di caricare file.
  </div>
<?php endif; ?>

<section class="admin-form" style="margin-bottom:2rem;">
  <h2 class="archivio-sezione-titolo">Limiti e conservazione</h2>
  <form method="post">
    <input type="hidden" name="csrf_token" value="<?= media_h(csrf_token()) ?>">
    <input type="hidden" name="azione" value="salva">

    <div class="form-group">
      <label for="media_storage_path">Percorso archivio (vuoto = predefinito)</label>
      <input type="text" id="media_storage_path" name="media_storage_path" maxlength="255"
             value="<?= media_h($valori['media_storage_path']) ?>"
             placeholder="es. D:\archivio-media oppure storage/media">
      <small class="archivio-muted">Predefinito: <code>storage/media</code> nella radice del progetto. Se il percorso è dentro la document root vengono create le protezioni Apache.</small>
    </div>

    <div class="form-group">
      <label for="media_max_upload_mb">Dimensione massima per file (MB)</label>
      <input type="number" id="media_max_upload_mb" name="media_max_upload_mb" min="1" max="10240"
             value="<?= (int) $valori['media_max_upload_mb'] ?>">
      <small class="archivio-muted">Valore desiderato: 1024 MB. Il limite effettivo dipende dallo spazio disco, dal server web e dai tempi di esecuzione.</small>
    </div>

    <div class="form-group">
      <label for="media_chunk_mb">Dimensione del blocco di invio (MB)</label>
      <input type="number" id="media_chunk_mb" name="media_chunk_mb" min="1" max="32"
             value="<?= (int) $valori['media_chunk_mb'] ?>">
      <small class="archivio-muted">Il server riduce automaticamente il blocco all'80% di upload_max_filesize e post_max_size.</small>
    </div>

    <div class="form-group">
      <label for="media_thumb_max_px">Lato massimo miniature (pixel)</label>
      <input type="number" id="media_thumb_max_px" name="media_thumb_max_px" min="96" max="1600"
             value="<?= (int) $valori['media_thumb_max_px'] ?>">
    </div>

    <div class="form-group">
      <label for="media_retention_giorni">Giorni di conservazione nel cestino</label>
      <input type="number" id="media_retention_giorni" name="media_retention_giorni" min="1" max="3650"
             value="<?= (int) $valori['media_retention_giorni'] ?>">
    </div>

    <h2 class="archivio-sezione-titolo" style="margin-top:1.6rem;">Archivi ZIP</h2>
    <div class="form-group">
      <label for="media_zip_max_file">Numero massimo di file per ZIP</label>
      <input type="number" id="media_zip_max_file" name="media_zip_max_file" min="1" max="20000"
             value="<?= (int) $valori['media_zip_max_file'] ?>">
    </div>
    <div class="form-group">
      <label for="media_zip_max_mb">Dimensione massima totale dello ZIP (MB)</label>
      <input type="number" id="media_zip_max_mb" name="media_zip_max_mb" min="10" max="20480"
             value="<?= (int) $valori['media_zip_max_mb'] ?>">
    </div>
    <div class="form-group">
      <label for="media_zip_parte_mb">Dimensione massima di ogni parte ZIP (MB)</label>
      <input type="number" id="media_zip_parte_mb" name="media_zip_parte_mb" min="10" max="4096"
             value="<?= (int) $valori['media_zip_parte_mb'] ?>">
    </div>
    <div class="form-group">
      <label for="media_zip_scadenza_ore">Ore di disponibilità di uno ZIP</label>
      <input type="number" id="media_zip_scadenza_ore" name="media_zip_scadenza_ore" min="1" max="168"
             value="<?= (int) $valori['media_zip_scadenza_ore'] ?>">
      <small class="archivio-muted">Alla scadenza le parti ZIP vengono eliminate dal disco.</small>
    </div>

    <div class="form-actions">
      <button type="submit" class="btn btn-gold">Salva impostazioni</button>
    </div>
  </form>
</section>

<section class="admin-form">
  <h2 class="archivio-sezione-titolo">Manutenzione manuale</h2>
  <p class="archivio-muted archivio-testo-piccolo">
    Le stesse pulizie possono essere eseguite dal cron con <code>php photo/tools/maintenance.php</code>.
    Vengono rimossi i caricamenti interrotti da più di 48 ore, i file nel cestino oltre il periodo di conservazione,
    gli ZIP scaduti e i file in quarantena più vecchi di 30 giorni.
  </p>
  <form method="post" onsubmit="return confirm('Eseguire ora le pulizie? Le operazioni sono irreversibili per i file scaduti.');">
    <input type="hidden" name="csrf_token" value="<?= media_h(csrf_token()) ?>">
    <input type="hidden" name="azione" value="pulizie">
    <div class="form-actions">
      <button type="submit" class="btn btn-outline">Esegui pulizie ora</button>
    </div>
  </form>
</section>

<?php require_once __DIR__ . '/_footer.php'; ?>
