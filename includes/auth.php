<?php
// includes/auth.php
// ============================================================
//  Gestione sessione e autenticazione admin
// ============================================================

declare(strict_types=1);

require_once __DIR__ . '/db.php';

// Avvia sessione con impostazioni sicure
function session_start_secure(): void
{
    if (session_status() === PHP_SESSION_NONE) {
        session_set_cookie_params([
            'lifetime' => 0,           // Sessione finisce alla chiusura browser
            'path'     => '/',
            'domain'   => ENV === 'production' ? '.torneobaone.it' : '',
            'secure'   => (ENV === 'production'),  // HTTPS solo in produzione
            'httponly' => true,
            'samesite' => 'Strict',
        ]);
        session_start();
    }
}

// Verifica se l'admin è loggato
function is_admin_logged(): bool
{
    session_start_secure();
    return !empty($_SESSION['admin_id']) && !empty($_SESSION['admin_token']);
}

// Protezione pagine admin: reindirizza al login se non autenticato
function require_admin(): void
{
    if (!is_admin_logged()) {
        header('Location: ' . ADMIN_PATH . '/login.php');
        exit;
    }
    $account = admin_current_account();
    if ($account === null) {
        header('Location: ' . ADMIN_PATH . '/login.php');
        exit;
    }

    if ($account['role'] === 'telecronista') {
        header('Location: ' . TELECRONISTA_URL . '/index.php');
        exit;
    }
    if (in_array($account['role'], ['uploader', 'editor'], true)) {
        header('Location: ' . ARCHIVIO_URL . '/index.php');
        exit;
    }
    if (in_array($account['role'], ['operatore', 'bar', 'cucina'], true)) {
        header('Location: ' . POS_URL . '/login.php');
        exit;
    }
    if ($account['role'] !== 'admin') {
        http_response_code(403);
        exit('Accesso non autorizzato.');
    }

    // Rigenera il token di sessione ad ogni richiesta (prevenzione session fixation)
    if (empty($_SESSION['last_regen']) || time() - $_SESSION['last_regen'] > 300) {
        session_regenerate_id(true);
        $_SESSION['last_regen'] = time();
    }
}

/**
 * Verifica le credenziali di un account interno, condivisa da tutti i moduli.
 *
 * @return array{id:int, username:string, role:string, station_name:?string}|null
 */
function admin_authenticate_credentials(string $username, string $password): ?array
{
    session_start_secure();

    // The limit is shared by login forms using the common session.
    if (!empty($_SESSION['login_attempts']) && $_SESSION['login_attempts'] >= 5) {
        $elapsed = time() - ($_SESSION['login_time'] ?? 0);
        if ($elapsed < 900) {
            return null;
        }
        $_SESSION['login_attempts'] = 0;
    }

    $pdo  = db();
    $stmt = $pdo->prepare('SELECT id, username, password_hash, role, station_name FROM admin WHERE username = ? LIMIT 1');
    $stmt->execute([$username]);
    $row = $stmt->fetch();

    if ($row && password_verify($password, $row['password_hash'])) {
        $_SESSION['login_attempts'] = 0;
        unset($_SESSION['login_time']);
        return [
            'id' => (int) $row['id'],
            'username' => (string) $row['username'],
            'role' => (string) ($row['role'] ?? 'admin'),
            'station_name' => $row['station_name'] !== null ? (string) $row['station_name'] : null,
        ];
    }

    $_SESSION['login_attempts'] = ($_SESSION['login_attempts'] ?? 0) + 1;
    $_SESSION['login_time']     = time();
    return null;
}

/**
 * Rilegge ruolo e stato dell'account interno per non fidarsi di claim vecchi
 * salvati nella sessione.
 *
 * @return array{id:int, username:string, role:string, station_name:?string}|null
 */
function admin_current_account(): ?array
{
    $id = (int) ($_SESSION['admin_id'] ?? 0);
    if ($id <= 0 || empty($_SESSION['admin_token'])) {
        return null;
    }

    $stmt = db()->prepare('SELECT id, username, role, station_name FROM admin WHERE id = ? LIMIT 1');
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    if (!$row) {
        unset($_SESSION['admin_id'], $_SESSION['admin_username'], $_SESSION['admin_role'], $_SESSION['admin_token']);
        return null;
    }

    unset($_SESSION['pos_admin_id'], $_SESSION['pos_admin_user'], $_SESSION['pos_admin_role'], $_SESSION['pos_admin_station']);
    $_SESSION['admin_username'] = (string) $row['username'];
    $_SESSION['admin_role'] = (string) $row['role'];
    return [
        'id' => (int) $row['id'],
        'username' => (string) $row['username'],
        'role' => (string) $row['role'],
        'station_name' => $row['station_name'] !== null ? (string) $row['station_name'] : null,
    ];
}

// Tentativo di login — restituisce true/false.
function admin_login(string $username, string $password): bool
{
    $account = admin_authenticate_credentials($username, $password);
    if ($account === null) {
        return false;
    }

    session_regenerate_id(true);
    unset($_SESSION['pos_admin_id'], $_SESSION['pos_admin_user'], $_SESSION['pos_admin_role'], $_SESSION['pos_admin_station']);
    $_SESSION['admin_id']       = $account['id'];
    $_SESSION['admin_username'] = $account['username'];
    $_SESSION['admin_role']     = $account['role'];
    $_SESSION['admin_token']    = bin2hex(random_bytes(32));
    return true;
}

/** Accesso alle sole funzioni di gestione partite per admin e telecronisti. */
function require_partite_access(): void
{
    if (!is_admin_logged()) {
        header('Location: ' . TELECRONISTA_URL . '/login.php');
        exit;
    }
    $account = admin_current_account();
    if ($account === null) {
        header('Location: ' . TELECRONISTA_URL . '/login.php');
        exit;
    }
    if (in_array($account['role'], ['uploader', 'editor'], true)) {
        header('Location: ' . ARCHIVIO_URL . '/index.php');
        exit;
    }
    if (in_array($account['role'], ['operatore', 'bar', 'cucina'], true)) {
        header('Location: ' . POS_URL . '/login.php');
        exit;
    }
    if (!in_array($account['role'], ['admin', 'telecronista'], true)) {
        http_response_code(403);
        exit('Accesso non autorizzato.');
    }
    if (empty($_SESSION['last_regen']) || time() - $_SESSION['last_regen'] > 300) {
        session_regenerate_id(true);
        $_SESSION['last_regen'] = time();
    }
    if (($_SESSION['admin_role'] ?? '') === 'telecronista'
        && strpos((string) ($_SERVER['SCRIPT_NAME'] ?? ''), '/admin/partite/') !== false) {
        $relative = basename((string) $_SERVER['SCRIPT_NAME']);
        $query = !empty($_SERVER['QUERY_STRING']) ? '?' . $_SERVER['QUERY_STRING'] : '';
        header('Location: ' . TELECRONISTA_URL . '/partite/' . $relative . $query);
        exit;
    }
}

function is_telecronista(): bool
{
    session_start_secure();
    if (!is_admin_logged()) {
        return false;
    }
    $account = admin_current_account();
    return $account !== null && $account['role'] === 'telecronista';
}

// Logout
function admin_logout(): void
{
    session_start_secure();
    $_SESSION = [];
    session_destroy();
}

// Cambio password admin
function admin_change_password(int $admin_id, string $new_password): bool
{
    if (strlen($new_password) < 8) {
        return false;
    }
    $hash = password_hash($new_password, PASSWORD_BCRYPT, ['cost' => 12]);
    $stmt = db()->prepare('UPDATE admin SET password_hash = ? WHERE id = ?');
    return $stmt->execute([$hash, $admin_id]);
}

// CSRF token per form admin
function csrf_token(): string
{
    session_start_secure();
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_verify(): bool
{
    return !empty($_POST['csrf_token'])
        && hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token']);
}
