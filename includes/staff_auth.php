<?php
// Central staff authentication. Authorization is always reloaded from MySQL.
declare(strict_types=1);

require_once __DIR__ . '/db.php';

const STAFF_SESSION_NAME = 'TBSTAFFSESSID';
const STAFF_IDLE_TIMEOUT = 1800;
const STAFF_ABSOLUTE_TIMEOUT = 28800;

function staff_session_start(): void
{
    if (session_status() !== PHP_SESSION_NONE) {
        return;
    }

    session_name(STAFF_SESSION_NAME);
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    ini_set('session.gc_maxlifetime', (string) STAFF_ABSOLUTE_TIMEOUT);

    $host = strtolower((string) ($_SERVER['HTTP_HOST'] ?? ''));
    $domain = ENV === 'production' && preg_match('/(?:^|\.)torneobaone\.it(?::\d+)?$/', $host)
        ? '.torneobaone.it'
        : '';
    $https = ENV === 'production'
        || (!empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off')
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');

    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'domain' => $domain,
        'secure' => $https,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

function staff_auth_forget_cache(): void
{
    unset($GLOBALS['_staff_auth_principal']);
}

function staff_clear_session_state(): void
{
    foreach (array_keys($_SESSION ?? []) as $key) {
        if (str_starts_with((string) $key, 'staff_')) {
            unset($_SESSION[$key]);
        }
    }
    staff_auth_forget_cache();
}

/** Resolve current account and all assignments from the database, never session roles. */
function staff_current_user(bool $refresh = false): ?array
{
    staff_session_start();
    if (!$refresh && array_key_exists('_staff_auth_principal', $GLOBALS)) {
        return $GLOBALS['_staff_auth_principal'];
    }

    $id = (int) ($_SESSION['staff_id'] ?? 0);
    $nonce = $_SESSION['staff_nonce'] ?? null;
    $now = time();
    $authenticatedAt = (int) ($_SESSION['staff_authenticated_at'] ?? 0);
    $lastActivity = (int) ($_SESSION['staff_last_activity'] ?? 0);
    if ($id < 1 || !is_string($nonce) || strlen($nonce) < 32
        || $authenticatedAt < 1 || $now - $authenticatedAt > STAFF_ABSOLUTE_TIMEOUT
        || $lastActivity < 1 || $now - $lastActivity > STAFF_IDLE_TIMEOUT) {
        staff_clear_session_state();
        return $GLOBALS['_staff_auth_principal'] = null;
    }

    try {
        $pdo = db();
        $hasPasswordFlag = table_has_column($pdo, 'admin', 'must_change_password');
        if (!$hasPasswordFlag) {
            throw new RuntimeException('Schema autenticazione staff non aggiornato.');
        }
        $stmt = $pdo->prepare('SELECT id, username, password_hash, attivo, is_super_admin, must_change_password FROM admin WHERE id = ? LIMIT 1');
        $stmt->execute([$id]);
        $account = $stmt->fetch();
        if (!$account || (int) $account['attivo'] !== 1) {
            staff_clear_session_state();
            return $GLOBALS['_staff_auth_principal'] = null;
        }

        $assignments = $pdo->prepare('SELECT a.area, a.ruolo, a.station_name FROM staff_assegnazioni a INNER JOIN staff_ruoli_area r ON r.area = a.area AND r.ruolo = a.ruolo WHERE a.id_admin = ? AND a.attivo=1 ORDER BY a.area');
        $assignments->execute([$id]);
        $areas = [];
        foreach ($assignments->fetchAll() as $assignment) {
            $areas[(string) $assignment['area']] = [
                'role' => (string) $assignment['ruolo'],
                'station_name' => $assignment['station_name'] !== null ? (string) $assignment['station_name'] : null,
            ];
        }

        $principal = [
            'id' => (int) $account['id'],
            'username' => (string) $account['username'],
            'is_super_admin' => (int) $account['is_super_admin'] === 1,
            'must_change_password' => (int) $account['must_change_password'] === 1,
            'assignments' => $areas,
        ];
        $_SESSION['staff_last_activity'] = $now;
        if (empty($_SESSION['staff_last_regen']) || $now - (int) $_SESSION['staff_last_regen'] >= 300) {
            session_regenerate_id(true);
            $_SESSION['staff_last_regen'] = $now;
        }
        return $GLOBALS['_staff_auth_principal'] = $principal;
    } catch (Throwable $e) {
        staff_clear_session_state();
        return $GLOBALS['_staff_auth_principal'] = null;
    }
}

function staff_login(string $username, string $password): bool
{
    staff_session_start();
    if (staff_login_origin_valid() !== true) {
        return false;
    }

    $attempts = (int) ($_SESSION['staff_login_attempts'] ?? 0);
    $started = (int) ($_SESSION['staff_login_attempt_started'] ?? 0);
    if ($attempts >= 5 && time() - $started < 900) {
        return false;
    }
    if ($started < 1 || time() - $started >= 900) {
        $attempts = 0;
        $started = time();
    }

    try {
        $pdo = db();
        $hasPasswordFlag = table_has_column($pdo, 'admin', 'must_change_password');
        if (!$hasPasswordFlag) {
            $_SESSION['staff_login_attempts'] = $attempts + 1;
            $_SESSION['staff_login_attempt_started'] = $started;
            return false;
        }
        $stmt = $pdo->prepare('SELECT id, username, password_hash, attivo, is_super_admin, must_change_password FROM admin WHERE username = ? LIMIT 1');
        $stmt->execute([$username]);
        $account = $stmt->fetch();
        $hash = (string) ($account['password_hash'] ?? '$2y$12$9ZWS2G3SKnNQe1BEIBL98uH5VdwK7fD6kPAeKYxpx6lG4gHjNhNOa');
        $valid = password_verify($password, $hash);
        if (!$account || !$valid || (int) $account['attivo'] !== 1) {
            $_SESSION['staff_login_attempts'] = $attempts + 1;
            $_SESSION['staff_login_attempt_started'] = $started;
            return false;
        }

        if (password_needs_rehash((string) $account['password_hash'], PASSWORD_DEFAULT)) {
            $rehash = $pdo->prepare('UPDATE admin SET password_hash = ? WHERE id = ? AND attivo = 1');
            $rehash->execute([password_hash($password, PASSWORD_DEFAULT), (int) $account['id']]);
        }

        staff_clear_session_state();
        session_regenerate_id(true);
        $now = time();
        $_SESSION['staff_id'] = (int) $account['id'];
        $_SESSION['staff_nonce'] = bin2hex(random_bytes(32));
        $_SESSION['staff_authenticated_at'] = $now;
        $_SESSION['staff_last_activity'] = $now;
        $_SESSION['staff_last_regen'] = $now;
        $_SESSION['staff_login_attempts'] = 0;
        $_SESSION['staff_csrf_token'] = bin2hex(random_bytes(32));
        staff_auth_forget_cache();
        return true;
    } catch (Throwable $e) {
        // Authentication failures are intentionally indistinguishable to callers.
        $_SESSION['staff_login_attempts'] = $attempts + 1;
        $_SESSION['staff_login_attempt_started'] = $started;
        return false;
    }
}

function staff_login_origin_valid(): bool
{
    $source = (string) ($_SERVER['HTTP_ORIGIN'] ?? '');
    if ($source === '') {
        $source = (string) ($_SERVER['HTTP_REFERER'] ?? '');
    }
    if ($source === '') {
        return PHP_SAPI === 'cli';
    }
    $sourceHost = strtolower((string) parse_url($source, PHP_URL_HOST));
    $requestHost = strtolower((string) preg_replace('/:\d+$/', '', (string) ($_SERVER['HTTP_HOST'] ?? '')));
    return $sourceHost !== '' && $requestHost !== '' && hash_equals($requestHost, $sourceHost);
}

function staff_logout(): void
{
    staff_session_start();
    staff_clear_session_state();
    // Keep unrelated namespaces (notably captain authentication) intact.
    session_regenerate_id(true);
    $_SESSION = array_filter(
        $_SESSION,
        static fn(string $key): bool => !str_starts_with($key, 'staff_'),
        ARRAY_FILTER_USE_KEY
    );
    if (session_name() === STAFF_SESSION_NAME && ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', [
            'expires' => time() - 42000,
            'path' => $params['path'],
            'domain' => $params['domain'],
            'secure' => $params['secure'],
            'httponly' => $params['httponly'],
            'samesite' => $params['samesite'] ?? 'Lax',
        ]);
    }
    staff_auth_forget_cache();
}

function staff_id(): int
{
    return (int) (staff_current_user()['id'] ?? 0);
}

function staff_username(): string
{
    return (string) (staff_current_user()['username'] ?? '');
}

function staff_role(string $area): string
{
    return (string) (staff_current_user()['assignments'][$area]['role'] ?? '');
}

function staff_station(string $area = 'pos'): ?string
{
    $station = staff_current_user()['assignments'][$area]['station_name'] ?? null;
    return is_string($station) && $station !== '' ? $station : null;
}

function staff_has_area(string $area): bool
{
    $principal = staff_current_user();
    return $principal !== null && ($principal['is_super_admin'] || isset($principal['assignments'][$area]));
}

function staff_is_super_admin(): bool
{
    return (bool) (staff_current_user()['is_super_admin'] ?? false);
}

function staff_password_change_required(): bool
{
    return (bool) (staff_current_user()['must_change_password'] ?? false);
}

function staff_require_authenticated(bool $allowPasswordChange = false): array
{
    $principal = staff_current_user();
    if ($principal === null) {
        header('Location: ' . (defined('STAFF_URL') ? STAFF_URL : SITE_URL . '/staff') . '/login.php');
        exit;
    }
    if (!$allowPasswordChange && $principal['must_change_password']) {
        header('Location: ' . (defined('STAFF_URL') ? STAFF_URL : SITE_URL . '/staff') . '/change-password.php');
        exit;
    }
    return $principal;
}

function staff_change_password(string $newPassword, string $confirmation): bool
{
    staff_session_start();
    if (!staff_csrf_verify() || $newPassword !== $confirmation
        || strlen($newPassword) < 12 || strlen($newPassword) > 72
        || preg_match('/[a-z]/', $newPassword) !== 1
        || preg_match('/[A-Z]/', $newPassword) !== 1
        || preg_match('/[0-9]/', $newPassword) !== 1) {
        return false;
    }
    $principal = staff_current_user(true);
    if ($principal === null) {
        return false;
    }
    try {
        $pdo = db();
        $stmt = $pdo->prepare('SELECT password_hash FROM admin WHERE id = ? AND attivo = 1');
        $stmt->execute([$principal['id']]);
        $oldHash = $stmt->fetchColumn();
        if (!is_string($oldHash) || password_verify($newPassword, $oldHash)) {
            return false;
        }
        if (!table_has_column($pdo, 'admin', 'must_change_password')) {
            return false;
        }
        $update = $pdo->prepare('UPDATE admin SET password_hash = ?, must_change_password = 0 WHERE id = ? AND attivo = 1');
        $update->execute([password_hash($newPassword, PASSWORD_DEFAULT), $principal['id']]);
        if ($update->rowCount() !== 1) {
            return false;
        }
        session_regenerate_id(true);
        $now = time();
        $_SESSION['staff_authenticated_at'] = $now;
        $_SESSION['staff_last_activity'] = $now;
        $_SESSION['staff_last_regen'] = $now;
        $_SESSION['staff_nonce'] = bin2hex(random_bytes(32));
        $_SESSION['staff_csrf_token'] = bin2hex(random_bytes(32));
        staff_auth_forget_cache();
        return true;
    } catch (Throwable $e) {
        return false;
    }
}

function staff_csrf_token(): string
{
    staff_session_start();
    if (empty($_SESSION['staff_csrf_token'])) {
        $_SESSION['staff_csrf_token'] = bin2hex(random_bytes(32));
    }
    return (string) $_SESSION['staff_csrf_token'];
}

function staff_csrf_verify(): bool
{
    staff_session_start();
    $submitted = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? ($_POST['csrf_token'] ?? '');
    return is_string($submitted) && isset($_SESSION['staff_csrf_token'])
        && hash_equals((string) $_SESSION['staff_csrf_token'], $submitted);
}

function staff_landing_url(string $area): string
{
    return match ($area) {
        'torneo' => ADMIN_PATH . '/index.php',
        'partite' => staff_role('partite') === 'telecronista' && !staff_is_super_admin()
            ? TELECRONISTA_URL . '/index.php'
            : ADMIN_PATH . '/partite/',
        'pos' => POS_URL . '/index.php',
        'archivio' => ARCHIVIO_URL . '/index.php',
        default => (defined('STAFF_URL') ? STAFF_URL : SITE_URL . '/staff') . '/index.php',
    };
}

function staff_continue_after_login(): never
{
    staff_require_authenticated(true);
    if (staff_password_change_required()) {
        header('Location: ' . (defined('STAFF_URL') ? STAFF_URL : SITE_URL . '/staff') . '/change-password.php');
        exit;
    }
    header('Location: ' . (defined('STAFF_URL') ? STAFF_URL : SITE_URL . '/staff') . '/index.php');
    exit;
}
