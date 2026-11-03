<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth_capitano.php';
require_once __DIR__ . '/../includes/token_helper.php';
require_once __DIR__ . '/../includes/mailer.php';

capitano_session_start_secure();

if (is_capitano_logged()) {
    header('Location: ' . SITE_URL . '/capitano/dashboard.php');
    exit;
}

$errors = [];
$success = '';
$numeroSquadre = (int) db()->query('SELECT COUNT(*) FROM squadre')->fetchColumn();
$iscrizioniAperte = $numeroSquadre < MAX_SQUADRE;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $numeroSquadre = (int) db()->query('SELECT COUNT(*) FROM squadre')->fetchColumn();
    $iscrizioniAperte = $numeroSquadre < MAX_SQUADRE;
    if (!capitano_csrf_verify()) {
        $errors[] = 'Token di sicurezza non valido.';
    } else {
        $nome = trim((string) ($_POST['nome'] ?? ''));
        $cognome = trim((string) ($_POST['cognome'] ?? ''));
        $dataNascita = trim((string) ($_POST['data_nascita'] ?? ''));
        $indirizzo = trim((string) ($_POST['indirizzo'] ?? ''));
        $email = trim(strtolower((string) ($_POST['email'] ?? '')));
        $telefono = trim((string) ($_POST['telefono'] ?? ''));
        $password = (string) ($_POST['password'] ?? '');
        $nomeSquadra = trim((string) ($_POST['nome_squadra'] ?? ''));
        $coloreSquadra = trim((string) ($_POST['colore_squadra'] ?? '#1a3a5c'));
        $logoUrl = trim((string) ($_POST['logo_url'] ?? ''));
        $numeroPrevisto = (int) ($_POST['numero_giocatori_previsto'] ?? 0);
        $accettaRegolamento = !empty($_POST['accetta_regolamento']);
        $accettaPrivacy = !empty($_POST['accetta_privacy']);
        $accettaFoto = !empty($_POST['accetta_foto']);

        if ($nomeSquadra === '' || $numeroPrevisto < 1 || $numeroPrevisto > 9) {
          $errors[] = 'Inserisci il nome della squadra e un numero di giocatori previsti compreso tra 1 e 9.';
        }
        if ($nome === '' || $cognome === '' || $dataNascita === '' || $indirizzo === '' || $email === '' || $password === '') {
            $errors[] = 'Compila tutti i dati del capitano.';
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Email non valida.';
        }
        if (strlen($password) < 8) {
            $errors[] = 'La password deve avere almeno 8 caratteri.';
        }
        if (!$accettaRegolamento || !$accettaPrivacy) {
            $errors[] = 'Devi accettare regolamento e privacy.';
        }
        if (!$iscrizioniAperte) {
            $errors[] = 'Il numero massimo di squadre iscrivibili (' . MAX_SQUADRE . ') è stato raggiunto.';
        }
        if ($dataNascita !== '' && strtotime($dataNascita) === false) {
            $errors[] = 'Data di nascita non valida.';
        }

        if (!$errors) {
            $pdo = db();
            $check = $pdo->prepare('SELECT id FROM capitani WHERE email = ? LIMIT 1');
            $check->execute([$email]);
            if ($check->fetch()) {
                $errors[] = 'Esiste già un account con questa email.';
            }
        }

        if (!$errors) {
            $pdo = db();
            $hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
            $stmt = $pdo->prepare('INSERT INTO capitani (nome, cognome, email, telefono, data_nascita, indirizzo, password_hash) VALUES (?, ?, ?, ?, ?, ?, ?)');
            $stmt->execute([$nome, $cognome, $email, $telefono ?: null, $dataNascita ?: null, $indirizzo ?: null, $hash]);
            $idCapitano = (int) $pdo->lastInsertId();

            $torneoId = null;
            $row = $pdo->query('SELECT id FROM tornei WHERE attivo = 1 ORDER BY id DESC LIMIT 1')->fetch();
            if ($row) {
                $torneoId = (int) $row['id'];
            } else {
                $pdo->prepare('INSERT INTO tornei (nome, stagione, attivo) VALUES (?, ?, 1)')->execute(['Torneo Baone', '2026']);
                $torneoId = (int) $pdo->lastInsertId();
            }

            $quotaDefault = '180.00';
            if (table_has_column($pdo, 'impostazioni', 'chiave')) {
                $qRow = $pdo->prepare('SELECT valore FROM impostazioni WHERE chiave = ? LIMIT 1');
                $qRow->execute(['quota_iscrizione_default']);
                $quotaDefault = $qRow->fetchColumn() ?: $quotaDefault;
            }

            $squadraColumns = ['nome'];
            $squadraValues = [$nomeSquadra];

            if (table_has_column($pdo, 'squadre', 'colore')) {
                $squadraColumns[] = 'colore';
                $squadraValues[] = $coloreSquadra !== '' ? $coloreSquadra : '#1a3a5c';
            }

            if (table_has_column($pdo, 'squadre', 'attiva')) {
                $squadraColumns[] = 'attiva';
                $squadraValues[] = 0;
            }

            if (table_has_column($pdo, 'squadre', 'logo_url')) {
                $squadraColumns[] = 'logo_url';
                $squadraValues[] = $logoUrl ?: null;
            }

            if (table_has_column($pdo, 'squadre', 'id_capitano')) {
                $squadraColumns[] = 'id_capitano';
                $squadraValues[] = $idCapitano;
            }

            if (table_has_column($pdo, 'squadre', 'numero_giocatori_previsto')) {
                $squadraColumns[] = 'numero_giocatori_previsto';
                $squadraValues[] = $numeroPrevisto ?: 10;
            }

            if (table_has_column($pdo, 'squadre', 'quota_iscrizione')) {
                $squadraColumns[] = 'quota_iscrizione';
                $squadraValues[] = $quotaDefault;
            }

            if (table_has_column($pdo, 'squadre', 'id_torneo')) {
                $squadraColumns[] = 'id_torneo';
                $squadraValues[] = $torneoId;
            }

            if (table_has_column($pdo, 'squadre', 'stato_iscrizione')) {
                $squadraColumns[] = 'stato_iscrizione';
                $squadraValues[] = 'bozza';
            }

            if (table_has_column($pdo, 'squadre', 'stato_pagamento')) {
                $squadraColumns[] = 'stato_pagamento';
                $squadraValues[] = 'non_pagato';
            }

            $placeholders = implode(', ', array_fill(0, count($squadraValues), '?'));
            $squadraStmt = $pdo->prepare('INSERT INTO squadre (' . implode(', ', array_map(fn($c) => '`' . str_replace('`', '', $c) . '`', $squadraColumns)) . ') VALUES (' . $placeholders . ')');
            $squadraStmt->execute($squadraValues);
            $idSquadra = (int) $pdo->lastInsertId();

            $documentTypes = ['regolamento' => 'Regolamento', 'privacy' => 'Privacy'];
            foreach ($documentTypes as $codice => $label) {
                try {
                    $doc = $pdo->prepare('SELECT id FROM tipi_documento WHERE codice = ? LIMIT 1');
                    $doc->execute([$codice]);
                    $docRow = $doc->fetch();
                    if ($docRow && table_has_column($pdo, 'consensi', 'id_documento')) {
                        $consenso = $pdo->prepare('INSERT INTO consensi (id_documento, id_capitano, concesso, ip_address, user_agent) VALUES ((SELECT id FROM documenti WHERE id_tipo = ? ORDER BY versione DESC LIMIT 1), ?, ?, ?, ?)');
                        $consenso->execute([$docRow['id'], $idCapitano, 1, $_SERVER['REMOTE_ADDR'] ?? null, substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255)]);
                    }
                } catch (Throwable $e) {
                    // Ignora errore se il database non dispone ancora delle nuove tabelle di documenti/consensi.
                }
            }

            if ($accettaFoto) {
                try {
                    $doc = $pdo->prepare('SELECT id FROM tipi_documento WHERE codice = "liberatoria_foto" LIMIT 1');
                    $doc->execute();
                    $docRow = $doc->fetch();
                    if ($docRow && table_has_column($pdo, 'consensi', 'id_documento')) {
                        $consenso = $pdo->prepare('INSERT INTO consensi (id_documento, id_capitano, concesso, ip_address, user_agent) VALUES ((SELECT id FROM documenti WHERE id_tipo = ? ORDER BY versione DESC LIMIT 1), ?, ?, ?, ?)');
                        $consenso->execute([$docRow['id'], $idCapitano, 1, $_SERVER['REMOTE_ADDR'] ?? null, substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255)]);
                    }
                } catch (Throwable $e) {
                    // Ignora errore se il database non dispone ancora delle nuove tabelle di documenti/consensi.
                }
            }

            $tokenVerify = create_verification_token($pdo, 'email_verifica', $idCapitano, null, null, 72);
            if ($tokenVerify) {
                $verificationLink = build_site_link('/capitano/verifica-email.php', $tokenVerify);
                $body = build_email_template(
                    'Verifica il tuo account',
                    "Ciao {$nome} {$cognome},\n\nAbbiamo creato il tuo account per il Torneo di Baone. Per completare la registrazione e poter accedere all'area capitano, verifica il tuo indirizzo email cliccando sul pulsante qui sotto.",
                    'Verifica il tuo account',
                    $verificationLink,
                    'Il link scade tra 72 ore.',
                    'Se non hai richiesto questa registrazione, puoi ignorare questa email.'
                );
                $sent = send_email($email, 'Verifica il tuo account', $body);
                $_SESSION['capitano_pending_verification'] = true;
                $_SESSION['capitano_pending_email'] = $email;
                $_SESSION['capitano_notice'] = $sent
                    ? 'Registrazione completata. Controlla la tua email e verifica il tuo account per accedere.'
                    : 'Registrazione completata. Non è stato possibile inviare la mail di verifica, ma puoi richiederla nuovamente dal login.';
            } else {
                $_SESSION['capitano_notice'] = 'Registrazione completata, ma non è stato possibile creare il link di verifica. Riprova più tardi.';
            }

            header('Location: ' . SITE_URL . '/capitano/login.php?verifica=sent');
            exit;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="it">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Registrazione capitano — <?= htmlspecialchars(SITE_NAME) ?></title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Barlow+Condensed:wght@600;700;800&family=Barlow:wght@400;500;600&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?= SITE_URL ?>/assets/css/style.css">
  <link rel="stylesheet" href="<?= SITE_URL ?>/assets/css/admin.css">
  <style>
    body { background: var(--navy); }
    .auth-card { max-width: 760px; margin: 2rem auto; background: var(--navy-mid); border:1px solid rgba(201,168,76,.18); border-radius: var(--radius); padding: 2rem; }
    .brand { text-align:center; margin-bottom: 1.2rem; }
    .brand img { width: 52px; margin:0 auto 0.7rem; }
    .brand h1 { font-family:'Barlow Condensed',sans-serif; text-transform:uppercase; letter-spacing:.08em; color:var(--gold); }
    .grid2 { display:grid; grid-template-columns:repeat(2, minmax(0,1fr)); gap: 1rem; }
    @media (max-width: 640px) { .grid2 { grid-template-columns:1fr; } }
  </style>
  <link rel="stylesheet" href="<?= SITE_URL ?>/assets/css/captain-portal.css?v=1">
</head>
<body class="captain-auth-flow captain-signup-flow">
<div class="auth-card">
  <div class="brand">
    <img src="<?= SITE_URL ?>/assets/brand/01_loghi/png/logo_scudetto_compatto.png" alt="">
    <h1>Registrazione Capitano</h1>
  </div>

  <?php if ($errors): foreach ($errors as $error): ?>
    <div class="alert alert-error" role="alert"><?= htmlspecialchars($error) ?></div>
  <?php endforeach; endif; ?>
  <?php if (!$iscrizioniAperte): ?>
    <div class="alert alert-error" role="alert">Le iscrizioni hanno raggiunto il limite di <?= MAX_SQUADRE ?> squadre.</div>
  <?php endif; ?>

  <?php if ($iscrizioniAperte): ?><form method="post" class="admin-form" novalidate>
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(capitano_csrf_token()) ?>">

    <p class="required-note">I campi contrassegnati con <span aria-hidden="true">*</span> sono obbligatori. Il consenso a foto e video è facoltativo.</p>
    <fieldset class="captain-step-fieldset">
      <legend style="padding: 0 .5rem; color: var(--gold); font-weight: 700;">Passaggio 1 — Dati della squadra</legend>
      <div class="grid2">
        <div class="form-group">
          <label for="nome_squadra">Nome squadra <span class="required-mark" aria-hidden="true">*</span></label>
          <input id="nome_squadra" type="text" name="nome_squadra" value="<?= htmlspecialchars($_POST['nome_squadra'] ?? '') ?>" required>
        </div>
        <div class="form-group">
          <label for="colore_squadra">Colore squadra</label>
          <input id="colore_squadra" type="color" name="colore_squadra" value="<?= htmlspecialchars($_POST['colore_squadra'] ?? '#1a3a5c') ?>" style="min-height:44px; width:100%; padding:0.2rem;">
        </div>
      </div>

      <div class="grid2">
        <div class="form-group">
          <label for="numero_giocatori_previsto">Numero giocatori previsti (escluso il capitano) <span class="required-mark" aria-hidden="true">*</span></label>
          <input id="numero_giocatori_previsto" type="number" name="numero_giocatori_previsto" min="1" max="9" value="<?= htmlspecialchars((string) ($_POST['numero_giocatori_previsto'] ?? 9)) ?>" required>
        </div>
        <!--<div class="form-group">
          <label>Logo URL</label>
          <input type="url" name="logo_url" value="<?= htmlspecialchars($_POST['logo_url'] ?? '') ?>" placeholder="https://...">
        </div>-->
      </div>
    </fieldset>

    <fieldset class="captain-step-fieldset">
      <legend style="padding: 0 .5rem; color: var(--gold); font-weight: 700;">Passaggio 2 — Dati del capitano</legend>
      <div class="grid2">
        <div class="form-group">
          <label for="capitano_nome">Nome <span class="required-mark" aria-hidden="true">*</span></label>
          <input id="capitano_nome" type="text" name="nome" value="<?= htmlspecialchars($_POST['nome'] ?? '') ?>" required>
        </div>
        <div class="form-group">
          <label for="capitano_cognome">Cognome <span class="required-mark" aria-hidden="true">*</span></label>
          <input id="capitano_cognome" type="text" name="cognome" value="<?= htmlspecialchars($_POST['cognome'] ?? '') ?>" required>
        </div>
      </div>

      <div class="grid2">
        <div class="form-group">
          <label for="capitano_data_nascita">Data di nascita <span class="required-mark" aria-hidden="true">*</span></label>
          <input id="capitano_data_nascita" type="date" name="data_nascita" value="<?= htmlspecialchars($_POST['data_nascita'] ?? '') ?>" required>
        </div>
        <div class="form-group">
          <label for="capitano_telefono">Telefono</label>
          <input id="capitano_telefono" type="text" name="telefono" value="<?= htmlspecialchars($_POST['telefono'] ?? '') ?>">
        </div>
      </div>

      <div class="form-group">
        <label for="capitano_indirizzo">Indirizzo <span class="required-mark" aria-hidden="true">*</span></label>
        <input id="capitano_indirizzo" type="text" name="indirizzo" value="<?= htmlspecialchars($_POST['indirizzo'] ?? '') ?>" required>
      </div>

      <div class="grid2">
        <div class="form-group">
          <label for="capitano_email">Email <span class="required-mark" aria-hidden="true">*</span></label>
          <input id="capitano_email" type="email" name="email" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required>
        </div>
        <div class="form-group">
          <label for="capitano_password">Password <span class="required-mark" aria-hidden="true">*</span></label>
          <input id="capitano_password" type="password" name="password" minlength="8" required>
        </div>
      </div>

      <div class="checklist">
        <label class="check-item"><input type="checkbox" name="accetta_regolamento" value="1" required> Accetto il regolamento del torneo. <span class="required-mark" aria-hidden="true">*</span></label><br>
        <label class="check-item"><input type="checkbox" name="accetta_privacy" value="1" required> Accetto l'informativa privacy. <span class="required-mark" aria-hidden="true">*</span></label><br>
        <label class="check-item"><input type="checkbox" name="accetta_foto" value="1"> Acconsento all’uso di foto e video (facoltativo).</label>
      </div>
    </fieldset>

    <div class="form-actions">
      <button type="submit" class="btn btn-gold">Crea account</button>
      <a href="<?= SITE_URL ?>/capitano/login.php" class="btn btn-outline">Ho già un account</a>
    </div>
  </form>
  <?php endif; ?>
</div>
</body>
</html>
