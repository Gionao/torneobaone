<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/config.php';
require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/includes/staff_navigation.php';

$principal = staff_current_user();
if ($principal === null) {
    header('Location: ' . STAFF_URL . '/login.php');
    exit;
}
staff_require_authenticated();
$siteNavConfig = staff_navigation('admin', 'profilo');
$pageTitle = 'Profilo staff';
?>
<!doctype html>
<html lang="it">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8') ?> — <?= htmlspecialchars(SITE_NAME, ENT_QUOTES, 'UTF-8') ?></title>
  <link rel="stylesheet" href="<?= SITE_URL ?>/assets/css/style.css">
  <link rel="stylesheet" href="<?= SITE_URL ?>/assets/css/admin.css">
  <link rel="stylesheet" href="<?= SITE_URL ?>/assets/css/baone-ui.css?v=phase04">
</head>
<body class="admin-body tb-ui">
<?php require dirname(__DIR__) . '/shared/site_nav.php'; ?>
<main class="admin-main"><div class="admin-container">
  <div class="admin-page-header"><h1>Profilo</h1><p>Informazioni del tuo account staff.</p></div>
  <section class="admin-form" aria-labelledby="profile-data-title">
    <h2 id="profile-data-title">Account</h2>
    <p><strong>Nome utente:</strong> <?= htmlspecialchars($principal['username'], ENT_QUOTES, 'UTF-8') ?></p>
    <?php if ($principal['is_super_admin']): ?>
      <p><strong>Ruolo:</strong> Super Admin</p>
    <?php else: ?>
      <ul><?php foreach ($principal['assignments'] as $area => $assignment): ?>
        <li><?= htmlspecialchars(ucfirst((string) $area) . ' — ' . (string) $assignment['role'], ENT_QUOTES, 'UTF-8') ?></li>
      <?php endforeach; ?></ul>
    <?php endif; ?>
    <p><a class="btn btn-outline" href="<?= STAFF_URL ?>/change-password.php">Cambia password</a></p>
  </section>
</div></main>
</body>
</html>
