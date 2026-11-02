<?php
// admin/_header.php
// Partial incluso da tutte le pagine admin
// $pageTitle deve essere definito prima dell'include
?>
<!DOCTYPE html>
<html lang="it">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= htmlspecialchars($pageTitle ?? 'Admin') ?> — <?= htmlspecialchars(SITE_NAME) ?></title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Barlow+Condensed:wght@600;700;800&family=Barlow:wght@400;500;600&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?= SITE_URL ?>/assets/css/style.css">
  <link rel="stylesheet" href="<?= SITE_URL ?>/assets/css/admin.css">
</head>
<body class="admin-body">

<?php
$adminNavPath = trim((string)(parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?: ''), '/');
$isTelecronistaArea = is_telecronista();
$adminNavCurrent = static fn(string $section): bool => match ($section) {
    'dashboard' => preg_match('~^(?:(?:admin|telecronista)/)?(?:index(?:\.php)?)?$~', $adminNavPath) === 1,
    'partite' => str_contains($adminNavPath, '/partite') || str_starts_with($adminNavPath, 'partite'),
    'squadre' => str_contains($adminNavPath, '/squadre') || str_starts_with($adminNavPath, 'squadre'),
    'giocatori' => str_contains($adminNavPath, '/giocatori'),
    'iscrizioni' => str_contains($adminNavPath, '/iscrizioni'),
    'pagamenti' => str_contains($adminNavPath, '/pagamenti'),
    default => false,
};
$siteNavConfig = [
    'id' => 'admin-navigation',
    'dense' => true,
    'brand_href' => $isTelecronistaArea ? TELECRONISTA_URL . '/' : ADMIN_PATH . '/',
    'brand_name' => $isTelecronistaArea ? 'Telecronista' : 'Admin',
    'brand_year' => 'BAONE 2026',
    'brand_label' => ($isTelecronistaArea ? 'Telecronista' : 'Amministrazione') . ' Baone',
    'label' => $isTelecronistaArea ? 'Navigazione telecronista' : 'Navigazione amministrazione',
    'primary' => $isTelecronistaArea ? [
        ['label' => 'Partite', 'href' => TELECRONISTA_URL . '/partite/', 'current' => $adminNavCurrent('partite')],
    ] : [
        ['label' => 'Dashboard', 'href' => ADMIN_PATH . '/', 'current' => $adminNavCurrent('dashboard')],
        ['label' => 'Partite', 'href' => ADMIN_PATH . '/partite/', 'current' => $adminNavCurrent('partite')],
        ['label' => 'Squadre', 'href' => ADMIN_PATH . '/squadre/', 'current' => $adminNavCurrent('squadre')],
        ['label' => 'Giocatori', 'href' => ADMIN_PATH . '/giocatori/', 'current' => $adminNavCurrent('giocatori')],
        ['label' => 'Iscrizioni', 'href' => ADMIN_PATH . '/iscrizioni/', 'current' => $adminNavCurrent('iscrizioni')],
        ['label' => 'Pagamenti', 'href' => ADMIN_PATH . '/pagamenti/transazioni', 'current' => $adminNavCurrent('pagamenti')],
        ['label' => 'Archivio media', 'href' => ARCHIVIO_URL . '/', 'current' => false],
    ],
    'actions' => [['label' => 'Esci', 'href' => ADMIN_PATH . '/logout', 'danger' => true]],
];
require __DIR__ . '/../shared/site_nav.php';
?>

<main class="admin-main">
<div class="admin-container">
