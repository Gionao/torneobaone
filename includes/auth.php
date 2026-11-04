<?php
// Backward-compatible function names backed by the centralized staff service.
declare(strict_types=1);

require_once __DIR__ . '/staff_authorization.php';

function session_start_secure(): void
{
    staff_session_start();
}

function is_admin_logged(): bool
{
    return staff_current_user() !== null;
}

function require_admin(): void
{
    $principal = staff_current_user();
    if ($principal === null) {
        header('Location: ' . ADMIN_PATH . '/login.php');
        exit;
    }
    staff_require_authenticated();
    require_sector_access('torneo', ['amministratore']);
}

/** Tournament administration workflows available to the delegated segreteria role. */
function require_tournament_operations(): void
{
    $principal = staff_current_user();
    if ($principal === null) {
        header('Location: ' . ADMIN_PATH . '/login.php');
        exit;
    }
    staff_require_authenticated();
    require_sector_access('torneo', ['amministratore', 'segreteria']);
}

/** Backward-compatible login API; credentials always use the shared staff table. */
function admin_login(string $username, string $password): bool
{
    return staff_login($username, $password);
}

function require_partite_access(): void
{
    $principal = staff_current_user();
    if ($principal === null) {
        header('Location: ' . TELECRONISTA_URL . '/login.php');
        exit;
    }
    staff_require_authenticated();
    require_sector_access('partite', ['gestore', 'telecronista']);
    if (!$principal['is_super_admin'] && staff_role('partite') === 'telecronista'
        && str_contains((string) ($_SERVER['SCRIPT_NAME'] ?? ''), '/admin/partite/')) {
        $relative = basename((string) $_SERVER['SCRIPT_NAME']);
        $query = !empty($_SERVER['QUERY_STRING']) ? '?' . $_SERVER['QUERY_STRING'] : '';
        header('Location: ' . TELECRONISTA_URL . '/partite/' . rawurlencode($relative) . $query);
        exit;
    }
}

function require_partite_manager(): void
{
    $principal = staff_current_user();
    if ($principal === null) {
        header('Location: ' . TELECRONISTA_URL . '/login.php');
        exit;
    }
    staff_require_authenticated();
    require_sector_access('partite', ['gestore']);
}

function is_telecronista(): bool
{
    $principal = staff_current_user();
    return $principal !== null && !$principal['must_change_password']
        && !$principal['is_super_admin'] && staff_role('partite') === 'telecronista';
}

function admin_logout(): void
{
    staff_logout();
}

function admin_change_password(int $admin_id, string $new_password): bool
{
    if ($admin_id !== staff_id() || !csrf_verify() || strlen($new_password) < 12 || strlen($new_password) > 72
        || preg_match('/[a-z]/', $new_password) !== 1
        || preg_match('/[A-Z]/', $new_password) !== 1
        || preg_match('/[0-9]/', $new_password) !== 1) {
        return false;
    }
    try {
        $pdo = db();
        if (!table_has_column($pdo, 'admin', 'must_change_password')) {
            return false;
        }
        $stmt = $pdo->prepare('UPDATE admin SET password_hash = ?, must_change_password = 0 WHERE id = ? AND attivo = 1');
        $stmt->execute([password_hash($new_password, PASSWORD_DEFAULT), $admin_id]);
        staff_auth_forget_cache();
        return $stmt->rowCount() === 1;
    } catch (Throwable $e) {
        return false;
    }
}

function csrf_token(): string
{
    return staff_csrf_token();
}

function csrf_verify(): bool
{
    return staff_csrf_verify();
}
