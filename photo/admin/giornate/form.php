<?php
// photo/admin/giornate/form.php — Creazione e modifica di una giornata
declare(strict_types=1);

require_once dirname(__DIR__) . '/_bootstrap.php';
require_media_editor();

$id = media_get_int('id');
$giornata = $id > 0 ? media_giornata($id) : null;
if ($id > 0 && !$giornata) {
    media_flash('error', 'Giornata non trovata.');
    media_redirect_interno('/edizioni/index.php');
}

$idEdizione = $giornata ? (int) $giornata['id_edizione'] : media_get_int('edizione');
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

$dati = [
    'nome' => (string) ($giornata['nome'] ?? ''),
    'data_evento' => (string) ($giornata['data_evento'] ?? ''),
    'ordine' => (int) ($giornata['ordine'] ?? 0),
    'note' => (string) ($giornata['note'] ?? ''),
];
$errore = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify()) {
        $errore = 'Sessione scaduta. Ricarica la pagina e riprova.';
    } else {
        $dati = [
            'id_edizione' => $idEdizione,
            'nome' => trim((string) ($_POST['nome'] ?? '')),
            'data_evento' => trim((string) ($_POST['data_evento'] ?? '')),
            'ordine' => media_post_int('ordine'),
            'note' => trim((string) ($_POST['note'] ?? '')),
        ];

        if ($dati['nome'] === '' || mb_strlen($dati['nome']) > 120) {
            $errore = 'Il nome della giornata è obbligatorio (massimo 120 caratteri).';
        } elseif ($dati['ordine'] < 0 || $dati['ordine'] > 32767) {
            $errore = 'L\'ordine deve essere un numero tra 0 e 32767.';
        } elseif (mb_strlen($dati['note']) > 2000) {
            $errore = 'Le note sono troppo lunghe (massimo 2000 caratteri).';
        }

        if ($errore === '' && $dati['data_evento'] !== '') {
            $d = DateTime::createFromFormat('Y-m-d', $dati['data_evento']);
            if (!$d || $d->format('Y-m-d') !== $dati['data_evento']) {
                $errore = 'La data indicata non è valida.';
            }
        }

        if ($errore === '') {
            try {
                if ($giornata) {
                    media_giornata_aggiorna($id, $dati);
                    media_log('giornata_modificata', 'media_giornate', $id, ['nome' => $dati['nome']]);
                    media_flash('success', 'Giornata aggiornata.');
                } else {
                    $nuovoId = media_giornata_crea($dati);
                    media_log('giornata_creata', 'media_giornate', $nuovoId, [
                        'nome' => $dati['nome'],
                        'edizione' => $idEdizione,
                    ]);
                    media_flash('success', 'Giornata creata.');
                }
                media_redirect_interno('/giornate/index.php?edizione=' . $idEdizione);
            } catch (Throwable $e) {
                $errore = DEBUG_MODE ? $e->getMessage() : 'Impossibile salvare la giornata.';
            }
        }
    }
}

$pageTitle = $giornata ? 'Modifica giornata' : 'Nuova giornata';
$paginaAttiva = 'edizioni';
require_once dirname(__DIR__) . '/_header.php';
?>

<nav class="archivio-briciole" aria-label="Percorso">
  <a href="<?= media_h(ARCHIVIO_URL) ?>/edizioni/index.php">Edizioni</a>
  <span class="sep">/</span>
  <a href="<?= media_h(media_url('giornate/index.php', ['edizione' => $idEdizione])) ?>"><?= media_h($edizione['nome']) ?></a>
  <span class="sep">/</span>
  <strong><?= $giornata ? 'Modifica giornata' : 'Nuova giornata' ?></strong>
</nav>

<div class="admin-page-header">
  <h1><?= $giornata ? 'Modifica giornata' : 'Nuova giornata' ?></h1>
  <p><?= media_h($edizione['nome']) ?> — <?= (int) $edizione['anno'] ?></p>
</div>

<?php if ($errore): ?><div class="alert alert-error" role="alert"><?= media_h($errore) ?></div><?php endif; ?>

<section class="admin-form">
  <form method="post">
    <input type="hidden" name="csrf_token" value="<?= media_h(csrf_token()) ?>">
    <div class="form-group">
      <label for="nome">Nome giornata *</label>
      <input type="text" id="nome" name="nome" required maxlength="120" value="<?= media_h($dati['nome']) ?>" placeholder="es. Prima giornata">
    </div>
    <div class="form-group">
      <label for="data_evento">Data</label>
      <input type="date" id="data_evento" name="data_evento" value="<?= media_h($dati['data_evento']) ?>">
    </div>
    <div class="form-group">
      <label for="ordine">Ordine di visualizzazione</label>
      <input type="number" id="ordine" name="ordine" min="0" max="32767" value="<?= (int) $dati['ordine'] ?>">
      <small class="archivio-muted">Numeri più bassi compaiono prima.</small>
    </div>
    <div class="form-group">
      <label for="note">Note</label>
      <textarea id="note" name="note" rows="3" maxlength="2000"><?= media_h($dati['note']) ?></textarea>
    </div>
    <div class="form-actions">
      <button type="submit" class="btn btn-gold">Salva</button>
      <a class="btn btn-outline" href="<?= media_h(media_url('giornate/index.php', ['edizione' => $idEdizione])) ?>">Annulla</a>
    </div>
  </form>
</section>

<?php require_once dirname(__DIR__) . '/_footer.php'; ?>
