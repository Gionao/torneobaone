<?php
require_once __DIR__ . '/../includes/config.php';

$publicRedesignPages = ['index.php', 'calendario.php', 'classifica.php', 'partita.php', 'squadre.php', 'squadra.php', 'live.php'];
$isPublicRedesign = in_array(basename($_SERVER['PHP_SELF'] ?? ''), $publicRedesignPages, true);

$currentPage = basename($_SERVER['PHP_SELF'] ?? 'index.php');
$navActive = match ($currentPage) {
    'index.php' => 'home',
    'calendario.php' => 'calendario',
    'classifica.php' => 'classifica',
    'squadre.php', 'squadra.php' => 'squadre',
    'iscrizione.php' => 'iscrizione',
    'login.php' => 'login',
    default => '',
};

$siteNavConfig = [
    'id' => 'public-navigation',
    'brand_href' => SITE_URL . '/',
    'brand_name' => 'Torneo Baone',
    'brand_year' => '2026',
    'brand_label' => 'Torneo Baone 2026 — Home',
    'label' => 'Navigazione principale',
    'primary' => [
        ['label' => 'Home', 'href' => SITE_URL . '/', 'current' => $navActive === 'home'],
        ['label' => 'Calendario', 'href' => SITE_URL . '/calendario', 'current' => $navActive === 'calendario'],
        ['label' => 'Classifica', 'href' => SITE_URL . '/classifica', 'current' => $navActive === 'classifica'],
        ['label' => 'Squadre', 'href' => SITE_URL . '/squadre', 'current' => $navActive === 'squadre'],
    ],
    'actions' => [
        ['label' => 'Iscrizione', 'href' => SITE_URL . '/iscrizione', 'primary' => true, 'current' => $navActive === 'iscrizione'],
        ['label' => 'Login', 'href' => SITE_URL . '/capitano/login', 'current' => $navActive === 'login'],
    ],
];

if ($isPublicRedesign) {
    $siteNavConfig['brand_label'] = 'Torneo Baone 2026 — Home';
}

require __DIR__ . '/site_nav.php';
