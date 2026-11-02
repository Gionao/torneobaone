<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/backup_common.php';

function restore_archive_path(array $argv): string
{
    foreach (array_slice($argv, 1) as $argument) {
        if (str_starts_with($argument, '--archive=')) {
            return substr($argument, strlen('--archive='));
        }
    }
    throw new InvalidArgumentException('Specificare --archive=percorso-del-backup.zip.');
}

function restore_extract_media(ZipArchive $archive, string $stagingPath): void
{
    $seen = [];
    for ($index = 0; $index < $archive->numFiles; $index++) {
        $name = $archive->getNameIndex($index);
        if ($name === false) {
            throw new RuntimeException('Archivio ZIP non leggibile.');
        }
        if ($name === 'database.sql' || $name === 'manifest.json') {
            continue;
        }
        if (!str_starts_with($name, 'media/')) {
            throw new RuntimeException('Voce non riconosciuta nel backup: ' . $name);
        }

        $relative = substr($name, strlen('media/'));
        $normalized = str_replace('\\', '/', $relative);
        $segments = explode('/', rtrim($normalized, '/'));
        if ($relative === ''
            || str_starts_with($normalized, '/')
            || str_contains($normalized, "\0")
            || str_contains($normalized, ':')
            || in_array('', $segments, true)
            || in_array('.', $segments, true)
            || in_array('..', $segments, true)) {
            throw new RuntimeException('Percorso non valido nell’archivio: ' . $name);
        }
        $seenKey = strtolower($normalized);
        if (isset($seen[$seenKey])) {
            throw new RuntimeException('Percorso duplicato nell’archivio: ' . $name);
        }
        $seen[$seenKey] = true;
        foreach ($segments as $segment) {
            if (!preg_match('/^[A-Za-z0-9._-]+$/D', $segment)
                || str_ends_with($segment, '.')
                || str_ends_with($segment, ' ')
                || preg_match('/^(CON|PRN|AUX|NUL|COM[1-9]|LPT[1-9])(?:\\..*)?$/i', $segment)) {
                throw new RuntimeException('Nome non supportato nell’archivio: ' . $name);
            }
        }

        $target = $stagingPath . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $normalized);
        if (str_ends_with($name, '/')) {
            if (!is_dir($target) && !mkdir($target, 0770, true) && !is_dir($target)) {
                throw new RuntimeException('Impossibile creare una cartella media di ripristino.');
            }
            continue;
        }

        $parent = dirname($target);
        if (!is_dir($parent) && !mkdir($parent, 0770, true) && !is_dir($parent)) {
            throw new RuntimeException('Impossibile creare una cartella media di ripristino.');
        }
        $source = $archive->getStream($name);
        $destination = fopen($target, 'wb');
        if ($source === false || $destination === false) {
            if (is_resource($source)) {
                fclose($source);
            }
            if (is_resource($destination)) {
                fclose($destination);
            }
            throw new RuntimeException('Impossibile estrarre il file media: ' . $name);
        }
        $copied = stream_copy_to_stream($source, $destination);
        fclose($source);
        fclose($destination);
        if ($copied === false) {
            throw new RuntimeException('Errore durante l’estrazione del file media: ' . $name);
        }
    }
}

function restore_extract_database(ZipArchive $archive): string
{
    $sqlStream = $archive->getStream('database.sql');
    if ($sqlStream === false) {
        throw new RuntimeException('Impossibile leggere il dump SQL dal backup.');
    }
    $sqlPath = tempnam(sys_get_temp_dir(), 'torneobaone-restore-sql-');
    if ($sqlPath === false) {
        fclose($sqlStream);
        throw new RuntimeException('Impossibile creare il file temporaneo di ripristino.');
    }
    try {
        $output = fopen($sqlPath, 'wb');
        if ($output === false) {
            throw new RuntimeException('Impossibile aprire il file temporaneo di ripristino.');
        }
        $copied = stream_copy_to_stream($sqlStream, $output);
        fclose($output);
        if ($copied === false) {
            throw new RuntimeException('Impossibile estrarre il dump SQL dal backup.');
        }
        return $sqlPath;
    } catch (Throwable $e) {
        if (is_file($sqlPath) && !@unlink($sqlPath)) {
            error_log('Impossibile rimuovere il dump SQL temporaneo: ' . $sqlPath);
        }
        throw $e;
    } finally {
        fclose($sqlStream);
    }
}

$sqlPath = null;
$archivePath = null;
$archive = null;
$stagingPath = null;
$defaultsPath = null;
$lock = null;
$exitCode = 0;

try {
    if (!in_array('--confirm-overwrite', $argv ?? [], true)) {
        throw new InvalidArgumentException(
            'Il ripristino sovrascrive i dati correnti. Ripetere con --confirm-overwrite.'
        );
    }
    $archivePath = restore_archive_path($argv ?? []);
    if (!is_file($archivePath) || !is_readable($archivePath)) {
        throw new RuntimeException('File di backup inesistente o non leggibile.');
    }
    if (!class_exists(ZipArchive::class)) {
        throw new RuntimeException('L’estensione PHP zip è necessaria per ripristinare i backup.');
    }
    $lock = backup_acquire_lock();

    $archive = new ZipArchive();
    if ($archive->open($archivePath) !== true) {
        throw new RuntimeException('Impossibile aprire l’archivio di backup.');
    }
    $manifestJson = $archive->getFromName('manifest.json');
    $manifest = is_string($manifestJson) ? json_decode($manifestJson, true, 512, JSON_THROW_ON_ERROR) : null;
    if (!is_array($manifest)
        || ($manifest['format_version'] ?? null) !== 1
        || ($manifest['database'] ?? null) !== DB_NAME
        || $archive->locateName('database.sql') === false) {
        throw new RuntimeException('Backup incompleto o incompatibile con il database configurato.');
    }
    $sqlPath = restore_extract_database($archive);

    $storageRoot = backup_storage_root();
    $backupRoot = backup_directory();
    if (backup_paths_overlap($backupRoot, $storageRoot)) {
        throw new RuntimeException('La destinazione backup non può sovrapporsi allo storage media.');
    }
    $storageParent = dirname($storageRoot);
    $stagingPath = $storageParent . DIRECTORY_SEPARATOR . '.media-restore-' . bin2hex(random_bytes(8));
    if (!mkdir($stagingPath, 0770, true)) {
        throw new RuntimeException('Impossibile creare lo staging per i file media.');
    }
    restore_extract_media($archive, $stagingPath);
    if ($archive->close() !== true) {
        throw new RuntimeException('Errore durante la chiusura del backup di ripristino.');
    }
    $archive = null;

    $defaultsPath = backup_mysql_defaults_file();
    $preRestoreBackup = backup_create();
    echo 'Backup di sicurezza pre-ripristino creato: ' . $preRestoreBackup . PHP_EOL;

    $mysqlBinary = backup_mysql_binary('MYSQL_PATH', 'mysql');
    backup_run_mysql(
        $mysqlBinary,
        ['--default-character-set=utf8mb4', '--binary-mode=1'],
        $defaultsPath,
        $sqlPath,
        null
    );

    $oldStoragePath = $storageRoot . '.pre-restore-' . bin2hex(random_bytes(6));
    if (!rename($storageRoot, $oldStoragePath)) {
        throw new RuntimeException('Database ripristinato, ma impossibile mettere in sicurezza lo storage corrente.');
    }
    if (!rename($stagingPath, $storageRoot)) {
        if (!rename($oldStoragePath, $storageRoot)) {
            throw new RuntimeException(
                'Ripristino file non riuscito; storage precedente conservato in ' . $oldStoragePath
            );
        }
        throw new RuntimeException('Database ripristinato, ma impossibile installare i file media del backup.');
    }
    $stagingPath = null;
    try {
        backup_remove_tree($oldStoragePath);
    } catch (Throwable $e) {
        fwrite(STDERR, 'Avviso: ripristino completato; storage precedente non rimosso: '
            . $oldStoragePath . ' (' . $e->getMessage() . ')' . PHP_EOL);
    }
    echo 'Ripristino completato per il database ' . DB_NAME . ' e lo storage media.' . PHP_EOL;
} catch (Throwable $e) {
    fwrite(STDERR, 'Errore ripristino: ' . $e->getMessage() . PHP_EOL);
    $exitCode = 1;
} finally {
    if ($archive instanceof ZipArchive) {
        $archive->close();
    }
    if (is_string($defaultsPath) && is_file($defaultsPath) && !@unlink($defaultsPath)) {
        error_log('Impossibile rimuovere il file temporaneo MySQL: ' . $defaultsPath);
    }
    if (is_string($sqlPath) && is_file($sqlPath) && !@unlink($sqlPath)) {
        error_log('Impossibile rimuovere il dump SQL temporaneo: ' . $sqlPath);
    }
    if (is_string($stagingPath) && is_dir($stagingPath)) {
        try {
            backup_remove_tree($stagingPath);
        } catch (Throwable $e) {
            error_log('Impossibile rimuovere lo staging del ripristino: ' . $e->getMessage());
        }
    }
    backup_release_lock($lock);
}
exit($exitCode);
