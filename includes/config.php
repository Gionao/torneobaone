<?php
// includes/config.php
// ============================================================
//  CONFIGURAZIONE DATABASE
//  Profilo attivo: LOCAL (XAMPP) o PRODUCTION (Altervista)
//  Cambia ENV per switchare tra i due ambienti
// ============================================================

define('ENV', 'local');   // <-- 'local' per XAMPP | 'production' per Altervista

if (ENV === 'local') {

    // ── XAMPP ──────────────────────────────────────────────
    define('DB_HOST',    'localhost');
    define('DB_NAME',    'torneobaone');   // Crea questo DB in phpMyAdmin locale
    define('DB_USER',    'admin');           // Utente XAMPP di default
    define('DB_PASS',    'admin');               // Password XAMPP di default (vuota)
    define('DB_CHARSET', 'utf8mb4');

    define('SITE_NAME',  'Torneo Calcio a 5 - Baone 2026 [LOCAL]');
    define('SITE_URL',   'http://localhost/dashboard/sito_torneo');   // cartella in htdocs
    define('DEBUG_MODE', true);

} else {

    // ── Altervista (produzione) ────────────────────────────
    define('DB_HOST',    'localhost');
    define('DB_NAME',    'my_nomeutente');           // = nome account Altervista
    define('DB_USER',    'my_nomeutente');
    define('DB_PASS',    'LA_TUA_PASSWORD_DB');
    define('DB_CHARSET', 'utf8mb4');

    define('SITE_NAME',  'Torneo Calcio a 5 - Baone 2026');
    define('SITE_URL',   'https://TUONOMEUTENTE.altervista.org');
    define('DEBUG_MODE', false);

}

define('ADMIN_PATH', SITE_URL . '/admin');

// --- Timezone ---
date_default_timezone_set('Europe/Rome');

// --- Mostra errori PHP se siamo in debug ---
if (DEBUG_MODE) {
    ini_set('display_errors', '1');
    ini_set('display_startup_errors', '1');
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', '0');
    error_reporting(0);
}