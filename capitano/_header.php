<?php
// capitano/_header.php

declare(strict_types=1);

require_once __DIR__ . '/../includes/config.php';

if (!isset($pageTitle)) {
    $pageTitle = 'Area Capitano';
}
?>
<!DOCTYPE html>
<html lang="it">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= htmlspecialchars($pageTitle) ?> — <?= htmlspecialchars(SITE_NAME) ?></title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-sRIl4kxILFvY47J16cr9ZwB07vP4J8+LH7qKQnuqkuIAvNWLzeN8tE5YBujZqJLB" crossorigin="anonymous">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Barlow+Condensed:wght@600;700;800&family=Barlow:wght@400;500;600&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?= SITE_URL ?>/assets/css/style.css">
  <link rel="stylesheet" href="<?= SITE_URL ?>/assets/css/admin.css">
</head>
<body class="admin-body">

<?php
$capitanoNavPath = trim((string)(parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?: ''), '/');
$siteNavConfig = [
    'id' => 'captain-navigation',
    'brand_href' => SITE_URL . '/capitano/dashboard',
    'brand_name' => 'Capitano',
    'brand_year' => 'BAONE 2026',
    'brand_label' => 'Area capitano Baone',
    'primary' => [
        ['label' => 'Dashboard', 'href' => SITE_URL . '/capitano/dashboard', 'current' => str_contains($capitanoNavPath, 'dashboard')],
        ['label' => 'Giocatori', 'href' => SITE_URL . '/capitano/giocatori', 'current' => str_contains($capitanoNavPath, 'giocatori')],
        ['label' => 'Pagamenti', 'href' => SITE_URL . '/capitano/pagamenti', 'current' => str_contains($capitanoNavPath, 'pagamenti')],
    ],
    'actions' => [['label' => 'Esci', 'href' => SITE_URL . '/capitano/logout', 'danger' => true]],
];
require __DIR__ . '/../shared/site_nav.php';
?>

<main class="admin-main">
  <div class="admin-container">
