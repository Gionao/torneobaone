<?php
declare(strict_types=1);

define('POS_API', true);
require_once __DIR__ . '/../includes/db.php';
header('Cache-Control: no-store');

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    http_response_code(405);
    pos_json(false, null, 'Metodo non consentito.');
}
$token = $_POST['token'] ?? '';
if (!is_string($token) || !preg_match('/\A[0-9a-f]{32}\z/', $token)) {
    http_response_code(400);
    pos_json(false, null, 'Codice ordine non valido.');
}

try {
    $stmt = $pdo->prepare('SELECT daily_number, status FROM pos_orders WHERE tracking_token_hash = ?
        AND is_staff = 0 AND created_at >= NOW() - INTERVAL 24 HOUR');
    $stmt->execute([hash('sha256', $token)]);
    $order = $stmt->fetch();
    if (!$order) {
        http_response_code(404);
        pos_json(false, null, 'Codice ordine sconosciuto o scaduto.');
    }
    $labels = [
        'pending' => 'In attesa',
        'preparing' => 'In preparazione',
        'ready' => 'Pronto per la consegna',
        'delivered' => 'Consegnato',
        'cancelled' => 'Annullato',
    ];
    pos_json(true, [
        'daily_number' => (int) $order['daily_number'],
        'status' => $order['status'],
        'status_label' => $labels[$order['status']] ?? 'Stato non disponibile',
    ]);
} catch (Throwable $e) {
    http_response_code(500);
    pos_json(false, null, 'Errore tracciamento ordine.');
}
