<?php
// includes/media_auth.php
// ============================================================
//  Autenticazione e autorizzazione dell'archivio.
//  Riutilizza la sessione e la tabella `admin` esistenti:
//  non esiste un secondo sistema di autenticazione.
// ============================================================

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/includes/auth.php';

if (!defined('MEDIA_ROOT_URL')) {
    define('MEDIA_ROOT_URL', SITE_URL . '/photo');
}
if (!defined('MEDIA_API_URL')) {
    define('MEDIA_API_URL', MEDIA_ROOT_URL . '/api');
}
if (!defined('MEDIA_UPLOAD_URL')) {
    define('MEDIA_UPLOAD_URL', MEDIA_ROOT_URL . '/upload');
}

/** Escape HTML coerente in tutta l'interfaccia. */
function media_h(mixed $valore): string
{
    return htmlspecialchars((string) $valore, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** Ruoli che possono accedere all'archivio. */
function media_ruoli(): array
{
    return ['uploader', 'editor', 'admin'];
}

/** Ruoli che possono essere assegnati dalla gestione utenti dell'archivio. */
function media_ruoli_assegnabili(): array
{
    return ['uploader', 'editor', 'admin'];
}

/**
 * Account corrente letto dal database a ogni richiesta.
 *
 * La sessione condivisa non viene considerata sufficiente: se l'account è
 * stato eliminato o il ruolo è cambiato, la sessione non deve conservare i
 * vecchi permessi. In caso di errore si chiude l'accesso (fail closed).
 *
 * @return array{id:int, username:string, role:string}|null
 */
function media_utente_corrente(): ?array
{
    static $caricato = false;
    static $utente = null;

    session_start_secure();
    if ($caricato) {
        return $utente;
    }
    $caricato = true;

    $id = (int) ($_SESSION['admin_id'] ?? 0);
    if ($id <= 0 || empty($_SESSION['admin_token'])) {
        return null;
    }

    try {
        $stmt = db()->prepare('SELECT id, username, role FROM admin WHERE id = ? LIMIT 1');
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        return null;
    }

    if (!$row) {
        unset($_SESSION['admin_id'], $_SESSION['admin_username'], $_SESSION['admin_role'], $_SESSION['admin_token']);
        return null;
    }

    $utente = [
        'id'       => (int) $row['id'],
        'username' => (string) $row['username'],
        'role'     => (string) $row['role'],
    ];
    return $utente;
}

function media_ruolo(): string
{
    $utente = media_utente_corrente();
    return $utente !== null ? $utente['role'] : '';
}

function media_is_logged(): bool
{
    $utente = media_utente_corrente();
    return $utente !== null && in_array($utente['role'], media_ruoli(), true);
}

function media_is_uploader(): bool
{
    return media_is_logged();
}

function media_is_editor(): bool
{
    return media_is_logged() && in_array(media_ruolo(), ['editor', 'admin'], true);
}

function media_is_admin(): bool
{
    return media_is_logged() && media_ruolo() === 'admin';
}

function media_admin_id(): int
{
    $utente = media_utente_corrente();
    return $utente !== null ? $utente['id'] : 0;
}

function media_admin_username(): string
{
    $utente = media_utente_corrente();
    return $utente !== null ? $utente['username'] : '';
}

function media_regenera_sessione(): void
{
    if (empty($_SESSION['last_regen']) || time() - (int) $_SESSION['last_regen'] > 300) {
        session_regenerate_id(true);
        $_SESSION['last_regen'] = time();
    }
}

/** Protezione pagine HTML: reindirizza al login archivio. */
function require_media_login(): void
{
    if (!media_is_logged()) {
        $destinazione = (string) ($_SERVER['REQUEST_URI'] ?? '');
        $login = ARCHIVIO_URL . '/login.php';
        if ($destinazione !== '' && !str_contains($destinazione, "\n") && !str_contains($destinazione, "\r")) {
            $login .= '?ritorna=' . rawurlencode($destinazione);
        }
        header('Location: ' . $login);
        exit;
    }
    media_regenera_sessione();
}

/** Protezione pagine HTML: solo editor e admin. */
function require_media_editor(): void
{
    require_media_login();
    if (!media_is_editor()) {
        http_response_code(403);
        require __DIR__ . '/../admin/_403.php';
        exit;
    }
}

/** Protezione pagine HTML: solo admin. */
function require_media_admin(): void
{
    require_media_login();
    if (!media_is_admin()) {
        http_response_code(403);
        require __DIR__ . '/../admin/_403.php';
        exit;
    }
}

/** Protezione endpoint JSON: 401 invece di redirect. */
function require_media_api_login(): void
{
    if (!media_is_logged()) {
        media_json(['ok' => false, 'errore' => 'Sessione non valida. Effettua di nuovo l\'accesso.'], 401);
    }
    media_regenera_sessione();
}

function media_json(array $payload, int $status = 200): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('X-Content-Type-Options: nosniff');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function media_richiedi_post(): void
{
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
        media_json(['ok' => false, 'errore' => 'Metodo non consentito.'], 405);
    }
}

function media_csrf_valido(): bool
{
    session_start_secure();
    $token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? ($_POST['csrf_token'] ?? '');
    return is_string($token)
        && $token !== ''
        && !empty($_SESSION['csrf_token'])
        && hash_equals((string) $_SESSION['csrf_token'], $token);
}

function media_richiedi_csrf(): void
{
    if (!media_csrf_valido()) {
        media_json(['ok' => false, 'errore' => 'Token di sicurezza non valido. Ricarica la pagina.'], 403);
    }
}

function media_ip(): ?string
{
    $ip = $_SERVER['REMOTE_ADDR'] ?? null;
    return is_string($ip) && $ip !== '' ? substr($ip, 0, 45) : null;
}

function media_user_agent(): ?string
{
    $ua = $_SERVER['HTTP_USER_AGENT'] ?? null;
    return is_string($ua) && $ua !== '' ? substr($ua, 0, 255) : null;
}

function media_flash(string $tipo, string $testo): void
{
    session_start_secure();
    $_SESSION['media_flash'] = ['type' => $tipo, 'text' => $testo];
}

function media_flash_leggi(): ?array
{
    session_start_secure();
    $flash = $_SESSION['media_flash'] ?? null;
    unset($_SESSION['media_flash']);
    return is_array($flash) ? $flash : null;
}

/**
 * Chiusura anticipata della sessione per non bloccare le altre richieste
 * durante upload e download lunghi.
 */
function media_chiudi_sessione(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_write_close();
    }
}

// ------------------------------------------------------------
//  Accesso per edizione (solo uploader)
// ------------------------------------------------------------

/**
 * Restituisce gli ID delle edizioni consentite all'uploader.
 * Editor e admin non sono limitati: restituisce null.
 *
 * @return list<int>|null
 */
function media_edizioni_consentite(?int $adminId = null): ?array
{
    if (media_is_editor()) {
        return null;
    }
    if (!media_is_logged()) {
        return [];
    }

    $adminId = $adminId ?? media_admin_id();
    static $cache = [];
    if (isset($cache[$adminId])) {
        return $cache[$adminId];
    }

    try {
        $stmt = db()->prepare('SELECT id_edizione FROM media_accesso_edizione WHERE id_admin = ? ORDER BY id_edizione');
        $stmt->execute([$adminId]);
        $ids = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
    } catch (Throwable $e) {
        $ids = [];
    }

    return $cache[$adminId] = $ids;
}

function media_puo_vedere_edizione(int $idEdizione): bool
{
    $consentite = media_edizioni_consentite();
    return $consentite === null || in_array($idEdizione, $consentite, true);
}

function media_puo_vedere_album(array $album): bool
{
    return media_puo_vedere_edizione((int) ($album['id_edizione'] ?? 0));
}

function media_puo_vedere_file(array $file): bool
{
    return media_puo_vedere_edizione((int) ($file['id_edizione'] ?? 0));
}

function media_puo_caricare_album(array $album): bool
{
    if (!media_is_logged() || !in_array(media_ruolo(), ['uploader', 'admin'], true)) {
        return false;
    }
    return media_puo_vedere_album($album);
}

/**
 * Un uploader può scaricare solo i propri originali; editor e admin tutti.
 */
function media_puo_scaricare(array $file, ?int $adminId = null, ?string $ruolo = null): bool
{
    $adminId = $adminId ?? media_admin_id();
    $ruolo   = $ruolo ?? media_ruolo();

    if (!in_array($ruolo, media_ruoli(), true)) {
        return false;
    }
    if (in_array($ruolo, ['editor', 'admin'], true)) {
        return true;
    }
    return (int) ($file['id_uploader'] ?? 0) === $adminId;
}

/** Un uploader può eliminare i propri file; l'editor no; l'admin tutto. */
function media_puo_eliminare(array $file, ?int $adminId = null, ?string $ruolo = null): bool
{
    $adminId = $adminId ?? media_admin_id();
    $ruolo   = $ruolo ?? media_ruolo();

    if ($ruolo === 'admin') {
        return true;
    }
    if ($ruolo === 'uploader') {
        return (int) ($file['id_uploader'] ?? 0) === $adminId;
    }
    return false;
}

/** Può gestire edizioni, giornate e album. */
function media_puo_gestire_struttura(): bool
{
    return media_is_editor();
}

/** Può creare archivi ZIP. */
function media_puo_generare_zip(): bool
{
    return media_is_editor();
}

/** Verifica che il ruolo indicato appartenga all'archivio. */
function media_ruolo_valido(string $ruolo): bool
{
    return in_array($ruolo, media_ruoli_assegnabili(), true);
}
