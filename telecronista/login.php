<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';

session_start_secure();

if (is_admin_logged()) {
    staff_continue_after_login();
}

$errore = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    if ($username === '' || $password === '') {
        $errore = 'Inserisci username e password.';
    } elseif (!admin_login($username, $password)) {
        $errore = 'Credenziali non valide. Riprova.';
    } else {
        staff_continue_after_login();
    }
}

$authPage = [
    'title' => 'Gestione partite',
    'subtitle' => 'Accesso riservato allo staff delle partite.',
    'logo' => SITE_URL . '/assets/brand/01_loghi/png/logo_principale.png',
    'logo_alt' => 'Torneo Baone',
    'page_title' => 'Accesso gestione partite — ' . SITE_NAME,
    'error' => $errore,
    'action' => TELECRONISTA_URL . '/login.php',
    'novalidate' => true,
    'fields' => [
        ['name' => 'username', 'label' => 'Nome utente', 'type' => 'text', 'required' => true, 'autofocus' => true, 'autocomplete' => 'username', 'value' => $_POST['username'] ?? ''],
        ['name' => 'password', 'label' => 'Password', 'type' => 'password', 'required' => true, 'autocomplete' => 'current-password'],
    ],
    'links' => [['label' => 'Torna al sito del torneo', 'href' => SITE_URL . '/']],
];
require __DIR__ . '/../shared/auth_page.php';
