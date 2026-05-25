<?php
// admin/partite/index.php
// ============================================================
//  Admin — Lista partite e calendario
// ============================================================

declare(strict_types=1);

require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/db.php';

require_admin();

$pdo = db();

// Flash message
$msg = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);

// Partite con squadre e fase
$partite = $pdo->query("
    SELECT p.*, f.nome AS fase_nome,
           sc.nome AS nome_casa, so.nome AS nome_osp
    FROM partite p
    JOIN fasi    f  ON f.id  = p.id_fase
    JOIN squadre sc ON sc.id = p.id_squadra_casa
    JOIN squadre so ON so.id = p.id_squadra_osp
    ORDER BY f.ordine ASC, p.data_ora ASC, p.id ASC
")->fetchAll();

$pageTitle = 'Gestione Partite';
require_once __DIR__ . '/../_header.php';
?>

<div class="admin-page-header">
  <h1>📅 Gestione Partite</h1>
  <p>Calendario, risultati ed eventi di gara</p>
</div>

<?php if ($msg): ?>
  <div class="alert alert-<?= htmlspecialchars($msg['type']) ?>"><?= htmlspecialchars($msg['text']) ?></div>
<?php endif; ?>

<div class="qa-grid" style="margin-bottom:1.5rem;">
  <a href="<?= SITE_URL ?>/admin/partite/form.php" class="qa-btn">+ Nuova Partita</a>
</div>

<?php if (empty($partite)): ?>
  <p class="empty-msg">Nessuna partita inserita. Usa il pulsante qui sopra per aggiungerne una.</p>
<?php else: ?>

<div class="admin-table-wrap">
  <table class="admin-table">
    <thead>
      <tr>
        <th>#</th>
        <th>Fase</th>
        <th>Data / Ora</th>
        <th>Casa</th>
        <th>Risultato</th>
        <th>Ospite</th>
        <th>Campo</th>
        <th>Stato</th>
        <th>Azioni</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($partite as $p):
        $hasScore = $p['giocata'] && $p['gol_casa'] !== null;
        $badgeClass = match(strtolower($p['fase_nome'])) {
          'girone' => 'fase-girone', 'playoff' => 'fase-playoff',
          'semifinale' => 'fase-semifinal', default => 'fase-final',
        };
      ?>
      <tr>
        <td style="color:var(--muted)"><?= $p['id'] ?></td>
        <td><span class="fase-badge <?= $badgeClass ?>"><?= htmlspecialchars($p['fase_nome']) ?></span></td>
        <td style="white-space:nowrap">
          <?= $p['data_ora'] ? date('d/m/Y H:i', strtotime($p['data_ora'])) : '<span style="color:var(--muted)">—</span>' ?>
        </td>
        <td><strong><?= htmlspecialchars($p['nome_casa']) ?></strong></td>
        <td style="text-align:center">
          <?php if ($hasScore): ?>
            <span class="score-badge" style="font-size:.85rem;padding:.1rem .5rem;">
              <?= $p['gol_casa'] ?>&nbsp;—&nbsp;<?= $p['gol_osp'] ?>
            </span>
          <?php else: ?>
            <span style="color:var(--muted)">vs</span>
          <?php endif; ?>
        </td>
        <td><strong><?= htmlspecialchars($p['nome_osp']) ?></strong></td>
        <td style="font-size:.8rem;color:var(--muted)"><?= htmlspecialchars($p['campo'] ?? '—') ?></td>
        <td>
          <?php if ($p['giocata']): ?>
            <span style="color:#6ddfab;font-size:.8rem;">✓ Giocata</span>
          <?php else: ?>
            <span style="color:var(--muted);font-size:.8rem;">In programma</span>
          <?php endif; ?>
        </td>
        <td>
          <div class="actions">
            <a href="form.php?id=<?= $p['id'] ?>" class="btn btn-sm btn-outline">Modifica</a>
            <a href="risultato.php?id=<?= $p['id'] ?>" class="btn btn-sm btn-gold">Risultato</a>
            <a href="eventi.php?id=<?= $p['id'] ?>" class="btn btn-sm btn-outline">Eventi</a>
            <a href="delete.php?id=<?= $p['id'] ?>&csrf=<?= csrf_token() ?>"
               class="btn btn-sm btn-red"
               onclick="return confirm('Eliminare questa partita?')">Elimina</a>
          </div>
        </td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>

<?php endif; ?>

<?php require_once __DIR__ . '/../_footer.php'; ?>