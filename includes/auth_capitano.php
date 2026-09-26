<?php
// includes/auth_capitano.php
// Sessione e autenticazione capitano

declare(strict_types=1);

require_once __DIR__ . '/db.php';

function capitano_session_start_secure(): void
{
    if (session_status() === PHP_SESSION_NONE) {
        session_set_cookie_params([
            'lifetime' => 0,
            'path' => '/',
            'secure' => (ENV === 'production'),
            'httponly' => true,
            'samesite' => 'Strict',
        ]);
        session_start();
    }
}

function is_capitano_logged(): bool
{
    capitano_session_start_secure();
    return !empty($_SESSION['capitano_id']) && !empty($_SESSION['capitano_auth']);
}

function require_capitano(): void
{
    if (!is_capitano_logged()) {
        header('Location: ' . SITE_URL . '/capitano/login.php');
        exit;
    }

    if (empty($_SESSION['capitano_last_regen']) || time() - $_SESSION['capitano_last_regen'] > 300) {
        session_regenerate_id(true);
        $_SESSION['capitano_last_regen'] = time();
    }
}

function capitano_login(string $email, string $password): bool
{
    capitano_session_start_secure();

    $pdo = db();
    $stmt = $pdo->prepare('SELECT id, password_hash FROM capitani WHERE email = ? LIMIT 1');
    $stmt->execute([$email]);
    $row = $stmt->fetch();

    if ($row && password_verify($password, $row['password_hash'])) {
        session_regenerate_id(true);
        $_SESSION['capitano_id'] = (int) $row['id'];
        $_SESSION['capitano_auth'] = bin2hex(random_bytes(32));
        return true;
    }

    return false;
}

function capitano_logout(): void
{
    capitano_session_start_secure();
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(
            session_name(),
            '',
            time() - 42000,
            $params['path'],
            $params['domain'],
            $params['secure'],
            $params['httponly']
        );
    }
    session_destroy();
}

function capitano_csrf_token(): string
{
    capitano_session_start_secure();
    if (empty($_SESSION['capitano_csrf_token'])) {
        $_SESSION['capitano_csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['capitano_csrf_token'];
}

function capitano_csrf_verify(): bool
{
    return !empty($_POST['csrf_token'])
        && hash_equals($_SESSION['capitano_csrf_token'] ?? '', $_POST['csrf_token']);
}
