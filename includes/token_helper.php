<?php
// includes/token_helper.php

declare(strict_types=1);

require_once __DIR__ . '/db.php';

function generate_token(): string
{
    return bin2hex(random_bytes(32));
}

function hash_token(string $token): string
{
    return hash('sha256', $token);
}

function build_site_link(string $path, string $token): string
{
    $normalized = '/' . ltrim($path, '/');
    return SITE_URL . $normalized . '?token=' . rawurlencode($token);
}

function create_verification_token(PDO $pdo, string $tipo, ?int $idCapitano = null, ?int $idGiocatore = null, ?int $idGenitore = null, int $hours = 72): ?string
{
    $token = generate_token();
    $expiresAt = (new DateTimeImmutable('+ ' . max(1, $hours) . ' hours'))->format('Y-m-d H:i:s');

    $sql = 'INSERT INTO token_verifica (tipo, token, id_capitano, id_giocatore, id_genitore, scadenza, usato_at, invalidato_at, created_at)
            VALUES (?, ?, ?, ?, ?, ?, NULL, NULL, CURRENT_TIMESTAMP)';

    $stmt = $pdo->prepare($sql);
    $ok = $stmt->execute([$tipo, hash_token($token), $idCapitano, $idGiocatore, $idGenitore, $expiresAt]);

    return $ok ? $token : null;
}

function get_valid_token(PDO $pdo, string $token): ?array
{
    $hashed = hash_token($token);
    $stmt = $pdo->prepare('SELECT * FROM token_verifica WHERE token = ? AND usato_at IS NULL AND invalidato_at IS NULL AND scadenza > NOW() LIMIT 1');
    $stmt->execute([$hashed]);
    $row = $stmt->fetch();

    return $row ?: null;
}

function consume_token(PDO $pdo, string $token): bool
{
    $hashed = hash_token($token);
    $stmt = $pdo->prepare('UPDATE token_verifica SET usato_at = NOW() WHERE token = ? AND usato_at IS NULL AND invalidato_at IS NULL AND scadenza > NOW() LIMIT 1');
    return $stmt->execute([$hashed]);
}

function invalidate_previous_tokens(PDO $pdo, string $tipo, ?int $idGiocatore = null, ?int $idGenitore = null): void
{
    if ($idGiocatore !== null) {
        $stmt = $pdo->prepare('UPDATE token_verifica SET invalidato_at = NOW() WHERE tipo = ? AND id_giocatore = ? AND usato_at IS NULL AND invalidato_at IS NULL');
        $stmt->execute([$tipo, $idGiocatore]);
        return;
    }
    if ($idGenitore !== null) {
        $stmt = $pdo->prepare('UPDATE token_verifica SET invalidato_at = NOW() WHERE tipo = ? AND id_genitore = ? AND usato_at IS NULL AND invalidato_at IS NULL');
        $stmt->execute([$tipo, $idGenitore]);
    }
}

function premio_link_token(string $token): string
{
    return build_site_link('/iscrizione/giocatore.php', $token);
}
