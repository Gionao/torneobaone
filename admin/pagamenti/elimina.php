<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/iscrizione_helper.php';
require_once __DIR__ . '/../../includes/operation_audit.php';
require_tournament_operations();

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !csrf_verify()) {
    http_response_code(400);
    exit('Richiesta non valida.');
}
$id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT) ?: 0;
$pdo = db();
$stmt = $pdo->prepare('SELECT id, id_squadra FROM transazioni WHERE id = ? LIMIT 1');
$stmt->execute([$id]);
$transazione = $stmt->fetch();
if (!$transazione) {
    $_SESSION['flash'] = ['type' => 'error', 'text' => 'Pagamento non trovato.'];
    header('Location: transazioni.php');
    exit;
}
try {
    $pdo->beginTransaction();
    $lock = $pdo->prepare('SELECT id, id_squadra, importo, stato, id_metodo_pagamento, data_pagamento FROM transazioni WHERE id = ? FOR UPDATE');
    $lock->execute([$id]);
    $before = $lock->fetch();
    if (!$before) {
        throw new RuntimeException('Pagamento non trovato.');
    }
    $pdo->prepare('DELETE FROM transazioni WHERE id = ?')->execute([$id]);
    record_operation_audit($pdo, staff_id(), 'torneo', 'pagamento_eliminato', ['before' => $before]);
    ricalcola_stato_pagamento($pdo, (int) $before['id_squadra']);
    $pdo->commit();
    $_SESSION['flash'] = ['type' => 'success', 'text' => 'Pagamento eliminato e totale squadra aggiornato.'];
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log('[PAGAMENTI] Failed to delete transaction: ' . $e->getMessage());
    $_SESSION['flash'] = ['type' => 'error', 'text' => 'Non è stato possibile eliminare il pagamento.'];
}
header('Location: transazioni.php');
exit;
