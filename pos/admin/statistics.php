<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/role_check.php';
require_role(['admin']);

$posNavActive = 'stats';
$selectedDate = $_GET['date'] ?? date('Y-m-d');
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $selectedDate)) {
    $selectedDate = date('Y-m-d');
}

$summaryStmt = $pdo->prepare("
    SELECT
        COUNT(CASE WHEN status != 'cancelled' THEN 1 END) AS total_orders,
        COALESCE(SUM(CASE WHEN status != 'cancelled' THEN total_amount ELSE 0 END), 0) AS total_revenue,
        COALESCE(SUM(CASE WHEN status != 'cancelled' THEN covers ELSE 0 END), 0) AS total_covers
    FROM pos_orders
    WHERE DATE(created_at) = ?
");
$summaryStmt->execute([$selectedDate]);
$summary = $summaryStmt->fetch() ?: [];

$couponStmt = $pdo->prepare("
    SELECT
        COUNT(oi.id) AS coupon_count,
        COALESCE(SUM(oi.discount_applied * oi.quantity), 0) AS coupon_value
    FROM pos_order_items oi
    INNER JOIN pos_orders o ON o.id = oi.order_id
    WHERE DATE(o.created_at) = ?
      AND o.status != 'cancelled'
      AND oi.coupon_id IS NOT NULL
");
$couponStmt->execute([$selectedDate]);
$couponStats = $couponStmt->fetch() ?: ['coupon_count' => 0, 'coupon_value' => 0];

$stationStmt = $pdo->prepare("
    SELECT
        COALESCE(NULLIF(station_name, ''), '—') AS station_name,
        COUNT(*) AS num_orders,
        COALESCE(SUM(total_amount), 0) AS revenue,
        COALESCE(SUM(covers), 0) AS covers
    FROM pos_orders
    WHERE DATE(created_at) = ? AND status != 'cancelled'
    GROUP BY station_name
    ORDER BY revenue DESC
");
$stationStmt->execute([$selectedDate]);
$byStation = $stationStmt->fetchAll();

foreach ($byStation as &$st) {
    $cSt = $pdo->prepare("
        SELECT COUNT(oi.id)
        FROM pos_order_items oi
        INNER JOIN pos_orders o ON o.id = oi.order_id
        WHERE DATE(o.created_at) = ?
          AND o.status != 'cancelled'
          AND oi.coupon_id IS NOT NULL
          AND COALESCE(NULLIF(o.station_name, ''), '—') = ?
    ");
    $cSt->execute([$selectedDate, $st['station_name']]);
    $st['coupons_used'] = (int) $cSt->fetchColumn();
}
unset($st);

$productsStmt = $pdo->prepare("
    SELECT p.name AS product_name, c.name AS category_name, c.department,
           SUM(oi.quantity) AS qty_sold,
           COALESCE(SUM(
               (oi.unit_price * oi.quantity)
               + COALESCE((
                   SELECT SUM(oim.extra_price) FROM pos_order_item_modifications oim
                   WHERE oim.order_item_id = oi.id AND oim.action = 'added'
               ), 0) * oi.quantity
               - oi.discount_applied * oi.quantity
           ), 0) AS revenue
    FROM pos_order_items oi
    INNER JOIN pos_orders o ON o.id = oi.order_id
    INNER JOIN pos_products p ON p.id = oi.product_id
    INNER JOIN pos_categories c ON c.id = p.category_id
    WHERE DATE(o.created_at) = ? AND o.status != 'cancelled'
    GROUP BY p.id, p.name, c.name, c.department
    ORDER BY qty_sold DESC
    LIMIT 25
");
$productsStmt->execute([$selectedDate]);
$topProducts = $productsStmt->fetchAll();

$hourlyStmt = $pdo->prepare("
    SELECT HOUR(created_at) AS hour_slot,
           COUNT(*) AS num_orders,
           COALESCE(SUM(total_amount), 0) AS revenue
    FROM pos_orders
    WHERE DATE(created_at) = ? AND status != 'cancelled'
    GROUP BY HOUR(created_at)
    ORDER BY hour_slot
");
$hourlyStmt->execute([$selectedDate]);
$hourly = $hourlyStmt->fetchAll();

$pageTitle = 'Statistiche POS';
?>
<!DOCTYPE html>
<html lang="it">
<head>
  <link rel="icon" type="image/x-icon" href="<?= SITE_URL ?>/assets/brand/02_favicon/favicon.ico">
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= htmlspecialchars($pageTitle) ?> — <?= htmlspecialchars(SITE_NAME) ?></title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Barlow+Condensed:wght@600;700;800&family=Barlow:wght@400;500;600&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?= SITE_URL ?>/assets/css/style.css">
  <link rel="stylesheet" href="<?= SITE_URL ?>/assets/css/baone-ui.css?v=phase04">
  <link rel="stylesheet" href="<?= SITE_URL ?>/assets/css/admin.css">
  <link rel="stylesheet" href="<?= POS_URL ?>/assets/css/pos.css?v=phase05">
</head>
<body class="admin-body tb-ui">
<?php require __DIR__ . '/../includes/navbar.php'; ?>
<main class="admin-main"><div class="admin-container">

<div class="admin-page-header">
  <h1>Statistiche fine serata</h1>
  <p>Riepilogo giornaliero punto ristoro</p>
</div>

<form method="get" class="admin-form" style="max-width:320px;margin-bottom:2rem;">
  <div class="form-group" style="margin:0">
    <label>Data</label>
    <input type="date" name="date" value="<?= htmlspecialchars($selectedDate) ?>" required>
  </div>
  <div class="form-actions" style="margin-top:.75rem;">
    <button type="submit" class="btn btn-gold">Aggiorna</button>
  </div>
</form>

<h2 class="pos-stats-section-title">Riepilogo generale</h2>
<div class="stat-grid">
  <div class="stat-card">
    <div class="stat-num"><?= (int) ($summary['total_orders'] ?? 0) ?></div>
    <div class="stat-lbl">Ordini</div>
  </div>
  <div class="stat-card stat-card--highlight">
    <div class="stat-num">€ <?= number_format((float) ($summary['total_revenue'] ?? 0), 2, ',', '.') ?></div>
    <div class="stat-lbl">Incassato</div>
  </div>
  <div class="stat-card">
    <div class="stat-num"><?= (int) ($couponStats['coupon_count'] ?? 0) ?></div>
    <div class="stat-lbl">Coupon riscattati</div>
  </div>
  <div class="stat-card">
    <div class="stat-num">€ <?= number_format((float) ($couponStats['coupon_value'] ?? 0), 2, ',', '.') ?></div>
    <div class="stat-lbl">Valore coupon</div>
  </div>
  <div class="stat-card">
    <div class="stat-num"><?= (int) ($summary['total_covers'] ?? 0) ?></div>
    <div class="stat-lbl">Coperti serviti</div>
  </div>
</div>

<h2 class="pos-stats-section-title">Per cassa</h2>
<div class="admin-table-wrap" style="margin-bottom:2rem;">
  <table class="admin-table">
    <thead>
      <tr>
        <th>Cassa</th>
        <th>Ordini</th>
        <th>Incassato</th>
        <th>Coupon</th>
        <th>Coperti</th>
      </tr>
    </thead>
    <tbody>
      <?php if (empty($byStation)): ?>
      <tr><td colspan="5" class="empty-msg">Nessun dato per questa data.</td></tr>
      <?php else: foreach ($byStation as $st): ?>
      <tr>
        <td><strong><?= htmlspecialchars($st['station_name']) ?></strong></td>
        <td><?= (int) $st['num_orders'] ?></td>
        <td>€ <?= number_format((float) $st['revenue'], 2, ',', '.') ?></td>
        <td><?= (int) $st['coupons_used'] ?></td>
        <td><?= (int) $st['covers'] ?></td>
      </tr>
      <?php endforeach; endif; ?>
    </tbody>
  </table>
</div>

<h2 class="pos-stats-section-title">Prodotti più venduti</h2>
<div class="admin-table-wrap" style="margin-bottom:2rem;">
  <table class="admin-table">
    <thead>
      <tr>
        <th>Prodotto</th>
        <th>Categoria</th>
        <th>Reparto</th>
        <th>Qtà</th>
        <th>Incasso</th>
      </tr>
    </thead>
    <tbody>
      <?php if (empty($topProducts)): ?>
      <tr><td colspan="5" class="empty-msg">Nessuna vendita.</td></tr>
      <?php else: foreach ($topProducts as $p): ?>
      <tr>
        <td><strong><?= htmlspecialchars($p['product_name']) ?></strong></td>
        <td><?= htmlspecialchars($p['category_name']) ?></td>
        <td><?= htmlspecialchars($p['department']) ?></td>
        <td><?= (int) $p['qty_sold'] ?></td>
        <td>€ <?= number_format((float) $p['revenue'], 2, ',', '.') ?></td>
      </tr>
      <?php endforeach; endif; ?>
    </tbody>
  </table>
</div>

<h2 class="pos-stats-section-title">Andamento orario</h2>
<div class="admin-table-wrap">
  <table class="admin-table">
    <thead>
      <tr><th>Ora</th><th>Ordini</th><th>Incasso</th></tr>
    </thead>
    <tbody>
      <?php if (empty($hourly)): ?>
      <tr><td colspan="3" class="empty-msg">Nessun dato.</td></tr>
      <?php else: foreach ($hourly as $h): ?>
      <tr>
        <td><?= sprintf('%02d:00', (int) $h['hour_slot']) ?> – <?= sprintf('%02d:59', (int) $h['hour_slot']) ?></td>
        <td><?= (int) $h['num_orders'] ?></td>
        <td>€ <?= number_format((float) $h['revenue'], 2, ',', '.') ?></td>
      </tr>
      <?php endforeach; endif; ?>
    </tbody>
  </table>
</div>

</div></main>
<script>
</script>
</body>
</html>
