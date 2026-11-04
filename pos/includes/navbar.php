<?php
// Navbar condivisa POS, con destinazioni in base al ruolo.
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/includes/staff_navigation.php';
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
$siteNavConfig = staff_navigation('pos', $posNavActive);
$posNavItems = $siteNavConfig['primary'];
foreach ($posNavItems as &$posNavItem) {
    if (in_array($posNavItem['key'] ?? '', ['kitchen', 'bar'], true) && $posRole === 'admin') {
        $posNavItem['target'] = '_blank';
    }
}
unset($posNavItem);
$posNavContext = trim($posUser . ' · ' . ($posRoleLabels[$posRole] ?? $posRole) . ($posRole === 'operatore' && $posStation !== '' ? ' · ' . $posStation : ''));
$siteNavConfig['context'] = $posNavContext;
$siteNavConfig['primary'] = $posNavItems;
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
