<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/iscrizione_helper.php';
require_admin();

$pdo = db();
$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT) ?: filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT) ?: 0;
$stmt = $pdo->prepare('SELECT * FROM transazioni WHERE id = ? LIMIT 1');
$stmt->execute([$id]);
$transazione = $stmt->fetch();
if (!$transazione) { header('Location: transazioni.php'); exit; }
$squadre = $pdo->query('SELECT id, nome FROM squadre ORDER BY nome')->fetchAll();
$metodi = $pdo->query('SELECT id, nome, attivo FROM metodi_pagamento ORDER BY ordine, nome')->fetchAll();
$errors = [];
$importo = trim((string) ($_POST['importo'] ?? $transazione['importo']));
$dataPagamento = trim((string) ($_POST['data_pagamento'] ?? $transazione['data_pagamento']));
$note = trim((string) ($_POST['note'] ?? $transazione['note'] ?? ''));
$idSquadra = (int) ($_POST['id_squadra'] ?? $transazione['id_squadra']);
$idMetodo = (int) ($_POST['id_metodo_pagamento'] ?? $transazione['id_metodo_pagamento']);
$stato = (string) ($_POST['stato'] ?? $transazione['stato']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify()) $errors[] = 'La sessione del modulo è scaduta. Ricarica la pagina e riprova.';
    $squadraIds = array_map(static fn(array $row): int => (int) $row['id'], $squadre);
    $metodoIds = array_map(static fn(array $row): int => (int) $row['id'], $metodi);
    if (!in_array($idSquadra, $squadraIds, true)) $errors[] = 'Seleziona una squadra valida.';
    if (!in_array($idMetodo, $metodoIds, true)) $errors[] = 'Seleziona un metodo di pagamento valido.';
    if (!is_numeric($importo) || (float) $importo <= 0 || (float) $importo > 999999.99) $errors[] = 'Inserisci un importo valido maggiore di zero.';
    $dateParts = explode('-', $dataPagamento);
    if (count($dateParts) !== 3 || !checkdate((int) ($dateParts[1] ?? 0), (int) ($dateParts[2] ?? 0), (int) ($dateParts[0] ?? 0))) $errors[] = 'Inserisci una data valida.';
    if (!in_array($stato, ['confermata', 'in_attesa', 'annullata'], true)) $errors[] = 'Seleziona uno stato valido.';
    if (strlen($note) > 60000) $errors[] = 'Le note sono troppo lunghe.';

    if (!$errors) {
        try {
            $pdo->beginTransaction();
            $update = $pdo->prepare('UPDATE transazioni SET id_squadra = ?, importo = ?, id_metodo_pagamento = ?, data_pagamento = ?, stato = ?, note = ? WHERE id = ?');
            $update->execute([$idSquadra, number_format((float) $importo, 2, '.', ''), $idMetodo, $dataPagamento, $stato, $note !== '' ? $note : null, $id]);
            ricalcola_stato_pagamento($pdo, (int) $transazione['id_squadra']);
            if ($idSquadra !== (int) $transazione['id_squadra']) ricalcola_stato_pagamento($pdo, $idSquadra);
            $pdo->commit();
            $_SESSION['flash'] = ['type' => 'success', 'text' => 'Pagamento aggiornato.'];
            header('Location: transazioni.php');
            exit;
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            error_log('[PAGAMENTI] Failed to update transaction: ' . $e->getMessage());
            $errors[] = 'Non è stato possibile aggiornare il pagamento. Riprova.';
        }
    }
}

$pageTitle = 'Modifica pagamento';
require_once __DIR__ . '/../_header.php';
?>
<div class="admin-page-header"><h1>Modifica pagamento</h1><p>Aggiorna importo, squadra, metodo, data o stato della transazione.</p></div>
<?php foreach ($errors as $error): ?><div class="alert alert-error"><?= htmlspecialchars($error) ?></div><?php endforeach; ?>
<form method="post" class="admin-form" style="max-width:720px">
<input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>"><input type="hidden" name="id" value="<?= (int) $id ?>">
<div class="form-group"><label for="id_squadra">Squadra</label><select id="id_squadra" name="id_squadra" required><?php foreach ($squadre as $row): ?><option value="<?= (int) $row['id'] ?>" <?= $idSquadra === (int) $row['id'] ? 'selected' : '' ?>><?= htmlspecialchars($row['nome']) ?></option><?php endforeach; ?></select></div>
<div class="form-row"><div class="form-group"><label for="importo">Importo (€)</label><input id="importo" type="number" name="importo" min="0.01" max="999999.99" step="0.01" value="<?= htmlspecialchars($importo) ?>" required></div><div class="form-group"><label for="data_pagamento">Data pagamento</label><input id="data_pagamento" type="date" name="data_pagamento" value="<?= htmlspecialchars($dataPagamento) ?>" required></div></div>
<div class="form-row"><div class="form-group"><label for="id_metodo_pagamento">Metodo</label><select id="id_metodo_pagamento" name="id_metodo_pagamento" required><?php foreach ($metodi as $row): ?><option value="<?= (int) $row['id'] ?>" <?= $idMetodo === (int) $row['id'] ? 'selected' : '' ?>><?= htmlspecialchars($row['nome'] . (!(int) $row['attivo'] ? ' (disattivato)' : '')) ?></option><?php endforeach; ?></select></div><div class="form-group"><label for="stato">Stato</label><select id="stato" name="stato" required><option value="confermata" <?= $stato === 'confermata' ? 'selected' : '' ?>>Confermato</option><option value="in_attesa" <?= $stato === 'in_attesa' ? 'selected' : '' ?>>In attesa</option><option value="annullata" <?= $stato === 'annullata' ? 'selected' : '' ?>>Annullato</option></select></div></div>
<div class="form-group"><label for="note">Note</label><textarea id="note" name="note" rows="4" maxlength="60000"><?= htmlspecialchars($note) ?></textarea></div>
<div class="inline-actions"><button type="submit" class="btn btn-gold">Salva modifiche</button><a class="btn btn-outline" href="transazioni.php">Annulla</a></div>
</form>
<?php require_once __DIR__ . '/../_footer.php'; ?>
