<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth_check.php';
$posNavActive = 'coupons';

$message = null;
$messageType = 'success';
$couponTypes = ['any', 'sandwich', 'drink', 'snack'];

if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="coupons_' . date('Y-m-d') . '.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['codice', 'tipo_prodotto', 'usato', 'data_utilizzo'], ';');
    $rows = $pdo->query('SELECT code, product_type_restriction, is_used, used_at FROM pos_coupons ORDER BY id DESC')->fetchAll();
    foreach ($rows as $r) {
        fputcsv($out, [
            $r['code'],
            $r['product_type_restriction'],
            (int) $r['is_used'] ? 'sì' : 'no',
            $r['used_at'] ?? '',
        ], ';');
    }
    fclose($out);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'generate') {
    $prefix = strtoupper(preg_replace('/[^A-Z0-9]/', '', $_POST['prefix'] ?? 'TORNEO'));
    $prefix = substr($prefix, 0, 13);
    $qty    = min(500, max(1, (int) ($_POST['quantity'] ?? 1)));
    $type   = $_POST['product_type_restriction'] ?? 'any';
    if ($prefix === '' || !in_array($type, $couponTypes, true)) {
        $messageType = 'error';
        $message = 'Prefisso e tipo prodotto obbligatori.';
    } else {
        $insert = $pdo->prepare('INSERT INTO pos_coupons (code, product_type_restriction, is_used) VALUES (?, ?, 0)');
        $chars  = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
        $generated = 0;
        for ($i = 0; $i < $qty; $i++) {
            $attempts = 0;
            do {
                $suffix = '';
                for ($j = 0; $j < 6; $j++) {
                    $suffix .= $chars[random_int(0, strlen($chars) - 1)];
                }
                $code = substr($prefix . '-' . $suffix, 0, 20);
                try {
                    $insert->execute([$code, $type]);
                    $generated++;
                    break;
                } catch (PDOException $e) {
                    $attempts++;
                }
            } while ($attempts < 20);
        }
        $message = "Generati {$generated} coupon.";
    }
}

$coupons = $pdo->query('SELECT * FROM pos_coupons ORDER BY id DESC LIMIT 500')->fetchAll();

$pageTitle = 'Coupon POS';
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
  <h1>Coupon</h1>
  <p>Generazione e lista codici monouso</p>
</div>

<?php if ($message): ?>
  <div class="alert alert-<?= $messageType === 'error' ? 'error' : 'success' ?>"><?= htmlspecialchars($message) ?></div>
<?php endif; ?>

<form method="post" class="admin-form" style="margin-bottom:2rem;">
  <input type="hidden" name="action" value="generate">
  <div class="form-group">
    <label>Prefisso (es. TORNEO2026)</label>
    <input type="text" name="prefix" value="TORNEO2026" required maxlength="13">
  </div>
  <div class="form-group">
    <label>Quantità</label>
    <input type="number" name="quantity" min="1" max="500" value="10" required>
  </div>
  <div class="form-group">
    <label>Tipo prodotto coperto</label>
    <select name="product_type_restriction" required>
      <?php foreach ($couponTypes as $t): ?>
      <option value="<?= $t ?>"><?= $t ?></option>
      <?php endforeach; ?>
    </select>
  </div>
  <div class="form-actions">
    <button type="submit" class="btn btn-gold">Genera coupon</button>
    <a href="?export=csv" class="btn btn-outline">Export CSV</a>
  </div>
</form>

<div class="admin-table-wrap">
  <table class="admin-table">
    <thead>
      <tr><th>Codice</th><th>Tipo</th><th>Stato</th><th>Utilizzo</th></tr>
    </thead>
    <tbody>
      <?php if (empty($coupons)): ?>
      <tr><td colspan="4" class="empty-msg">Nessun coupon.</td></tr>
      <?php else: foreach ($coupons as $c): ?>
      <tr>
        <td><code><?= htmlspecialchars($c['code']) ?></code></td>
        <td><?= htmlspecialchars($c['product_type_restriction']) ?></td>
        <td>
          <?php if ((int) $c['is_used']): ?>
          <span class="status-badge delivered coupon-badge-used">Usato</span>
          <?php else: ?>
          <span class="status-badge ready coupon-badge-free">Libero</span>
          <?php endif; ?>
        </td>
        <td style="color:var(--muted)"><?= $c['used_at'] ? htmlspecialchars($c['used_at']) : '—' ?></td>
      </tr>
      <?php endforeach; endif; ?>
    </tbody>
  </table>
</div>

</div></main>
<script>
document.getElementById('posNavToggle')?.addEventListener('click', () => {
  document.getElementById('posNavLinks')?.classList.toggle('open');
});
</script>
</body>
</html>
