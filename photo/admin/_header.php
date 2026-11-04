<?php
// photo/admin/_header.php
// Partial comune dell'archivio. $pageTitle e $paginaAttiva vanno definiti prima.
$mediaFlash = media_flash_leggi();
$mediaRuolo = media_ruolo();
$mediaRuoliLabel = [
    'uploader' => 'Collaboratore',
    'editor'   => 'Editor',
    'admin'    => 'Amministratore',
];
$paginaAttiva = $paginaAttiva ?? '';
$cssVersione = @filemtime(dirname(__DIR__) . '/assets/css/archivio.css') ?: time();
?>
<!DOCTYPE html>
<html lang="it">
<head>
  <link rel="icon" type="image/x-icon" href="<?= SITE_URL ?>/assets/brand/02_favicon/favicon.ico">
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="robots" content="noindex, nofollow">
  <meta name="csrf-token" content="<?= media_h(csrf_token()) ?>">
  <title><?= media_h($pageTitle ?? 'Archivio') ?> — <?= media_h(SITE_NAME) ?></title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Barlow+Condensed:wght@600;700;800&family=Barlow:wght@400;500;600&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?= media_h(SITE_URL) ?>/assets/css/style.css">
  <link rel="stylesheet" href="<?= media_h(SITE_URL) ?>/assets/css/admin.css">
  <link rel="stylesheet" href="<?= media_h(MEDIA_ROOT_URL) ?>/assets/css/archivio.css?v=<?= (int) $cssVersione ?>">
</head>
<body class="admin-body archivio-body">

<?php
$archivioNavPrimary = [
    ['label' => 'Panoramica', 'href' => ARCHIVIO_URL . '/', 'current' => $paginaAttiva === 'dashboard'],
    ['label' => 'Galleria', 'href' => ARCHIVIO_URL . '/galleria', 'current' => $paginaAttiva === 'galleria'],
];
if (in_array(media_ruolo(), ['uploader', 'admin'], true)) {
    $archivioNavPrimary[] = ['label' => 'Carica', 'href' => ARCHIVIO_URL . '/upload', 'current' => $paginaAttiva === 'upload'];
}
if (media_is_editor()) {
    $archivioNavPrimary[] = ['label' => 'Edizioni', 'href' => ARCHIVIO_URL . '/edizioni/', 'current' => $paginaAttiva === 'edizioni'];
}
if (media_is_admin()) {
    $archivioNavPrimary[] = ['label' => 'Cestino', 'href' => ARCHIVIO_URL . '/cestino', 'current' => $paginaAttiva === 'cestino'];
    $archivioNavPrimary[] = ['label' => 'Utenti', 'href' => ARCHIVIO_URL . '/utenti', 'current' => $paginaAttiva === 'utenti'];
    $archivioNavPrimary[] = ['label' => 'Impostazioni', 'href' => ARCHIVIO_URL . '/impostazioni', 'current' => $paginaAttiva === 'impostazioni'];
}
$siteNavConfig = [
    'id' => 'archive-navigation',
    'dense' => true,
    'brand_href' => ARCHIVIO_URL . '/',
    'brand_name' => 'Archivio',
    'brand_year' => 'BAONE 2026',
    'brand_label' => 'Archivio foto e video Baone',
    'label' => 'Navigazione archivio',
    'primary' => $archivioNavPrimary,
    'actions' => array_merge(
        media_is_admin() ? [['label' => 'Pannello admin', 'href' => ADMIN_PATH . '/', 'primary' => true]] : [],
        [['label' => 'Esci', 'href' => ARCHIVIO_URL . '/logout', 'danger' => true]]
    ),
];
require __DIR__ . '/../../shared/site_nav.php';
?>

<main class="admin-main">
<div class="admin-container archivio-container">
  <div class="archivio-ruolo">
    <span class="role-badge role-badge--<?= media_h($mediaRuolo) ?>">
      <?= media_h($mediaRuoliLabel[$mediaRuolo] ?? $mediaRuolo) ?>
    </span>
    <span><?= media_h(media_admin_username()) ?></span>
  </div>

  <?php if ($mediaFlash): ?>
    <div class="alert alert-<?= ($mediaFlash['type'] ?? '') === 'error' ? 'error' : 'success' ?>" role="status">
      <?= media_h((string) ($mediaFlash['text'] ?? '')) ?>
    </div>
  <?php endif; ?>
