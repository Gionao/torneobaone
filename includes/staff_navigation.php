<?php
declare(strict_types=1);

require_once __DIR__ . '/staff_authorization.php';

/** Build staff navigation from the same effective capabilities enforced by route guards. */
function staff_navigation(string $surface, string $active = ''): array
{
    $principal = staff_current_user(true);
    $items = [];
    $add = static function (string $capability, string $label, string $href, string $key) use (&$items, $active): void {
        foreach ($items as $existing) {
            if (($existing['key'] ?? '') === $key) {
                return;
            }
        }
        if (staff_has_capability($capability)) {
            $items[] = ['label' => $label, 'href' => $href, 'current' => $active === $key, 'key' => $key];
        }
    };

    if ($surface === 'admin') {
        $matchOnly = $principal !== null
            && !$principal['is_super_admin']
            && count($principal['assignments']) === 1
            && isset($principal['assignments']['partite']);

        if ($matchOnly) {
            $add('partite.manage', 'Partite', TELECRONISTA_URL . '/partite/', 'partite');
            $add('partite.limited', 'Partite', TELECRONISTA_URL . '/partite/', 'partite');
        } else {
            $add('admin.dashboard', 'Dashboard', ADMIN_PATH . '/', 'dashboard');
            $add('torneo.teams', 'Squadre', ADMIN_PATH . '/squadre/', 'squadre');
            $add('torneo.players', 'Giocatori', ADMIN_PATH . '/giocatori/', 'giocatori');
            $add('torneo.registrations', 'Iscrizioni', ADMIN_PATH . '/iscrizioni/', 'iscrizioni');
            $add('torneo.payments', 'Pagamenti', ADMIN_PATH . '/pagamenti/transazioni', 'pagamenti');
            $add('torneo.payment_methods', 'Metodi di pagamento', ADMIN_PATH . '/pagamenti/metodi.php', 'metodi');
            $add('torneo.staff', 'Utenti torneo', STAFF_URL . '/sector-users.php?sector=torneo', 'utenti-torneo');
            $add('partite.manage', 'Partite', ADMIN_PATH . '/partite/', 'partite');
            $add('partite.limited', 'Partite', TELECRONISTA_URL . '/partite/', 'partite');
            $add('pos.admin', 'Punto vendita', POS_URL . '/', 'pos');
            $add('pos.operator', 'Punto vendita', POS_URL . '/', 'pos');
            $add('pos.bar', 'Punto vendita', POS_URL . '/', 'pos');
            $add('pos.kitchen', 'Punto vendita', POS_URL . '/', 'pos');
            $add('archivio.admin', 'Archivio media', ARCHIVIO_URL . '/', 'archivio');
            $add('archivio.editor', 'Archivio media', ARCHIVIO_URL . '/', 'archivio');
            $add('archivio.uploader', 'Archivio media', ARCHIVIO_URL . '/', 'archivio');
        }
        $profile = ['label' => 'Profilo', 'href' => STAFF_URL . '/profile.php', 'current' => $active === 'profilo', 'key' => 'profilo'];
        $actions = [$profile, ['label' => 'Logout', 'href' => STAFF_URL . '/logout.php', 'danger' => true]];
        if ($principal !== null && ($principal['is_super_admin'] || count($principal['assignments']) > 1)) {
            $actions[] = ['label' => 'Cambia area', 'href' => STAFF_URL . '/index.php'];
        }
        if ($principal !== null && $principal['is_super_admin']) {
            $actions[] = ['label' => 'Gestione staff', 'href' => STAFF_URL . '/users.php'];
        }
        return [
            'id' => 'admin-navigation', 'dense' => true,
            'brand_href' => $matchOnly ? TELECRONISTA_URL . '/' : (staff_has_capability('admin.dashboard') ? ADMIN_PATH . '/' : STAFF_URL . '/index.php'),
            'brand_name' => $matchOnly ? 'Partite' : 'Amministrazione',
            'brand_year' => 'BAONE 2026',
            'brand_label' => $matchOnly ? 'Gestione partite Baone' : 'Amministrazione Baone',
            'label' => 'Navigazione amministrazione', 'primary' => $items, 'actions' => $actions,
        ];
    }

    if ($surface === 'pos') {
        $add('pos.operator', 'Operatore', POS_URL . '/operator_panel', 'operator');
        $add('pos.kitchen', 'Cucina', POS_URL . '/kitchen_display', 'kitchen');
        $add('pos.bar', 'Bar', POS_URL . '/bar_display', 'bar');
        $add('pos.menu', 'Menu', POS_URL . '/admin/menu_management', 'menu');
        $add('pos.coupons', 'Coupon', POS_URL . '/admin/coupons', 'coupons');
        $add('pos.users', 'Utenti', POS_URL . '/admin/users', 'users');
        $add('pos.statistics', 'Statistiche', POS_URL . '/admin/statistics', 'stats');
        $add('pos.history', 'Storico', POS_URL . '/admin/orders_history', 'history');
        $actions = [['label' => 'Esci', 'href' => POS_URL . '/logout', 'danger' => true]];
        if ($principal !== null && ($principal['is_super_admin'] || count($principal['assignments']) > 1)) {
            array_unshift($actions, ['label' => 'Cambia area', 'href' => STAFF_URL . '/index.php']);
        }
        return [
            'id' => 'pos-navigation', 'dense' => true, 'brand_href' => POS_URL . '/',
            'brand_name' => 'POS', 'brand_year' => 'BAONE 2026', 'brand_label' => 'Punto vendita Baone',
            'label' => 'Navigazione POS', 'primary' => $items,
            'actions' => $actions,
        ];
    }

    if ($surface === 'archivio') {
        $add('archivio.view', 'Panoramica', ARCHIVIO_URL . '/', 'dashboard');
        $add('archivio.view', 'Galleria', ARCHIVIO_URL . '/galleria', 'galleria');
        $add('archivio.upload', 'Carica', ARCHIVIO_URL . '/upload', 'upload');
        $add('archivio.structure', 'Edizioni', ARCHIVIO_URL . '/edizioni/', 'edizioni');
        $add('archivio.trash', 'Cestino', ARCHIVIO_URL . '/cestino', 'cestino');
        $add('archivio.users', 'Utenti', ARCHIVIO_URL . '/utenti', 'utenti');
        $add('archivio.settings', 'Impostazioni', ARCHIVIO_URL . '/impostazioni', 'impostazioni');
        $actions = array_merge(
            staff_has_capability('admin.dashboard') ? [['label' => 'Pannello admin', 'href' => ADMIN_PATH . '/', 'primary' => true]] : [],
            [['label' => 'Esci', 'href' => ARCHIVIO_URL . '/logout', 'danger' => true]]
        );
        if ($principal !== null && ($principal['is_super_admin'] || count($principal['assignments']) > 1)) {
            array_unshift($actions, ['label' => 'Cambia area', 'href' => STAFF_URL . '/index.php']);
        }
        return [
            'id' => 'archive-navigation', 'dense' => true, 'brand_href' => ARCHIVIO_URL . '/',
            'brand_name' => 'Archivio', 'brand_year' => 'BAONE 2026', 'brand_label' => 'Archivio foto e video Baone',
            'label' => 'Navigazione archivio', 'primary' => $items,
            'actions' => $actions,
        ];
    }

    return ['primary' => [], 'actions' => []];
}
