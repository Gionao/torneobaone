<?php
// includes/classifica_helper.php
// ============================================================
//  Ricalcolo classifica — chiamato dopo ogni INSERT/UPDATE risultato
// ============================================================

declare(strict_types=1);

/**
 * Svuota la tabella classifica e la riscrive da zero
 * basandosi su tutte le partite del girone già giocate.
 * Regolamento: V=3pt, P=1pt, S=0pt.
 */
function ricalcola_classifica(PDO $pdo): void
{
    // Raccoglie tutte le partite di girone già giocate
    $stmt = $pdo->query("
        SELECT
            p.id_squadra_casa,
            p.id_squadra_osp,
            p.gol_casa,
            p.gol_osp
        FROM partite p
        JOIN fasi f ON f.id = p.id_fase
        WHERE p.giocata = 1
          AND p.gol_casa IS NOT NULL
          AND f.nome = 'Girone'
    ");
    $partite = $stmt->fetchAll();

    // Accumula statistiche
    $stats = [];

    $init = function(int $id) use (&$stats): void {
        if (!isset($stats[$id])) {
            $stats[$id] = [
                'punti' => 0, 'giocate' => 0, 'vinte' => 0,
                'pareggiate' => 0, 'perse' => 0,
                'gol_fatti' => 0, 'gol_subiti' => 0, 'diff_reti' => 0,
            ];
        }
    };

    foreach ($partite as $p) {
        $c = (int)$p['id_squadra_casa'];
        $o = (int)$p['id_squadra_osp'];
        $gc = (int)$p['gol_casa'];
        $go = (int)$p['gol_osp'];

        $init($c); $init($o);

        $stats[$c]['giocate']++;
        $stats[$o]['giocate']++;
        $stats[$c]['gol_fatti']   += $gc;
        $stats[$c]['gol_subiti']  += $go;
        $stats[$o]['gol_fatti']   += $go;
        $stats[$o]['gol_subiti']  += $gc;
        $stats[$c]['diff_reti']   += $gc - $go;
        $stats[$o]['diff_reti']   += $go - $gc;

        if ($gc > $go) {
            $stats[$c]['vinte']++;  $stats[$c]['punti'] += 3;
            $stats[$o]['perse']++;
        } elseif ($gc < $go) {
            $stats[$o]['vinte']++;  $stats[$o]['punti'] += 3;
            $stats[$c]['perse']++;
        } else {
            $stats[$c]['pareggiate']++;  $stats[$c]['punti']++;
            $stats[$o]['pareggiate']++;  $stats[$o]['punti']++;
        }
    }

    // Aggiungi statistiche disciplinari (ammonizioni/espulsioni)
    $discStmt = $pdo->query("
        SELECT id_squadra,
               SUM(tipo IN ('ammonizione','doppia_ammonizione')) AS amm,
               SUM(tipo = 'espulsione')                          AS esp
        FROM eventi_partita
        GROUP BY id_squadra
    ");
    $disc = $discStmt->fetchAll(PDO::FETCH_KEY_PAIR) ?: [];
    // Richiede fetchAll normale e poi indexing
    $discBySquadra = [];
    $pdo->query("
        SELECT id_squadra,
               SUM(tipo IN ('ammonizione','doppia_ammonizione')) AS amm,
               SUM(tipo = 'espulsione')                          AS esp
        FROM eventi_partita
        GROUP BY id_squadra
    ")->fetchAll() ?: [];

    $discRaw = $pdo->query("
        SELECT id_squadra,
               SUM(CASE WHEN tipo IN ('ammonizione','doppia_ammonizione') THEN 1 ELSE 0 END) AS amm,
               SUM(CASE WHEN tipo = 'espulsione' THEN 1 ELSE 0 END) AS esp
        FROM eventi_partita
        GROUP BY id_squadra
    ")->fetchAll();
    foreach ($discRaw as $d) {
        $discBySquadra[(int)$d['id_squadra']] = $d;
    }

    // Riscrivi la tabella classifica in transazione
    $pdo->beginTransaction();
    try {
        // Assicurati che ogni squadra con partite abbia una riga
        $upsert = $pdo->prepare("
            INSERT INTO classifica
                (id_squadra, punti, giocate, vinte, pareggiate, perse,
                 gol_fatti, gol_subiti, diff_reti, ammonizioni, espulsioni)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
                punti       = VALUES(punti),
                giocate     = VALUES(giocate),
                vinte       = VALUES(vinte),
                pareggiate  = VALUES(pareggiate),
                perse       = VALUES(perse),
                gol_fatti   = VALUES(gol_fatti),
                gol_subiti  = VALUES(gol_subiti),
                diff_reti   = VALUES(diff_reti),
                ammonizioni = VALUES(ammonizioni),
                espulsioni  = VALUES(espulsioni)
        ");

        // Azzera le squadre non più presenti
        if (!empty($stats)) {
            $placeholders = implode(',', array_fill(0, count($stats), '?'));
            $pdo->prepare("
                UPDATE classifica SET
                    punti=0, giocate=0, vinte=0, pareggiate=0, perse=0,
                    gol_fatti=0, gol_subiti=0, diff_reti=0
                WHERE id_squadra NOT IN ($placeholders)
            ")->execute(array_keys($stats));
        }

        foreach ($stats as $sqId => $s) {
            $disc = $discBySquadra[$sqId] ?? ['amm' => 0, 'esp' => 0];
            $upsert->execute([
                $sqId,
                $s['punti'], $s['giocate'], $s['vinte'],
                $s['pareggiate'], $s['perse'],
                $s['gol_fatti'], $s['gol_subiti'], $s['diff_reti'],
                (int)($disc['amm'] ?? 0),
                (int)($disc['esp'] ?? 0),
            ]);
        }

        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
}