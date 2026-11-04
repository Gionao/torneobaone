<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/config.php';
require_once dirname(__DIR__) . '/includes/auth.php';

$principal = staff_current_user();
if ($principal === null) {
    header('Location: ' . STAFF_URL . '/login.php');
    exit;
}
staff_require_authenticated();

$settori = [
    'torneo' => ['nome' => 'Amministrazione torneo', 'url' => staff_landing_url('torneo')],
    'partite' => ['nome' => 'Gestione partite', 'url' => staff_landing_url('partite')],
    'pos' => ['nome' => 'Punto vendita', 'url' => staff_landing_url('pos')],
    'archivio' => ['nome' => 'Archivio multimediale', 'url' => staff_landing_url('archivio')],
];
$accessibili = [];
foreach ($settori as $codice => $settore) {
    if ($principal['is_super_admin'] || isset($principal['assignments'][$codice])) {
        $accessibili[$codice] = $settore;
    }
}
if (staff_has_capability('partite.manage') && !isset($accessibili['partite'])) {
    $accessibili['partite'] = $settori['partite'];
}
if (count($accessibili) === 1 && !$principal['is_super_admin']) {
    header('Location: ' . reset($accessibili)['url']);
    exit;
}
$pageTitle = 'Selezione area staff';
?>
<!DOCTYPE html>
<html lang="it">
<head>
  <link rel="icon" type="image/x-icon" href="<?= SITE_URL ?>/assets/brand/02_favicon/favicon.ico">
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8') ?> — <?= htmlspecialchars(SITE_NAME, ENT_QUOTES, 'UTF-8') ?></title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Barlow+Condensed:wght@600;700;800&family=Barlow:wght@400;500;600&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?= SITE_URL ?>/assets/css/style.css">
  <link rel="stylesheet" href="<?= SITE_URL ?>/assets/css/admin.css">
  <link rel="stylesheet" href="<?= SITE_URL ?>/assets/css/baone-ui.css?v=phase04">
  <link rel="stylesheet" href="<?= SITE_URL ?>/assets/css/admin-workspace.css?v=3">
</head>
<body class="tb-ui admin-body">
<main class="admin-main"><div class="admin-container">
  <div class="admin-page-header">
    <h1>Seleziona un’area</h1>
    <p>Accesso staff per <?= htmlspecialchars($principal['username'], ENT_QUOTES, 'UTF-8') ?></p>
  </div>
  <?php if ($accessibili === []): ?>
    <div class="alert alert-error" role="alert">Il tuo account non ha aree di lavoro assegnate. Contatta il Super Admin.</div>
  <?php else: ?>
    <div class="stat-grid">
      <?php foreach ($accessibili as $settore): ?>
        <a class="stat-card" href="<?= htmlspecialchars($settore['url'], ENT_QUOTES, 'UTF-8') ?>">
          <div class="stat-lbl"><?= htmlspecialchars($settore['nome'], ENT_QUOTES, 'UTF-8') ?></div>
          <span class="stat-link">Apri area →</span>
        </a>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
  <?php if ($principal['is_super_admin']): ?><p><a class="btn btn-gold" href="<?= STAFF_URL ?>/users.php">Gestione staff</a></p><?php endif; ?>
  <p style="margin-top:1.5rem"><a class="btn btn-outline" href="<?= STAFF_URL ?>/logout.php">Esci</a></p>
</div></main>
</body>
</html>
