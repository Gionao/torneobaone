<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/db.php';
require_admin();

$id   = filter_input(INPUT_GET, 'id',   FILTER_VALIDATE_INT);
$csrf = $_GET['csrf'] ?? '';

if ($id && hash_equals($_SESSION['csrf_token'] ?? '', $csrf)) {
    db()->prepare('DELETE FROM squadre WHERE id = ?')->execute([$id]);
    $_SESSION['flash'] = ['type' => 'success', 'text' => 'Squadra eliminata.'];
} else {
    $_SESSION['flash'] = ['type' => 'error', 'text' => 'Operazione non autorizzata.'];
}
header('Location: index.php');
exit;