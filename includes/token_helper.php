<?php
// includes/token_helper.php

declare(strict_types=1);

require_once __DIR__ . '/db.php';

function generate_token(): string
{
    return bin2hex(random_bytes(32));
}

function create_verification_token(PDO $pdo, string $tipo, ?int $idCapitano = null, ?int $idGiocatore = null, ?int $idGenitore = null, int $hours = 72): ?string
{
    $token = generate_token();
    $expiresAt = (new DateTimeImmutable('+ ' . max(1, $hours) . ' hours'))->format('Y-m-d H:i:s');

    $sql = 'INSERT INTO token_verifica (tipo, token, id_capitano, id_giocatore, id_genitore, scadenza, usato_at, invalidato_at, created_at)
            VALUES (?, ?, ?, ?, ?, ?, NULL, NULL, CURRENT_TIMESTAMP)';

    $stmt = $pdo->prepare($sql);
    $ok = $stmt->execute([$tipo, $token, $idCapitano, $idGiocatore, $idGenitore, $expiresAt]);

    return $ok ? $token : null;
}

function get_valid_token(PDO $pdo, string $token): ?array
{
    $stmt = $pdo->prepare('SELECT * FROM token_verifica WHERE token = ? AND usato_at IS NULL AND invalidato_at IS NULL AND scadenza > NOW() LIMIT 1');
    $stmt->execute([$token]);
    $row = $stmt->fetch();

    return $row ?: null;
}

function consume_token(PDO $pdo, string $token): bool
{
    $stmt = $pdo->prepare('UPDATE token_verifica SET usato_at = NOW() WHERE token = ? AND usato_at IS NULL AND invalidato_at IS NULL AND scadenza > NOW() LIMIT 1');
    return $stmt->execute([$token]);
}

function invalidate_previous_tokens(PDO $pdo, string $tipo, ?int $idGiocatore = null): void
{
    if ($idGiocatore === null) {
        return;
    }

    $stmt = $pdo->prepare('UPDATE token_verifica SET invalidato_at = NOW() WHERE tipo = ? AND id_giocatore = ? AND usato_at IS NULL AND invalidato_at IS NULL');
    $stmt->execute([$tipo, $idGiocatore]);
}

function premio_link_token(string $token): string
{
    return SITE_URL . '/iscrizione/giocatore.php?token=' . rawurlencode($token);
}
