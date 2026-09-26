<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth_capitano.php';
require_once __DIR__ . '/../includes/token_helper.php';
require_once __DIR__ . '/../includes/mailer.php';

function table_has_column(PDO $pdo, string $table, string $column): bool
{
    $stmt = $pdo->prepare('SHOW COLUMNS FROM `' . str_replace('`', '', $table) . '`');
    $stmt->execute();
    $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
    return in_array($column, $columns, true);
}

capitano_session_start_secure();

if (is_capitano_logged()) {
    header('Location: ' . SITE_URL . '/capitano/dashboard.php');
    exit;
}

$errors = [];
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!capitano_csrf_verify()) {
        $errors[] = 'Token di sicurezza non valido.';
    } else {
        $nome = trim((string) ($_POST['nome'] ?? ''));
        $cognome = trim((string) ($_POST['cognome'] ?? ''));
        $email = trim(strtolower((string) ($_POST['email'] ?? '')));
        $telefono = trim((string) ($_POST['telefono'] ?? ''));
        $password = (string) ($_POST['password'] ?? '');;
        $nomeSquadra = trim((string) ($_POST['nome_squadra'] ?? ''));
        $logoUrl = trim((string) ($_POST['logo_url'] ?? ''));
        $numeroPrevisto = (int) ($_POST['numero_giocatori_previsto'] ?? 0);
        $accettaRegolamento = !empty($_POST['accetta_regolamento']);
        $accettaPrivacy = !empty($_POST['accetta_privacy']);
        $accettaFoto = !empty($_POST['accetta_foto']);

        if ($nome === '' || $cognome === '' || $email === '' || $password === '' || $nomeSquadra === '') {
            $errors[] = 'Compila tutti i campi obbligatori.';
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
            $stmt = $pdo->prepare('INSERT INTO capitani (nome, cognome, email, telefono, password_hash) VALUES (?, ?, ?, ?, ?)');
            $stmt->execute([$nome, $cognome, $email, $telefono ?: null, $hash]);
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
                $squadraValues[] = '#1a3a5c';
            }

            if (table_has_column($pdo, 'squadre', 'logo_url')) {
                $squadraColumns[] = 'logo_url';
                $squadraValues[] = $logoUrl ?: null;
            }

            if (table_has_column($pdo, 'squadre', 'id_capitano')) {
                $squadraColumns[] = 'id_capitano';
                $squadraValues[] = $idCapitano;
            }

            if (table_has_column($pdo, 'squadre', 'capitano')) {
                $squadraColumns[] = 'capitano';
                $squadraValues[] = trim($nome . ' ' . $cognome);
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

            $_SESSION['capitano_id'] = $idCapitano;
            $_SESSION['capitano_auth'] = bin2hex(random_bytes(32));
            header('Location: ' . SITE_URL . '/capitano/dashboard.php?nuova_squadra=1');
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
</head>
<body>
<div class="auth-card">
  <div class="brand">
    <img src="<?= SITE_URL ?>/assets/img/logo_torneo.png" alt="Logo">
    <h1>Registrazione Capitano</h1>
  </div>

  <?php if ($errors): foreach ($errors as $error): ?>
    <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
  <?php endforeach; endif; ?>

  <form method="post" class="admin-form" novalidate>
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(capitano_csrf_token()) ?>">
    <div class="grid2">
      <div class="form-group">
        <label>Nome</label>
        <input type="text" name="nome" value="<?= htmlspecialchars($_POST['nome'] ?? '') ?>" required>
      </div>
      <div class="form-group">
        <label>Cognome</label>
        <input type="text" name="cognome" value="<?= htmlspecialchars($_POST['cognome'] ?? '') ?>" required>
      </div>
    </div>

    <div class="grid2">
      <div class="form-group">
        <label>Email</label>
        <input type="email" name="email" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required>
      </div>
      <div class="form-group">
        <label>Telefono</label>
        <input type="text" name="telefono" value="<?= htmlspecialchars($_POST['telefono'] ?? '') ?>">
      </div>
    </div>

    <div class="form-group">
      <label>Password</label>
      <input type="password" name="password" minlength="8" required>
    </div>

    <div class="form-group">
      <label>Nome squadra</label>
      <input type="text" name="nome_squadra" value="<?= htmlspecialchars($_POST['nome_squadra'] ?? '') ?>" required>
    </div>

    <div class="grid2">
      <div class="form-group">
        <label>Numero giocatori previsti</label>
        <input type="number" name="numero_giocatori_previsto" min="1" max="20" value="<?= htmlspecialchars((string) ($_POST['numero_giocatori_previsto'] ?? 10)) ?>">
      </div>
      <div class="form-group">
        <label>Logo URL</label>
        <input type="url" name="logo_url" value="<?= htmlspecialchars($_POST['logo_url'] ?? '') ?>" placeholder="https://...">
      </div>
    </div>

    <div class="checklist">
      <label class="check-item"><input type="checkbox" name="accetta_regolamento" value="1" required> Accetto il regolamento del torneo.</label>
      <label class="check-item"><input type="checkbox" name="accetta_privacy" value="1" required> Accetto l'informativa privacy.</label>
      <label class="check-item"><input type="checkbox" name="accetta_foto" value="1"> Consento la liberatoria foto (facoltativa).</label>
    </div>

    <div class="form-actions">
      <button type="submit" class="btn btn-gold">Crea account</button>
      <a href="<?= SITE_URL ?>/capitano/login.php" class="btn btn-outline">Ho già un account</a>
    </div>
  </form>
</div>
</body>
</html>
