<?php
// includes/db.php
// ============================================================
//  Connessione PDO — Singleton
//  Uso: $pdo = db();
// ============================================================

declare(strict_types=1);

require_once __DIR__ . '/config.php';

function db(): PDO
{
    static $pdo = null;

    if ($pdo === null) {
        $dsn = sprintf(
            'mysql:host=%s;dbname=%s;charset=%s',
            DB_HOST,
            DB_NAME,
            DB_CHARSET
        );

        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,   // prepared statements nativi
        ];

        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            if (DEBUG_MODE) {
                // Dettaglio completo in locale — mai in produzione
                http_response_code(503);
                die('<pre style="background:#1a1a2e;color:#e8c76a;padding:1.5rem;font-size:.9rem">'
                    . '<strong>❌ Errore connessione DB</strong>' . "\n\n"
                    . htmlspecialchars($e->getMessage()) . "\n\n"
                    . '<strong>Controlla:</strong>' . "\n"
                    . '• XAMPP: Apache e MySQL sono avviati nel Control Panel?' . "\n"
                    . '• DB_NAME "' . DB_NAME . '" esiste in phpMyAdmin?' . "\n"
                    . '• Credenziali in includes/config.php corrette?'
                    . '</pre>');
            }
            http_response_code(503);
            die('Servizio temporaneamente non disponibile. Riprova tra poco.');
        }
    }

    return $pdo;
}

function table_has_column(PDO $pdo, string $table, string $column): bool
{
    $safeTable = preg_replace('/[^A-Za-z0-9_]/', '', $table);
    if ($safeTable === '') {
        return false;
    }

    try {
        $stmt = $pdo->prepare('SHOW COLUMNS FROM `' . $safeTable . '`');
        $stmt->execute();
        $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
        return in_array($column, $columns, true);
    } catch (Throwable $e) {
        return false;
    }
}