<?php
// photo/admin/edizioni/form.php — Creazione e modifica edizione
declare(strict_types=1);

require_once dirname(__DIR__) . '/_bootstrap.php';
require_media_editor();

$id = media_get_int('id');
$edizione = $id > 0 ? media_edizione($id) : null;
if ($id > 0 && !$edizione) {
    media_flash('error', 'Edizione non trovata.');
    media_redirect_interno('/edizioni/index.php');
}

$dati = [
    'nome' => (string) ($edizione['nome'] ?? ''),
    'anno' => (int) ($edizione['anno'] ?? date('Y')),
    'descrizione' => (string) ($edizione['descrizione'] ?? ''),
    'data_inizio' => (string) ($edizione['data_inizio'] ?? ''),
    'data_fine' => (string) ($edizione['data_fine'] ?? ''),
    'attiva' => (int) ($edizione['attiva'] ?? 1),
];
$errore = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify()) {
        $errore = 'Sessione scaduta. Ricarica la pagina e riprova.';
    } else {
        $dati = [
            'nome' => trim((string) ($_POST['nome'] ?? '')),
            'anno' => media_post_int('anno'),
            'descrizione' => trim((string) ($_POST['descrizione'] ?? '')),
            'data_inizio' => trim((string) ($_POST['data_inizio'] ?? '')),
            'data_fine' => trim((string) ($_POST['data_fine'] ?? '')),
            'attiva' => isset($_POST['attiva']) ? 1 : 0,
        ];

        if ($dati['nome'] === '' || mb_strlen($dati['nome']) > 120) {
            $errore = 'Il nome dell\'edizione è obbligatorio (massimo 120 caratteri).';
        } elseif ($dati['anno'] < 2000 || $dati['anno'] > 2100) {
            $errore = 'Indica un anno valido tra 2000 e 2100.';
        } elseif (mb_strlen($dati['descrizione']) > 2000) {
            $errore = 'La descrizione è troppo lunga (massimo 2000 caratteri).';
        }

        $dateValide = true;
        foreach (['data_inizio', 'data_fine'] as $campo) {
            if ($dati[$campo] !== '') {
                $d = DateTime::createFromFormat('Y-m-d', $dati[$campo]);
                if (!$d || $d->format('Y-m-d') !== $dati[$campo]) {
                    $dateValide = false;
                }
            }
        }
        if ($errore === '' && !$dateValide) {
            $errore = 'Le date indicate non sono valide.';
        }
        if ($errore === '' && $dati['data_inizio'] !== '' && $dati['data_fine'] !== '' && $dati['data_inizio'] > $dati['data_fine']) {
            $errore = 'La data di inizio non può essere successiva alla data di fine.';
        }

        if ($errore === '') {
            try {
                if ($edizione) {
                    media_edizione_aggiorna($id, $dati);
                    media_log('edizione_modificata', 'media_edizioni', $id, ['nome' => $dati['nome'], 'anno' => $dati['anno']]);
                    media_flash('success', 'Edizione aggiornata.');
                } else {
                    $nuovoId = media_edizione_crea($dati, media_admin_id());
                    media_log('edizione_creata', 'media_edizioni', $nuovoId, ['nome' => $dati['nome'], 'anno' => $dati['anno']]);
                    media_flash('success', 'Edizione creata.');
                }
                media_redirect_interno('/edizioni/index.php');
            } catch (Throwable $e) {
                $errore = DEBUG_MODE ? $e->getMessage() : 'Impossibile salvare l\'edizione.';
            }
        }
    }
}

$pageTitle = $edizione ? 'Modifica edizione' : 'Nuova edizione';
$paginaAttiva = 'edizioni';
require_once dirname(__DIR__) . '/_header.php';
?>

<div class="admin-page-header">
  <h1><?= $edizione ? 'Modifica edizione' : 'Nuova edizione' ?></h1>
  <p><?= $edizione ? media_h($edizione['nome']) : 'Crea un contenitore per giornate, album e file' ?></p>
</div>

<?php if ($errore): ?><div class="alert alert-error" role="alert"><?= media_h($errore) ?></div><?php endif; ?>

<section class="admin-form">
  <form method="post">
    <input type="hidden" name="csrf_token" value="<?= media_h(csrf_token()) ?>">
    <div class="form-group">
      <label for="nome">Nome edizione *</label>
      <input type="text" id="nome" name="nome" required maxlength="120" value="<?= media_h($dati['nome']) ?>" placeholder="es. Torneo di Baone">
    </div>
    <div class="form-group">
      <label for="anno">Anno *</label>
      <input type="number" id="anno" name="anno" required min="2000" max="2100" value="<?= (int) $dati['anno'] ?>">
    </div>
    <div class="form-group">
      <label for="descrizione">Descrizione</label>
      <textarea id="descrizione" name="descrizione" rows="3" maxlength="2000"><?= media_h($dati['descrizione']) ?></textarea>
    </div>
    <div class="form-group">
      <label for="data_inizio">Data di inizio</label>
      <input type="date" id="data_inizio" name="data_inizio" value="<?= media_h($dati['data_inizio']) ?>">
    </div>
    <div class="form-group">
      <label for="data_fine">Data di fine</label>
      <input type="date" id="data_fine" name="data_fine" value="<?= media_h($dati['data_fine']) ?>">
    </div>
    <div class="form-group">
      <label style="display:flex;align-items:center;gap:.6rem;text-transform:none;letter-spacing:0;">
        <input type="checkbox" name="attiva" value="1" <?= (int) $dati['attiva'] === 1 ? 'checked' : '' ?> style="width:auto;">
        Edizione attiva (le edizioni archiviate restano comunque consultabili)
      </label>
    </div>
    <div class="form-actions">
      <button type="submit" class="btn btn-gold">Salva</button>
      <a class="btn btn-outline" href="<?= media_h(ARCHIVIO_URL) ?>/edizioni/index.php">Annulla</a>
    </div>
  </form>
</section>

<?php require_once dirname(__DIR__) . '/_footer.php'; ?>
