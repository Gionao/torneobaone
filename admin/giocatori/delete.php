<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/db.php';
require_admin();

$id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && $id && csrf_verify()) {
    db()->prepare('DELETE FROM giocatori WHERE id = ?')->execute([$id]);
    $_SESSION['flash'] = ['type' => 'success', 'text' => 'Giocatore eliminato.'];
} else {
    $_SESSION['flash'] = ['type' => 'error', 'text' => 'Operazione non autorizzata.'];
}
header('Location: index.php');
exit;
