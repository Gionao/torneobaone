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
    if (empty($dataNascita) || empty($dataInizio)) {
        return false;
    }

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

    if (!empty($giocatore['email'])) {
        $tokenExists = $pdo->prepare('SELECT id FROM token_verifica WHERE tipo = "email_verifica" AND id_giocatore = ? AND usato_at IS NULL AND invalidato_at IS NULL AND scadenza > NOW() LIMIT 1');
        $tokenExists->execute([$idGiocatore]);

        if ($tokenExists->fetch()) {
            $stato = 'email_da_verificare';
        } else {
            $stato = 'invitato';
        }
    }

    if (!empty($giocatore['email']) && !empty($giocatore['data_nascita']) && is_minorenne($giocatore['data_nascita'], $dataInizio ?? '2000-01-01')) {
        $parentStmt = $pdo->prepare('SELECT id FROM genitori WHERE id_giocatore = ? LIMIT 1');
        $parentStmt->execute([$idGiocatore]);
        if ($parentStmt->fetch()) {
            $stato = 'completo';
        } else {
            $stato = 'in_attesa_genitore';
        }
    }

    if (
        !empty($giocatore['email'])
        && !empty($giocatore['telefono'])
        && !empty($giocatore['data_nascita'])
        && !empty($giocatore['indirizzo'])
        && !is_minorenne($giocatore['data_nascita'], $dataInizio ?? '2000-01-01')
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
