<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';

$pdo = db();

/*
|--------------------------------------------------------------------------
| Partita live
|--------------------------------------------------------------------------
*/

$stmt = $pdo->query("
SELECT
    p.*,
    sc.id AS id_casa,
    sc.nome AS casa,
    sc.logo_url AS logo_casa,
    so.id AS id_ospite,
    so.nome AS ospite,
    so.logo_url AS logo_ospite,
    f.nome AS fase
FROM partite p
JOIN squadre sc ON sc.id = p.id_squadra_casa
JOIN squadre so ON so.id = p.id_squadra_osp
JOIN fasi f ON f.id = p.id_fase
WHERE p.giocata = 0
AND p.data_ora <= NOW()
ORDER BY p.data_ora
LIMIT 1
");

$partita = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$partita) {

    $stmt = $pdo->query("
    SELECT
        p.*,
        sc.id AS id_casa,
        sc.nome AS casa,
        sc.logo_url AS logo_casa,
        so.id AS id_ospite,
        so.nome AS ospite,
        so.logo_url AS logo_ospite,
        f.nome AS fase
    FROM partite p
    JOIN squadre sc ON sc.id = p.id_squadra_casa
    JOIN squadre so ON so.id = p.id_squadra_osp
    JOIN fasi f ON f.id = p.id_fase
    WHERE p.giocata = 0
    ORDER BY p.data_ora
    LIMIT 1
    ");

    $partita = $stmt->fetch(PDO::FETCH_ASSOC);
}

$eventi = [];
$golCasa = 0;
$golOsp = 0;

// Etichette leggibili per ogni tipo di evento (allineate a admin/partite/eventi.php)
$iconeEvento = [
    'gol'                => '⚽',
    'gol_autorete'       => '⚽ (aut.)',
    'ammonizione'        => '🟨',
    'doppia_ammonizione' => '🟨🟨',
    'espulsione'         => '🟥',
    'mvp'                => '⭐',
];

if ($partita) {

    // NOTA: la colonna del tipo evento si chiama "tipo" (non "tipo_evento"),
    // e il nome del giocatore va ricavato con LEFT JOIN su "giocatori"
    // (fallback su "nome_libero" se il giocatore non è censito in rosa).
    $stmt = $pdo->prepare("
        SELECT
            e.*,
            COALESCE(CONCAT(g.nome, ' ', g.cognome), e.nome_libero) AS giocatore_display
        FROM eventi_partita e
        LEFT JOIN giocatori g ON g.id = e.id_giocatore
        WHERE e.id_partita = ?
        ORDER BY e.minuto ASC, e.id ASC
    ");

    $stmt->execute([$partita['id']]);
    $eventi = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($eventi as $e) {

        $tipo = $e['tipo'] ?? null;
        $idSquadra = $e['id_squadra'] ?? null;

        if (!$tipo || !$idSquadra) continue;

        if ($tipo === 'gol') {

            if ((int)$idSquadra === (int)$partita['id_casa']) {
                $golCasa++;
            } else {
                $golOsp++;
            }

        } elseif ($tipo === 'gol_autorete') {

            if ((int)$idSquadra === (int)$partita['id_casa']) {
                $golOsp++;
            } else {
                $golCasa++;
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="it">

<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">

<title>Live | Torneo Baone 2026</title>

<meta http-equiv="refresh" content="10">

<style>

body{
    background:#07131f;
    color:#fff;
    font-family:Arial,Helvetica,sans-serif;
}

.live-container{
    max-width:1200px;
    margin:auto;
    padding:40px 20px;
}

.live-title{
    text-align:center;
    font-size:42px;
    margin-bottom:50px;
}

.scoreboard{
    display:flex;
    justify-content:space-between;
    align-items:center;
    gap:40px;
}

.team{
    flex:1;
    text-align:center;
}

.team img{
    width:180px;
    height:180px;
    object-fit:contain;
    margin-bottom:20px;
}

.team-name{
    font-size:34px;
    font-weight:bold;
}

.score{
    min-width:280px;
    text-align:center;
}

.score-number{
    font-size:110px;
    font-weight:bold;
    line-height:1;
}

.phase{
    margin-top:20px;
    font-size:20px;
    opacity:.8;
}

.info{
    margin-top:50px;
    text-align:center;
    font-size:22px;
    opacity:.8;
}

.refresh{
    margin-top:60px;
    text-align:center;
    font-size:15px;
    opacity:.5;
}

.events{
    margin-top:50px;
    display:flex;
    gap:40px;
}

.events div{
    flex:1;
}

.events h3{
    border-bottom:1px solid rgba(255,255,255,0.2);
    padding-bottom:10px;
    margin-bottom:10px;
}

.event-item{
    margin-bottom:6px;
    font-size:16px;
    opacity:.9;
}

.empty-msg{
    font-size:14px;
    opacity:.5;
}

</style>

</head>

<body>

<div class="live-container">

<h1 class="live-title">⚽ LIVE MATCH</h1>

<?php if($partita): ?>

<div class="scoreboard">

    <div class="team">

        <?php if(!empty($partita['logo_casa'])): ?>
            <img src="<?= htmlspecialchars($partita['logo_casa']) ?>">
        <?php endif; ?>

        <div class="team-name">
            <?= htmlspecialchars($partita['casa']) ?>
        </div>

    </div>

    <div class="score">

        <div class="score-number">
            <?= $golCasa ?> - <?= $golOsp ?>
        </div>

        <div class="phase">
            <?= htmlspecialchars($partita['fase']) ?>
        </div>

    </div>

    <div class="team">

        <?php if(!empty($partita['logo_ospite'])): ?>
            <img src="<?= htmlspecialchars($partita['logo_ospite']) ?>">
        <?php endif; ?>

        <div class="team-name">
            <?= htmlspecialchars($partita['ospite']) ?>
        </div>

    </div>

</div>

<div class="events">

    <div>
        <h3><?= htmlspecialchars($partita['casa']) ?></h3>

        <?php $trovati = false; ?>
        <?php foreach($eventi as $e): ?>
            <?php if(($e['id_squadra'] ?? null) == $partita['id_casa']): ?>
                <?php $trovati = true; ?>
                <div class="event-item">
                    <?= htmlspecialchars((string)($e['minuto'] ?? '')) ?>' -
                    <?= $iconeEvento[$e['tipo'] ?? ''] ?? htmlspecialchars((string)($e['tipo'] ?? '')) ?>
                    <?= !empty($e['giocatore_display']) ? ' - ' . htmlspecialchars((string)$e['giocatore_display']) : '' ?>
                </div>
            <?php endif; ?>
        <?php endforeach; ?>
        <?php if(!$trovati): ?>
            <div class="empty-msg">Nessun evento.</div>
        <?php endif; ?>

    </div>

    <div>
        <h3><?= htmlspecialchars($partita['ospite']) ?></h3>

        <?php $trovati = false; ?>
        <?php foreach($eventi as $e): ?>
            <?php if(($e['id_squadra'] ?? null) == $partita['id_ospite']): ?>
                <?php $trovati = true; ?>
                <div class="event-item">
                    <?= htmlspecialchars((string)($e['minuto'] ?? '')) ?>' -
                    <?= $iconeEvento[$e['tipo'] ?? ''] ?? htmlspecialchars((string)($e['tipo'] ?? '')) ?>
                    <?= !empty($e['giocatore_display']) ? ' - ' . htmlspecialchars((string)$e['giocatore_display']) : '' ?>
                </div>
            <?php endif; ?>
        <?php endforeach; ?>
        <?php if(!$trovati): ?>
            <div class="empty-msg">Nessun evento.</div>
        <?php endif; ?>

    </div>

</div>

<div class="info">

📅 <?= date('d/m/Y H:i', strtotime($partita['data_ora'])) ?>

<?php if(!empty($partita['campo'])): ?>
<br>📍 Campo <?= htmlspecialchars($partita['campo']) ?>
<?php endif; ?>

</div>

<?php else: ?>

<h2 style="text-align:center">
Nessuna partita disponibile.
</h2>

<?php endif; ?>

<div class="refresh">
Aggiornamento automatico ogni 10 secondi
</div>

</div>

</body>
</html>