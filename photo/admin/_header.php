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

<nav class="admin-navbar archivio-navbar">
  <div class="admin-nav-inner">
    <a class="admin-brand" href="<?= media_h(ARCHIVIO_URL) ?>/index.php">
      <img src="<?= media_h(SITE_URL) ?>/assets/img/logo_torneo.png" alt="Logo Torneo" class="admin-logo">
      <span>Archivio <em>Baone</em></span>
    </a>
    <button type="button" class="archivio-nav-toggle" id="archivioNavToggle"
            aria-controls="archivioNavLinks" aria-expanded="false" aria-label="Apri il menu">
      <span></span><span></span><span></span>
    </button>
    <div class="admin-nav-links archivio-nav-links" id="archivioNavLinks">
      <a href="<?= media_h(ARCHIVIO_URL) ?>/index.php" class="<?= $paginaAttiva === 'dashboard' ? 'attivo' : '' ?>">Panoramica</a>
      <a href="<?= media_h(ARCHIVIO_URL) ?>/galleria.php" class="<?= $paginaAttiva === 'galleria' ? 'attivo' : '' ?>">Galleria</a>
      <?php if (in_array(media_ruolo(), ['uploader', 'admin'], true)): ?>
      <a href="<?= media_h(ARCHIVIO_URL) ?>/upload.php" class="<?= $paginaAttiva === 'upload' ? 'attivo' : '' ?>">Carica</a>
      <?php endif; ?>
      <?php if (media_is_editor()): ?>
      <a href="<?= media_h(ARCHIVIO_URL) ?>/edizioni/index.php" class="<?= $paginaAttiva === 'edizioni' ? 'attivo' : '' ?>">Edizioni</a>
      <?php endif; ?>
      <?php if (media_is_admin()): ?>
      <a href="<?= media_h(ARCHIVIO_URL) ?>/cestino.php" class="<?= $paginaAttiva === 'cestino' ? 'attivo' : '' ?>">Cestino</a>
      <a href="<?= media_h(ARCHIVIO_URL) ?>/utenti.php" class="<?= $paginaAttiva === 'utenti' ? 'attivo' : '' ?>">Utenti</a>
      <a href="<?= media_h(ARCHIVIO_URL) ?>/impostazioni.php" class="<?= $paginaAttiva === 'impostazioni' ? 'attivo' : '' ?>">Impostazioni</a>
      <a href="<?= media_h(ADMIN_PATH) ?>/index.php">Pannello</a>
      <?php endif; ?>
      <a href="<?= media_h(ARCHIVIO_URL) ?>/logout.php" class="admin-logout">Esci</a>
    </div>
  </div>
</nav>

<main class="admin-main">
<div class="admin-container archivio-container">
  <div class="archivio-ruolo">
    <span class="role-badge role-badge--<?= media_h($mediaRuolo) ?>">
      <?= media_h($mediaRuoliLabel[$mediaRuolo] ?? $mediaRuolo) ?>
    </span>
    <span><?= media_h((string) ($_SESSION['admin_username'] ?? '')) ?></span>
  </div>

  <?php if ($mediaFlash): ?>
    <div class="alert alert-<?= ($mediaFlash['type'] ?? '') === 'error' ? 'error' : 'success' ?>" role="status">
      <?= media_h((string) ($mediaFlash['text'] ?? '')) ?>
    </div>
  <?php endif; ?>
