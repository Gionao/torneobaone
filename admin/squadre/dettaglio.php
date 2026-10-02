<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/db.php';
require_admin();

$pdo = db();
$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT) ?: 0;
$stmt = $pdo->prepare('SELECT s.*, c.nome AS capitano_nome, c.cognome AS capitano_cognome, c.email AS capitano_email, c.email_verificata_at AS capitano_email_verificata_at FROM squadre s LEFT JOIN capitani c ON c.id = s.id_capitano WHERE s.id = ?');
$stmt->execute([$id]);
$squadra = $stmt->fetch();
if (!$squadra) { header('Location: index.php'); exit; }

$playersStmt = $pdo->prepare('SELECT g.*, gen.id AS id_genitore, gen.email AS email_genitore FROM giocatori g LEFT JOIN genitori gen ON gen.id_giocatore = g.id WHERE g.id_squadra = ? AND g.stato_iscrizione <> "annullato" ORDER BY g.cognome, g.nome');
$playersStmt->execute([$id]);
$giocatori = $playersStmt->fetchAll();
$documenti = $pdo->query('SELECT t.codice, t.nome, d.id AS id_documento FROM tipi_documento t JOIN documenti d ON d.id_tipo = t.id WHERE d.versione = (SELECT MAX(d2.versione) FROM documenti d2 WHERE d2.id_tipo = t.id) AND t.codice IN ("privacy", "regolamento", "liberatoria_foto") ORDER BY FIELD(t.codice, "privacy", "regolamento", "liberatoria_foto")')->fetchAll();
$consensoStmt = $pdo->prepare('SELECT 1 FROM consensi WHERE id_documento = ? AND concesso = 1 AND ((id_giocatore = ?) OR (? IS NOT NULL AND id_genitore = ?)) LIMIT 1');
$consensoCapitanoStmt = $pdo->prepare('SELECT 1 FROM consensi WHERE id_documento = ? AND id_capitano = ? AND concesso = 1 LIMIT 1');
$payments = $pdo->prepare('SELECT COALESCE(SUM(CASE WHEN stato = "confermata" THEN importo ELSE 0 END), 0) AS confermato, COALESCE(SUM(CASE WHEN stato = "in_attesa" THEN importo ELSE 0 END), 0) AS attesa FROM transazioni WHERE id_squadra = ?');
$payments->execute([$id]);
$pagamenti = $payments->fetch();
$saldo = (float) $pagamenti['confermato'];
$quota = (float) $squadra['quota_iscrizione'];
$pagamentoOk = $saldo >= $quota;
$checkOk = $pagamentoOk && count($giocatori) > 0 && !empty($squadra['id_capitano']);
if (!empty($squadra['id_capitano'])) {
    if (empty($squadra['capitano_email_verificata_at'])) $checkOk = false;
    foreach ($documenti as $doc) {
        if (!in_array($doc['codice'], ['privacy', 'regolamento'], true)) continue;
        $consensoCapitanoStmt->execute([(int) $doc['id_documento'], (int) $squadra['id_capitano']]);
        if (!$consensoCapitanoStmt->fetchColumn()) $checkOk = false;
    }
}
foreach ($giocatori as $giocatore) {
    if ($giocatore['stato_iscrizione'] !== 'completo') $checkOk = false;
    foreach ($documenti as $doc) {
        if (!in_array($doc['codice'], ['privacy', 'regolamento'], true)) continue;
        $consensoStmt->execute([(int)$doc['id_documento'], (int)$giocatore['id'], $giocatore['id_genitore'], $giocatore['id_genitore']]);
        if (!$consensoStmt->fetchColumn()) $checkOk = false;
    }
}

$pageTitle = 'Check squadra — ' . $squadra['nome'];
require_once __DIR__ . '/../_header.php';
?>
<div class="admin-page-header"><h1>Check squadra: <?= htmlspecialchars($squadra['nome']) ?></h1><p><a href="index.php" style="color:var(--muted)">← Torna alle squadre</a> · <a href="<?= SITE_URL ?>/admin/giocatori/index.php?squadra=<?= (int) $id ?>" style="color:var(--muted)">Gestisci rosa</a></p></div>
<div class="alert alert-<?= $checkOk ? 'success' : 'error' ?>"><?= $checkOk ? 'Squadra pronta: registrazioni, consensi obbligatori e pagamento sono regolari.' : 'Controllo incompleto: verifica registrazioni, consensi obbligatori e pagamento qui sotto.' ?></div>
<section class="admin-form" style="max-width:none;margin-bottom:1.5rem">
<h2>Pagamento</h2>
<p><span class="state-pill <?= $pagamentoOk ? 'ok' : 'bad' ?>"><?= $pagamentoOk ? 'Regolare' : 'Da completare' ?></span>
<strong style="margin-left:.6rem">€<?= number_format($saldo, 2, ',', '.') ?></strong> versati su €<?= number_format($quota, 2, ',', '.') ?>
<?php if ((float) $pagamenti['attesa'] > 0): ?> · €<?= number_format((float) $pagamenti['attesa'], 2, ',', '.') ?> in attesa<?php endif; ?></p>
<a class="btn btn-sm btn-outline" href="<?= SITE_URL ?>/admin/pagamenti/inserisci.php?squadra=<?= (int) $id ?>">Registra pagamento</a>
</section>
<h2>Capitano e giocatori</h2>
<?php if (!$giocatori && empty($squadra['id_capitano'])): ?><p class="empty-msg">Capitano non assegnato e nessun giocatore in rosa.</p><?php else: ?>
<div class="admin-table-wrap"><table class="admin-table"><thead><tr><th>Persona</th><th>Registrazione</th><?php foreach ($documenti as $doc): ?><th><?= htmlspecialchars($doc['nome']) ?><?= $doc['codice'] === 'liberatoria_foto' ? ' (se richiesta)' : '' ?></th><?php endforeach; ?></tr></thead><tbody>
<?php if (!empty($squadra['id_capitano'])): ?>
<tr class="captain-check-row"><td><strong>👑 <?= htmlspecialchars(trim((string) $squadra['capitano_nome'] . ' ' . (string) $squadra['capitano_cognome'])) ?></strong><br><span class="captain-check-badge">CAPITANO</span><br><small><?= htmlspecialchars($squadra['capitano_email'] ?? 'Email mancante') ?></small></td>
<td><span class="state-pill <?= !empty($squadra['capitano_email_verificata_at']) ? 'ok' : 'bad' ?>"><?= !empty($squadra['capitano_email_verificata_at']) ? 'Registrato' : 'Email non verificata' ?></span></td>
<?php foreach ($documenti as $doc): $consensoCapitanoStmt->execute([(int)$doc['id_documento'], (int)$squadra['id_capitano']]); $ok = (bool)$consensoCapitanoStmt->fetchColumn(); ?>
<td><span class="state-pill <?= $ok ? 'ok' : ($doc['codice'] === 'liberatoria_foto' ? 'warn' : 'bad') ?>"><?= $ok ? 'Accettato' : ($doc['codice'] === 'liberatoria_foto' ? 'Non presente' : 'Mancante') ?></span></td>
<?php endforeach; ?></tr>
<?php else: ?><tr class="captain-check-row"><td><strong>👑 Capitano</strong></td><td colspan="<?= 1 + count($documenti) ?>"><span class="state-pill bad">Non assegnato</span></td></tr><?php endif; ?>
<?php if (!$giocatori): ?><tr><td colspan="<?= 2 + count($documenti) ?>" class="empty-msg">Nessun giocatore in rosa.</td></tr><?php endif; ?>
<?php foreach ($giocatori as $g): ?>
<tr><td><strong><?= htmlspecialchars($g['cognome'] . ' ' . $g['nome']) ?></strong><br><small><?= htmlspecialchars($g['email'] ?? 'Email mancante') ?></small></td>
<td><span class="state-pill <?= $g['stato_iscrizione'] === 'completo' ? 'ok' : 'bad' ?>"><?= $g['stato_iscrizione'] === 'completo' ? 'Registrato' : htmlspecialchars(ucfirst(str_replace('_', ' ', $g['stato_iscrizione']))) ?></span></td>
<?php foreach ($documenti as $doc): $consensoStmt->execute([(int)$doc['id_documento'], (int)$g['id'], $g['id_genitore'], $g['id_genitore']]); $ok = (bool)$consensoStmt->fetchColumn(); ?>
<td><span class="state-pill <?= $ok ? 'ok' : ($doc['codice'] === 'liberatoria_foto' ? 'warn' : 'bad') ?>"><?= $ok ? 'Accettato' : ($doc['codice'] === 'liberatoria_foto' ? 'Non presente' : 'Mancante') ?></span></td>
<?php endforeach; ?></tr>
<?php endforeach; ?></tbody></table></div><?php endif; ?>
<style>
.captain-check-row { background:rgba(201,168,76,.09); box-shadow:inset 4px 0 0 var(--gold); }
.captain-check-badge { display:inline-block;margin:.15rem 0 .3rem;padding:.12rem .4rem;border-radius:4px;background:var(--gold);color:#0d1b2a;font-size:.65rem;font-weight:800;letter-spacing:.06em; }
</style>
<?php require_once __DIR__ . '/../_footer.php'; ?>
