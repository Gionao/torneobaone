<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/includes/registration_validation.php';

$checks = 0;
$assert = static function (bool $condition, string $message) use (&$checks): void {
    ++$checks;
    if (!$condition) {
        fwrite(STDERR, "FAIL: $message\n");
        exit(1);
    }
};
$at = new DateTimeImmutable('2026-11-04', new DateTimeZone('Europe/Rome'));
$assert(registration_age_on('2008-11-04', $at) === 18, 'età nel giorno del compleanno');
$assert(registration_age_on('2008-11-05', $at) === 17, 'età prima del compleanno');
$assert(registration_age_on('2010-11-04', $at) === 16, 'età minima esatta nel giorno del compleanno');
$assert(registration_is_adult_on('2008-11-04', $at), 'adulto esattamente al compimento dei 18 anni');
$assert(!registration_is_adult_on('2008-11-05', $at), 'minorenne il giorno prima dei 18 anni');
$assert(registration_age_on('2027-01-01', $at) === null, 'data futura respinta');
$assert(registration_normalize_email('  TEST+tag@example.invalid ') === 'test+tag@example.invalid', 'email normalizzata senza collassare alias');
$syntheticCf = 'ZZZZZZ00A00Z000A';
foreach (range('A', 'Z') as $checkCharacter) {
    $candidate = substr($syntheticCf, 0, 15) . $checkCharacter;
    if (registration_valid_codice_fiscale($candidate)) {
        $syntheticCf = $candidate;
        break;
    }
}
$assert(registration_valid_codice_fiscale($syntheticCf), 'checksum CF sintetico valido');
$wrongChecksum = substr($syntheticCf, 0, 15) . ($syntheticCf[15] === 'A' ? 'B' : 'A');
$assert(!registration_valid_codice_fiscale($wrongChecksum), 'checksum CF errato');
$assert(!registration_valid_codice_fiscale(substr($syntheticCf, 0, 15)), 'formato CF errato');
$minor = registration_validate_person([
    'nome' => 'Test', 'cognome' => 'Utente', 'email' => 'test@example.invalid', 'telefono' => '0000000000',
    'data_nascita' => '2010-11-05', 'codice_fiscale' => $syntheticCf, 'indirizzo' => 'Indirizzo sintetico',
], $at);
$assert(isset($minor['errors']['data_nascita']), 'età minore di 16 alla submission respinta');
$validSixteen = registration_validate_person([
    'nome' => 'Prova', 'cognome' => 'Sintetica', 'email' => 'player@example.invalid', 'telefono' => '0000000000',
    'data_nascita' => '2010-11-04', 'codice_fiscale' => $syntheticCf, 'indirizzo' => 'Indirizzo sintetico',
], $at);
$assert(!isset($validSixteen['errors']['data_nascita']), '16 anni esatti ammessi alla submission');
$assert(isset(registration_validate_person([], $at)['errors']['nome']), 'campo obbligatorio validato server-side');
fwrite(STDOUT, "Test validazione registrazione: $checks verifiche superate.\n");
