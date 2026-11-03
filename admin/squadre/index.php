<?php
// admin/squadre/index.php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/db.php';
require_admin();

$pdo = db();
$msg = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);

$squadre = $pdo->query("
    SELECT s.*,
           COUNT(DISTINCT g.id) AS num_giocatori,
           COALESCE(c.punti, 0) AS punti,
           CONCAT(COALESCE(cap.nome, ''), ' ', COALESCE(cap.cognome, '')) AS capitano_nome
    FROM squadre s
    LEFT JOIN giocatori g ON g.id_squadra = s.id
    LEFT JOIN classifica c ON c.id_squadra = s.id
    LEFT JOIN capitani cap ON cap.id = s.id_capitano
    GROUP BY s.id
    ORDER BY s.nome
")->fetchAll();

$pageTitle = 'Gestione Squadre';
require_once __DIR__ . '/../_header.php';
?>

<div class="admin-page-header">
  <h1>🛡️ Gestione Squadre</h1>
  <p>CRUD completo per le squadre partecipanti</p>
</div>

<?php if ($msg): ?>
  <div class="alert alert-<?= htmlspecialchars($msg['type']) ?>"><?= htmlspecialchars($msg['text']) ?></div>
<?php endif; ?>

<div class="qa-grid" style="margin-bottom:1.5rem;">
  <a href="form.php" class="qa-btn">+ Nuova Squadra</a>
</div>

<?php if (empty($squadre)): ?>
  <p class="empty-msg">Nessuna squadra inserita.</p>
<?php else: ?>
<div class="admin-table-wrap tb-table-wrap">
  <table class="admin-table tb-table">
    <thead>
      <tr>
        <th>Logo</th>
        <th>Colore</th>
        <th>Nome Squadra</th>
        <th>Capitano</th>
        <th>Giocatori</th>
        <th>Punti</th>
        <th>Autorizzata</th>
        <th>Azioni</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($squadre as $s): ?>
      <?php
        $logoSrc = '';
        if (!empty($s['logo_url'])) {
            $logoSrc = preg_match('#^https?://#', $s['logo_url'])
                ? $s['logo_url']
                : SITE_URL . '/' . ltrim($s['logo_url'], '/');
        }
      ?>
      <tr>
        <td>
          <?php if ($logoSrc): ?>
            <img src="<?= htmlspecialchars($logoSrc) ?>" alt=""
                 class="admin-team-logo">
          <?php else: ?>
            <span style="color:var(--muted);font-size:.75rem;">—</span>
          <?php endif; ?>
        </td>
        <td>
          <span style="display:inline-block;width:20px;height:20px;border-radius:4px;background:<?= htmlspecialchars($s['colore']) ?>;border:1px solid rgba(255,255,255,.15);"></span>
        </td>
        <td><strong><?= htmlspecialchars($s['nome']) ?></strong></td>
        <td style="color:var(--muted)"><?= htmlspecialchars(trim((string) ($s['capitano_nome'] ?? '')) ?: '—') ?></td>
        <td><?= $s['num_giocatori'] ?></td>
        <td class="pts" style="font-size:1rem;"><?= $s['punti'] ?></td>
        <td><span class="state-pill <?= !empty($s['autorizzata']) ? 'ok' : 'bad' ?>"><?= !empty($s['autorizzata']) ? 'Sì' : 'No' ?></span></td>
        <td>
          <div class="actions">
            <a href="dettaglio.php?id=<?= $s['id'] ?>" class="btn btn-sm btn-gold">Check squadra</a>
            <a href="form.php?id=<?= $s['id'] ?>" class="btn btn-sm btn-outline">Modifica</a>
            <a href="<?= ADMIN_PATH ?>/giocatori/index.php?squadra=<?= $s['id'] ?>" class="btn btn-sm btn-outline">Rosa</a>
            <a href="delete.php?id=<?= $s['id'] ?>&csrf=<?= csrf_token() ?>"
               class="btn btn-sm btn-red"
               onclick="return confirm('Eliminare questa squadra e tutti i dati correlati?')">Elimina</a>
          </div>
        </td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>
<?php endif; ?>

<style>
.admin-team-logo { width:28px; height:28px; object-fit:contain; border-radius:4px; vertical-align:middle; }
</style>

<?php require_once __DIR__ . '/../_footer.php'; ?>
