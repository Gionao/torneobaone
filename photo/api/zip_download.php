<?php
// photo/api/zip_download.php — Download di una parte ZIP generata
declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';

require_media_api_login();

if (!media_is_editor()) {
    http_response_code(403);
    exit('Accesso non consentito.');
}

$uuid = trim((string) ($_GET['uuid'] ?? ''));
$parte = filter_input(INPUT_GET, 'parte', FILTER_VALIDATE_INT);
$parte = $parte === false || $parte === null ? 0 : (int) $parte;
if (!media_uuid_valido($uuid) || $parte < 1) {
    http_response_code(404);
    exit('Archivio non trovato.');
}

$job = media_zip_job_per_uuid($uuid);
if (!$job || $job['stato'] !== 'completato') {
    http_response_code(404);
    exit('Archivio non disponibile.');
}
if ((int) $job['id_admin'] !== media_admin_id() && !media_is_admin()) {
    http_response_code(403);
    exit('Accesso non consentito.');
}

$parti = json_decode((string) ($job['parti'] ?? '[]'), true);
if (!is_array($parti) || !isset($parti[$parte - 1])) {
    http_response_code(404);
    exit('Parte dell\'archivio non trovata.');
}
$dati = $parti[$parte - 1];
if (!is_array($dati) || empty($dati['percorso'])) {
    http_response_code(404);
    exit('Parte dell\'archivio non trovata.');
}

try {
    $assoluto = media_storage_path((string) $dati['percorso']);
} catch (Throwable $e) {
    http_response_code(404);
    exit('Parte dell\'archivio non trovata.');
}
if (!is_file($assoluto)) {
    http_response_code(404);
    exit('Parte dell\'archivio non disponibile.');
}

@set_time_limit(0);
media_chiudi_sessione();

$nome = 'archivio-media-parte-' . $parte . '.zip';
media_log('zip_scaricato', 'media_zip_job', (int) $job['id'], [
    'uuid'  => $uuid,
    'parte' => $parte,
]);

header('Content-Type: application/zip');
header('Content-Length: ' . (int) filesize($assoluto));
header('Content-Disposition: attachment; filename="' . $nome . '"');
header('Cache-Control: private, no-store, max-age=0');
header('X-Content-Type-Options: nosniff');
header('Accept-Ranges: none');

$handle = fopen($assoluto, 'rb');
if ($handle === false) {
    http_response_code(500);
    exit;
}
while (!feof($handle)) {
    $pezzo = fread($handle, 262144);
    if ($pezzo === false) {
        break;
    }
    echo $pezzo;
    flush();
}
fclose($handle);
exit;
