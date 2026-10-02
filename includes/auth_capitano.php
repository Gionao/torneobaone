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

function is_capitano_team_active(?int $idCapitano = null): bool
{
    $pdo = db();
    $capitanoId = $idCapitano ?? (int) ($_SESSION['capitano_id'] ?? 0);
    if ($capitanoId <= 0) {
        return false;
    }

    if (!table_has_column($pdo, 'squadre', 'attiva')) {
        return true;
    }

    $stmt = $pdo->prepare('SELECT attiva FROM squadre WHERE id_capitano = ? LIMIT 1');
    $stmt->execute([$capitanoId]);
    $row = $stmt->fetch();

    return !empty($row) && (int) ($row['attiva'] ?? 0) === 1;
}

function is_capitano_email_verified(?int $idCapitano = null): bool
{
    $pdo = db();
    $capitanoId = $idCapitano ?? (int) ($_SESSION['capitano_id'] ?? 0);
    if ($capitanoId <= 0) {
        return false;
    }

    $stmt = $pdo->prepare('SELECT email_verificata_at FROM capitani WHERE id = ? LIMIT 1');
    $stmt->execute([$capitanoId]);
    $row = $stmt->fetch();

    return !empty($row['email_verificata_at']);
}

function require_capitano(): void
{
    if (!is_capitano_logged()) {
        header('Location: ' . SITE_URL . '/capitano/login.php');
        exit;
    }

    if (!is_capitano_email_verified()) {
        $_SESSION['capitano_pending_verification'] = true;
        header('Location: ' . SITE_URL . '/capitano/richiedi-verifica.php');
        exit;
    }

    if (!is_capitano_team_active()) {
        $_SESSION['capitano_pending_verification'] = true;
        header('Location: ' . SITE_URL . '/capitano/richiedi-verifica.php');
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
    unset($_SESSION['capitano_login_error']);

    $pdo = db();
    $stmt = $pdo->prepare('SELECT id, password_hash, email_verificata_at FROM capitani WHERE email = ? LIMIT 1');
    $stmt->execute([$email]);
    $row = $stmt->fetch();

    if ($row && password_verify($password, $row['password_hash'])) {
        if (empty($row['email_verificata_at'])) {
            $_SESSION['capitano_pending_verification'] = true;
            $_SESSION['capitano_pending_email'] = $email;
            $_SESSION['capitano_login_error'] = 'email_non_verificata';
            return false;
        }

        if (table_has_column($pdo, 'squadre', 'attiva') && !is_capitano_team_active((int) $row['id'])) {
            $_SESSION['capitano_login_error'] = 'squadra_non_attiva';
            return false;
        }

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
