<?php
// Accesso all'archivio privato: mantiene sessione, CSRF e autorizzazione esistenti.
declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';

session_start_secure();

if (media_is_logged()) {
    header('Location: ' . ARCHIVIO_URL . '/index.php');
    exit;
}

$errore = '';
$username = '';
$ritorna = (string) ($_GET['ritorna'] ?? $_POST['ritorna'] ?? '');
$destinazione = ARCHIVIO_URL . '/index.php';
if (preg_match('~\A/photo/admin/(?!/)(?!.*(?:^|/)\.\.(?:/|$))[A-Za-z0-9_./-]*(?:\?[A-Za-z0-9_=&%-]*)?\z~', $ritorna) === 1) {
    $destinazione = SITE_URL . $ritorna;
}

if (is_admin_logged() && !media_is_logged()) {
    $errore = 'Questo account non ha accesso all’archivio. Usa un account autorizzato.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify()) {
        $errore = 'Sessione scaduta. Ricarica la pagina e riprova.';
    } else {
        $username = trim((string) ($_POST['username'] ?? ''));
        $password = (string) ($_POST['password'] ?? '');

        if ($username === '' || $password === '') {
            $errore = 'Inserisci nome utente e password.';
        } elseif (!staff_login($username, $password)) {
            $errore = 'Credenziali non valide. Riprova.';
        } elseif (!media_is_logged()) {
            $errore = 'Questo account non ha accesso all’archivio.';
        } else {
            if (staff_password_change_required()) {
                header('Location: ' . STAFF_URL . '/change-password.php');
                exit;
            }
            if (count(staff_current_user()['assignments']) > 1 && !staff_is_super_admin()) {
                header('Location: ' . STAFF_URL . '/index.php');
                exit;
            }
            header('Location: ' . $destinazione);
            exit;
        }
    }
}

$authPage = [
    'title' => 'Archivio multimediale',
    'eyebrow' => 'Area riservata',
    'subtitle' => 'Foto e video privati del Torneo di Baone. Accesso consentito solo allo staff autorizzato.',
    'logo' => SITE_URL . '/assets/brand/01_loghi/png/logo_principale.png',
    'logo_alt' => 'Torneo Baone',
    'page_title' => 'Accedi all’archivio — ' . SITE_NAME,
    'error' => $errore,
    'noindex' => true,
    'action' => ARCHIVIO_URL . '/login.php',
    'novalidate' => true,
    'hidden' => [
        ['name' => 'csrf_token', 'value' => csrf_token()],
        ['name' => 'ritorna', 'value' => $ritorna],
    ],
    'fields' => [
        ['name' => 'username', 'label' => 'Nome utente', 'type' => 'text', 'required' => true, 'autofocus' => true, 'maxlength' => 60, 'autocomplete' => 'username', 'value' => $username],
        ['name' => 'password', 'label' => 'Password', 'type' => 'password', 'required' => true, 'autocomplete' => 'current-password'],
    ],
    'links' => [['label' => 'Torna al sito del torneo', 'href' => SITE_URL . '/']],
    'footer' => 'Archivio privato: nessun contenuto è pubblico. Non esiste registrazione autonoma.',
];
require dirname(__DIR__, 2) . '/shared/auth_page.php';
