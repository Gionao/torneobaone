<?php
// pos/includes/role_check.php — Controllo permessi per ruolo POS
declare(strict_types=1);

/**
 * @param list<string> $allowed_roles admin | operatore | bar | cucina
 */
function require_role(array $allowed_roles): void
{
    $role = pos_current_role();

    if (!in_array($role, $allowed_roles, true)) {
        if (defined('POS_API') && POS_API) {
            http_response_code(403);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(
                ['success' => false, 'data' => null, 'error' => 'Accesso non autorizzato'],
                JSON_UNESCAPED_UNICODE
            );
            exit;
        }

        header('Location: ' . STAFF_URL . '/index.php');
        exit;
    }
}
