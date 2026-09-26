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
    // Rigenera il token di sessione ad ogni richiesta (prevenzione session fixation)
    if (empty($_SESSION['last_regen']) || time() - $_SESSION['last_regen'] > 300) {
        session_regenerate_id(true);
        $_SESSION['last_regen'] = time();
    }
}

// Tentativo di login — restituisce true/false
function admin_login(string $username, string $password): bool
{
    session_start_secure();

    // Brute-force: max 5 tentativi in 15 minuti
    if (!empty($_SESSION['login_attempts']) && $_SESSION['login_attempts'] >= 5) {
        $elapsed = time() - ($_SESSION['login_time'] ?? 0);
        if ($elapsed < 900) {
            return false;  // Blocca per 15 minuti
        }
        // Reset dopo il timeout
        $_SESSION['login_attempts'] = 0;
    }

    $pdo  = db();
    $stmt = $pdo->prepare('SELECT id, password_hash FROM admin WHERE username = ? LIMIT 1');
    $stmt->execute([$username]);
    $row = $stmt->fetch();

    if ($row && password_verify($password, $row['password_hash'])) {
        // Login riuscito — inizializza sessione
        session_regenerate_id(true);
        $_SESSION['admin_id']    = $row['id'];
        $_SESSION['admin_token'] = bin2hex(random_bytes(32));
        $_SESSION['login_attempts'] = 0;
        return true;
    }

    // Login fallito — incrementa contatore
    $_SESSION['login_attempts'] = ($_SESSION['login_attempts'] ?? 0) + 1;
    $_SESSION['login_time']     = time();
    return false;
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