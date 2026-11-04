<?php
// Navbar condivisa POS, con destinazioni in base al ruolo.
declare(strict_types=1);

$posNavActive = $posNavActive ?? '';
$posUser = staff_username() ?: 'Staff';
$posRole = pos_current_role();
$posStation = trim((string) (staff_station('pos') ?? ''));
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
?>
<script>
(() => {
  const token = <?= json_encode(staff_csrf_token(), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
  const nativeFetch = window.fetch.bind(window);
  window.fetch = (input, init = {}) => {
    const url = typeof input === 'string' ? input : input.url;
    const method = (init.method || (typeof input !== 'string' && input.method) || 'GET').toUpperCase();
    if (url.includes('/api/') && method === 'POST') {
      const headers = new Headers(init.headers || (typeof input !== 'string' ? input.headers : undefined));
      headers.set('X-CSRF-Token', token);
      init = {...init, headers};
    }
    return nativeFetch(input, init);
  };
})();
</script>
