<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli' || getenv('TB_AUTH_TEST_MODE') !== '1') {
    fwrite(STDERR, "Esecuzione consentita solo in CLI con TB_AUTH_TEST_MODE=1.\n");
    exit(2);
}
$database = (string) getenv('TB_AUTH_TEST_DB');
if (!in_array($database, ['tb_auth_p07_clean_20261104', 'tb_auth_p07_legacy_20261104'], true)) {
    fwrite(STDERR, "TB_AUTH_TEST_DB deve indicare un database Phase 07 disposable autorizzato.\n");
    exit(2);
}
putenv('DB_HOST=' . (getenv('TB_AUTH_TEST_DB_HOST') ?: '127.0.0.1'));
putenv('DB_NAME=' . $database);
putenv('DB_USER=' . (getenv('TB_AUTH_TEST_DB_USER') ?: 'root'));
putenv('DB_PASS=' . (getenv('TB_AUTH_TEST_DB_PASSWORD') ?: ''));
putenv('APP_ENV=local');
$_SERVER['HTTP_HOST'] = 'torneobaone.test';
require_once dirname(__DIR__, 2) . '/includes/auth.php';

$pdo = db();
$passed = 0;
$failed = 0;
$check = static function (bool $result, string $label) use (&$passed, &$failed): void {
    if ($result) {
        ++$passed;
        fwrite(STDOUT, "PASS $label\n");
    } else {
        ++$failed;
        fwrite(STDOUT, "FAIL $label\n");
    }
};
$suffix = bin2hex(random_bytes(5));
$ids = [];
$sectorRoles = STAFF_SECTOR_ROLES;
$sectors = array_keys($sectorRoles);
$roleUsers = [];

try {
    $insert = $pdo->prepare('INSERT INTO admin (username,password_hash,attivo,is_super_admin,must_change_password) VALUES (?, ?, ?, 0, 0)');
    foreach ($sectorRoles as $sector => $roles) {
        foreach ($roles as $role) {
            $username = 'p07_' . $sector . '_' . $role . '_' . $suffix;
            $insert->execute([$username, password_hash('Temporary123Aa', PASSWORD_DEFAULT), 1]);
            $id = (int) $pdo->lastInsertId();
            $ids[] = $id;
            $roleUsers[$sector . ':' . $role] = $id;
            $pdo->prepare('INSERT INTO staff_assegnazioni (id_admin,area,ruolo,attivo) VALUES (?,?,?,1)')
                ->execute([$id, $sector, $role]);
        }
    }
    $insert->execute(['p07_norole_' . $suffix, password_hash('Temporary123Aa', PASSWORD_DEFAULT), 1]);
    $noRoleId = (int) $pdo->lastInsertId();
    $ids[] = $noRoleId;
    $insert->execute(['p07_disabled_' . $suffix, password_hash('Temporary123Aa', PASSWORD_DEFAULT), 0]);
    $disabledId = (int) $pdo->lastInsertId();
    $ids[] = $disabledId;
    $pdo->prepare("INSERT INTO staff_assegnazioni (id_admin,area,ruolo,attivo) VALUES (?, 'pos', 'operatore', 1)")->execute([$disabledId]);

    session_start_secure();
    $setPrincipal = static function (int $id): void {
        $_SESSION['staff_id'] = $id;
        $_SESSION['staff_nonce'] = bin2hex(random_bytes(32));
        $_SESSION['staff_authenticated_at'] = time();
        $_SESSION['staff_last_activity'] = time();
        $_SESSION['staff_last_regen'] = time();
        staff_auth_forget_cache();
    };

    foreach ($roleUsers as $key => $id) {
        [$assignedSector, $assignedRole] = explode(':', $key, 2);
        $setPrincipal($id);
        foreach ($sectors as $sector) {
            $expected = $sector === $assignedSector;
            $check(can_access_sector($sector) === $expected, "$key sector access $sector");
            $check(has_sector_role($sector, STAFF_SECTOR_ROLES[$sector]) === $expected, "$key role guard $sector");
            $canManage = $expected && $assignedRole === array_key_first(STAFF_SECTOR_HIERARCHY[$sector]);
            $check(can_manage_sector_users($sector) === $canManage, "$key delegated management $sector");
            $subordinate = array_values(array_filter(STAFF_SECTOR_ROLES[$sector], static fn(string $r): bool => $r !== array_key_first(STAFF_SECTOR_HIERARCHY[$sector])));
            $canDelegate = $canManage && $subordinate !== [];
            $check(can_assign_sector_role($sector, $subordinate[0] ?? array_key_first(STAFF_SECTOR_HIERARCHY[$sector]), $noRoleId) === $canDelegate,
                "$key assignment policy $sector");
            $check(!can_assign_sector_role($sector, array_key_first(STAFF_SECTOR_HIERARCHY[$sector]), $noRoleId),
                "$key cannot grant highest role $sector");
        }
    }

    $posAdminId = $roleUsers['pos:amministratore'];
    $setPrincipal($posAdminId);
    $duplicateRejected = false;
    try {
        $pdo->prepare("INSERT INTO staff_assegnazioni (id_admin,area,ruolo) VALUES (?, 'pos', 'bar')")->execute([$posAdminId]);
    } catch (PDOException $e) {
        $duplicateRejected = true;
    }
    $check($duplicateRejected, 'database rejects duplicate account-sector assignment');
    $invalidRoleRejected = false;
    try {
        $pdo->prepare("INSERT INTO staff_assegnazioni (id_admin,area,ruolo) VALUES (?, 'pos', 'telecronista')")->execute([$noRoleId]);
    } catch (PDOException $e) {
        $invalidRoleRejected = true;
    }
    $check($invalidRoleRejected, 'database rejects invalid sector-role pair');
    $check(!can_manage_sector_users('archivio') && !can_assign_sector_role('archivio', 'uploader', $noRoleId),
        'POS administrator cannot manage archive roles');
    $globalStatusRejected = false;
    try {
        staff_set_global_status($noRoleId, false);
    } catch (RuntimeException $e) {
        $globalStatusRejected = true;
    }
    $check($globalStatusRejected, 'sector administrator cannot change global account status');
    $check(staff_find_sector_accounts('pos', "' OR 1=1") === [], 'injection-shaped staff search input remains a literal scoped search');
    $matchManagerId = $roleUsers['partite:gestore'];
    $setPrincipal($matchManagerId);
    $check(!can_manage_sector_users('torneo') && !can_assign_sector_role('torneo', 'segreteria', $noRoleId),
        'match manager cannot manage tournament staff');
    $setPrincipal($roleUsers['archivio:amministratore']);
    $check(!can_manage_sector_users('pos') && !can_assign_sector_role('pos', 'bar', $noRoleId),
        'archive administrator cannot manage POS roles');

    $setPrincipal($noRoleId);
    foreach ($sectors as $sector) {
        $check(!can_access_sector($sector) && !can_manage_sector_users($sector), "no-role denied $sector");
    }
    $setPrincipal($disabledId);
    $check(staff_current_user(true) === null, 'disabled identity invalidates active session');
    foreach ($sectors as $sector) {
        $check(!can_access_sector($sector), "disabled identity denied $sector");
    }

    $saUsername = 'p07_super_' . $suffix;
    $insert->execute([$saUsername, password_hash('Temporary123Aa', PASSWORD_DEFAULT), 1]);
    $superId = (int) $pdo->lastInsertId();
    $ids[] = $superId;
    $pdo->prepare('UPDATE admin SET is_super_admin=1 WHERE id=?')->execute([$superId]);
    $setPrincipal($superId);
    foreach ($sectors as $sector) {
        $check(can_access_sector($sector) && has_sector_role($sector, STAFF_SECTOR_ROLES[$sector]), "Super Admin override $sector");
        $check(can_manage_sector_users($sector), "Super Admin delegation $sector");
        $check(can_assign_sector_role($sector, array_key_first(STAFF_SECTOR_HIERARCHY[$sector]), $noRoleId), "Super Admin can grant highest role $sector");
    }
    $check(!can_assign_sector_role('invalid', 'admin', $noRoleId), 'unknown sector fails closed');
    $lastAdminProtected = false;
    try {
        $pdo->prepare('UPDATE admin SET is_super_admin=0 WHERE id=?')->execute([$superId]);
    } catch (PDOException $e) {
        $lastAdminProtected = true;
    }
    $check($lastAdminProtected, 'last active Super Admin cannot be demoted');
} finally {
    if (session_status() === PHP_SESSION_ACTIVE) {
        staff_logout();
    }
    $cleanupIds = array_values(array_filter($ids, static fn(int $id): bool => !isset($superId) || $id !== $superId));
    if ($cleanupIds !== []) {
        $marks = implode(',', array_fill(0, count($cleanupIds), '?'));
        $pdo->prepare("DELETE FROM staff_assegnazioni WHERE id_admin IN ($marks)")->execute($cleanupIds);
        $pdo->prepare("DELETE FROM admin WHERE id IN ($marks)")->execute($cleanupIds);
    }
}
if (isset($superId)) {
    fwrite(STDOUT, "Disposable DB retains its Super Admin fixture until that database is dropped.\n");
}

fwrite(STDOUT, "\nAuthorization matrix: $passed passed, $failed failed.\n");
exit($failed === 0 ? 0 : 1);
