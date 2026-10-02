<?php
// includes/iscrizione_helper.php

declare(strict_types=1);

require_once __DIR__ . '/db.php';

function get_active_torneo_start_date(PDO $pdo): ?string
{
    $stmt = $pdo->query('SELECT data_inizio FROM tornei WHERE attivo = 1 ORDER BY id DESC LIMIT 1');
    $row = $stmt->fetch();
    return $row['data_inizio'] ?? null;
}

function is_minorenne(?string $dataNascita, ?string $dataInizio): bool
{
    if (empty($dataNascita)) {
        return false;
    }
    $dataInizio = $dataInizio ?: date('Y-m-d');

    try {
        $birth = new DateTimeImmutable($dataNascita);
        $start = new DateTimeImmutable($dataInizio);
        $diff = $start->diff($birth);
        return (int) $diff->format('%y') < 18;
    } catch (Exception $e) {
        return false;
    }
}

function ricalcola_stato_pagamento(PDO $pdo, int $idSquadra): void
{
    $stmt = $pdo->prepare('SELECT quota_iscrizione FROM squadre WHERE id = ? LIMIT 1');
    $stmt->execute([$idSquadra]);
    $squadra = $stmt->fetch();
    if (!$squadra) {
        return;
    }

    $quota = (float) ($squadra['quota_iscrizione'] ?? 0);
    $sumStmt = $pdo->prepare('SELECT COALESCE(SUM(importo), 0) AS totale FROM transazioni WHERE id_squadra = ? AND stato = "confermata"');
    $sumStmt->execute([$idSquadra]);
    $totale = (float) ($sumStmt->fetchColumn() ?: 0);

    if ($totale <= 0.0) {
        $stato = 'non_pagato';
    } elseif ($totale < $quota) {
        $stato = 'parziale';
    } else {
        $stato = 'pagato';
    }

    $upd = $pdo->prepare('UPDATE squadre SET stato_pagamento = ? WHERE id = ?');
    $upd->execute([$stato, $idSquadra]);
}

function ricalcola_stato_squadra(PDO $pdo, int $idSquadra): void
{
    $stmt = $pdo->prepare('SELECT COUNT(*) AS tot, SUM(CASE WHEN stato_iscrizione = "completo" THEN 1 ELSE 0 END) AS completi FROM giocatori WHERE id_squadra = ? AND stato_iscrizione <> "annullato"');
    $stmt->execute([$idSquadra]);
    $row = $stmt->fetch();

    $tot = (int) ($row['tot'] ?? 0);
    $completi = (int) ($row['completi'] ?? 0);

    if ($tot === 0) {
        $stato = 'bozza';
    } elseif ($tot > 0 && $tot === $completi) {
        $stato = 'completa';
    } else {
        $stato = 'in_corso';
    }

    $upd = $pdo->prepare('UPDATE squadre SET stato_iscrizione = ? WHERE id = ?');
    $upd->execute([$stato, $idSquadra]);
}

function avanza_stato_giocatore(PDO $pdo, int $idGiocatore): void
{
    $select = 'SELECT g.* FROM giocatori g INNER JOIN squadre s ON s.id = g.id_squadra WHERE g.id = ? LIMIT 1';
    $columns = [];
    if (table_has_column($pdo, 'squadre', 'id_capitano')) {
        $columns[] = 's.id_capitano';
    }
    if (table_has_column($pdo, 'squadre', 'id_torneo')) {
        $columns[] = 's.id_torneo';
    }
    if (!empty($columns)) {
        $select = 'SELECT g.*, ' . implode(', ', $columns) . ' FROM giocatori g INNER JOIN squadre s ON s.id = g.id_squadra WHERE g.id = ? LIMIT 1';
    }

    $stmt = $pdo->prepare($select);
    $stmt->execute([$idGiocatore]);
    $giocatore = $stmt->fetch();

    if (!$giocatore) {
        return;
    }

    $dataInizio = get_active_torneo_start_date($pdo);
    $stato = 'invitato';
    $verifiedStmt = $pdo->prepare('SELECT id FROM token_verifica WHERE tipo = "email_verifica" AND id_giocatore = ? AND usato_at IS NOT NULL LIMIT 1');
    $verifiedStmt->execute([$idGiocatore]);
    $emailVerificata = (bool) $verifiedStmt->fetchColumn();
    $parentStmt = $pdo->prepare('SELECT id FROM genitori WHERE id_giocatore = ? LIMIT 1');
    $parentStmt->execute([$idGiocatore]);
    $parent = $parentStmt->fetch();

    if (!empty($giocatore['email'])) {
        $tokenExists = $pdo->prepare('SELECT id FROM token_verifica WHERE tipo = "email_verifica" AND id_giocatore = ? AND usato_at IS NULL AND invalidato_at IS NULL AND scadenza > NOW() LIMIT 1');
        $tokenExists->execute([$idGiocatore]);

        if ($tokenExists->fetch()) {
            $stato = 'email_da_verificare';
        } else {
            $stato = 'invitato';
        }
    }

    $richiedeConsensoGenitore = $parent || (
        !empty($giocatore['email'])
        && !empty($giocatore['data_nascita'])
        && is_minorenne($giocatore['data_nascita'], $dataInizio)
    );
    if ($richiedeConsensoGenitore) {
        if (!$emailVerificata) {
            $stato = 'email_da_verificare';
        } elseif ($parent) {
            $consentStmt = $pdo->prepare('SELECT COUNT(DISTINCT c.id_documento) FROM consensi c JOIN documenti d ON d.id = c.id_documento JOIN tipi_documento t ON t.id = d.id_tipo WHERE c.id_genitore = ? AND t.codice IN ("privacy", "regolamento")');
            $consentStmt->execute([(int) $parent['id']]);
            $stato = (int) $consentStmt->fetchColumn() >= 2 ? 'completo' : 'in_attesa_genitore';
        } else {
            $stato = 'in_attesa_genitore';
        }
    }

    if (
        $emailVerificata
        && !$parent
        && !empty($giocatore['email'])
        && !empty($giocatore['telefono'])
        && !empty($giocatore['data_nascita'])
        && !empty($giocatore['indirizzo'])
        && !is_minorenne($giocatore['data_nascita'], $dataInizio)
    ) {
        $stato = 'completo';
    }

    $upd = $pdo->prepare('UPDATE giocatori SET stato_iscrizione = ? WHERE id = ?');
    $upd->execute([$stato, $idGiocatore]);

    $idSquadra = (int) ($giocatore['id_squadra'] ?? 0);
    if ($idSquadra > 0) {
        ricalcola_stato_squadra($pdo, $idSquadra);
    }
}

function registra_consenso(PDO $pdo, string $codiceDocumento, string $soggetto, int $idSoggetto, string $ip, string $userAgent): void
{
    $stmt = $pdo->prepare('SELECT d.id FROM documenti d JOIN tipi_documento t ON t.id = d.id_tipo WHERE t.codice = ? ORDER BY d.versione DESC LIMIT 1');
    $stmt->execute([$codiceDocumento]);
    $idDocumento = $stmt->fetchColumn();
    if (!$idDocumento) {
        return;
    }
    $colonna = in_array($soggetto, ['id_capitano', 'id_giocatore', 'id_genitore'], true) ? $soggetto : null;
    if ($colonna === null) {
        return;
    }
    $sql = 'INSERT INTO consensi (id_documento, ' . $colonna . ', concesso, ip_address, user_agent) '
        . 'SELECT ?, ?, 1, ?, ? WHERE NOT EXISTS '
        . '(SELECT 1 FROM consensi WHERE id_documento = ? AND ' . $colonna . ' = ?)';
    $pdo->prepare($sql)->execute([(int) $idDocumento, $idSoggetto, $ip ?: null, substr($userAgent, 0, 255), (int) $idDocumento, $idSoggetto]);
}

function etichetta_stato_iscrizione(string $stato): string
{
    return [
        'completo' => 'Iscrizione confermata', 'in_attesa_genitore' => 'In attesa del genitore',
        'email_da_verificare' => 'Email da verificare', 'invitato' => 'Invito inviato',
        'non_registrato' => 'Da completare', 'annullato' => 'Annullato',
    ][$stato] ?? ucfirst(str_replace('_', ' ', $stato));
}

function etichetta_stato_pagamento(string $stato): string
{
    return ['non_pagato' => 'Da pagare', 'parziale' => 'Pagamento parziale', 'pagato' => 'Pagato'][$stato] ?? ucfirst(str_replace('_', ' ', $stato));
}
