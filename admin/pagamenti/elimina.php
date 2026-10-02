<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/iscrizione_helper.php';
require_admin();

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
    $pdo->prepare('DELETE FROM transazioni WHERE id = ?')->execute([$id]);
    ricalcola_stato_pagamento($pdo, (int) $transazione['id_squadra']);
    $pdo->commit();
    $_SESSION['flash'] = ['type' => 'success', 'text' => 'Pagamento eliminato e totale squadra aggiornato.'];
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log('[PAGAMENTI] Failed to delete transaction: ' . $e->getMessage());
    $_SESSION['flash'] = ['type' => 'error', 'text' => 'Non è stato possibile eliminare il pagamento.'];
}
header('Location: transazioni.php');
exit;
