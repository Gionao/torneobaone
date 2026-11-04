<?php
// photo/admin/_header.php
// Partial comune dell'archivio. $pageTitle e $paginaAttiva vanno definiti prima.
require_once dirname(__DIR__, 2) . '/includes/staff_navigation.php';
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
$siteNavConfig = staff_navigation('archivio', $paginaAttiva);
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
