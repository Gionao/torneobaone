<?php
declare(strict_types=1);

// Opt-in: invia una mail a Mailpit e annulla tutti i dati di prova nel database.
if (PHP_SAPI !== 'cli' || !in_array('--mailpit', $argv, true)) {
    fwrite(STDERR, "Usage: php tests/email_verification_integration.php --mailpit\n");
    exit(1);
}

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/mailer.php';
require_once __DIR__ . '/../includes/token_helper.php';

function verification_check(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
    echo "PASS: {$message}\n";
}

function verification_render(string $page): string
{
    ob_start();
    try {
        require __DIR__ . '/../' . $page;
        return (string) ob_get_contents();
    } finally {
        ob_end_clean();
    }
}

function verification_mailpit(string $path): array
{
    $context = stream_context_create(['http' => ['timeout' => 5]]);
    $json = file_get_contents('http://127.0.0.1:8025/api/v1/' . $path, false, $context);
    if ($json === false) {
        throw new RuntimeException('Mailpit API unreachable');
    }
    return json_decode($json, true, 512, JSON_THROW_ON_ERROR);
}

$pdo = null;
$exitCode = 0;
$previousPort = getenv('SMTP_PORT');
try {
    verification_check(
        ENV === 'local'
        && in_array(getenv('SMTP_HOST') ?: SMTP_HOST, ['127.0.0.1', 'localhost'], true)
        && (int) (getenv('SMTP_PORT') ?: SMTP_PORT) === 1025
        && (getenv('SMTP_ENCRYPTION') ?: SMTP_ENCRYPTION) === 'none'
        && (getenv('SMTP_USERNAME') ?: SMTP_USERNAME) === ''
        && (getenv('SMTP_PASSWORD') ?: SMTP_PASSWORD) === '',
        'Local Mailpit configuration'
    );
    verification_mailpit('info');
    $pdo = db();
    $pdo->beginTransaction();
    $email = 'verification-test-' . bin2hex(random_bytes(6)) . '@example.test';
    $pdo->prepare('INSERT INTO capitani (nome, cognome, email, password_hash) VALUES (?, ?, ?, ?)')
        ->execute(['Test', 'Mailpit', $email, password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT)]);
    $captainId = (int) $pdo->lastInsertId();
    $_SERVER['REQUEST_METHOD'] = 'POST';
    $_POST = ['email' => $email];

    putenv('SMTP_PORT=1');
    $failed = verification_render('capitano/richiedi-verifica.php');
    verification_check(str_contains($failed, 'Il server email non ha accettato il messaggio.'), 'SMTP failure is shown');
    verification_check(!str_contains($failed, 'ti abbiamo inviato un nuovo link'), 'SMTP failure does not claim successful delivery');
    putenv($previousPort === false ? 'SMTP_PORT' : 'SMTP_PORT=' . $previousPort);

    $sent = verification_render('capitano/richiedi-verifica.php');
    verification_check(str_contains($sent, 'ti abbiamo inviato un nuovo link'), 'Verification resend succeeds');
    $messages = verification_mailpit('search?query=' . rawurlencode('to:' . $email));
    verification_check(count($messages['messages'] ?? []) === 1, 'Mailpit captured exactly one verification email');
    $message = verification_mailpit('message/' . rawurlencode($messages['messages'][0]['ID']));
    $html = html_entity_decode((string) ($message['HTML'] ?? ''), ENT_QUOTES, 'UTF-8');
    verification_check(str_contains($html, SITE_URL . '/capitano/verifica-email.php?token='), 'Email contains the local verification link');
    verification_check((bool) preg_match('/verifica-email\.php\?token=([a-f0-9]{64})/', $html, $matches), 'Email contains a valid token');
    $token = $matches[1];
    verification_check(get_valid_token($pdo, $token) !== null, 'Verification token is stored and usable');

    $_SERVER['REQUEST_METHOD'] = 'GET';
    $_GET = ['token' => $token];
    $verified = verification_render('capitano/verifica-email.php');
    verification_check(str_contains($verified, 'La tua email è stata verificata con successo.'), 'Confirmation page accepts the emailed token');
    $stmt = $pdo->prepare('SELECT email_verificata_at FROM capitani WHERE id = ?');
    $stmt->execute([$captainId]);
    verification_check((bool) $stmt->fetchColumn(), 'Captain email is marked verified');
    verification_check(get_valid_token($pdo, $token) === null, 'Confirmation consumes the token');
    $reused = verification_render('capitano/verifica-email.php');
    verification_check(str_contains($reused, 'Il link di verifica non è valido o è scaduto.'), 'Reusing the confirmation link is rejected');

    $pdo->prepare('INSERT INTO squadre (nome, id_capitano) VALUES (?, ?)')->execute(['Mailpit integration ' . bin2hex(random_bytes(6)), $captainId]);
    $teamId = (int) $pdo->lastInsertId();
    foreach ([false, true] as $minor) {
        $label = $minor ? 'Minor' : 'Adult';
        $playerEmail = strtolower($label) . '-' . $email;
        $parentEmail = 'parent-' . $playerEmail;
        $pdo->prepare('INSERT INTO giocatori (id_squadra, nome, cognome, email) VALUES (?, ?, ?, ?)')
            ->execute([$teamId, 'Test', $label, $playerEmail]);
        $playerId = (int) $pdo->lastInsertId();
        $invitation = create_verification_token($pdo, 'invito_giocatore', $captainId, $playerId, null, 168);
        $_GET = ['token' => $invitation];
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST = [
            'token' => $invitation, 'nome' => 'Test', 'cognome' => $label,
            'email' => $playerEmail, 'telefono' => '0000000000',
            'data_nascita' => $minor ? date('Y-m-d', strtotime('-10 years')) : '1990-01-01',
            'indirizzo' => 'Indirizzo di prova', 'accetta_privacy' => '1', 'accetta_regolamento' => '1',
            'genitore_nome' => 'Test', 'genitore_cognome' => 'Parent', 'genitore_email' => $parentEmail,
        ];
        if ($minor) {
            $pdo->prepare('INSERT INTO squadre (nome) VALUES (?)')->execute(['Other team ' . bin2hex(random_bytes(6))]);
            $otherTeamId = (int) $pdo->lastInsertId();
            $otherEmail = 'other-' . $email;
            $pdo->prepare('INSERT INTO giocatori (id_squadra, nome, cognome, email) VALUES (?, ?, ?, ?)')
                ->execute([$otherTeamId, 'Test', 'Invited', '  ' . strtoupper($otherEmail) . '  ']);
            foreach ([
                'own email' => $playerEmail,
                'adult teammate' => 'adult-' . $email,
                'captain' => $email,
                'invited player from another team' => '  ' . strtoupper($otherEmail) . '  ',
            ] as $conflict => $conflictingEmail) {
                $_POST['genitore_email'] = $conflictingEmail;
                $rejected = verification_render('iscrizione/giocatore.php');
                verification_check(str_contains($rejected, 'L’email del genitore deve essere diversa'), "Parent email rejects $conflict");
                verification_check(get_valid_token($pdo, $invitation) !== null, 'Rejected parent email preserves the invitation');
                $parents = $pdo->prepare('SELECT COUNT(*) FROM genitori WHERE id_giocatore = ?');
                $parents->execute([$playerId]);
                verification_check((int) $parents->fetchColumn() === 0, 'Rejected parent email is not saved');
                $tokens = $pdo->prepare('SELECT COUNT(*) FROM token_verifica WHERE id_giocatore = ? AND tipo = ?');
                $tokens->execute([$playerId, 'email_verifica']);
                verification_check((int) $tokens->fetchColumn() === 0, 'Rejected parent email does not create a verification token');
            }
            $unsent = verification_mailpit('search?query=' . rawurlencode('to:' . $playerEmail));
            verification_check(count($unsent['messages'] ?? []) === 0, 'Rejected parent emails do not send verification messages');
            $_POST['genitore_email'] = $parentEmail;
        }
        $registered = verification_render('iscrizione/giocatore.php');
        verification_check(str_contains($registered, 'Dati salvati. Controlla la tua email'), "$label registration sends verification");
        verification_check(get_valid_token($pdo, $invitation) === null, "$label invitation is consumed after sending");
        $playerMessages = verification_mailpit('search?query=' . rawurlencode('to:' . $playerEmail));
        verification_check(count($playerMessages['messages'] ?? []) === 1, "$label verification captured in Mailpit");
        $playerMessage = verification_mailpit('message/' . rawurlencode($playerMessages['messages'][0]['ID']));
        verification_check((bool) preg_match('/verifica-email\.php\?token=([a-f0-9]{64})/', $playerMessage['HTML'] ?? '', $playerMatches), "$label verification link is present");
        if ($minor) {
            $before = verification_mailpit('search?query=' . rawurlencode('to:' . $parentEmail));
            verification_check(count($before['messages'] ?? []) === 0, 'Parent email is deferred until player verification');
        }
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_GET = ['token' => $playerMatches[1]];
        $confirmed = verification_render('iscrizione/verifica-email.php');
        verification_check(str_contains($confirmed, 'Email verificata'), "$label verification page succeeds");
        $state = $pdo->prepare('SELECT stato_iscrizione FROM giocatori WHERE id = ?');
        $state->execute([$playerId]);
        verification_check($state->fetchColumn() === ($minor ? 'in_attesa_genitore' : 'completo'), "$label registration reaches the correct state");
        if ($minor) {
            $after = verification_mailpit('search?query=' . rawurlencode('to:' . $parentEmail));
            verification_check(count($after['messages'] ?? []) === 1, 'Parent confirmation email captured in Mailpit');
            $parentMessage = verification_mailpit('message/' . rawurlencode($after['messages'][0]['ID']));
            verification_check((bool) preg_match('/conferma-genitore\.php\?token=([a-f0-9]{64})/', $parentMessage['HTML'] ?? '', $parentMatches), 'Parent confirmation link is present');
            $parentToken = get_valid_token($pdo, $parentMatches[1]);
            verification_check($parentToken !== null && $parentToken['tipo'] === 'consenso_genitore', 'Parent confirmation token is usable');
            $_SERVER['REQUEST_METHOD'] = 'POST';
            $_POST = ['token' => $parentMatches[1], 'accetta_privacy' => '1', 'accetta_regolamento' => '1'];
            $approved = verification_render('iscrizione/conferma-genitore.php');
            verification_check(str_contains($approved, 'Iscrizione del minorenne confermata.'), 'Parent approval succeeds');
            $state->execute([$playerId]);
            verification_check($state->fetchColumn() === 'completo', 'Minor registration is complete after parent approval');
            verification_check(get_valid_token($pdo, $parentMatches[1]) === null, 'Parent approval consumes the token');
        }
    }
} catch (Throwable $e) {
    fwrite(STDERR, 'FAIL: ' . $e->getMessage() . "\n");
    $exitCode = 1;
} finally {
    putenv($previousPort === false ? 'SMTP_PORT' : 'SMTP_PORT=' . $previousPort);
    if ($pdo instanceof PDO && $pdo->inTransaction()) {
        $pdo->rollBack();
        echo "Test database changes rolled back.\n";
    }
}
exit($exitCode);
