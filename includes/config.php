<?php
// includes/config.php
// ============================================================
//  Configurazione centrale del sito e della posta
//  Nessun segreto deve essere scritto direttamente nel repository.
//  Il progetto carica prima config.local.php se presente, poi usa
//  variabili d'ambiente / valori di fallback sicuri.
// ============================================================

if (file_exists(__DIR__ . '/../config.local.php')) {
    require_once __DIR__ . '/../config.local.php';
}

if (!defined('ENV')) {
    define('ENV', getenv('APP_ENV') ?: 'local');
}

if (!defined('DB_HOST')) {
    define('DB_HOST', getenv('DB_HOST') ?: 'localhost');
}
if (!defined('DB_NAME')) {
    define('DB_NAME', getenv('DB_NAME') ?: 'my_torneobaone');
}
if (!defined('DB_USER')) {
    define('DB_USER', getenv('DB_USER') ?: 'admin');
}
if (!defined('DB_PASS')) {
    define('DB_PASS', getenv('DB_PASS') ?: 'admin');
}
if (!defined('DB_CHARSET')) {
    define('DB_CHARSET', getenv('DB_CHARSET') ?: 'utf8mb4');
}

if (!defined('SITE_NAME')) {
    define('SITE_NAME', ENV === 'production' ? 'Torneo Calcio a 5 - Baone 2026' : 'Torneo Calcio a 5 - Baone 2026 [LOCAL]');
}
if (!defined('SITE_URL')) {
    define('SITE_URL', getenv('SITE_URL') ?: 'http://192.168.1.57/sito_torneo');
}
if (!defined('DEBUG_MODE')) {
    define('DEBUG_MODE', ENV !== 'production');
}

if (!defined('SMTP_HOST')) {
    define('SMTP_HOST', getenv('SMTP_HOST') ?: '');
}
if (!defined('SMTP_PORT')) {
    define('SMTP_PORT', getenv('SMTP_PORT') ?: '587');
}
if (!defined('SMTP_USERNAME')) {
    define('SMTP_USERNAME', getenv('SMTP_USERNAME') ?: '');
}
if (!defined('SMTP_PASSWORD')) {
    define('SMTP_PASSWORD', getenv('SMTP_PASSWORD') ?: '');
}
if (!defined('SMTP_ENCRYPTION')) {
    define('SMTP_ENCRYPTION', getenv('SMTP_ENCRYPTION') ?: 'tls');
}
if (!defined('MAIL_FROM_ADDRESS')) {
    define('MAIL_FROM_ADDRESS', getenv('MAIL_FROM_ADDRESS') ?: 'noreply@localhost');
}
if (!defined('MAIL_FROM_NAME')) {
    define('MAIL_FROM_NAME', getenv('MAIL_FROM_NAME') ?: SITE_NAME);
}
if (!defined('MAIL_REPLY_TO')) {
    define('MAIL_REPLY_TO', getenv('MAIL_REPLY_TO') ?: MAIL_FROM_ADDRESS);
}

define('ADMIN_PATH', SITE_URL . '/admin');

date_default_timezone_set('Europe/Rome');

if (DEBUG_MODE) {
    ini_set('display_errors', '1');
    ini_set('display_startup_errors', '1');
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', '0');
    error_reporting(0);
}