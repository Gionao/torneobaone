<?php
// admin/partite/delete.php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/classifica_helper.php';
require_partite_manager();

$id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && $id && csrf_verify()) {
    db()->prepare('DELETE FROM partite WHERE id = ?')->execute([$id]);
    ricalcola_classifica(db());
    $_SESSION['flash'] = ['type' => 'success', 'text' => 'Partita eliminata.'];
} else {
    $_SESSION['flash'] = ['type' => 'error', 'text' => 'Operazione non autorizzata.'];
}
header('Location: index.php');
exit;
