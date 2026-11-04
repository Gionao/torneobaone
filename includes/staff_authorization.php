<?php
// Central policy and delegated staff-assignment management.
declare(strict_types=1);

require_once __DIR__ . '/staff_auth.php';

const STAFF_SECTOR_ROLES = [
    'torneo' => ['amministratore', 'segreteria'],
    'partite' => ['gestore', 'telecronista'],
    'pos' => ['amministratore', 'operatore', 'bar', 'cucina'],
    'archivio' => ['amministratore', 'editor', 'uploader'],
];

const STAFF_SECTOR_HIERARCHY = [
    'torneo' => ['amministratore' => [], 'segreteria' => ['amministratore']],
    'partite' => ['gestore' => [], 'telecronista' => ['gestore']],
    'pos' => ['amministratore' => [], 'operatore' => ['amministratore'], 'bar' => ['amministratore'], 'cucina' => ['amministratore']],
    'archivio' => ['amministratore' => [], 'editor' => ['amministratore'], 'uploader' => ['amministratore']],
];

function current_staff_user(): ?array
{
    return staff_current_user(true);
}

function is_super_admin(): bool
{
    return staff_is_super_admin();
}

function get_sector_role(int $userId, string $sector): ?string
{
    if (!isset(STAFF_SECTOR_ROLES[$sector]) || $userId < 1) {
        return null;
    }
    $current = staff_current_user(true);
    if ($current === null) {
        return null;
    }
    if ($current['id'] === $userId) {
        return staff_role($sector) ?: null;
    }
    try {
        $stmt = db()->prepare('SELECT a.ruolo FROM staff_assegnazioni a INNER JOIN admin u ON u.id=a.id_admin AND u.attivo=1 INNER JOIN staff_ruoli_area r ON r.area=a.area AND r.ruolo=a.ruolo WHERE a.id_admin=? AND a.area=? AND a.attivo=1 LIMIT 1');
        $stmt->execute([$userId, $sector]);
        $role = $stmt->fetchColumn();
        return is_string($role) && in_array($role, STAFF_SECTOR_ROLES[$sector], true) ? $role : null;
    } catch (Throwable $e) {
        return null;
    }
}

function can_access_sector(string $sector): bool
{
    return isset(STAFF_SECTOR_ROLES[$sector]) && staff_current_user(true) !== null
        && (is_super_admin() || get_sector_role(staff_id(), $sector) !== null);
}

function has_sector_role(string $sector, array $allowedRoles): bool
{
    if (!isset(STAFF_SECTOR_ROLES[$sector])) {
        return false;
    }
    if (is_super_admin() && staff_current_user(true) !== null) {
        return true;
    }
    $allowed = array_values(array_intersect($allowedRoles, STAFF_SECTOR_ROLES[$sector]));
    return $allowed !== [] && in_array(get_sector_role(staff_id(), $sector), $allowed, true);
}

function can_manage_sector_users(string $sector): bool
{
    return isset(STAFF_SECTOR_HIERARCHY[$sector])
        && staff_current_user(true) !== null
        && (is_super_admin() || get_sector_role(staff_id(), $sector) === array_key_first(STAFF_SECTOR_HIERARCHY[$sector]));
}

function can_assign_sector_role(string $sector, string $targetRole, ?int $targetUserId = null): bool
{
    if (!isset(STAFF_SECTOR_HIERARCHY[$sector]) || !in_array($targetRole, STAFF_SECTOR_ROLES[$sector], true)) {
        return false;
    }
    $current = staff_current_user(true);
    if ($current === null) {
        return false;
    }
    if (is_super_admin()) {
        return true;
    }
    $adminRole = array_key_first(STAFF_SECTOR_HIERARCHY[$sector]);
    if (get_sector_role($current['id'], $sector) !== $adminRole || $targetRole === $adminRole) {
        return false;
    }
    // A sector manager cannot alter their own administrator assignment.
    return $targetUserId === null || $targetUserId !== $current['id'];
}

function require_sector_access(string $sector, array $allowedRoles = []): void
{
    if (!isset(STAFF_SECTOR_ROLES[$sector])) {
        http_response_code(403);
        exit('Accesso non autorizzato.');
    }
    staff_require_authenticated();
    $allowedRoles = $allowedRoles === [] ? STAFF_SECTOR_ROLES[$sector] : $allowedRoles;
    if (!has_sector_role($sector, $allowedRoles)) {
        http_response_code(403);
        exit('Accesso non autorizzato.');
    }
}

function staff_validate_sector_role(string $sector, string $role): bool
{
    return isset(STAFF_SECTOR_ROLES[$sector]) && in_array($role, STAFF_SECTOR_ROLES[$sector], true);
}

function staff_assign_sector_role(int $targetUserId, string $sector, string $role, ?string $station = null): void
{
    if (!can_assign_sector_role($sector, $role, $targetUserId)) {
        throw new RuntimeException('Non sei autorizzato a modificare questa assegnazione.');
    }
    if (!staff_validate_sector_role($sector, $role)) {
        throw new RuntimeException('Ruolo non valido.');
    }
    if ($sector !== 'pos' || !in_array($role, ['operatore'], true)) {
        $station = null;
    }
    $pdo = db();
    $pdo->beginTransaction();
    try {
        $target = $pdo->prepare('SELECT id, is_super_admin FROM admin WHERE id=? AND attivo=1 FOR UPDATE');
        $target->execute([$targetUserId]);
        $account = $target->fetch();
        if (!$account || ((int) $account['is_super_admin'] === 1 && !is_super_admin())) {
            throw new RuntimeException('Account non disponibile.');
        }
        $old = $pdo->prepare('SELECT ruolo FROM staff_assegnazioni WHERE id_admin=? AND area=? FOR UPDATE');
        $old->execute([$targetUserId, $sector]);
        $oldRole = $old->fetchColumn();
        $adminRole = array_key_first(STAFF_SECTOR_HIERARCHY[$sector]);
        if (!is_super_admin() && $oldRole === $adminRole) {
            throw new RuntimeException('Solo il Super Admin può modificare l’amministratore di settore.');
        }
        if ($oldRole !== false && !can_assign_sector_role($sector, $role, $targetUserId)) {
            throw new RuntimeException('Non sei autorizzato a modificare questa assegnazione.');
        }
        $upsert = $pdo->prepare('INSERT INTO staff_assegnazioni (id_admin,area,ruolo,station_name,attivo) VALUES (?,?,?,?,1) ON DUPLICATE KEY UPDATE ruolo=VALUES(ruolo), station_name=VALUES(station_name), attivo=1');
        $upsert->execute([$targetUserId, $sector, $role, $station]);
        $pdo->commit();
        staff_auth_forget_cache();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}

function staff_revoke_sector_role(int $targetUserId, string $sector): void
{
    if (!isset(STAFF_SECTOR_ROLES[$sector]) || !can_manage_sector_users($sector)
        || (!is_super_admin() && $targetUserId === staff_id())) {
        throw new RuntimeException('Non sei autorizzato a revocare questa assegnazione.');
    }
    $existing = db()->prepare('SELECT ruolo FROM staff_assegnazioni WHERE id_admin=? AND area=?');
    $existing->execute([$targetUserId, $sector]);
    $oldRole = $existing->fetchColumn();
    $targetSuper = db()->prepare('SELECT is_super_admin FROM admin WHERE id=?');
    $targetSuper->execute([$targetUserId]);
    if (!is_super_admin() && (int) $targetSuper->fetchColumn() === 1) {
        throw new RuntimeException('Non puoi modificare un account Super Admin.');
    }
    if (!is_string($oldRole) || (!is_super_admin() && $oldRole === array_key_first(STAFF_SECTOR_HIERARCHY[$sector]))) {
        throw new RuntimeException('Solo il Super Admin può modificare l’amministratore di settore.');
    }
    $stmt = db()->prepare('UPDATE staff_assegnazioni SET attivo=0 WHERE id_admin=? AND area=?');
    $stmt->execute([$targetUserId, $sector]);
    staff_auth_forget_cache();
}

function staff_set_sector_assignment_active(int $targetUserId, string $sector, bool $active): void
{
    if (!isset(STAFF_SECTOR_ROLES[$sector]) || !can_manage_sector_users($sector)
        || (!is_super_admin() && $targetUserId === staff_id())) {
        throw new RuntimeException('Non sei autorizzato a modificare questa assegnazione.');
    }
    $pdo = db();
    $stmt = $pdo->prepare('SELECT ruolo FROM staff_assegnazioni WHERE id_admin=? AND area=?');
    $stmt->execute([$targetUserId, $sector]);
    $role = $stmt->fetchColumn();
    $targetSuper = $pdo->prepare('SELECT is_super_admin FROM admin WHERE id=?');
    $targetSuper->execute([$targetUserId]);
    if (!is_super_admin() && (int) $targetSuper->fetchColumn() === 1) {
        throw new RuntimeException('Non puoi modificare un account Super Admin.');
    }
    if (!is_string($role) || (!is_super_admin() && $role === array_key_first(STAFF_SECTOR_HIERARCHY[$sector]))
        || !can_assign_sector_role($sector, $role, $targetUserId)) {
        throw new RuntimeException('Assegnazione non disponibile.');
    }
    $update = $pdo->prepare('UPDATE staff_assegnazioni SET attivo=? WHERE id_admin=? AND area=?');
    $update->execute([$active ? 1 : 0, $targetUserId, $sector]);
    staff_auth_forget_cache();
}

function staff_create_account(string $username, string $password, string $sector, string $role, ?string $station = null): int
{
    if (!preg_match('/\A[A-Za-z0-9._-]{3,60}\z/', $username)
        || strlen($password) < 12 || strlen($password) > 72
        || preg_match('/[a-z]/', $password) !== 1 || preg_match('/[A-Z]/', $password) !== 1
        || preg_match('/[0-9]/', $password) !== 1
        || !can_assign_sector_role($sector, $role)) {
        throw new RuntimeException('Dati account o ruolo non validi.');
    }
    $pdo = db();
    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare('INSERT INTO admin (username,password_hash,attivo,is_super_admin,must_change_password) VALUES (?, ?, 1, 0, 1)');
        $stmt->execute([$username, password_hash($password, PASSWORD_DEFAULT)]);
        $id = (int) $pdo->lastInsertId();
        $assignment = $pdo->prepare('INSERT INTO staff_assegnazioni (id_admin,area,ruolo,station_name) VALUES (?,?,?,?)');
        $assignment->execute([$id, $sector, $role, $sector === 'pos' && $role === 'operatore' ? $station : null]);
        $pdo->commit();
        return $id;
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}

function staff_generate_temporary_password(int $length = 20): string
{
    $lower = 'abcdefghijkmnopqrstuvwxyz';
    $upper = 'ABCDEFGHJKLMNPQRSTUVWXYZ';
    $digits = '23456789';
    $alphabet = $lower . $upper . $digits;
    $size = max(16, min(48, $length));
    $characters = [
        $lower[random_int(0, strlen($lower) - 1)],
        $upper[random_int(0, strlen($upper) - 1)],
        $digits[random_int(0, strlen($digits) - 1)],
    ];
    while (count($characters) < $size) {
        $characters[] = $alphabet[random_int(0, strlen($alphabet) - 1)];
    }
    for ($i = count($characters) - 1; $i > 0; --$i) {
        $j = random_int(0, $i);
        [$characters[$i], $characters[$j]] = [$characters[$j], $characters[$i]];
    }
    return implode('', $characters);
}

/** Creates a minimum-scope account and returns its temporary secret exactly once to the caller. */
function staff_create_account_with_temporary_password(string $username, string $sector, string $role, ?string $station = null): array
{
    $password = staff_generate_temporary_password();
    $id = staff_create_account($username, $password, $sector, $role, $station);
    return ['id' => $id, 'temporary_password' => $password];
}

function staff_update_username(int $targetUserId, string $username): void
{
    if (!is_super_admin() || $targetUserId < 1 || !preg_match('/\A[A-Za-z0-9._-]{3,60}\z/', $username)) {
        throw new RuntimeException('Username non valido o operazione non autorizzata.');
    }
    $stmt = db()->prepare('UPDATE admin SET username=? WHERE id=?');
    $stmt->execute([$username, $targetUserId]);
    if ($stmt->rowCount() !== 1) {
        throw new RuntimeException('Account non trovato o username invariato.');
    }
}

/** Scoped lookup: returns only matching username/id and this sector's role. */
function staff_find_sector_accounts(string $sector, string $query, int $limit = 10): array
{
    if (!isset(STAFF_SECTOR_ROLES[$sector]) || !can_manage_sector_users($sector)) {
        return [];
    }
    $query = trim($query);
    if (strlen($query) < 3) {
        return [];
    }
    $limit = max(1, min(20, $limit));
    $stmt = db()->prepare('SELECT u.id,u.username,a.ruolo,a.station_name FROM admin u LEFT JOIN staff_assegnazioni a ON a.id_admin=u.id AND a.area=? AND a.attivo=1 WHERE u.attivo=1 AND u.is_super_admin=0 AND u.username LIKE ? ORDER BY u.username LIMIT ' . $limit);
    $stmt->execute([$sector, '%' . addcslashes($query, '%_\\') . '%']);
    return $stmt->fetchAll();
}

function staff_set_global_status(int $targetUserId, bool $active): void
{
    if (!is_super_admin() || $targetUserId < 1) {
        throw new RuntimeException('Operazione riservata al Super Admin.');
    }
    $pdo = db();
    $pdo->beginTransaction();
    try {
        $pdo->query('SELECT id FROM staff_security_guard WHERE id=1 FOR UPDATE');
        $stmt = $pdo->prepare('UPDATE admin SET attivo=? WHERE id=?');
        $stmt->execute([$active ? 1 : 0, $targetUserId]);
        if ($stmt->rowCount() !== 1) {
            throw new RuntimeException('Account non trovato.');
        }
        $pdo->commit();
        staff_auth_forget_cache();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}

function staff_set_super_admin(int $targetUserId, bool $enabled): void
{
    if (!is_super_admin() || $targetUserId < 1) {
        throw new RuntimeException('Operazione riservata al Super Admin.');
    }
    $pdo = db();
    $pdo->beginTransaction();
    try {
        $pdo->query('SELECT id FROM staff_security_guard WHERE id=1 FOR UPDATE');
        $stmt = $pdo->prepare('UPDATE admin SET is_super_admin=? WHERE id=? AND attivo=1');
        $stmt->execute([$enabled ? 1 : 0, $targetUserId]);
        if ($stmt->rowCount() !== 1) {
            throw new RuntimeException('Account attivo non trovato.');
        }
        $pdo->commit();
        staff_auth_forget_cache();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}

function staff_create_super_admin(string $username, string $password): int
{
    if (!is_super_admin() || !preg_match('/\A[A-Za-z0-9._-]{3,60}\z/', $username)
        || strlen($password) < 12 || strlen($password) > 72
        || preg_match('/[a-z]/', $password) !== 1 || preg_match('/[A-Z]/', $password) !== 1
        || preg_match('/[0-9]/', $password) !== 1) {
        throw new RuntimeException('Dati account non validi.');
    }
    $pdo = db();
    $pdo->beginTransaction();
    try {
        $pdo->query('SELECT id FROM staff_security_guard WHERE id=1 FOR UPDATE');
        $stmt = $pdo->prepare('INSERT INTO admin (username,password_hash,attivo,is_super_admin,must_change_password) VALUES (?, ?, 1, 1, 1)');
        $stmt->execute([$username, password_hash($password, PASSWORD_DEFAULT)]);
        $id = (int) $pdo->lastInsertId();
        $pdo->commit();
        return $id;
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}
