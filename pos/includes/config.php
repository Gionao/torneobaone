<?php
declare(strict_types=1);

// ─── Configurazione POS ───────────────────────────────────────────────────

// Modalità campetto:
//   'separate'  → campetto è una destinazione separata senza numero tavolo
//   'threshold' → tavoli con numero >= CAMPETTO_THRESHOLD sono automaticamente campetto
define('CAMPETTO_MODE', 'separate');

// Usato solo se CAMPETTO_MODE = 'threshold'
define('CAMPETTO_THRESHOLD', 50);

// Numero massimo tavoli area ristorante (validazione input)
define('MAX_TABLE_NUMBER', 99);

/**
 * Etichetta testuale destinazione (storico / statistiche).
 */
function pos_destination_label(string $type, ?int $tableNumber): string
{
    return match ($type) {
        'tavolo'   => $tableNumber !== null ? 'Tavolo ' . $tableNumber : 'Tavolo',
        'asporto'  => 'Asporto',
        'campetto' => $tableNumber !== null ? 'Campetto ' . $tableNumber : 'Campetto',
        default    => ucfirst($type),
    };
}

/**
 * Classe CSS badge destinazione.
 */
function pos_destination_badge_class(string $type): string
{
    return match ($type) {
        'tavolo'   => 'pos-badge-dest-tavolo',
        'asporto'  => 'pos-badge-dest-asporto',
        'campetto' => 'pos-badge-dest-campetto',
        default    => 'pos-badge-dest-tavolo',
    };
}

/**
 * Testo badge con emoji (UI operatore / display).
 */
function pos_destination_badge_text(string $type, ?int $tableNumber): string
{
    return match ($type) {
        'tavolo'   => '🪑 TAVOLO' . ($tableNumber !== null ? ' ' . $tableNumber : ''),
        'asporto'  => '🥡 ASPORTO',
        'campetto' => '⚽ CAMPETTO' . ($tableNumber !== null ? ' ' . $tableNumber : ''),
        default    => strtoupper($type),
    };
}
