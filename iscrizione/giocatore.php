<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/token_helper.php';
require_once __DIR__ . '/../includes/iscrizione_helper.php';
require_once __DIR__ . '/../includes/mailer.php';

$token = trim((string) ($_GET['token'] ?? ''));
$errors = [];
$success = '';
$pdo = db();
$effectiveSmtpHost = getenv('SMTP_HOST') ?: SMTP_HOST;
$mailIsSandbox = stripos((string) $effectiveSmtpHost, 'sandbox.smtp.mailtrap.io') !== false;

$tokenData = $token !== '' ? get_valid_token($pdo, $token) : null;

if (!$tokenData || ($tokenData['tipo'] ?? '') !== 'invito_giocatore') {
    $errors[] = 'Link non valido o scaduto.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = trim((string) ($_POST['token'] ?? ''));
    $tokenData = get_valid_token($pdo, $token);

    if (!$tokenData || ($tokenData['tipo'] ?? '') !== 'invito_giocatore') {
        $errors[] = 'Link non valido o scaduto.';
    } else {
        $recentAttempt = $pdo->prepare('SELECT MAX(created_at) FROM token_verifica WHERE id_giocatore = ? AND tipo IN ("email_verifica", "consenso_genitore") AND created_at >= DATE_SUB(NOW(), INTERVAL 30 SECOND)');
        $recentAttempt->execute([(int) ($tokenData['id_giocatore'] ?? 0)]);
        if ($recentAttempt->fetchColumn()) {
            $errors[] = 'È stato appena richiesto un invio. Attendi 30 secondi prima di riprovare.';
        }
        $nome = trim((string) ($_POST['nome'] ?? ''));
        $cognome = trim((string) ($_POST['cognome'] ?? ''));
        $email = trim(strtolower((string) ($_POST['email'] ?? '')));
        $telefono = trim((string) ($_POST['telefono'] ?? ''));
        $dataNascita = trim((string) ($_POST['data_nascita'] ?? ''));
        $indirizzo = trim((string) ($_POST['indirizzo'] ?? ''));
        $genitoreNome = trim((string) ($_POST['genitore_nome'] ?? ''));
        $genitoreCognome = trim((string) ($_POST['genitore_cognome'] ?? ''));
        $genitoreEmail = trim(strtolower((string) ($_POST['genitore_email'] ?? '')));
        $dataInizio = get_active_torneo_start_date($pdo);
        $minorenne = is_minorenne($dataNascita, $dataInizio);

        if ($nome === '' || $cognome === '' || $email === '' || $telefono === '' || $dataNascita === '' || $indirizzo === '') {
            $errors[] = 'Tutti i campi obbligatori devono essere compilati.';
        }
        if (strlen($nome) > 100 || strlen($cognome) > 100 || strlen($email) > 190 || strlen($telefono) > 30 || strlen($indirizzo) > 255) {
            $errors[] = 'Uno o più dati superano la lunghezza massima consentita.';
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Email non valida.';
        }
        $birthParts = explode('-', $dataNascita);
        if (count($birthParts) !== 3 || !checkdate((int) ($birthParts[1] ?? 0), (int) ($birthParts[2] ?? 0), (int) ($birthParts[0] ?? 0)) || $dataNascita > date('Y-m-d')) {
            $errors[] = 'Inserisci una data di nascita valida.';
        }
        if (empty($_POST['accetta_privacy']) || empty($_POST['accetta_regolamento'])) {
            $errors[] = 'Per proseguire devi accettare privacy e regolamento.';
        }
        if ($minorenne && ($genitoreNome === '' || $genitoreCognome === '' || strlen($genitoreNome) > 100 || strlen($genitoreCognome) > 100 || strlen($genitoreEmail) > 190 || !filter_var($genitoreEmail, FILTER_VALIDATE_EMAIL))) {
            $errors[] = 'Per un minorenne servono nome, cognome ed email del genitore.';
        }
        if ($minorenne && filter_var($genitoreEmail, FILTER_VALIDATE_EMAIL)) {
            // Il controllo comprende tutte le squadre e anche gli inviti non ancora completati.
            $participantEmail = $pdo->prepare('SELECT id FROM giocatori WHERE LOWER(TRIM(email)) = ? UNION ALL SELECT id FROM capitani WHERE LOWER(TRIM(email)) = ? LIMIT 1');
            $participantEmail->execute([$genitoreEmail, $genitoreEmail]);
            if ($genitoreEmail === $email || $participantEmail->fetchColumn() !== false) {
                $errors[] = 'L’email del genitore deve essere diversa da quella del minorenne e non può essere già utilizzata da un giocatore o capitano.';
            }
        }

        if (!$errors) {
            $idGiocatore = (int) ($tokenData['id_giocatore'] ?? 0);
            $upd = $pdo->prepare('UPDATE giocatori SET nome = ?, cognome = ?, email = ?, telefono = ?, data_nascita = ?, indirizzo = ? WHERE id = ?');
            $upd->execute([$nome, $cognome, $email, $telefono, $dataNascita, $indirizzo, $idGiocatore]);
            registra_consenso($pdo, 'privacy', 'id_giocatore', $idGiocatore, (string) ($_SERVER['REMOTE_ADDR'] ?? ''), (string) ($_SERVER['HTTP_USER_AGENT'] ?? ''));
            registra_consenso($pdo, 'regolamento', 'id_giocatore', $idGiocatore, (string) ($_SERVER['REMOTE_ADDR'] ?? ''), (string) ($_SERVER['HTTP_USER_AGENT'] ?? ''));
            if (!empty($_POST['accetta_foto'])) {
                registra_consenso($pdo, 'liberatoria_foto', 'id_giocatore', $idGiocatore, (string) ($_SERVER['REMOTE_ADDR'] ?? ''), (string) ($_SERVER['HTTP_USER_AGENT'] ?? ''));
            }

            invalidate_previous_tokens($pdo, 'email_verifica', $idGiocatore);
            $emailErrore = null;
            $tokenVerify = create_verification_token($pdo, 'email_verifica', null, $idGiocatore, null, 72);
            if ($tokenVerify) {
                $link = build_site_link('/iscrizione/verifica-email.php', $tokenVerify);
                $body = build_email_template(
                    'Verifica la tua email',
                    "Ciao {$nome},\n\nper completare la tua iscrizione devi verificare il tuo indirizzo email. Clicca sul pulsante qui sotto.",
                    'Verifica la tua email',
                    $link,
                    'Il link scade tra 72 ore.',
                    'Se non hai richiesto questa verifica, puoi ignorare questa email.'
                );
                $emailInviata = send_email($email, 'Verifica la tua email', $body, $emailErrore);
            } else {
                $emailInviata = false;
                $emailErrore = 'Non è stato possibile creare il link di verifica del giocatore.';
            }

            if ($minorenne) {
                $parent = $pdo->prepare('SELECT id FROM genitori WHERE id_giocatore = ? LIMIT 1');
                $parent->execute([$idGiocatore]);
                $idGenitore = $parent->fetchColumn();
                if ($idGenitore) {
                    $pdo->prepare('UPDATE genitori SET nome = ?, cognome = ?, email = ? WHERE id = ?')->execute([$genitoreNome, $genitoreCognome, $genitoreEmail, $idGenitore]);
                } else {
                    $pdo->prepare('INSERT INTO genitori (id_giocatore, nome, cognome, email) VALUES (?, ?, ?, ?)')->execute([$idGiocatore, $genitoreNome, $genitoreCognome, $genitoreEmail]);
                    $idGenitore = (int) $pdo->lastInsertId();
                }
                // A renewed player registration may update the parent email;
                // invalidate any older approval link before waiting for the
                // player's email verification to send a fresh one.
                invalidate_previous_tokens($pdo, 'consenso_genitore', null, (int) $idGenitore);
                if ($emailInviata) {
                    $success = 'Dati salvati. Controlla la tua email: dopo la verifica invieremo al genitore il link per approvare l’iscrizione.';
                    if ($mailIsSandbox) {
                        $success .= ' Mailtrap Sandbox intercetta il messaggio: controlla la sua inbox, non la casella reale.';
                    }
                } else {
                    $errors[] = $emailErrore ?: 'Invio email non riuscito. Riprova.';
                    $errors[] = 'I dati sono stati salvati. Il link d’invito resta valido: puoi riprovare l’invio da questa pagina.';
                }
            } else {
                if ($emailInviata) {
                    $success = 'Dati salvati. Controlla la tua email per verificare l’iscrizione.';
                    if ($mailIsSandbox) {
                        $success = 'Dati salvati. Mailtrap Sandbox ha intercettato il messaggio: controlla la sua inbox, non la casella reale.';
                    }
                } else {
                    $errors[] = $emailErrore ?: 'Invio email non riuscito. Riprova.';
                    $errors[] = 'I dati sono stati salvati. Il link d’invito resta valido: puoi riprovare l’invio da questa pagina.';
                }
            }

            if ($success !== '') {
                consume_token($pdo, $token);
                $token = '';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="it">
<head>
  <link rel="icon" type="image/x-icon" href="<?= SITE_URL ?>/assets/brand/02_favicon/favicon.ico">
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Completa iscrizione giocatore — <?= htmlspecialchars(SITE_NAME) ?></title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Barlow+Condensed:wght@600;700;800&family=Barlow:wght@400;500;600&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?= SITE_URL ?>/assets/css/style.css">
  <link rel="stylesheet" href="<?= SITE_URL ?>/assets/css/iscrizioni.css">
  <link rel="stylesheet" href="<?= SITE_URL ?>/assets/css/registration-portal.css?v=phase05">
</head>
<body class="registration-form-page">
  <div class="app-shell">
    <div class="card-shell">
      <div class="brand-row">
        <img src="<?= SITE_URL ?>/assets/brand/01_loghi/png/logo_scudetto_compatto.png" alt="">
        <h1>Iscrizione</h1>
      </div>
      <p class="subtle">Completa i tuoi dati personali per proseguire con l'iscrizione.</p>

      <?php if ($errors): foreach ($errors as $err): ?>
        <div class="alert alert-error" role="alert"><?= htmlspecialchars($err) ?></div>
      <?php endforeach; endif; ?>
      <?php if ($success): ?>
        <div class="alert alert-success" role="status"><?= htmlspecialchars($success) ?></div>
      <?php endif; ?>

      <?php if ($token !== '' && !$success): ?>
        <form method="post" class="player-form" novalidate>
          <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>">
          <p class="required-note">I campi contrassegnati con <span aria-hidden="true">*</span> sono obbligatori.</p>
          <div class="form-row">
            <div class="form-group">
              <label for="nome">Nome <span class="required-mark" aria-hidden="true">*</span></label>
              <input id="nome" type="text" name="nome" maxlength="100" value="<?= htmlspecialchars($_POST['nome'] ?? '') ?>" required>
            </div>
            <div class="form-group">
              <label for="cognome">Cognome <span class="required-mark" aria-hidden="true">*</span></label>
              <input id="cognome" type="text" name="cognome" maxlength="100" value="<?= htmlspecialchars($_POST['cognome'] ?? '') ?>" required>
            </div>
          </div>
          <div class="form-row">
            <div class="form-group">
              <label for="email">Email <span class="required-mark" aria-hidden="true">*</span></label>
              <input id="email" type="email" name="email" maxlength="190" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required>
            </div>
            <div class="form-group">
              <label for="telefono">Telefono <span class="required-mark" aria-hidden="true">*</span></label>
              <input id="telefono" type="text" name="telefono" maxlength="30" value="<?= htmlspecialchars($_POST['telefono'] ?? '') ?>" required>
            </div>
          </div>
          <div class="form-group">
            <label for="data_nascita">Data di nascita <span class="required-mark" aria-hidden="true">*</span></label>
            <input id="data_nascita" type="date" name="data_nascita" value="<?= htmlspecialchars($_POST['data_nascita'] ?? '') ?>" required>
          </div>
          <div class="form-group">
            <label for="indirizzo">Indirizzo (via/piazza - civico - paese) <span class="required-mark" aria-hidden="true">*</span></label>
            <input id="indirizzo" type="text" name="indirizzo" maxlength="255" placeholder="Via Roma - 10 - Baone" value="<?= htmlspecialchars($_POST['indirizzo'] ?? '') ?>" required>
          </div>
          <section class="parent-fields" id="parent-fields" hidden>
            <h2>Iscrizione minorenne</h2>
            <p>Inserisci i dati del genitore, che riceverà il link per completare e confermare l’iscrizione.</p>
            <div class="form-row">
              <div class="form-group"><label for="genitore_nome">Nome genitore</label><input id="genitore_nome" type="text" name="genitore_nome" maxlength="100" value="<?= htmlspecialchars($_POST['genitore_nome'] ?? '') ?>"></div>
              <div class="form-group"><label for="genitore_cognome">Cognome genitore</label><input id="genitore_cognome" type="text" name="genitore_cognome" maxlength="100" value="<?= htmlspecialchars($_POST['genitore_cognome'] ?? '') ?>"></div>
            </div>
            <div class="form-group"><label for="genitore_email">Email genitore</label><input id="genitore_email" type="email" name="genitore_email" maxlength="190" aria-describedby="genitore_email_hint" value="<?= htmlspecialchars($_POST['genitore_email'] ?? '') ?>"><small id="genitore_email_hint">Usa un indirizzo diverso da quello del minorenne e non utilizzato da giocatori o capitani.</small></div>
          </section>
          <section class="consent-fields">
            <h2>Consensi</h2>
            <label class="check-item"><input type="checkbox" name="accetta_privacy" value="1" required <?= !empty($_POST['accetta_privacy']) ? 'checked' : '' ?>> Accetto l’informativa privacy. <span class="required-mark" aria-hidden="true">*</span></label>
            <label class="check-item"><input type="checkbox" name="accetta_regolamento" value="1" required <?= !empty($_POST['accetta_regolamento']) ? 'checked' : '' ?>> Accetto il regolamento del torneo. <span class="required-mark" aria-hidden="true">*</span></label>
            <label class="check-item"><input type="checkbox" name="accetta_foto" value="1" <?= !empty($_POST['accetta_foto']) ? 'checked' : '' ?>> Acconsento all’uso di foto e video (facoltativo).</label>
          </section>
          <div class="inline-actions">
            <button type="submit" class="btn btn-gold">Salva dati</button>
          </div>
        </form>
      <?php endif; ?>
    </div>
  </div>
  <script>
    const birthInput = document.querySelector('[name="data_nascita"]');
    const parentFields = document.getElementById('parent-fields');
    const startDate = <?= json_encode(get_active_torneo_start_date($pdo) ?: date('Y-m-d')) ?>;
    if (birthInput && parentFields) {
      function toggleParentFields() {
        if (!birthInput.value) { parentFields.hidden = true; return; }
        const birth = new Date(birthInput.value + 'T00:00:00');
        const start = new Date(startDate + 'T00:00:00');
        let age = start.getFullYear() - birth.getFullYear();
        if (start.getMonth() < birth.getMonth() || (start.getMonth() === birth.getMonth() && start.getDate() < birth.getDate())) age--;
        parentFields.hidden = age >= 18;
        parentFields.querySelectorAll('input').forEach((el) => el.required = age < 18);
      }
      birthInput.addEventListener('change', toggleParentFields);
      toggleParentFields();
    }
  </script>
</body>
</html>
