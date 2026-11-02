<?php
// pos/includes/auth_check.php — Guard sessione POS
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/includes/auth.php';
session_start_secure();

if (!isset($_SESSION['pos_admin_id'])) {
    if (defined('POS_API') && POS_API) {
        http_response_code(401);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(
            ['success' => false, 'data' => null, 'error' => 'Non autenticato'],
            JSON_UNESCAPED_UNICODE
        );
        exit;
    }

    require_once dirname(__DIR__, 2) . '/includes/config.php';
    header('Location: ' . POS_URL . '/login.php');
    exit;
}

// Compatibilità sessioni create prima dell'introduzione dei ruoli
if (empty($_SESSION['pos_admin_role'])) {
    require_once __DIR__ . '/db.php';
    $stmt = $pdo->prepare('SELECT id, username, role, station_name FROM admin WHERE id = ? LIMIT 1');
    $stmt->execute([(int) $_SESSION['pos_admin_id']]);
    $row = $stmt->fetch();
    if ($row) {
        $_SESSION['pos_admin_user']    = $row['username'];
        $_SESSION['pos_admin_role']    = $row['role'] ?? 'operatore';
        $_SESSION['pos_admin_station'] = $row['station_name'];
    }
}
