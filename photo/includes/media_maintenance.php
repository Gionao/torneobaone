<?php
// includes/media_maintenance.php
// ============================================================
//  Pulizie periodiche: upload abbandonati, cestino, ZIP scaduti.
//  Possono essere eseguite dal cron oppure manualmente dall'admin.
// ============================================================

declare(strict_types=1);

require_once __DIR__ . '/media_config.php';
require_once __DIR__ . '/media_storage.php';
require_once __DIR__ . '/media_repository.php';
require_once __DIR__ . '/media_zip.php';

/** @return array{upload:int, cestino:int, zip:int, zip_bloccati:int, quarantena:int} */
function media_pulizie_periodiche(): array
{
    return [
        'upload'        => media_pulizia_upload(),
        'cestino'       => media_pulizia_cestino(),
        'zip'           => media_zip_pulisci_scaduti(),
        'zip_bloccati'  => media_zip_ripristina_bloccati(),
        'quarantena'    => media_pulizia_quarantena(),
    ];
}

function media_pulizia_upload(int $ore = 48): int
{
    $n = 0;
    foreach (media_upload_vecchi($ore) as $upload) {
        media_storage_delete((string) $upload['percorso_tmp']);
        media_upload_annulla((int) $upload['id'], 'annullato', 'Caricamento scaduto.');
        media_log('upload_scaduto', 'media_upload', (int) $upload['id'], ['uuid' => $upload['uuid']]);
        $n++;
    }
    return $n;
}

function media_pulizia_cestino(): int
{
    $n = 0;
    foreach (media_file_eliminati_scaduti(media_retention_days()) as $file) {
        if (media_elimina_definitivamente((int) $file['id'])) {
            $n++;
        }
    }
    return $n;
}

function media_pulizia_quarantena(int $giorni = 30): int
{
    try {
        $dir = media_storage_dir('quarantena');
    } catch (Throwable $e) {
        return 0;
    }
    $limite = time() - max(1, $giorni) * 86400;
    $n = 0;
    foreach (glob($dir . DIRECTORY_SEPARATOR . '*') ?: [] as $path) {
        if (is_file($path) && filemtime($path) !== false && filemtime($path) < $limite) {
            if (@unlink($path)) {
                $n++;
            }
        }
    }
    return $n;
}

/** Elimina definitivamente un singolo file dal cestino. */
function media_elimina_definitivamente(int $id): bool
{
    $record = media_file($id);
    if (!$record || empty($record['eliminato_il'])) {
        return false;
    }

    $fileDaRipristinare = [];
    try {
        foreach (array_filter([
            (string) $record['percorso'],
            (string) ($record['miniatura'] ?? ''),
        ]) as $percorso) {
            $quarantinato = media_storage_quarantina($percorso);
            if ($quarantinato !== null) {
                $fileDaRipristinare[] = [
                    'quarantinato' => $quarantinato,
                    'originale' => $percorso,
                ];
            }
        }
    } catch (Throwable $e) {
        media_ripristina_file_quarantinati($fileDaRipristinare, $e);
        throw $e;
    }

    try {
        if (!media_file_elimina_record($id)) {
            media_ripristina_file_quarantinati($fileDaRipristinare);
            return false;
        }
    } catch (Throwable $e) {
        media_ripristina_file_quarantinati($fileDaRipristinare, $e);
        throw $e;
    }

    foreach ($fileDaRipristinare as $file) {
        $path = media_storage_path($file['quarantinato']);
        if (is_file($path) && !@unlink($path)) {
            media_log('file_quarantena_non_eliminata', 'media_file', $id, [
                'uuid' => $record['uuid'],
                'percorso' => $file['quarantinato'],
            ]);
        }
    }

    media_log('file_eliminato_definitivamente', 'media_file', $id, [
        'uuid'   => $record['uuid'],
        'sha256' => $record['sha256'],
    ]);
    return true;
}

/**
 * Ripristina i file se la cancellazione del record database non riesce.
 *
 * @param list<array{quarantinato:string, originale:string}> $file
 */
function media_ripristina_file_quarantinati(array $file, ?Throwable $causa = null): void
{
    $nonRipristinati = [];
    foreach (array_reverse($file) as $elemento) {
        if (!media_storage_ripristina_quarantena($elemento['quarantinato'], $elemento['originale'])) {
            $nonRipristinati[] = $elemento['originale'];
        }
    }

    if ($nonRipristinati !== []) {
        throw new RuntimeException(
            'Eliminazione interrotta; non è stato possibile ripristinare tutti i file: '
            . implode(', ', $nonRipristinati),
            0,
            $causa
        );
    }
}
