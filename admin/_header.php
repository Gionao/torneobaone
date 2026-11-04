<?php
// admin/_header.php
// Partial incluso da tutte le pagine admin
// $pageTitle deve essere definito prima dell'include
?>
<!DOCTYPE html>
<html lang="it">
<head>
  <link rel="icon" type="image/x-icon" href="<?= SITE_URL ?>/assets/brand/02_favicon/favicon.ico">
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= htmlspecialchars($pageTitle ?? 'Admin') ?> — <?= htmlspecialchars(SITE_NAME) ?></title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Barlow+Condensed:wght@600;700;800&family=Barlow:wght@400;500;600&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?= SITE_URL ?>/assets/css/style.css">
  <link rel="stylesheet" href="<?= SITE_URL ?>/assets/css/admin.css">
  <link rel="stylesheet" href="<?= SITE_URL ?>/assets/css/baone-ui.css?v=phase04">
  <link rel="stylesheet" href="<?= SITE_URL ?>/assets/css/admin-workspace.css?v=3">
</head>
<body class="tb-ui admin-body admin-workspace">
<a class="tb-skip-link" href="#admin-contenuto">Vai al contenuto principale</a>

<?php
require_once __DIR__ . '/../includes/staff_navigation.php';
$adminNavPath = trim((string)(parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?: ''), '/');
$adminNavCurrent = static fn(string $section): bool => match ($section) {
    'dashboard' => preg_match('~^(?:(?:admin|telecronista)(?:/index(?:\.php)?)?|index(?:\.php)?)?$~', $adminNavPath) === 1,
    'partite' => str_contains($adminNavPath, '/partite') || str_starts_with($adminNavPath, 'partite'),
    'squadre' => str_contains($adminNavPath, '/squadre') || str_starts_with($adminNavPath, 'squadre'),
    'giocatori' => str_contains($adminNavPath, '/giocatori'),
    'iscrizioni' => str_contains($adminNavPath, '/iscrizioni'),
    'pagamenti' => str_contains($adminNavPath, '/pagamenti') && !str_contains($adminNavPath, '/pagamenti/metodi'),
    'metodi' => str_contains($adminNavPath, '/pagamenti/metodi'),
    default => false,
};
$adminNavIcons = [
    'dashboard' => 'M3 3h7v7H3z M14 3h7v4h-7z M14 10h7v11h-7z M3 14h7v7H3z',
    'matches' => 'M6 3v3 M18 3v3 M4 8h16 M5 5h14a1 1 0 0 1 1 1v13H4V6a1 1 0 0 1 1-1z',
    'teams' => 'M16 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2 M10 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8z M20 21v-2a4 4 0 0 0-3-3.87 M16 3.13a4 4 0 0 1 0 7.75',
    'players' => 'M12 12a4 4 0 1 0 0-8 4 4 0 0 0 0 8z M4 21a8 8 0 0 1 16 0',
    'registrations' => 'M8 3h8 M9 3v4h6V3 M6 5H4v16h16V5h-2 M8 12h8 M8 16h8',
    'payments' => 'M3 6h18v12H3z M3 10h18 M7 15h4',
    'media' => 'M3 5h18v14H3z M8 5l2 3h4l2-3 M12 11a3 3 0 1 0 0 6 3 3 0 0 0 0-6z',
];
$activeAdminNav = '';
foreach (['dashboard', 'partite', 'squadre', 'giocatori', 'iscrizioni', 'metodi', 'pagamenti'] as $section) {
    if ($adminNavCurrent($section)) {
        $activeAdminNav = $section;
        break;
    }
}
$siteNavConfig = staff_navigation('admin', $activeAdminNav);
$isTelecronistaArea = staff_role('partite') === 'telecronista';
$navigationIcons = ['dashboard' => 'dashboard', 'partite' => 'matches', 'squadre' => 'teams', 'giocatori' => 'players', 'iscrizioni' => 'registrations', 'pagamenti' => 'payments', 'metodi' => 'payments', 'utenti-torneo' => 'dashboard'];
$siteNavConfig['primary'] = array_map(static function (array $item) use ($navigationIcons): array {
    $icon = $navigationIcons[$item['key'] ?? ''] ?? 'dashboard';
    $item['icon'] = $icon;
    return $item;
}, $siteNavConfig['primary']);
?>
<div class="admin-shell">
  <aside class="admin-sidebar" aria-label="Navigazione pannello">
    <a class="admin-sidebar-brand" href="<?= htmlspecialchars($siteNavConfig['brand_href'], ENT_QUOTES, 'UTF-8') ?>">
      <img src="<?= SITE_URL ?>/assets/brand/01_loghi/png/logo_principale.png" alt="" width="42" height="42">
      <span><strong><?= htmlspecialchars($siteNavConfig['brand_name'], ENT_QUOTES, 'UTF-8') ?></strong><small><?= htmlspecialchars($siteNavConfig['brand_year'], ENT_QUOTES, 'UTF-8') ?></small></span>
    </a>
    <div class="admin-sidebar-label"><?= $isTelecronistaArea ? 'Strumenti gara' : 'Aree autorizzate' ?></div>
    <nav class="admin-sidebar-nav" aria-label="<?= htmlspecialchars($siteNavConfig['label'], ENT_QUOTES, 'UTF-8') ?>">
      <?php foreach ($siteNavConfig['primary'] as $siteNavItem): ?>
        <a class="admin-sidebar-link tb-navigation__link<?= !empty($siteNavItem['current']) ? ' is-current' : '' ?>" href="<?= htmlspecialchars((string) $siteNavItem['href'], ENT_QUOTES, 'UTF-8') ?>"<?= !empty($siteNavItem['current']) ? ' aria-current="page"' : '' ?>>
          <svg class="admin-sidebar-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="<?= $adminNavIcons[$siteNavItem['icon'] ?? ''] ?? $adminNavIcons['dashboard'] ?>"/></svg>
          <span><?= htmlspecialchars((string) $siteNavItem['label'], ENT_QUOTES, 'UTF-8') ?></span>
        </a>
      <?php endforeach; ?>
      <?php foreach ($siteNavConfig['actions'] as $siteNavAction): ?>
        <a class="admin-sidebar-link<?= !empty($siteNavAction['danger']) ? ' is-danger' : '' ?><?= !empty($siteNavAction['current']) ? ' is-current' : '' ?>" href="<?= htmlspecialchars((string) $siteNavAction['href'], ENT_QUOTES, 'UTF-8') ?>"<?= !empty($siteNavAction['current']) ? ' aria-current="page"' : '' ?>>
          <span><?= htmlspecialchars((string) $siteNavAction['label'], ENT_QUOTES, 'UTF-8') ?></span>
        </a>
      <?php endforeach; ?>
    </nav>
    <div class="admin-sidebar-bottom"><span><?= $isTelecronistaArea ? 'Accesso telecronista' : 'Area riservata' ?></span></div>
  </aside>
  <div class="admin-workspace-main">
    <header class="admin-topbar">
      <div class="admin-topbar-context"><span class="admin-topbar-kicker">TORNEO BAONE · 2026</span><strong><?= $isTelecronistaArea ? 'Gestione partite' : 'Amministrazione' ?></strong></div>
    </header>
    <main class="admin-main" id="admin-contenuto">
      <div class="admin-container">
