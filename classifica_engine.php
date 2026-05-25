<?php
// includes/classifica_engine.php
// ============================================================
//  Motore di ricalcolo classifica
//  Chiamare ricalcola_classifica() dopo ogni INSERT/UPDATE risultato
// ============================================================

declare(strict_types=1);

require_once __DIR__ . '/db.php';

/**
 * Ricalcola completamente la tabella `classifica`
 * a partire dalle partite del GIRONE (id_fase = 1) già giocate.
 *
 * La funzione:
 *  1. Recupera tutte le partite di girone con risultato
 *  2. Calcola punti/statistiche per ogni squadra
 *  3. Svuota e riscrive la tabella `classifica`
 *
 * @return bool  true se tutto ok, false in caso di errore
 */
function ricalcola_classifica(): bool
{
    $pdo = db();

    try {
        $pdo->beginTransaction();

        // 1. Recupera tutte le partite di girone già giocate
        $stmt = $pdo->prepare(
            'SELECT id, id_squadra_casa, id_squadra_osp, gol_casa, gol_osp
             FROM partite
             WHERE id_fase = 1 AND giocata = 1
               AND gol_casa IS NOT NULL AND gol_osp IS NOT NULL'
        );
        $stmt->execute();
        $partite = $stmt->fetchAll();

        // 2. Recupera tutte le squadre registrate
        $squadre_stmt = $pdo->query('SELECT id FROM squadre');
        $squadre_ids  = $squadre_stmt->fetchAll(PDO::FETCH_COLUMN);

        // 3. Inizializza struttura dati per ogni squadra
        $stats = [];
        foreach ($squadre_ids as $sid) {
            $stats[$sid] = [
                'punti'      => 0, 'giocate'   => 0,
                'vinte'      => 0, 'pareggiate' => 0, 'perse'     => 0,
                'gol_fatti'  => 0, 'gol_subiti' => 0, 'diff_reti' => 0,
                'ammonizioni'=> 0, 'espulsioni' => 0,
            ];
        }

        // 4. Elabora ogni partita
        foreach ($partite as $p) {
            $casa = $p['id_squadra_casa'];
            $osp  = $p['id_squadra_osp'];
            $gc   = (int)$p['gol_casa'];
            $go   = (int)$p['gol_osp'];

            if (!isset($stats[$casa]) || !isset($stats[$osp])) continue;

            // Squadra di casa
            $stats[$casa]['giocate']++;
            $stats[$casa]['gol_fatti']  += $gc;
            $stats[$casa]['gol_subiti'] += $go;

            // Squadra ospite
            $stats[$osp]['giocate']++;
            $stats[$osp]['gol_fatti']  += $go;
            $stats[$osp]['gol_subiti'] += $gc;

            if ($gc > $go) {
                // Vittoria casa
                $stats[$casa]['punti'] += 3;
                $stats[$casa]['vinte']++;
                $stats[$osp]['perse']++;
            } elseif ($gc < $go) {
                // Vittoria ospite
                $stats[$osp]['punti'] += 3;
                $stats[$osp]['vinte']++;
                $stats[$casa]['perse']++;
            } else {
                // Pareggio
                $stats[$casa]['punti'] += 1;
                $stats[$osp]['punti']  += 1;
                $stats[$casa]['pareggiate']++;
                $stats[$osp]['pareggiate']++;
            }
        }

        // 5. Aggiungi cartellini dalle eventi_partita (solo girone)
        $card_stmt = $pdo->prepare(
            'SELECT ep.id_squadra, ep.tipo
             FROM eventi_partita ep
             JOIN partite p ON p.id = ep.id_partita
             WHERE p.id_fase = 1 AND p.giocata = 1
               AND ep.tipo IN (\'ammonizione\', \'espulsione\', \'doppia_ammonizione\')'
        );
        $card_stmt->execute();
        foreach ($card_stmt->fetchAll() as $ev) {
            $sid = $ev['id_squadra'];
            if (!isset($stats[$sid])) continue;
            if ($ev['tipo'] === 'ammonizione') {
                $stats[$sid]['ammonizioni']++;
            } elseif ($ev['tipo'] === 'espulsione' || $ev['tipo'] === 'doppia_ammonizione') {
                $stats[$sid]['espulsioni']++;
            }
        }

        // 6. Calcola differenza reti
        foreach ($stats as $sid => &$s) {
            $s['diff_reti'] = $s['gol_fatti'] - $s['gol_subiti'];
        }
        unset($s);

        // 7. Svuota e riscrivi tabella classifica
        $pdo->exec('DELETE FROM classifica');

        $insert = $pdo->prepare(
            'INSERT INTO classifica
               (id_squadra, punti, giocate, vinte, pareggiate, perse,
                gol_fatti, gol_subiti, diff_reti, ammonizioni, espulsioni)
             VALUES
               (:id_squadra, :punti, :giocate, :vinte, :pareggiate, :perse,
                :gol_fatti, :gol_subiti, :diff_reti, :ammonizioni, :espulsioni)'
        );

        foreach ($stats as $sid => $s) {
            $insert->execute(array_merge([':id_squadra' => $sid], array_combine(
                array_map(fn($k) => ":$k", array_keys($s)),
                array_values($s)
            )));
        }

        $pdo->commit();
        return true;

    } catch (Exception $e) {
        $pdo->rollBack();
        if (DEBUG_MODE) {
            error_log('Errore ricalcolo classifica: ' . $e->getMessage());
        }
        return false;
    }
}
