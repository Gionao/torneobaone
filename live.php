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
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Barlow+Condensed:wght@600;700;800&family=Barlow:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="assets/css/style.css?v=public-menu-5">
<link rel="stylesheet" href="assets/css/public-redesign.css?v=1">

</head>

<body class="public-site public-live">

<?php include __DIR__ . '/shared/header.php'; ?>

<div class="live-container">

<h1 class="live-title"><span class="live-pill"><span aria-hidden="true"></span> Diretta</span><br>Partita live</h1>

<?php if($partita): ?>

<div class="scoreboard">

    <div class="team">

        <?php if(!empty($partita['logo_casa'])): ?>
            <img src="<?= htmlspecialchars($partita['logo_casa']) ?>" alt="" width="180" height="180">
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
            <img src="<?= htmlspecialchars($partita['logo_ospite']) ?>" alt="" width="180" height="180">
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
