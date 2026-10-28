<?php
// includes/media_zip.php
// ============================================================
//  Generazione di archivi ZIP in background.
//  I file vengono scritti su disco a blocchi, senza caricarli
//  in memoria. Ogni job può produrre più parti.
// ============================================================

declare(strict_types=1);

require_once __DIR__ . '/media_config.php';
require_once __DIR__ . '/media_storage.php';
require_once __DIR__ . '/media_repository.php';

function media_zip_disponibile(): bool
{
    return class_exists('ZipArchive');
}

function media_zip_etichetta_ambito(string $ambito, int $id): string
{
    return match ($ambito) {
        'edizione' => 'edizione-' . $id,
        'giornata' => 'giornata-' . $id,
        'album'    => 'album-' . $id,
        default    => 'archivio-' . $id,
    };
}

function media_zip_nome_sicuro(string $nome): string
{
    $nome = str_replace(['\\', '/', "\0"], '-', $nome);
    $nome = preg_replace('/[\x00-\x1F\x7F]/u', '', $nome) ?? '';
    $nome = preg_replace('/\s+/u', ' ', $nome) ?? '';
    $nome = trim($nome, " .-");
    return $nome !== '' ? mb_substr($nome, 0, 180, 'UTF-8') : 'file';
}

/**
 * Processa un job ZIP. Restituisce i dati aggiornati del job.
 *
 * @throws RuntimeException
 */
function media_zip_processa_job(array $job): array
{
    if (!media_zip_disponibile()) {
        throw new RuntimeException('Estensione ZIP non disponibile sul server.');
    }

    $idJob = (int) $job['id'];
    if (!media_zip_job_prendi_in_carico($idJob)) {
        throw new RuntimeException('Job già preso in carico o non più disponibile.');
    }

    $partiCreate = [];
    try {
        if (!empty($job['file_ids'])) {
            $ids = json_decode((string) $job['file_ids'], true);
            $file = is_array($ids) ? media_file_per_ids($ids) : [];
        } else {
            if (($job['ambito'] ?? '') === 'selezione') {
                throw new RuntimeException('Selezione di file non valida.');
            }
            $file = media_file_per_ambito((string) $job['ambito'], (int) $job['id_ambito']);
        }
        if ($file === []) {
            throw new RuntimeException('Nessun file da includere nell\'archivio.');
        }
        if (count($file) > media_zip_max_file()) {
            throw new RuntimeException('Troppi file per un singolo archivio (limite: ' . media_zip_max_file() . ').');
        }

        $totale = 0;
        foreach ($file as $f) {
            $totale += (int) $f['dimensione'];
        }
        if ($totale > media_zip_max_bytes()) {
            throw new RuntimeException('Archivio troppo grande (limite: ' . media_bytes_leggibili(media_zip_max_bytes()) . ').');
        }

        $limiteParte = media_zip_parte_bytes();
        $parti = [];
        $indiceParte = 0;
        $dimensioneParte = 0;
        $zip = null;
        $percorsoParte = '';
        $usati = [];

        $apriParte = function () use (&$zip, &$percorsoParte, &$indiceParte, &$dimensioneParte, $job): void {
            $indiceParte++;
            $percorsoParte = 'zip_tmp/' . $job['uuid'] . '_parte' . $indiceParte . '.zip';
            $assoluto = media_storage_path($percorsoParte);
            if (is_file($assoluto)) {
                @unlink($assoluto);
            }
            $zip = new ZipArchive();
            if ($zip->open($assoluto, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
                throw new RuntimeException('Impossibile creare il file ZIP.');
            }
            $dimensioneParte = 0;
        };

        $chiudiParte = function () use (&$zip, &$percorsoParte, &$parti, &$dimensioneParte, &$indiceParte): void {
            if (!$zip instanceof ZipArchive) {
                return;
            }
            if ($zip->close() !== true) {
                throw new RuntimeException('Impossibile finalizzare il file ZIP.');
            }
            $zip = null;
            $assoluto = media_storage_path($percorsoParte);
            $dimensione = is_file($assoluto) ? (int) filesize($assoluto) : 0;
            if ($dimensione <= 0) {
                throw new RuntimeException('File ZIP vuoto o non creato.');
            }
            $parti[] = [
                'percorso'   => $percorsoParte,
                'nome'       => 'archivio-parte-' . $indiceParte . '.zip',
                'dimensione' => $dimensione,
            ];
            $dimensioneParte = 0;
        };

        $apriParte();
        $aggiunti = 0;

        foreach ($file as $f) {
            try {
                $assoluto = media_storage_path((string) $f['percorso']);
            } catch (Throwable $e) {
                continue;
            }
            if (!is_file($assoluto)) {
                continue;
            }

            $dimensioneFile = (int) filesize($assoluto);
            if ($dimensioneParte > 0 && $dimensioneParte + $dimensioneFile > $limiteParte) {
                $chiudiParte();
                $apriParte();
            }

            $base = media_zip_nome_sicuro((string) $f['nome_originale']);
            $cartella = media_zip_nome_sicuro((string) $f['anno_edizione'])
                . '/' . media_zip_nome_sicuro((string) $f['nome_giornata'])
                . '/' . media_zip_nome_sicuro((string) $f['nome_album']);
            $voce = $cartella . '/' . $base;
            $n = 1;
            while (isset($usati[$voce])) {
                $ext = pathinfo($base, PATHINFO_EXTENSION);
                $stem = pathinfo($base, PATHINFO_FILENAME);
                $voce = $cartella . '/' . $stem . ' (' . $n . ')' . ($ext !== '' ? '.' . $ext : '');
                $n++;
            }
            $usati[$voce] = true;

            if (!$zip->addFile($assoluto, $voce)) {
                throw new RuntimeException('Impossibile aggiungere un file all\'archivio.');
            }
            if (method_exists($zip, 'setCompressionName')) {
                // Foto e video sono già compressi: evitare dispendio di CPU.
                $zip->setCompressionName($voce, ZipArchive::CM_STORE);
            }
            $dimensioneParte += $dimensioneFile;
            $aggiunti++;
        }

        if ($aggiunti === 0) {
            // Chiude e scarta un'eventuale parte vuota.
            if ($zip instanceof ZipArchive) {
                @$zip->close();
                $zip = null;
            }
            media_storage_delete($percorsoParte);
            throw new RuntimeException('Nessun file valido da includere nell\'archivio.');
        }
        $chiudiParte();

        if ($parti === []) {
            throw new RuntimeException('Nessun file valido da includere nell\'archivio.');
        }

        $dimensioneTotale = 0;
        foreach ($parti as $p) {
            $dimensioneTotale += (int) $p['dimensione'];
            $partiCreate[] = (string) $p['percorso'];
        }

        media_zip_job_completa(
            $idJob,
            $parti,
            count($file),
            $dimensioneTotale,
            media_zip_scadenza_ore()
        );
        media_log('zip_generato', 'media_zip_job', $idJob, [
            'ambito' => $job['ambito'],
            'id'     => (int) $job['id_ambito'],
            'parti'  => count($parti),
            'file'   => count($file),
        ]);

        return media_zip_job($idJob) ?? [];
    } catch (Throwable $e) {
        foreach ($partiCreate as $p) {
            media_storage_delete($p);
        }
        // Rimuove eventuali parti parziali create prima dell'errore.
        try {
            $prefisso = media_storage_dir('zip_tmp') . DIRECTORY_SEPARATOR . (string) $job['uuid'] . '_parte';
            foreach (glob($prefisso . '*.zip') ?: [] as $residuo) {
                @unlink($residuo);
            }
        } catch (Throwable $e2) {
            // ignore
        }
        media_zip_job_errore($idJob, $e->getMessage());
        media_log('zip_errore', 'media_zip_job', $idJob, ['errore' => $e->getMessage()]);
        throw $e;
    }
}

/** Processa i job in attesa (usato dal cron). */
function media_zip_processa_in_attesa(int $max = 3): array
{
    $esiti = [];
    foreach (media_zip_job_in_attesa($max) as $job) {
        try {
            media_zip_processa_job($job);
            $esiti[] = ['id' => (int) $job['id'], 'ok' => true];
        } catch (Throwable $e) {
            $esiti[] = ['id' => (int) $job['id'], 'ok' => false, 'errore' => $e->getMessage()];
        }
    }
    return $esiti;
}

/**
 * Riporta in attesa i job rimasti bloccati "in_corso" (es. processo
 * interrotto) per consentire un nuovo tentativo.
 */
function media_zip_ripristina_bloccati(): int
{
    $n = 0;
    foreach (media_zip_job_in_corso_bloccati(2) as $job) {
        media_zip_elimina_parti($job);
        db()->prepare('
            UPDATE media_zip_job SET stato = \'in_attesa\', started_at = NULL, parti = NULL
            WHERE id = ? AND stato = \'in_corso\'
        ')->execute([(int) $job['id']]);
        media_log('zip_ripristinato', 'media_zip_job', (int) $job['id']);
        $n++;
    }
    return $n;
}

/** Elimina gli ZIP scaduti e aggiorna lo stato. */
function media_zip_pulisci_scaduti(): int
{
    $n = 0;
    foreach (media_zip_job_scaduti() as $job) {
        $parti = json_decode((string) ($job['parti'] ?? '[]'), true);
        if (is_array($parti)) {
            foreach ($parti as $p) {
                if (!empty($p['percorso'])) {
                    media_storage_delete((string) $p['percorso']);
                }
            }
        }
        db()->prepare('UPDATE media_zip_job SET stato = \'scaduto\', parti = NULL WHERE id = ?')
            ->execute([(int) $job['id']]);
        $n++;
    }
    return $n;
}

/** Elimina fisicamente le parti di un job. */
function media_zip_elimina_parti(array $job): void
{
    $parti = json_decode((string) ($job['parti'] ?? '[]'), true);
    if (is_array($parti)) {
        foreach ($parti as $p) {
            if (!empty($p['percorso'])) {
                media_storage_delete((string) $p['percorso']);
            }
        }
    }
}
