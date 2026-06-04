<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth_check.php';
$posNavActive = 'history';

$statusFilter = $_GET['status'] ?? '';
$dateFrom     = $_GET['date_from'] ?? '';
$dateTo       = $_GET['date_to'] ?? '';

$allowedStatus = ['', 'pending', 'preparing', 'ready', 'delivered', 'cancelled'];
if (!in_array($statusFilter, $allowedStatus, true)) {
    $statusFilter = '';
}

$sql = "
    SELECT o.id, o.daily_number, o.status, o.created_at,
           COALESCE(SUM(
               (oi.unit_price * oi.quantity)
               + COALESCE((
                   SELECT SUM(oim.extra_price) FROM pos_order_item_modifications oim
                   WHERE oim.order_item_id = oi.id AND oim.action = 'added'
               ), 0) * oi.quantity
               - oi.discount_applied * oi.quantity
           ), 0) AS order_total,
           SUM(CASE WHEN oi.coupon_id IS NOT NULL THEN 1 ELSE 0 END) AS coupon_count
    FROM pos_orders o
    LEFT JOIN pos_order_items oi ON oi.order_id = o.id
    WHERE 1=1
";
$params = [];

if ($statusFilter !== '') {
    $sql .= ' AND o.status = ?';
    $params[] = $statusFilter;
}
if ($dateFrom !== '') {
    $sql .= ' AND DATE(o.created_at) >= ?';
    $params[] = $dateFrom;
}
if ($dateTo !== '') {
    $sql .= ' AND DATE(o.created_at) <= ?';
    $params[] = $dateTo;
}

$sql .= ' GROUP BY o.id ORDER BY o.created_at DESC LIMIT 300';

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$orders = $stmt->fetchAll();

$totalRevenue = 0.0;
$totalCoupons = 0;
foreach ($orders as $o) {
    if ($o['status'] !== 'cancelled') {
        $totalRevenue += (float) $o['order_total'];
        $totalCoupons += (int) $o['coupon_count'];
    }
}

$itemStmt = $pdo->prepare("
    SELECT oi.*, p.name AS product_name, c.name AS coupon_code
    FROM pos_order_items oi
    INNER JOIN pos_products p ON p.id = oi.product_id
    LEFT JOIN pos_coupons c ON c.id = oi.coupon_id
    WHERE oi.order_id = ?
    ORDER BY oi.id
");
$modStmt = $pdo->prepare("
    SELECT oim.*, i.name AS ingredient_name
    FROM pos_order_item_modifications oim
    INNER JOIN pos_ingredients i ON i.id = oim.ingredient_id
    WHERE oim.order_item_id = ?
");

$pageTitle = 'Storico ordini POS';
?>
<!DOCTYPE html>
<html lang="it">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= htmlspecialchars($pageTitle) ?> — <?= htmlspecialchars(SITE_NAME) ?></title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Barlow+Condensed:wght@600;700;800&family=Barlow:wght@400;500;600&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?= SITE_URL ?>/assets/css/style.css">
  <link rel="stylesheet" href="<?= SITE_URL ?>/assets/css/admin.css">
  <link rel="stylesheet" href="<?= SITE_URL ?>/pos/assets/css/pos.css">
</head>
<body class="admin-body">
<?php require __DIR__ . '/../includes/navbar.php'; ?>
<main class="admin-main"><div class="admin-container">

<div class="admin-page-header">
  <h1>Storico ordini</h1>
  <p>Consultazione ordini passati con dettaglio righe</p>
</div>

<form method="get" class="admin-form" style="max-width:100%;margin-bottom:1.5rem;display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:1rem;align-items:end;">
  <div class="form-group" style="margin:0">
    <label>Stato</label>
    <select name="status">
      <option value="">Tutti</option>
      <?php foreach (['pending','preparing','ready','delivered','cancelled'] as $st): ?>
      <option value="<?= $st ?>" <?= $statusFilter === $st ? 'selected' : '' ?>><?= $st ?></option>
      <?php endforeach; ?>
    </select>
  </div>
  <div class="form-group" style="margin:0">
    <label>Da data</label>
    <input type="date" name="date_from" value="<?= htmlspecialchars($dateFrom) ?>">
  </div>
  <div class="form-group" style="margin:0">
    <label>A data</label>
    <input type="date" name="date_to" value="<?= htmlspecialchars($dateTo) ?>">
  </div>
  <div class="form-actions" style="margin:0">
    <button type="submit" class="btn btn-gold">Filtra</button>
    <a href="orders_history.php" class="btn btn-outline">Reset</a>
  </div>
</form>

<div class="admin-table-wrap">
  <table class="admin-table" id="historyTable">
    <thead>
      <tr>
        <th>#</th>
        <th>Data/ora</th>
        <th>Stato</th>
        <th>Totale</th>
        <th>Coupon</th>
      </tr>
    </thead>
    <tbody>
      <?php if (empty($orders)): ?>
      <tr><td colspan="5" class="empty-msg">Nessun ordine trovato.</td></tr>
      <?php else: foreach ($orders as $o):
        $orderId = (int) $o['id'];
        $itemStmt->execute([$orderId]);
        $items = $itemStmt->fetchAll();
      ?>
      <tr class="pos-history-row" data-order-id="<?= $orderId ?>">
        <td><strong>#<?= (int) $o['daily_number'] ?></strong></td>
        <td><?= htmlspecialchars($o['created_at']) ?></td>
        <td><span class="status-badge <?= htmlspecialchars($o['status']) ?>"><?= htmlspecialchars($o['status']) ?></span></td>
        <td>€ <?= number_format((float) $o['order_total'], 2, ',', '.') ?></td>
        <td><?= (int) $o['coupon_count'] > 0 ? (int) $o['coupon_count'] : '—' ?></td>
      </tr>
      <tr class="pos-history-detail" id="detail-<?= $orderId ?>">
        <td colspan="5">
          <ul style="list-style:none;margin:0;padding:0;">
            <?php foreach ($items as $it):
              $modStmt->execute([(int) $it['id']]);
              $mods = $modStmt->fetchAll();
            ?>
            <li style="margin-bottom:.75rem;padding-bottom:.5rem;border-bottom:1px solid rgba(255,255,255,.06);">
              <strong><?= (int) $it['quantity'] ?>× <?= htmlspecialchars($it['product_name']) ?></strong>
              — € <?= number_format((float) $it['unit_price'], 2, ',', '.') ?>
              <?php if ($it['coupon_id']): ?>
              <span class="pos-badge-coupon">Coupon <?= htmlspecialchars($it['coupon_code'] ?? '') ?></span>
              (sconto € <?= number_format((float) $it['discount_applied'], 2, ',', '.') ?>)
              <?php endif; ?>
              <span class="status-badge <?= htmlspecialchars($it['status']) ?>" style="margin-left:.35rem;"><?= htmlspecialchars($it['status']) ?></span>
              <?php if ($mods): ?>
              <div style="margin-top:.35rem;font-size:.82rem;color:var(--muted);">
                <?php foreach ($mods as $m): ?>
                <span class="pos-mod-tag <?= $m['action'] === 'removed' ? 'removed' : 'added' ?>" style="margin-right:.25rem;">
                  <?= $m['action'] === 'removed' ? 'NO ' : '+ ' ?><?= htmlspecialchars($m['ingredient_name']) ?>
                </span>
                <?php endforeach; ?>
              </div>
              <?php endif; ?>
            </li>
            <?php endforeach; ?>
          </ul>
        </td>
      </tr>
      <?php endforeach; endif; ?>
    </tbody>
    <?php if (!empty($orders)): ?>
    <tfoot>
      <tr class="pos-history-footer">
        <td colspan="3"><strong>Totali (ordini non annullati nel filtro)</strong></td>
        <td><strong>€ <?= number_format($totalRevenue, 2, ',', '.') ?></strong></td>
        <td><strong><?= $totalCoupons ?> riscattati</strong></td>
      </tr>
    </tfoot>
    <?php endif; ?>
  </table>
</div>

</div></main>
<script>
document.getElementById('posNavToggle')?.addEventListener('click', () => {
  document.getElementById('posNavLinks')?.classList.toggle('open');
});
document.querySelectorAll('.pos-history-row').forEach(row => {
  row.addEventListener('click', () => {
    const id = row.dataset.orderId;
    const detail = document.getElementById('detail-' + id);
    if (detail) detail.classList.toggle('open');
  });
});
</script>
</body>
</html>
