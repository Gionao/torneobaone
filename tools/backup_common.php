<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/config.php';
require_once dirname(__DIR__) . '/photo/includes/media_storage.php';

const BACKUP_RETENTION_DAYS = 5;

function backup_directory(): string
{
    $directory = getenv('BACKUP_DIR') ?: 'C:\\laragon\\backups\\torneobaone';
    if (!preg_match('/^(?:[A-Za-z]:[\\\\\/]|\\\\\\\\)/', $directory)) {
        throw new RuntimeException('BACKUP_DIR deve essere un percorso assoluto.');
    }
    $projectRoot = realpath(dirname(__DIR__));
    if ($projectRoot === false) {
        throw new RuntimeException('Impossibile risolvere la cartella del progetto.');
    }

    $ancestor = $directory;
    $missing = [];
    while (!is_dir($ancestor)) {
        $parent = dirname($ancestor);
        if ($parent === $ancestor) {
            throw new RuntimeException('Percorso di backup non valido: ' . $directory);
        }
        array_unshift($missing, basename($ancestor));
        $ancestor = $parent;
    }
    $resolvedCandidate = realpath($ancestor);
    if ($resolvedCandidate === false) {
        throw new RuntimeException('Impossibile risolvere il percorso di backup.');
    }
    foreach ($missing as $segment) {
        $resolvedCandidate .= DIRECTORY_SEPARATOR . $segment;
    }

    $prefix = rtrim($projectRoot, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
    if (strcasecmp($resolvedCandidate, $projectRoot) === 0
        || strncasecmp($resolvedCandidate, $prefix, strlen($prefix)) === 0) {
        throw new RuntimeException('La cartella backup deve restare fuori dalla document root del progetto.');
    }

    if (!is_dir($directory) && !mkdir($directory, 0770, true) && !is_dir($directory)) {
        throw new RuntimeException('Impossibile creare la cartella dei backup: ' . $directory);
    }
    $realDirectory = realpath($directory);
    if ($realDirectory === false) {
        throw new RuntimeException('Impossibile risolvere il percorso dei backup.');
    }
    return $realDirectory;
}

function backup_acquire_lock()
{
    $lockPath = backup_directory() . DIRECTORY_SEPARATOR . '.torneobaone-backup.lock';
    $handle = fopen($lockPath, 'c');
    if ($handle === false) {
        throw new RuntimeException('Impossibile creare il lock per backup/ripristino.');
    }
    if (!flock($handle, LOCK_EX | LOCK_NB)) {
        fclose($handle);
        throw new RuntimeException('È già in corso un backup o un ripristino.');
    }
    return $handle;
}

function backup_release_lock($handle): void
{
    if (is_resource($handle)) {
        flock($handle, LOCK_UN);
        fclose($handle);
    }
}

function backup_mysql_defaults_file(): string
{
    foreach ([DB_HOST, DB_USER, DB_PASS] as $value) {
        if (str_contains($value, "\n") || str_contains($value, "\r") || str_contains($value, "\0")) {
            throw new RuntimeException('La configurazione del database contiene caratteri non supportati.');
        }
    }

    $escape = static fn(string $value): string => '"' . str_replace(
        ['\\', '"'],
        ['\\\\', '\\"'],
        $value
    ) . '"';
    $contents = "[client]\n"
        . 'host=' . $escape(DB_HOST) . "\n"
        . 'user=' . $escape(DB_USER) . "\n"
        . 'password=' . $escape(DB_PASS) . "\n"
        . "default-character-set=utf8mb4\n";

    $path = tempnam(sys_get_temp_dir(), 'torneobaone-mysql-');
    if ($path === false) {
        throw new RuntimeException('Impossibile creare il file temporaneo per il client MySQL.');
    }
    if (file_put_contents($path, $contents, LOCK_EX) === false) {
        @unlink($path);
        throw new RuntimeException('Impossibile creare il file temporaneo per il client MySQL.');
    }
    return $path;
}

function backup_mysql_binary(string $environmentVariable, string $binaryName): string
{
    $configured = getenv($environmentVariable);
    if ($configured !== false && trim($configured) !== '') {
        return trim($configured);
    }

    $laragonBinaries = glob('C:\\laragon\\bin\\mysql\\*\\bin\\' . $binaryName . '.exe') ?: [];
    if (count($laragonBinaries) === 1) {
        return $laragonBinaries[0];
    }
    if (count($laragonBinaries) > 1) {
        throw new RuntimeException(
            'Trovate più versioni di ' . $binaryName . '. Impostare ' . $environmentVariable . '.'
        );
    }
    return $binaryName;
}

/**
 * Runs a MySQL command without exposing the password in its command line.
 *
 * @param list<string> $arguments
 */
function backup_run_mysql(string $binary, array $arguments, string $defaultsFile, ?string $inputPath, ?string $outputPath): void
{
    $errorPath = tempnam(sys_get_temp_dir(), 'torneobaone-mysql-error-');
    if ($errorPath === false) {
        throw new RuntimeException('Impossibile creare il file temporaneo degli errori MySQL.');
    }

    $descriptors = [
        0 => $inputPath !== null ? ['file', $inputPath, 'rb'] : ['file', 'NUL', 'r'],
        1 => $outputPath !== null ? ['file', $outputPath, 'wb'] : ['file', 'NUL', 'w'],
        2 => ['file', $errorPath, 'wb'],
    ];
    $command = array_merge([$binary, '--defaults-extra-file=' . $defaultsFile], $arguments);
    try {
        $process = proc_open($command, $descriptors, $pipes, null, null, ['bypass_shell' => true]);
        if (!is_resource($process)) {
            throw new RuntimeException('Impossibile avviare il client MySQL. Verificare MYSQLDUMP_PATH o MYSQL_PATH.');
        }

        $status = proc_close($process);
        if ($status !== 0) {
            $details = trim((string) file_get_contents($errorPath));
            throw new RuntimeException(
                'Il client MySQL è terminato con codice ' . $status
                . ($details !== '' ? ': ' . $details : '.')
            );
        }
    } finally {
        if (is_file($errorPath) && !@unlink($errorPath)) {
            error_log('Impossibile rimuovere il file temporaneo MySQL: ' . $errorPath);
        }
    }
}

function backup_storage_root(): string
{
    $root = media_storage_root(false);
    $realRoot = realpath($root);
    if ($realRoot === false || !is_dir($realRoot)) {
        throw new RuntimeException('La cartella dello storage media configurata non esiste.');
    }
    if (is_link($root)) {
        throw new RuntimeException('Lo storage media non può essere un collegamento simbolico durante il backup.');
    }
    $projectRoot = realpath(dirname(__DIR__));
    if ($projectRoot === false) {
        throw new RuntimeException('Impossibile risolvere la cartella del progetto.');
    }
    $rootPrefix = rtrim($realRoot, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
    if (dirname($realRoot) === $realRoot
        || strcasecmp($realRoot, $projectRoot) === 0
        || strncasecmp($projectRoot, $rootPrefix, strlen($rootPrefix)) === 0) {
        throw new RuntimeException('Lo storage media non può coincidere con o contenere la cartella del progetto.');
    }
    return $realRoot;
}

function backup_paths_overlap(string $first, string $second): bool
{
    $first = rtrim($first, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
    $second = rtrim($second, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
    return strncasecmp($first, $second, strlen($first)) === 0
        || strncasecmp($second, $first, strlen($second)) === 0;
}

function backup_create(): string
{
    if (!class_exists(ZipArchive::class)) {
        throw new RuntimeException('L’estensione PHP zip è necessaria per creare i backup.');
    }

    $destination = backup_directory();
    $storageRoot = backup_storage_root();
    if (backup_paths_overlap($destination, $storageRoot)) {
        throw new RuntimeException('La destinazione backup non può sovrapporsi allo storage media.');
    }
    $backupPath = $destination . DIRECTORY_SEPARATOR
        . 'torneobaone_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.zip';
    $partialPath = $backupPath . '.partial';
    $sqlPath = tempnam(sys_get_temp_dir(), 'torneobaone-database-');
    if ($sqlPath === false) {
        throw new RuntimeException('Impossibile creare il dump temporaneo del database.');
    }

    $defaultsPath = null;
    $archive = null;
    try {
        $defaultsPath = backup_mysql_defaults_file();
        $dumpBinary = backup_mysql_binary('MYSQLDUMP_PATH', 'mysqldump');
        backup_run_mysql(
            $dumpBinary,
            [
                '--quick',
                '--single-transaction',
                '--routines',
                '--triggers',
                '--events',
                '--hex-blob',
                '--add-drop-database',
                '--set-gtid-purged=OFF',
                '--default-character-set=utf8mb4',
                '--databases',
                DB_NAME,
            ],
            $defaultsPath,
            null,
            $sqlPath
        );

        $archive = new ZipArchive();
        if ($archive->open($partialPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Impossibile creare il file ZIP del backup.');
        }
        if (!$archive->addFile($sqlPath, 'database.sql')) {
            throw new RuntimeException('Impossibile aggiungere il dump del database al backup.');
        }
        if (!$archive->addFromString('manifest.json', json_encode([
            'format_version' => 1,
            'database' => DB_NAME,
            'created_at' => date(DATE_ATOM),
            'media_prefix' => 'media/',
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR))) {
            throw new RuntimeException('Impossibile aggiungere il manifest al backup.');
        }

        $rootPrefix = rtrim($storageRoot, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($storageRoot, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST
        );
        foreach ($iterator as $entry) {
            if ($entry->isLink()) {
                continue;
            }
            $fullPath = $entry->getPathname();
            $realPath = $entry->getRealPath();
            if ($realPath === false || strncasecmp($realPath, $rootPrefix, strlen($rootPrefix)) !== 0) {
                throw new RuntimeException('Percorso media fuori dalla cartella storage: ' . $fullPath);
            }
            $relative = str_replace('\\', '/', substr($fullPath, strlen($rootPrefix)));
            if ($entry->isDir()) {
                if (!$archive->addEmptyDir('media/' . rtrim($relative, '/') . '/')) {
                    throw new RuntimeException('Impossibile aggiungere una cartella media al backup: ' . $relative);
                }
            } elseif ($entry->isFile() && !$archive->addFile($fullPath, 'media/' . $relative)) {
                throw new RuntimeException('Impossibile aggiungere un file media al backup: ' . $relative);
            }
        }

        if (!$archive->close()) {
            $archive = null;
            throw new RuntimeException('Errore durante la finalizzazione del backup ZIP.');
        }
        $archive = null;
        if (!rename($partialPath, $backupPath)) {
            throw new RuntimeException('Impossibile pubblicare il file di backup completato.');
        }

        backup_prune_old_files($destination);
        return $backupPath;
    } finally {
        if ($archive instanceof ZipArchive) {
            $archive->close();
        }
        foreach ([$sqlPath, $defaultsPath, $partialPath] as $temporaryPath) {
            if (is_string($temporaryPath) && is_file($temporaryPath) && !@unlink($temporaryPath)) {
                error_log('Impossibile rimuovere un file temporaneo del backup: ' . $temporaryPath);
            }
        }
    }
}

function backup_prune_old_files(string $directory): void
{
    $cutoff = time() - BACKUP_RETENTION_DAYS * 86400;
    foreach (glob($directory . DIRECTORY_SEPARATOR . 'torneobaone_*.zip') ?: [] as $path) {
        $modified = filemtime($path);
        if ($modified === false) {
            throw new RuntimeException('Impossibile verificare l’età del backup: ' . $path);
        }
        if ($modified < $cutoff && !unlink($path)) {
            throw new RuntimeException('Impossibile eliminare il backup scaduto: ' . $path);
        }
    }
}

function backup_remove_tree(string $path): void
{
    if (is_link($path) || is_file($path)) {
        if (!unlink($path)) {
            throw new RuntimeException('Impossibile rimuovere il file: ' . $path);
        }
        return;
    }
    if (!is_dir($path)) {
        return;
    }

    $iterator = new FilesystemIterator($path, FilesystemIterator::SKIP_DOTS);
    foreach ($iterator as $entry) {
        backup_remove_tree($entry->getPathname());
    }
    if (!rmdir($path)) {
        throw new RuntimeException('Impossibile rimuovere la cartella: ' . $path);
    }
}
