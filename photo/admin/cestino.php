<?php
// photo/admin/cestino.php — Cestino dell'archivio (solo amministratori)
declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';
require_once dirname(__DIR__) . '/includes/media_maintenance.php';
require_media_admin();

$pagina = max(1, media_get_int('pagina', 1));
$perPagina = 100;
$offset = ($pagina - 1) * $perPagina;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify()) {
        media_flash('error', 'Sessione scaduta. Ricarica la pagina e riprova.');
        media_redirect_interno('/cestino.php');
    }

    $azione = (string) ($_POST['azione'] ?? '');
    $id = media_post_int('id');
    $file = $id > 0 ? media_file($id) : null;

    try {
        if ($azione === 'ripristina') {
            if (!$file || empty($file['eliminato_il'])) {
                throw new RuntimeException('File non presente nel cestino.');
            }
            media_file_ripristina($id);
            media_log('file_ripristinato', 'media_file', $id, ['uuid' => $file['uuid']]);
            media_flash('success', 'File ripristinato nella galleria.');
        } elseif ($azione === 'elimina') {
            if (!$file || empty($file['eliminato_il'])) {
                throw new RuntimeException('File non presente nel cestino.');
            }
            media_elimina_definitivamente($id);
            media_flash('success', 'File eliminato definitivamente.');
        } elseif ($azione === 'svuota') {
            $totale = 0;
            for ($giro = 0; $giro < 40; $giro++) {
                $gruppo = media_file_eliminati(500, 0);
                if ($gruppo === []) {
                    break;
                }
                foreach ($gruppo as $record) {
                    if (media_elimina_definitivamente((int) $record['id'])) {
                        $totale++;
                    }
                }
            }
            media_flash('success', 'Cestino svuotato: ' . $totale . ' file eliminati definitivamente.');
        } else {
            throw new RuntimeException('Azione non riconosciuta.');
        }
    } catch (Throwable $e) {
        media_flash('error', $e->getMessage());
    }

    media_redirect_interno('/cestino.php');
}

$file = media_file_eliminati($perPagina + 1, $offset);
$haSuccessivi = count($file) > $perPagina;
if ($haSuccessivi) {
    $file = array_slice($file, 0, $perPagina);
}

$pageTitle = 'Cestino';
$paginaAttiva = 'cestino';
require_once __DIR__ . '/_header.php';
?>

<div class="archivio-pagina-testata">
  <div class="admin-page-header">
    <h1>Cestino</h1>
    <p>I file eliminati restano recuperabili per <?= media_retention_days() ?> giorni, poi vengono rimossi automaticamente.</p>
  </div>
  <?php if ($file !== []): ?>
    <div class="archivio-pagina-azioni">
      <form method="post" onsubmit="return confirm('Eliminare definitivamente tutti i file nel cestino? L\'operazione non può essere annullata.');">
        <input type="hidden" name="csrf_token" value="<?= media_h(csrf_token()) ?>">
        <input type="hidden" name="azione" value="svuota">
        <button type="submit" class="btn btn-sm btn-red">Svuota cestino</button>
      </form>
    </div>
  <?php endif; ?>
</div>

<?php if ($file === []): ?>
  <div class="archivio-empty">
    <strong>Cestino vuoto</strong>
    I file spostati nel cestino compariranno qui.
  </div>
<?php else: ?>
  <div class="admin-table-wrap">
    <table class="admin-table archivio-tabella">
      <thead>
        <tr>
          <th>File</th>
          <th>Raccolta</th>
          <th>Caricato da</th>
          <th>Dimensione</th>
          <th>Eliminato il</th>
          <th>Azioni</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($file as $f): ?>
          <tr>
            <td>
              <strong><?= media_h($f['nome_originale']) ?></strong><br>
              <span class="archivio-muted archivio-testo-piccolo"><?= media_h(strtoupper((string) $f['estensione'])) ?> · <?= media_h($f['uuid']) ?></span>
            </td>
            <td class="archivio-muted archivio-testo-piccolo">
              <?= media_h($f['nome_edizione']) ?><br>
              <?= media_h($f['nome_giornata']) ?> · <?= media_h($f['nome_album']) ?>
            </td>
            <td><?= media_h($f['username_uploader'] ?: 'Utente rimosso') ?></td>
            <td><?= media_h(media_bytes_leggibili((int) $f['dimensione'])) ?></td>
            <td><?= media_h(date('d/m/Y H:i', strtotime((string) $f['eliminato_il']))) ?></td>
            <td class="actions">
              <form method="post" style="display:inline;"
                    onsubmit="return confirm('Ripristinare questo file nella galleria?');">
                <input type="hidden" name="csrf_token" value="<?= media_h(csrf_token()) ?>">
                <input type="hidden" name="azione" value="ripristina">
                <input type="hidden" name="id" value="<?= (int) $f['id'] ?>">
                <button type="submit" class="btn btn-sm btn-gold">Ripristina</button>
              </form>
              <form method="post" style="display:inline;"
                    onsubmit="return confirm('Eliminare definitivamente questo file? L\'operazione non può essere annullata.');">
                <input type="hidden" name="csrf_token" value="<?= media_h(csrf_token()) ?>">
                <input type="hidden" name="azione" value="elimina">
                <input type="hidden" name="id" value="<?= (int) $f['id'] ?>">
                <button type="submit" class="btn btn-sm btn-red">Elimina</button>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>

  <?php if ($pagina > 1 || $haSuccessivi): ?>
    <nav class="archivio-paginazione" aria-label="Paginazione">
      <?php if ($pagina > 1): ?>
        <a href="<?= media_h(media_url('cestino.php', ['pagina' => $pagina - 1])) ?>">Precedente</a>
      <?php endif; ?>
      <span class="corrente" aria-current="page"><?= $pagina ?></span>
      <?php if ($haSuccessivi): ?>
        <a href="<?= media_h(media_url('cestino.php', ['pagina' => $pagina + 1])) ?>">Successiva</a>
      <?php endif; ?>
    </nav>
  <?php endif; ?>
<?php endif; ?>

<?php require_once __DIR__ . '/_footer.php'; ?>
