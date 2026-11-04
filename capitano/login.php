<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth_capitano.php';

capitano_session_start_secure();

if (is_capitano_logged()) {
    header('Location: ' . SITE_URL . '/capitano/dashboard.php');
    exit;
}

$errore = '';
$notice = (string) ($_SESSION['capitano_notice'] ?? '');
unset($_SESSION['capitano_notice']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim((string) ($_POST['email'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');

    if ($email === '' || $password === '') {
        $errore = 'Inserisci email e password.';
    } elseif (!capitano_login($email, $password)) {
        $loginError = $_SESSION['capitano_login_error'] ?? '';
        unset($_SESSION['capitano_login_error']);
        if ($loginError === 'email_non_verificata') {
            $errore = 'L’account non è ancora verificato. Controlla la tua email o richiedi un nuovo link.';
        } elseif ($loginError === 'squadra_non_attiva') {
            $errore = 'La password è corretta, ma la squadra non risulta attiva. Contatta l’organizzazione.';
        } else {
            $errore = 'Credenziali non valide.';
        }
    } else {
        header('Location: ' . SITE_URL . '/capitano/dashboard.php');
        exit;
    }
}

$authPage = [
    'title' => 'Area capitano',
    'subtitle' => 'Accedi per gestire la squadra e gli inviti.',
    'logo' => SITE_URL . '/assets/brand/01_loghi/png/logo_scudetto_compatto.png',
    'logo_alt' => 'Scudetto del Torneo Baone',
    'page_title' => 'Login capitano — ' . SITE_NAME,
    'error' => $errore,
    'notice' => $notice,
    'action' => SITE_URL . '/capitano/login.php',
    'fields' => [
        ['name' => 'email', 'label' => 'Email', 'type' => 'email', 'required' => true, 'autofocus' => true, 'autocomplete' => 'username', 'value' => $_POST['email'] ?? ''],
        ['name' => 'password', 'label' => 'Password', 'type' => 'password', 'required' => true, 'autocomplete' => 'current-password'],
    ],
    'links' => [
        ['label' => 'Password dimenticata', 'href' => SITE_URL . '/capitano/password-dimenticata.php'],
        ['label' => 'Verifica account', 'href' => SITE_URL . '/capitano/richiedi-verifica.php'],
        ['label' => 'Registrati', 'href' => SITE_URL . '/capitano/registrati.php'],
        ['label' => 'Torna al sito', 'href' => SITE_URL . '/'],
    ],
];
require __DIR__ . '/../shared/auth_page.php';
