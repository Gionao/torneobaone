<?php
/**
 * One-time, local CLI bootstrap for an existing staff account.
 * Usage: php database/bootstrap_super_admin.php <existing-username>
 */
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

if ($argc !== 2 || trim($argv[1]) === '') {
    fwrite(STDERR, "Uso: php database/bootstrap_super_admin.php <username-esistente>\n");
    exit(2);
}

require_once dirname(__DIR__) . '/includes/db.php';

$pdo = db();
$pdo->beginTransaction();

try {
    // Serializes bootstrap/promotion against the last-Super-Admin guards.
    $guard = $pdo->query('SELECT id FROM staff_security_guard WHERE id = 1 FOR UPDATE')->fetchColumn();
    if ((int) $guard !== 1) {
        throw new RuntimeException('Riga di protezione Super Admin non disponibile.');
    }

    $stmt = $pdo->prepare('SELECT id, attivo, is_super_admin FROM admin WHERE username = ? FOR UPDATE');
    $stmt->execute([$argv[1]]);
    $account = $stmt->fetch();
    if (!$account) {
        throw new RuntimeException('Account staff non trovato. Creare prima l’account con una procedura sicura.');
    }
    if ((int) $account['attivo'] !== 1) {
        throw new RuntimeException('L’account selezionato non è attivo.');
    }
    if ((int) $account['is_super_admin'] === 1) {
        throw new RuntimeException('L’account è già Super Admin.');
    }

    $activeSuperAdmins = (int) $pdo->query(
        'SELECT COUNT(*) FROM admin WHERE attivo = 1 AND is_super_admin = 1'
    )->fetchColumn();
    if ($activeSuperAdmins !== 0) {
        throw new RuntimeException('Esiste già un Super Admin attivo; bootstrap consentito solo quando non ne esistono.');
    }

    $update = $pdo->prepare('UPDATE admin SET is_super_admin = 1 WHERE id = ? AND attivo = 1 AND is_super_admin = 0');
    $update->execute([(int) $account['id']]);
    if ($update->rowCount() !== 1) {
        throw new RuntimeException('Promozione non applicata; ripetere la verifica manualmente.');
    }

    $pdo->commit();
    fwrite(STDOUT, "Account promosso a Super Admin. Conservare la password esistente e verificare l’accesso in modo sicuro.\n");
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    fwrite(STDERR, 'Bootstrap non completato: ' . $e->getMessage() . "\n");
    exit(1);
}
