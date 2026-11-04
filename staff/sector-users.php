<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/config.php';
require_once dirname(__DIR__) . '/includes/auth.php';

$sector = (string) ($_GET['sector'] ?? '');
if (!in_array($sector, ['torneo', 'partite'], true)) {
    http_response_code(404);
    exit('Area non trovata.');
}
$principal = staff_require_authenticated();
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
if (!can_manage_sector_users($sector)) {
    staff_forbidden();
}
$labels = ['torneo' => 'Torneo', 'partite' => 'Partite'];
$subordinate = array_values(array_filter(STAFF_SECTOR_ROLES[$sector], static fn(string $role): bool => $role !== array_key_first(STAFF_SECTOR_HIERARCHY[$sector])));
$messaggio = '';
$errore = '';
$query = trim((string) ($_GET['q'] ?? ''));

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    try {
        if (!csrf_verify()) {
            throw new RuntimeException('Token di sicurezza non valido.');
        }
        $action = (string) ($_POST['action'] ?? '');
        $userId = (int) ($_POST['user_id'] ?? 0);
        if ($action === 'assign') {
            $username = trim((string) ($_POST['username'] ?? ''));
            $role = (string) ($_POST['role'] ?? '');
            if (!in_array($role, $subordinate, true) && !is_super_admin()) {
                throw new RuntimeException('Ruolo non assegnabile.');
            }
            $find = db()->prepare('SELECT id FROM admin WHERE username=? AND attivo=1 AND is_super_admin=0 LIMIT 1');
            $find->execute([$username]);
            $existing = (int) ($find->fetchColumn() ?: 0);
            if ($existing > 0) {
                staff_assign_sector_role($existing, $sector, $role);
                $messaggio = 'Accesso assegnato all’account esistente.';
            } else {
                $created = staff_create_account_with_temporary_password($username, $sector, $role);
                $messaggio = 'Account creato. Password temporanea (mostrata una sola volta): ' . $created['temporary_password'];
            }
        } elseif ($action === 'revoke') {
            staff_revoke_sector_role($userId, $sector);
            $messaggio = 'Accesso al settore revocato.';
        } elseif ($action === 'toggle') {
            staff_set_sector_assignment_active($userId, $sector, (int) ($_POST['active'] ?? 0) === 1);
            $messaggio = 'Stato dell’assegnazione aggiornato.';
        } elseif ($action === 'role') {
            $role = (string) ($_POST['role'] ?? '');
            if (!in_array($role, $subordinate, true) && !is_super_admin()) {
                throw new RuntimeException('Ruolo non assegnabile.');
            }
            staff_assign_sector_role($userId, $sector, $role);
            $messaggio = 'Ruolo aggiornato.';
        } else {
            throw new RuntimeException('Operazione non riconosciuta.');
        }
    } catch (Throwable $e) {
        $errore = $e->getMessage();
    }
}

$accounts = [];
if ($query !== '') {
    $accounts = staff_find_sector_accounts($sector, $query, 15);
} else {
    $stmt = db()->prepare('SELECT u.id,u.username,a.ruolo,a.attivo,a.station_name FROM admin u INNER JOIN staff_assegnazioni a ON a.id_admin=u.id AND a.area=? WHERE u.attivo=1 AND u.is_super_admin=0 ORDER BY a.ruolo,u.username');
    $stmt->execute([$sector]);
    $accounts = $stmt->fetchAll();
}
$csrf = csrf_token();
?>
<!DOCTYPE html><html lang="it"><head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Utenti <?= htmlspecialchars($labels[$sector], ENT_QUOTES, 'UTF-8') ?> — <?= htmlspecialchars(SITE_NAME, ENT_QUOTES, 'UTF-8') ?></title>
<link rel="stylesheet" href="<?= SITE_URL ?>/assets/css/style.css"><link rel="stylesheet" href="<?= SITE_URL ?>/assets/css/admin.css"><link rel="stylesheet" href="<?= SITE_URL ?>/assets/css/baone-ui.css?v=phase04">
</head><body class="admin-body"><main class="admin-main"><div class="admin-container">
<div class="admin-page-header"><h1>Utenti <?= htmlspecialchars($labels[$sector], ENT_QUOTES, 'UTF-8') ?></h1><p>Gestisci esclusivamente le assegnazioni di quest’area.</p></div>
<?php if ($messaggio !== ''): ?><div class="alert alert-success"><?= htmlspecialchars($messaggio, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
<?php if ($errore !== ''): ?><div class="alert alert-error"><?= htmlspecialchars($errore, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
<section class="admin-form"><h2>Cerca account staff</h2><form method="get"><input type="hidden" name="sector" value="<?= htmlspecialchars($sector, ENT_QUOTES, 'UTF-8') ?>"><div class="form-group"><label for="q">Username (almeno 3 caratteri)</label><input id="q" name="q" value="<?= htmlspecialchars($query, ENT_QUOTES, 'UTF-8') ?>" minlength="3" maxlength="60"></div><button class="btn btn-outline">Cerca</button></form></section>
<section class="admin-form"><h2>Nuova assegnazione</h2><form method="post"><input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>"><input type="hidden" name="action" value="assign"><div class="form-group"><label>Username account esistente o nuovo</label><input name="username" required minlength="3" maxlength="60" pattern="[A-Za-z0-9._-]+"></div><div class="form-group"><label>Ruolo di settore</label><select name="role" required><?php foreach ($subordinate as $role): ?><option value="<?= htmlspecialchars($role, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($role, ENT_QUOTES, 'UTF-8') ?></option><?php endforeach; ?><?php if (is_super_admin()): ?><option value="<?= htmlspecialchars(array_key_first(STAFF_SECTOR_HIERARCHY[$sector]), ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars(array_key_first(STAFF_SECTOR_HIERARCHY[$sector]), ENT_QUOTES, 'UTF-8') ?></option><?php endif; ?></select></div><button class="btn btn-gold">Assegna accesso</button></form></section>
<div class="admin-table-wrap"><table class="admin-table"><thead><tr><th>Username</th><th>Ruolo <?= htmlspecialchars($labels[$sector], ENT_QUOTES, 'UTF-8') ?></th><th>Stato assegnazione</th><th>Operazioni</th></tr></thead><tbody>
<?php foreach ($accounts as $account): ?><tr><td><?= htmlspecialchars($account['username'], ENT_QUOTES, 'UTF-8') ?></td><td><?= htmlspecialchars((string) ($account['ruolo'] ?? 'Nessuna assegnazione'), ENT_QUOTES, 'UTF-8') ?></td><td><?= !isset($account['attivo']) ? 'Non assegnato' : ((int)$account['attivo'] === 1 ? 'Attiva' : 'Disattiva') ?></td><td>
<?php if (!empty($account['ruolo'])): ?><form method="post" style="display:inline"><input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>"><input type="hidden" name="action" value="role"><input type="hidden" name="user_id" value="<?= (int)$account['id'] ?>"><select name="role"><?php foreach ($subordinate as $role): ?><option value="<?= htmlspecialchars($role, ENT_QUOTES, 'UTF-8') ?>" <?= $account['ruolo'] === $role ? 'selected' : '' ?>><?= htmlspecialchars($role, ENT_QUOTES, 'UTF-8') ?></option><?php endforeach; ?></select><button class="btn btn-sm btn-outline">Modifica ruolo</button></form>
<form method="post" style="display:inline"><input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>"><input type="hidden" name="action" value="toggle"><input type="hidden" name="user_id" value="<?= (int)$account['id'] ?>"><input type="hidden" name="active" value="<?= (int)$account['attivo'] ? 0 : 1 ?>"><button class="btn btn-sm btn-outline"><?= (int)$account['attivo'] ? 'Disattiva' : 'Riattiva' ?></button></form>
<form method="post" style="display:inline" onsubmit="return confirm('Revocare solo l’accesso a quest’area?')"><input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>"><input type="hidden" name="action" value="revoke"><input type="hidden" name="user_id" value="<?= (int)$account['id'] ?>"><button class="btn btn-sm btn-red">Revoca</button></form><?php else: ?><span>Può essere assegnato</span><?php endif; ?></td></tr><?php endforeach; ?>
</tbody></table></div><p><a href="<?= STAFF_URL ?>/index.php">Torna alla selezione area</a></p></div></main></body></html>
