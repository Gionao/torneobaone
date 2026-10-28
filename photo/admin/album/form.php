<?php
// photo/admin/album/form.php — Creazione e modifica di un album
declare(strict_types=1);

require_once dirname(__DIR__) . '/_bootstrap.php';
require_media_editor();

$id = media_get_int('id');
$album = $id > 0 ? media_album($id) : null;
if ($id > 0 && !$album) {
    media_flash('error', 'Album non trovato.');
    media_redirect_interno('/edizioni/index.php');
}

$idGiornata = $album ? (int) $album['id_giornata'] : media_get_int('giornata');
$giornata = $idGiornata > 0 ? media_giornata($idGiornata) : null;
if (!$giornata) {
    media_flash('error', 'Giornata non trovata.');
    media_redirect_interno('/edizioni/index.php');
}
if (!media_puo_vedere_edizione((int) $giornata['id_edizione'])) {
    http_response_code(403);
    require dirname(__DIR__) . '/_403.php';
    exit;
}
$edizione = media_edizione((int) $giornata['id_edizione']);

$dati = [
    'nome' => (string) ($album['nome'] ?? ''),
    'descrizione' => (string) ($album['descrizione'] ?? ''),
    'ordine' => (int) ($album['ordine'] ?? 0),
];
$errore = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify()) {
        $errore = 'Sessione scaduta. Ricarica la pagina e riprova.';
    } else {
        $dati = [
            'id_giornata' => $idGiornata,
            'nome' => trim((string) ($_POST['nome'] ?? '')),
            'descrizione' => trim((string) ($_POST['descrizione'] ?? '')),
            'ordine' => media_post_int('ordine'),
        ];

        if ($dati['nome'] === '' || mb_strlen($dati['nome']) > 120) {
            $errore = 'Il nome dell\'album è obbligatorio (massimo 120 caratteri).';
        } elseif ($dati['ordine'] < 0 || $dati['ordine'] > 32767) {
            $errore = 'L\'ordine deve essere un numero tra 0 e 32767.';
        } elseif (mb_strlen($dati['descrizione']) > 2000) {
            $errore = 'La descrizione è troppo lunga (massimo 2000 caratteri).';
        }

        if ($errore === '') {
            try {
                if ($album) {
                    media_album_aggiorna($id, $dati);
                    media_log('album_modificato', 'media_album', $id, ['nome' => $dati['nome']]);
                    media_flash('success', 'Album aggiornato.');
                } else {
                    $nuovoId = media_album_crea($dati);
                    media_log('album_creato', 'media_album', $nuovoId, [
                        'nome' => $dati['nome'],
                        'giornata' => $idGiornata,
                    ]);
                    media_flash('success', 'Album creato.');
                }
                media_redirect_interno('/album/index.php?giornata=' . $idGiornata);
            } catch (Throwable $e) {
                $errore = DEBUG_MODE ? $e->getMessage() : 'Impossibile salvare l\'album.';
            }
        }
    }
}

$pageTitle = $album ? 'Modifica album' : 'Nuovo album';
$paginaAttiva = 'edizioni';
require_once dirname(__DIR__) . '/_header.php';
?>

<nav class="archivio-briciole" aria-label="Percorso">
  <a href="<?= media_h(ARCHIVIO_URL) ?>/edizioni/index.php">Edizioni</a>
  <span class="sep">/</span>
  <a href="<?= media_h(media_url('giornate/index.php', ['edizione' => (int) $giornata['id_edizione']])) ?>"><?= media_h($edizione['nome'] ?? '') ?></a>
  <span class="sep">/</span>
  <a href="<?= media_h(media_url('album/index.php', ['giornata' => $idGiornata])) ?>"><?= media_h($giornata['nome']) ?></a>
  <span class="sep">/</span>
  <strong><?= $album ? 'Modifica album' : 'Nuovo album' ?></strong>
</nav>

<div class="admin-page-header">
  <h1><?= $album ? 'Modifica album' : 'Nuovo album' ?></h1>
  <p><?= media_h($giornata['nome']) ?><?= $edizione ? ' · ' . media_h($edizione['nome']) : '' ?></p>
</div>

<?php if ($errore): ?><div class="alert alert-error" role="alert"><?= media_h($errore) ?></div><?php endif; ?>

<section class="admin-form">
  <form method="post">
    <input type="hidden" name="csrf_token" value="<?= media_h(csrf_token()) ?>">
    <div class="form-group">
      <label for="nome">Nome album *</label>
      <input type="text" id="nome" name="nome" required maxlength="120" value="<?= media_h($dati['nome']) ?>" placeholder="es. Premiazione">
    </div>
    <div class="form-group">
      <label for="descrizione">Descrizione</label>
      <textarea id="descrizione" name="descrizione" rows="3" maxlength="2000"><?= media_h($dati['descrizione']) ?></textarea>
    </div>
    <div class="form-group">
      <label for="ordine">Ordine di visualizzazione</label>
      <input type="number" id="ordine" name="ordine" min="0" max="32767" value="<?= (int) $dati['ordine'] ?>">
      <small class="archivio-muted">Numeri più bassi compaiono prima.</small>
    </div>
    <div class="form-actions">
      <button type="submit" class="btn btn-gold">Salva</button>
      <a class="btn btn-outline" href="<?= media_h(media_url('album/index.php', ['giornata' => $idGiornata])) ?>">Annulla</a>
    </div>
  </form>
</section>

<?php require_once dirname(__DIR__) . '/_footer.php'; ?>
