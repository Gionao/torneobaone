<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/iscrizione_helper.php';

require_admin();

$pdo = db();
$squadre = $pdo->query('SELECT id, nome FROM squadre ORDER BY nome')->fetchAll();
$metodi = $pdo->query('SELECT id, nome FROM metodi_pagamento WHERE attivo = 1 ORDER BY ordine, nome')->fetchAll();
$errors = [];
$importo = trim((string) ($_POST['importo'] ?? ''));
$dataPagamento = trim((string) ($_POST['data_pagamento'] ?? date('Y-m-d')));
$note = trim((string) ($_POST['note'] ?? ''));
$idSquadra = (int) ($_POST['id_squadra'] ?? $_GET['squadra'] ?? 0);
$idMetodo = (int) ($_POST['id_metodo_pagamento'] ?? 0);
$stato = (string) ($_POST['stato'] ?? 'confermata');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify()) {
        $errors[] = 'La sessione del modulo è scaduta. Ricarica la pagina e riprova.';
    }

    $squadraIds = array_map(static fn(array $squadra): int => (int) $squadra['id'], $squadre);
    $metodoIds = array_map(static fn(array $metodo): int => (int) $metodo['id'], $metodi);
    if (!in_array($idSquadra, $squadraIds, true)) {
        $errors[] = 'Seleziona una squadra valida.';
    }
    if (!in_array($idMetodo, $metodoIds, true)) {
        $errors[] = 'Seleziona un metodo di pagamento attivo.';
    }
    if (!is_numeric($importo) || (float) $importo <= 0 || (float) $importo > 999999.99) {
        $errors[] = 'Inserisci un importo valido maggiore di zero.';
    }
    $dataParts = explode('-', $dataPagamento);
    if (count($dataParts) !== 3 || !checkdate((int) ($dataParts[1] ?? 0), (int) ($dataParts[2] ?? 0), (int) ($dataParts[0] ?? 0))) {
        $errors[] = 'Inserisci una data valida.';
    }
    if (!in_array($stato, ['confermata', 'in_attesa', 'annullata'], true)) {
        $errors[] = 'Seleziona uno stato valido.';
    }
    if (strlen($note) > 60000) {
        $errors[] = 'Le note sono troppo lunghe.';
    }

    if (!$errors) {
        try {
            $pdo->beginTransaction();
            $insert = $pdo->prepare('INSERT INTO transazioni (id_squadra, importo, id_metodo_pagamento, data_pagamento, stato, note, id_organizzatore) VALUES (?, ?, ?, ?, ?, ?, ?)');
            $insert->execute([
                $idSquadra,
                number_format((float) $importo, 2, '.', ''),
                $idMetodo,
                $dataPagamento,
                $stato,
                $note !== '' ? $note : null,
                (int) ($_SESSION['admin_id'] ?? 0),
            ]);
            ricalcola_stato_pagamento($pdo, $idSquadra);
            $pdo->commit();
            header('Location: ' . ADMIN_PATH . '/pagamenti/transazioni.php?inserito=1');
            exit;
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log('[PAGAMENTI] Failed to register transaction: ' . $e->getMessage());
            $errors[] = 'Non è stato possibile registrare il pagamento. Riprova.';
        }
    }
}

$pageTitle = 'Inserisci pagamento';
require_once __DIR__ . '/../_header.php';
?>

<div class="admin-page-header">
  <h1>Inserisci pagamento</h1>
  <p>Registra un pagamento ricevuto o un versamento ancora in attesa.</p>
</div>

<?php foreach ($errors as $error): ?>
  <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
<?php endforeach; ?>

<?php if (!$squadre): ?>
  <div class="alert alert-info">Non ci sono squadre da selezionare.</div>
<?php elseif (!$metodi): ?>
  <div class="alert alert-info">Non ci sono metodi di pagamento attivi. Configurane uno prima di registrare un pagamento.</div>
<?php else: ?>
  <form method="post" class="admin-form" style="max-width:720px;">
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">
    <div class="form-group">
      <label for="id_squadra">Squadra</label>
      <select id="id_squadra" name="id_squadra" required>
        <option value="">Seleziona una squadra</option>
        <?php foreach ($squadre as $squadra): ?>
          <option value="<?= (int) $squadra['id'] ?>" <?= $idSquadra === (int) $squadra['id'] ? 'selected' : '' ?>><?= htmlspecialchars($squadra['nome']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="form-row">
      <div class="form-group">
        <label for="importo">Importo (€)</label>
        <input id="importo" type="number" name="importo" min="0.01" max="999999.99" step="0.01" value="<?= htmlspecialchars($importo) ?>" required>
      </div>
      <div class="form-group">
        <label for="data_pagamento">Data pagamento</label>
        <input id="data_pagamento" type="date" name="data_pagamento" value="<?= htmlspecialchars($dataPagamento) ?>" required>
      </div>
    </div>
    <div class="form-row">
      <div class="form-group">
        <label for="id_metodo_pagamento">Metodo</label>
        <select id="id_metodo_pagamento" name="id_metodo_pagamento" required>
          <option value="">Seleziona un metodo</option>
          <?php foreach ($metodi as $metodo): ?>
            <option value="<?= (int) $metodo['id'] ?>" <?= $idMetodo === (int) $metodo['id'] ? 'selected' : '' ?>><?= htmlspecialchars($metodo['nome']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-group">
        <label for="stato">Stato</label>
        <select id="stato" name="stato" required>
          <option value="confermata" <?= $stato === 'confermata' ? 'selected' : '' ?>>Confermato</option>
          <option value="in_attesa" <?= $stato === 'in_attesa' ? 'selected' : '' ?>>In attesa</option>
          <option value="annullata" <?= $stato === 'annullata' ? 'selected' : '' ?>>Annullato</option>
        </select>
      </div>
    </div>
    <div class="form-group">
      <label for="note">Note</label>
      <textarea id="note" name="note" rows="4" maxlength="60000"><?= htmlspecialchars($note) ?></textarea>
    </div>
    <div class="inline-actions">
      <button type="submit" class="btn btn-gold">Registra pagamento</button>
      <a class="btn btn-outline" href="<?= ADMIN_PATH ?>/pagamenti/transazioni.php">Annulla</a>
    </div>
  </form>
<?php endif; ?>

<?php require_once __DIR__ . '/../_footer.php'; ?>
