<?php
// includes/config.php
// ============================================================
//  Configurazione centrale del sito e della posta
//  Nessun segreto deve essere scritto direttamente nel repository.
//  Le impostazioni specifiche dell'ambiente arrivano da variabili d'ambiente;
//  i valori predefiniti consentono l'avvio in locale.
// ============================================================

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
    define('DB_USER', getenv('DB_USER') ?: 'root');
}
if (!defined('DB_PASS')) {
    define('DB_PASS', getenv('DB_PASS') ?: '');
}
if (!defined('DB_CHARSET')) {
    define('DB_CHARSET', getenv('DB_CHARSET') ?: 'utf8mb4');
}

if (!defined('SITE_NAME')) {
    define('SITE_NAME', getenv('SITE_NAME') ?: (ENV === 'production'
        ? 'Torneo Calcio a 5 - Baone 2027'
        : 'Torneo Calcio a 5 - Baone 2027 [LOCAL]'));
}
if (!defined('SITE_URL')) {
    define('SITE_URL', getenv('SITE_URL') ?: (ENV === 'production'
        ? 'https://torneobaone.it'
        : 'http://torneobaone.test'));
}
if (!defined('ADMIN_PATH')) {
    define('ADMIN_PATH', getenv('ADMIN_PATH') ?: (getenv('ADMIN_URL') ?: (ENV === 'production'
        ? 'https://admin.torneobaone.it/admin'
        : SITE_URL . '/admin')));
}
if (!defined('POS_URL')) {
    define('POS_URL', getenv('POS_URL') ?: (ENV === 'production'
        ? 'https://pos.torneobaone.it/pos'
        : SITE_URL . '/pos'));
}
if (!defined('TELECRONISTA_URL')) {
    define('TELECRONISTA_URL', getenv('TELECRONISTA_URL') ?: (ENV === 'production'
        ? 'https://admin.torneobaone.it/telecronista'
        : SITE_URL . '/telecronista'));
}
if (!defined('ARCHIVIO_URL')) {
    define('ARCHIVIO_URL', getenv('ARCHIVIO_URL') ?: SITE_URL . '/photo/admin');
}
if (!defined('MAX_SQUADRE')) {
    $configuredMaxSquadre = getenv('MAX_SQUADRE');
    define(
        'MAX_SQUADRE',
        $configuredMaxSquadre !== false && ctype_digit($configuredMaxSquadre) && (int) $configuredMaxSquadre > 0
            ? (int) $configuredMaxSquadre
            : 20
    );
}
if (!defined('DEBUG_MODE')) {
    $configuredDebugMode = getenv('DEBUG_MODE');
    $parsedDebugMode = $configuredDebugMode === false
        ? null
        : filter_var($configuredDebugMode, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
    define('DEBUG_MODE', $parsedDebugMode ?? (ENV !== 'production'));
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
    define('MAIL_FROM_ADDRESS', getenv('MAIL_FROM_ADDRESS') ?: 'noreply@torneobaone.test');
}
if (!defined('MAIL_FROM_NAME')) {
    define('MAIL_FROM_NAME', getenv('MAIL_FROM_NAME') ?: SITE_NAME);
}
if (!defined('MAIL_REPLY_TO')) {
    define('MAIL_REPLY_TO', getenv('MAIL_REPLY_TO') ?: MAIL_FROM_ADDRESS);
}

date_default_timezone_set('Europe/Rome');

if (DEBUG_MODE) {
    ini_set('display_errors', '1');
    ini_set('display_startup_errors', '1');
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', '0');
    error_reporting(0);
}