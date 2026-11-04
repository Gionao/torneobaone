<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli' || getenv('TB_AUTH_TEST_MODE') !== '1') {
    fwrite(STDERR, "Esecuzione consentita solo in CLI con TB_AUTH_TEST_MODE=1.\n");
    exit(2);
}

$testDatabase = (string) getenv('TB_AUTH_TEST_DB');
if (!in_array($testDatabase, ['tb_auth_migration_test', 'my_torneobaone_auth_test'], true)) {
    fwrite(STDERR, "TB_AUTH_TEST_DB deve indicare un database disposable esplicitamente autorizzato.\n");
    exit(2);
}

putenv('DB_HOST=' . (getenv('TB_AUTH_TEST_DB_HOST') ?: '127.0.0.1'));
putenv('DB_NAME=' . $testDatabase);
putenv('DB_USER=' . (getenv('TB_AUTH_TEST_DB_USER') ?: 'root'));
putenv('DB_PASS=' . (getenv('TB_AUTH_TEST_DB_PASSWORD') ?: ''));
putenv('APP_ENV=local');
$_SERVER['HTTP_HOST'] = 'torneobaone.test';

require_once dirname(__DIR__, 2) . '/includes/auth.php';
require_once dirname(__DIR__, 2) . '/includes/staff_authorization.php';
require_once dirname(__DIR__, 2) . '/includes/auth_capitano.php';
require_once dirname(__DIR__, 2) . '/photo/includes/media_auth.php';

$passed = 0;
$failed = 0;
$check = static function (bool $condition, string $name) use (&$passed, &$failed): void {
    if ($condition) {
        ++$passed;
        fwrite(STDOUT, "OK  $name\n");
    } else {
        ++$failed;
        fwrite(STDOUT, "FAIL $name\n");
    }
};

$pdo = db();
$assignmentActiveColumn = $pdo->query("SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='staff_assegnazioni' AND COLUMN_NAME='attivo'")->fetchColumn();
if (!(int) $assignmentActiveColumn) {
    $pdo->exec('ALTER TABLE staff_assegnazioni ADD COLUMN attivo TINYINT(1) NOT NULL DEFAULT 1');
}
$suffix = bin2hex(random_bytes(5));
$username = 'test_' . $suffix;
$disabledUsername = 'disabled_' . $suffix;
$targetUsername = 'target_' . $suffix;
$password = 'TemporaryPass123';
$hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 4]);

try {
    $insert = $pdo->prepare('INSERT INTO admin (username,password_hash,attivo,is_super_admin,must_change_password) VALUES (?, ?, 1, 0, 0)');
    $insert->execute([$username, $hash]);
    $staffId = (int) $pdo->lastInsertId();
    $insert->execute([$disabledUsername, $hash]);
    $disabledId = (int) $pdo->lastInsertId();
    $insert->execute([$targetUsername, $hash]);
    $targetId = (int) $pdo->lastInsertId();

    $roles = [
        [$staffId, 'torneo', 'amministratore'],
        [$staffId, 'pos', 'operatore'],
        [$staffId, 'archivio', 'uploader'],
        [$disabledId, 'pos', 'bar'],
        [$targetId, 'torneo', 'segreteria'],
    ];
    $assignment = $pdo->prepare('INSERT INTO staff_assegnazioni (id_admin,area,ruolo,station_name) VALUES (?, ?, ?, ?)');
    foreach ($roles as [$accountId, $area, $role]) {
        $assignment->execute([$accountId, $area, $role, $role === 'operatore' ? 'Cassa test' : null]);
    }
    $pdo->prepare('UPDATE admin SET attivo=0 WHERE id=?')->execute([$disabledId]);

    session_start_secure();
    $check(staff_current_user(true) === null, 'nessun accesso staff senza autenticazione');
    $sessionBeforeLogin = session_id();
    $check(staff_login($username, $password), 'login valido con hash password legacy compatibile');
    $check(session_id() !== $sessionBeforeLogin, 'ID sessione rigenerato dopo autenticazione');
    $current = staff_current_user(true);
    $check(($current['id'] ?? 0) === $staffId, 'sessione centrale autenticata');
    $check(staff_role('torneo') === 'amministratore' && staff_role('pos') === 'operatore'
        && staff_role('archivio') === 'uploader', 'stesse credenziali e ruoli in piu settori');
    $check(staff_station() === 'Cassa test', 'postazione POS letta dall assegnazione corrente');
    $check(can_access_sector('torneo') && !can_access_sector('partite'), 'accesso settore negato fuori assegnazione');
    $check(!can_assign_sector_role('pos', 'amministratore', $targetId), 'amministratore settore non nominabile da subordinate');
    $check(!can_assign_sector_role('pos', 'operatore', $staffId), 'auto-modifica assegnazione non consentita al non amministratore');
    foreach (STAFF_SECTOR_ROLES as $sector => $sectorRoles) {
        foreach ($sectorRoles as $sectorRole) {
            $check(staff_validate_sector_role($sector, $sectorRole), 'ruolo allowlist ' . $sector . '/' . $sectorRole);
        }
    }

    // Exercise the delegated boundary with this session acting as POS administrator.
    $pdo->prepare("UPDATE staff_assegnazioni SET ruolo='amministratore' WHERE id_admin=? AND area='pos'")->execute([$staffId]);
    staff_auth_forget_cache();
    $check(can_manage_sector_users('pos') && can_assign_sector_role('pos', 'bar', $targetId), 'amministratore POS puo delegare ruolo subordinato');
    $check(!can_assign_sector_role('pos', 'amministratore', $targetId), 'amministratore POS non puo promuovere amministratore');
    staff_assign_sector_role($targetId, 'pos', 'bar');
    $check(get_sector_role($targetId, 'pos') === 'bar', 'assegnazione di settore scritta centralmente');
    staff_set_sector_assignment_active($targetId, 'pos', false);
    $check(get_sector_role($targetId, 'pos') === null, 'disattivazione assegnazione revoca subito i permessi');
    staff_set_sector_assignment_active($targetId, 'pos', true);
    $check(get_sector_role($targetId, 'pos') === 'bar', 'riattivazione assegnazione consente accesso corrente');
    staff_revoke_sector_role($targetId, 'pos');
    $check(get_sector_role($targetId, 'pos') === null && get_sector_role($targetId, 'torneo') === 'segreteria',
        'revoca settore non elimina gli altri accessi condivisi');
    $managerSession = $_SESSION;
    $_SESSION['staff_id'] = $targetId;
    $_SESSION['staff_nonce'] = bin2hex(random_bytes(32));
    $_SESSION['staff_authenticated_at'] = time();
    $_SESSION['staff_last_activity'] = time();
    $_SESSION['staff_last_regen'] = time();
    staff_auth_forget_cache();
    $check(!can_access_sector('pos') && can_access_sector('torneo'),
        'revoca da altra sessione nega subito settore e conserva gli altri permessi');
    $_SESSION = $managerSession;
    staff_auth_forget_cache();
    $lookupRows = staff_find_sector_accounts('pos', substr($targetUsername, 0, 8));
    $check($lookupRows !== [] && !array_key_exists('password_hash', $lookupRows[0]) && !array_key_exists('other_assignments', $lookupRows[0]),
        'ricerca settore espone solo dati minimi del settore richiesto');
    staff_assign_sector_role($targetId, 'pos', 'operatore', 'Cassa aggiornata');
    $check(staff_role('pos') === 'amministratore' && get_sector_role($targetId, 'pos') === 'operatore', 'modifica ruolo non altera la sessione amministratore');
    $targetStation = $pdo->prepare("SELECT station_name FROM staff_assegnazioni WHERE id_admin=? AND area='pos'");
    $targetStation->execute([$targetId]);
    $check($targetStation->fetchColumn() === 'Cassa aggiornata', 'modifica postazione POS assegnazione-locale');
    $managerSession = $_SESSION;
    $_SESSION['staff_id'] = $targetId;
    $_SESSION['staff_nonce'] = bin2hex(random_bytes(32));
    $_SESSION['staff_authenticated_at'] = time();
    $_SESSION['staff_last_activity'] = time();
    $_SESSION['staff_last_regen'] = time();
    staff_auth_forget_cache();
    $pdo->prepare('UPDATE admin SET attivo=0 WHERE id=?')->execute([$targetId]);
    $check(staff_current_user(true) === null, 'disattivazione globale revoca subito sessione attiva multi-settore');
    $pdo->prepare('UPDATE admin SET attivo=1 WHERE id=?')->execute([$targetId]);
    $_SESSION = $managerSession;
    staff_auth_forget_cache();
    $newUsername = 'created_' . $suffix;
    $createdAccount = staff_create_account_with_temporary_password($newUsername, 'pos', 'bar');
    $createdCheck = $pdo->prepare('SELECT must_change_password,password_hash FROM admin WHERE id=?');
    $createdCheck->execute([$createdAccount['id']]);
    $createdRow = $createdCheck->fetch();
    $check(strlen($createdAccount['temporary_password']) >= 16
        && preg_match('/[a-z]/', $createdAccount['temporary_password']) === 1
        && preg_match('/[A-Z]/', $createdAccount['temporary_password']) === 1
        && preg_match('/[0-9]/', $createdAccount['temporary_password']) === 1
        && (int)$createdRow['must_change_password'] === 1
        && password_verify($createdAccount['temporary_password'], $createdRow['password_hash']), 'creazione genera password temporanea e impone sostituzione');
    $hasMediaTables = (int) $pdo->query("SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME IN ('media_edizioni','media_accesso_edizione')")->fetchColumn() === 2;
    if ($hasMediaTables) {
        $editionInsert = $pdo->prepare('INSERT INTO media_edizioni (nome,anno,attiva,creata_da) VALUES (?, ?, 1, ?)');
        $editionInsert->execute(['Test ACL A ' . $suffix, 2091, $staffId]);
        $editionA = (int) $pdo->lastInsertId();
        $editionInsert->execute(['Test ACL B ' . $suffix, 2092, $staffId]);
        $editionB = (int) $pdo->lastInsertId();
        $pdo->prepare('INSERT INTO media_accesso_edizione (id_admin,id_edizione,creato_da) VALUES (?, ?, ?)')->execute([$staffId, $editionA, $staffId]);
        $check(media_puo_vedere_edizione($editionA) && !media_puo_vedere_edizione($editionB), 'uploader limitato alle edizioni assegnate');
    } else {
        fwrite(STDOUT, "SKIP archive edition check (media schema non installato in questo disposable DB)\n");
    }

    staff_logout();
    $check(staff_current_user(true) === null, 'logout invalida la sessione staff');

    session_start_secure();
    $check(staff_login($username, $password), 'login condiviso disponibile dalla seconda entry');
    $pdo->prepare('UPDATE admin SET attivo=0 WHERE id=?')->execute([$staffId]);
    staff_auth_forget_cache();
    $check(staff_current_user(true) === null, 'disattivazione revoca una sessione gia attiva');
    $pdo->prepare('UPDATE admin SET attivo=1 WHERE id=?')->execute([$staffId]);
    staff_logout();

    session_start_secure();
    $check(!staff_login($username, 'password errata'), 'login fallito con password errata');
    $check(!staff_login($disabledUsername, $password), 'account disattivato rifiutato');

    $pdo->prepare('UPDATE admin SET must_change_password=1 WHERE id=?')->execute([$staffId]);
    $check(staff_login($username, $password), 'password temporanea accettata al login');
    $check(staff_password_change_required(), 'cambio password obbligatorio impostato');
    $newPassword = 'NuovaPasswordSicura123';
    $_POST['csrf_token'] = 'token-csrf-errato';
    $check(!staff_change_password($newPassword, $newPassword), 'CSRF non valido respinto');
    $_POST['csrf_token'] = staff_csrf_token();
    $check(staff_change_password($newPassword, $newPassword), 'cambio password con CSRF e policy valido');
    $saved = $pdo->prepare('SELECT password_hash,must_change_password FROM admin WHERE id=?');
    $saved->execute([$staffId]);
    $savedAccount = $saved->fetch();
    $check(password_verify($newPassword, $savedAccount['password_hash']) && (int) $savedAccount['must_change_password'] === 0,
        'hash aggiornato e obbligo rimosso solo al completamento');

    $_SESSION['capitano_id'] = 987654;
    $_SESSION['capitano_auth'] = 'sessione-capitano-test';
    $_SESSION['staff_last_activity'] = time() - STAFF_IDLE_TIMEOUT - 1;
    staff_auth_forget_cache();
    $check(staff_current_user(true) === null, 'scadenza per inattivita');
    $check(($_SESSION['capitano_id'] ?? 0) === 987654, 'scadenza staff non elimina lo stato capitano');

    $_SESSION['staff_id'] = $staffId;
    $_SESSION['staff_nonce'] = bin2hex(random_bytes(32));
    $_SESSION['staff_authenticated_at'] = time() - STAFF_ABSOLUTE_TIMEOUT - 1;
    $_SESSION['staff_last_activity'] = time();
    staff_auth_forget_cache();
    $check(staff_current_user(true) === null, 'scadenza assoluta della sessione');
    $check(($_SESSION['capitano_auth'] ?? '') === 'sessione-capitano-test', 'namespace capitano isolato');

    $_SERVER['HTTP_ORIGIN'] = 'https://attacker.example';
    $check(!staff_login_origin_valid(), 'origine esterna rifiutata');
    $_SERVER['HTTP_ORIGIN'] = 'http://torneobaone.test';
    $check(staff_login_origin_valid(), 'origine stessa host consentita');
    $check(!preg_match('~\A/photo/admin/(?!/)(?!.*(?:^|/)\.\.(?:/|$))[A-Za-z0-9_./-]*(?:\?[A-Za-z0-9_=&%-]*)?\z~', '//attacker.example'),
        'percorso di ritorno esterno non valido');

    $check(session_name() === STAFF_SESSION_NAME, 'cookie sessione staff distinto dal cookie capitano predefinito');
} finally {
    if (session_status() === PHP_SESSION_ACTIVE) {
        staff_logout();
    }
    $pdo->prepare('DELETE FROM staff_assegnazioni WHERE id_admin IN (?, ?, ?, ?)')->execute([$staffId ?? 0, $disabledId ?? 0, $targetId ?? 0, $createdAccount['id'] ?? 0]);
    if (!empty($staffId) && isset($hasMediaTables) && $hasMediaTables) {
        $pdo->prepare('DELETE FROM media_accesso_edizione WHERE id_admin=?')->execute([$staffId]);
        $pdo->prepare('DELETE FROM media_edizioni WHERE creata_da=? AND nome LIKE ?')->execute([$staffId, 'Test ACL %']);
    }
    $pdo->prepare('DELETE FROM admin WHERE id IN (?, ?, ?, ?)')->execute([$staffId ?? 0, $disabledId ?? 0, $targetId ?? 0, $createdAccount['id'] ?? 0]);
}

fwrite(STDOUT, "\nRisultato: {$passed} superati, {$failed} falliti.\n");
exit($failed === 0 ? 0 : 1);
