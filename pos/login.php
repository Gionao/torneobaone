<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/config.php';
require_once dirname(__DIR__) . '/includes/auth.php';

session_start_secure();

if (is_admin_logged()) {
    staff_continue_after_login();
}

$errore = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    if ($username === '' || $password === '') {
        $errore = 'Credenziali non valide';
    } else {
        if (staff_login($username, $password)) {
            staff_continue_after_login();
        }
        $errore = 'Credenziali non valide';
    }
}

$authPage = [
    'title' => 'Area gestione POS',
    'subtitle' => 'Accesso riservato allo staff del punto vendita.',
    'logo' => SITE_URL . '/assets/brand/01_loghi/png/logo_principale.png',
    'logo_alt' => 'Torneo Baone',
    'page_title' => 'Area gestione POS — ' . SITE_NAME,
    'error' => $errore,
    'action' => POS_URL . '/login.php',
    'novalidate' => true,
    'show_password_toggle' => true,
    'navigation' => [
        'id' => 'pos-login-navigation',
        'brand_href' => SITE_URL . '/',
        'brand_name' => 'Torneo Baone',
        'brand_year' => '2026',
        'brand_label' => 'Torneo Baone 2026',
        'label' => 'Navigazione principale',
        'primary' => [
            ['label' => 'Home', 'href' => SITE_URL . '/'],
            ['label' => 'Calendario', 'href' => SITE_URL . '/calendario'],
            ['label' => 'Classifica', 'href' => SITE_URL . '/classifica'],
            ['label' => 'Squadre', 'href' => SITE_URL . '/squadre'],
        ],
        'actions' => [['label' => 'Area staff', 'href' => '#auth-form', 'primary' => true, 'current' => true]],
    ],
    'fields' => [
        ['name' => 'username', 'label' => 'Nome utente', 'type' => 'text', 'required' => true, 'autofocus' => true, 'autocomplete' => 'username', 'value' => $_POST['username'] ?? ''],
        ['name' => 'password', 'label' => 'Password', 'type' => 'password', 'required' => true, 'autocomplete' => 'current-password'],
    ],
    'links' => [['label' => 'Torna al sito del torneo', 'href' => SITE_URL . '/']],
    'footer' => 'Torneo Calcio a 5 — Baone 2026',
];
require dirname(__DIR__) . '/shared/auth_page.php';
