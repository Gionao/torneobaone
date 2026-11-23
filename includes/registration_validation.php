<?php
declare(strict_types=1);

/** Email comparison policy: trim and lowercase the entire address. Provider aliases remain distinct. */
function registration_normalize_email(string $email): string
{
    return strtolower(trim($email));
}

function registration_is_adult_on(string $birthDate, ?DateTimeImmutable $at = null): bool
{
    $birth = DateTimeImmutable::createFromFormat('!Y-m-d', $birthDate, new DateTimeZone('Europe/Rome'));
    $errors = DateTimeImmutable::getLastErrors();
    if (!$birth || ($errors !== false && ($errors['warning_count'] > 0 || $errors['error_count'] > 0))
        || $birth->format('Y-m-d') !== $birthDate) {
        return false;
    }
    $at ??= new DateTimeImmutable('today', new DateTimeZone('Europe/Rome'));
    return $birth->modify('+18 years') <= $at->setTime(0, 0);
}

function registration_age_on(string $birthDate, ?DateTimeImmutable $at = null): ?int
{
    $birth = DateTimeImmutable::createFromFormat('!Y-m-d', $birthDate, new DateTimeZone('Europe/Rome'));
    $errors = DateTimeImmutable::getLastErrors();
    if (!$birth || ($errors !== false && ($errors['warning_count'] > 0 || $errors['error_count'] > 0))
        || $birth->format('Y-m-d') !== $birthDate) {
        return null;
    }
    $at ??= new DateTimeImmutable('today', new DateTimeZone('Europe/Rome'));
    if ($birth > $at->setTime(0, 0)) {
        return null;
    }
    return (int) $birth->diff($at->setTime(0, 0))->y;
}

/** Format/checksum validation only; it does not establish a person's identity. */
function registration_valid_codice_fiscale(string $value): bool
{
    $cf = strtoupper(preg_replace('/\s+/', '', trim($value)) ?? '');
    if (!preg_match('/\A[A-Z]{6}[0-9LMNPQRSTUV]{2}[ABCDEHLMPRST][0-9LMNPQRSTUV]{2}[A-Z][0-9LMNPQRSTUV]{3}[A-Z]\z/', $cf)) {
        return false;
    }

    $odd = [
        '0'=>1,'1'=>0,'2'=>5,'3'=>7,'4'=>9,'5'=>13,'6'=>15,'7'=>17,'8'=>19,'9'=>21,
        'A'=>1,'B'=>0,'C'=>5,'D'=>7,'E'=>9,'F'=>13,'G'=>15,'H'=>17,'I'=>19,'J'=>21,
        'K'=>2,'L'=>4,'M'=>18,'N'=>20,'O'=>11,'P'=>3,'Q'=>6,'R'=>8,'S'=>12,'T'=>14,
        'U'=>16,'V'=>10,'W'=>22,'X'=>25,'Y'=>24,'Z'=>23,
    ];
    $sum = 0;
    for ($i = 0; $i < 15; $i++) {
        $char = $cf[$i];
        if (($i % 2) === 0) {
            $sum += $odd[$char];
        } else {
            $sum += ctype_digit($char) ? (int) $char : ord($char) - ord('A');
        }
    }
    return $cf[15] === chr(ord('A') + ($sum % 26));
}

function registration_validate_person(array $input, ?DateTimeImmutable $submittedAt = null): array
{
    $errors = [];
    foreach (['nome' => 'Nome', 'cognome' => 'Cognome', 'email' => 'Email', 'telefono' => 'Telefono', 'data_nascita' => 'Data di nascita', 'codice_fiscale' => 'Codice fiscale', 'indirizzo' => 'Indirizzo'] as $key => $label) {
        if (trim((string) ($input[$key] ?? '')) === '') {
            $errors[$key] = $label . ' obbligatorio.';
        }
    }
    $email = registration_normalize_email((string) ($input['email'] ?? ''));
    if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'Inserisci un indirizzo email valido.';
    }
    $birth = trim((string) ($input['data_nascita'] ?? ''));
    $age = $birth === '' ? null : registration_age_on($birth, $submittedAt);
    if ($birth !== '' && $age === null) {
        $errors['data_nascita'] = 'Inserisci una data di nascita valida e non futura.';
    } elseif ($age !== null && $age < 16) {
        $errors['data_nascita'] = 'Per partecipare devi avere almeno 16 anni alla data di invio.';
    }
    $cf = strtoupper(preg_replace('/\s+/', '', trim((string) ($input['codice_fiscale'] ?? ''))) ?? '');
    if ($cf !== '' && !registration_valid_codice_fiscale($cf)) {
        $errors['codice_fiscale'] = 'Il codice fiscale non supera il controllo di formato e checksum. Questo controllo non verifica l’identità.';
    }
    foreach (['nome' => 100, 'cognome' => 100, 'email' => 190, 'telefono' => 30, 'codice_fiscale' => 16, 'indirizzo' => 255] as $key => $max) {
        if (strlen(trim((string) ($input[$key] ?? ''))) > $max) {
            $errors[$key] = 'Il campo supera la lunghezza massima consentita.';
        }
    }
    return ['errors' => $errors, 'age' => $age, 'email' => $email, 'codice_fiscale' => $cf];
}
