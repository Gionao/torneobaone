<?php
// Navbar condivisa POS, con destinazioni in base al ruolo.
declare(strict_types=1);

$posNavActive = $posNavActive ?? '';
$posUser = (string)($_SESSION['pos_admin_user'] ?? 'Staff');
$posRole = (string)($_SESSION['pos_admin_role'] ?? '');
$posStation = trim((string)($_SESSION['pos_admin_station'] ?? ''));
$posRoleLabels = [
    'admin' => 'Admin',
    'operatore' => 'Operatore',
    'bar' => 'Bar',
    'cucina' => 'Cucina',
];
$posNavItemsByRole = [
    'admin' => [
        ['label' => 'Operatore', 'href' => POS_URL . '/operator_panel', 'key' => 'operator'],
        ['label' => 'Cucina', 'href' => POS_URL . '/kitchen_display', 'key' => 'kitchen', 'target' => '_blank'],
        ['label' => 'Bar', 'href' => POS_URL . '/bar_display', 'key' => 'bar', 'target' => '_blank'],
        ['label' => 'Menu', 'href' => POS_URL . '/admin/menu_management', 'key' => 'menu'],
        ['label' => 'Coupon', 'href' => POS_URL . '/admin/coupons', 'key' => 'coupons'],
        ['label' => 'Utenti', 'href' => POS_URL . '/admin/users', 'key' => 'users'],
        ['label' => 'Statistiche', 'href' => POS_URL . '/admin/statistics', 'key' => 'stats'],
        ['label' => 'Storico', 'href' => POS_URL . '/admin/orders_history', 'key' => 'history'],
    ],
    'operatore' => [
        ['label' => 'Operatore', 'href' => POS_URL . '/operator_panel', 'key' => 'operator'],
        ['label' => 'Storico', 'href' => POS_URL . '/admin/orders_history', 'key' => 'history'],
    ],
    'bar' => [['label' => 'Bar', 'href' => POS_URL . '/bar_display', 'key' => 'bar']],
    'cucina' => [['label' => 'Cucina', 'href' => POS_URL . '/kitchen_display', 'key' => 'kitchen']],
];
$posNavItems = [];
foreach ($posNavItemsByRole[$posRole] ?? [] as $posNavItem) {
    $posNavItems[] = [
        'label' => $posNavItem['label'],
        'href' => $posNavItem['href'],
        'current' => $posNavActive === $posNavItem['key'],
        'target' => $posNavItem['target'] ?? null,
    ];
}
$posNavContext = trim($posUser . ' · ' . ($posRoleLabels[$posRole] ?? $posRole) . ($posRole === 'operatore' && $posStation !== '' ? ' · ' . $posStation : ''));
$siteNavConfig = [
    'id' => 'pos-navigation',
    'dense' => true,
    'brand_href' => POS_URL . '/',
    'brand_name' => 'POS',
    'brand_year' => 'BAONE 2026',
    'brand_label' => 'Punto vendita Baone',
    'label' => 'Navigazione POS',
    'context' => $posNavContext,
    'primary' => $posNavItems,
    'actions' => [['label' => 'Esci', 'href' => POS_URL . '/logout', 'danger' => true]],
];
require __DIR__ . '/../../shared/site_nav.php';
