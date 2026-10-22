<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/role_check.php';
require_role(['admin']);
$posNavActive = 'menu';

$tab = $_GET['tab'] ?? 'products';
if (!in_array($tab, ['products', 'categories', 'ingredients'], true)) {
    $tab = 'products';
}

$message = null;
$messageType = 'success';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    try {
        switch ($action) {
            case 'save_category':
                $id   = (int) ($_POST['id'] ?? 0);
                $name = trim($_POST['name'] ?? '');
                $dept = $_POST['department'] ?? 'kitchen';
                if ($name === '' || !in_array($dept, ['kitchen', 'bar'], true)) {
                    throw new RuntimeException('Dati categoria non validi.');
                }
                if ($id > 0) {
                    $pdo->prepare('UPDATE pos_categories SET name = ?, department = ? WHERE id = ?')
                        ->execute([$name, $dept, $id]);
                } else {
                    $pdo->prepare('INSERT INTO pos_categories (name, department) VALUES (?, ?)')
                        ->execute([$name, $dept]);
                }
                $message = 'Categoria salvata.';
                $tab = 'categories';
                break;

            case 'delete_category':
                $pdo->prepare('DELETE FROM pos_categories WHERE id = ?')->execute([(int) $_POST['id']]);
                $message = 'Categoria eliminata.';
                $tab = 'categories';
                break;

            case 'save_ingredient':
                $id   = (int) ($_POST['id'] ?? 0);
                $name = trim($_POST['name'] ?? '');
                if ($name === '') {
                    throw new RuntimeException('Nome ingrediente obbligatorio.');
                }
                if ($id > 0) {
                    $pdo->prepare('UPDATE pos_ingredients SET name = ? WHERE id = ?')->execute([$name, $id]);
                } else {
                    $pdo->prepare('INSERT INTO pos_ingredients (name) VALUES (?)')->execute([$name]);
                }
                $message = 'Ingrediente salvato.';
                $tab = 'ingredients';
                break;

            case 'delete_ingredient':
                $pdo->prepare('DELETE FROM pos_ingredients WHERE id = ?')->execute([(int) $_POST['id']]);
                $message = 'Ingrediente eliminato.';
                $tab = 'ingredients';
                break;

            case 'save_product':
                $id         = (int) ($_POST['id'] ?? 0);
                $name       = trim($_POST['name'] ?? '');
                $price      = (float) ($_POST['price'] ?? 0);
                $categoryId = (int) ($_POST['category_id'] ?? 0);
                $desc   = trim($_POST['description'] ?? '');
                $active = isset($_POST['is_active']) ? 1 : 0;
                if ($name === '' || $categoryId < 1) {
                    throw new RuntimeException('Dati prodotto non validi.');
                }
                if ($id > 0) {
                    $pdo->prepare('
                        UPDATE pos_products SET name = ?, price = ?, category_id = ?, description = ?, is_active = ?
                        WHERE id = ?
                    ')->execute([$name, $price, $categoryId, $desc, $active, $id]);
                } else {
                    $pdo->prepare('
                        INSERT INTO pos_products (name, price, category_id, description, is_active)
                        VALUES (?, ?, ?, ?, ?)
                    ')->execute([$name, $price, $categoryId, $desc, $active]);
                }
                $message = 'Prodotto salvato.';
                $tab = 'products';
                break;

            case 'toggle_product':
                $pdo->prepare('UPDATE pos_products SET is_active = IF(is_active=1,0,1) WHERE id = ?')
                    ->execute([(int) $_POST['id']]);
                $message = 'Stato prodotto aggiornato.';
                $tab = 'products';
                break;

            case 'save_product_ingredients':
                $productId = (int) ($_POST['product_id'] ?? 0);
                $pdo->prepare('DELETE FROM pos_product_ingredients WHERE product_id = ?')->execute([$productId]);
                $selected = $_POST['ingredient'] ?? [];
                $ins = $pdo->prepare('
                    INSERT INTO pos_product_ingredients (product_id, ingredient_id, is_removable, is_extra, extra_price)
                    VALUES (?, ?, ?, ?, ?)
                ');
                foreach ($selected as $ingId => $on) {
                    $ingId = (int) $ingId;
                    $rem   = isset($_POST['removable'][$ingId]) ? 1 : 0;
                    $extra = isset($_POST['extra'][$ingId]) ? 1 : 0;
                    $ep    = (float) ($_POST['extra_price'][$ingId] ?? 0);
                    $ins->execute([$productId, $ingId, $rem, $extra, $ep]);
                }
                $message = 'Ingredienti associati.';
                $tab = 'products';
                $_GET['manage_product'] = $productId;
                break;
        }
    } catch (Throwable $e) {
        $messageType = 'error';
        $message = DEBUG_MODE ? $e->getMessage() : 'Operazione non riuscita.';
    }
}

$categories  = $pdo->query('SELECT * FROM pos_categories ORDER BY department, name')->fetchAll();
$ingredients = $pdo->query('SELECT * FROM pos_ingredients ORDER BY name')->fetchAll();
$products    = $pdo->query("
    SELECT p.*, c.name AS category_name, c.department
    FROM pos_products p
    INNER JOIN pos_categories c ON c.id = p.category_id
    ORDER BY c.department, c.name, p.name
")->fetchAll();

$manageProductId = (int) ($_GET['manage_product'] ?? 0);
$productLinks = [];
if ($manageProductId > 0) {
    $stmt = $pdo->prepare('SELECT * FROM pos_product_ingredients WHERE product_id = ?');
    $stmt->execute([$manageProductId]);
    foreach ($stmt->fetchAll() as $row) {
        $productLinks[(int) $row['ingredient_id']] = $row;
    }
}

$pageTitle = 'Gestione menu POS';
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
  <link rel="stylesheet" href="<?= POS_URL ?>/assets/css/pos.css">
</head>
<body class="admin-body">
<?php require __DIR__ . '/../includes/navbar.php'; ?>
<main class="admin-main"><div class="admin-container">

<div class="admin-page-header">
  <h1>Gestione menu</h1>
  <p>Prodotti, categorie e ingredienti</p>
</div>

<?php if ($message): ?>
  <div class="alert alert-<?= $messageType === 'error' ? 'error' : 'success' ?>"><?= htmlspecialchars($message) ?></div>
<?php endif; ?>

<nav class="pos-admin-tabs">
  <a href="?tab=products" class="<?= $tab === 'products' ? 'active' : '' ?>">Prodotti</a>
  <a href="?tab=categories" class="<?= $tab === 'categories' ? 'active' : '' ?>">Categorie</a>
  <a href="?tab=ingredients" class="<?= $tab === 'ingredients' ? 'active' : '' ?>">Ingredienti</a>
</nav>

<?php if ($tab === 'categories'): ?>
<section>
  <form method="post" class="admin-form" style="margin-bottom:2rem;">
    <input type="hidden" name="action" value="save_category">
    <input type="hidden" name="id" id="catId" value="0">
    <div class="form-group">
      <label>Nome categoria</label>
      <input type="text" name="name" id="catName" required>
    </div>
    <div class="form-group">
      <label>Dipartimento</label>
      <select name="department" id="catDept">
        <option value="kitchen">Cucina</option>
        <option value="bar">Bar</option>
      </select>
    </div>
    <div class="form-actions">
      <button type="submit" class="btn btn-gold">Salva categoria</button>
    </div>
  </form>
  <div class="admin-table-wrap">
    <table class="admin-table">
      <thead><tr><th>Nome</th><th>Dipartimento</th><th>Azioni</th></tr></thead>
      <tbody>
        <?php foreach ($categories as $c): ?>
        <tr>
          <td><?= htmlspecialchars($c['name']) ?></td>
          <td><?= htmlspecialchars($c['department']) ?></td>
          <td class="actions">
            <button type="button" class="btn btn-sm btn-outline btn-edit-cat"
              data-id="<?= (int) $c['id'] ?>"
              data-name="<?= htmlspecialchars($c['name'], ENT_QUOTES) ?>"
              data-dept="<?= htmlspecialchars($c['department'], ENT_QUOTES) ?>">Modifica</button>
            <form method="post" style="display:inline" onsubmit="return confirm('Eliminare?');">
              <input type="hidden" name="action" value="delete_category">
              <input type="hidden" name="id" value="<?= (int) $c['id'] ?>">
              <button type="submit" class="btn btn-sm btn-red">Elimina</button>
            </form>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</section>
<script>
document.querySelectorAll('.btn-edit-cat').forEach(btn => {
  btn.onclick = () => {
    document.getElementById('catId').value = btn.dataset.id;
    document.getElementById('catName').value = btn.dataset.name;
    document.getElementById('catDept').value = btn.dataset.dept;
  };
});
</script>

<?php elseif ($tab === 'ingredients'): ?>
<section>
  <form method="post" class="admin-form" style="margin-bottom:2rem;">
    <input type="hidden" name="action" value="save_ingredient">
    <input type="hidden" name="id" id="ingId" value="0">
    <div class="form-group">
      <label>Nome ingrediente</label>
      <input type="text" name="name" id="ingName" required>
    </div>
    <div class="form-actions"><button type="submit" class="btn btn-gold">Salva</button></div>
  </form>
  <div class="admin-table-wrap">
    <table class="admin-table">
      <thead><tr><th>Nome</th><th>Azioni</th></tr></thead>
      <tbody>
        <?php foreach ($ingredients as $ing): ?>
        <tr>
          <td><?= htmlspecialchars($ing['name']) ?></td>
          <td class="actions">
            <button type="button" class="btn btn-sm btn-outline btn-edit-ing"
              data-id="<?= (int) $ing['id'] ?>"
              data-name="<?= htmlspecialchars($ing['name'], ENT_QUOTES) ?>">Modifica</button>
            <form method="post" style="display:inline" onsubmit="return confirm('Eliminare?');">
              <input type="hidden" name="action" value="delete_ingredient">
              <input type="hidden" name="id" value="<?= (int) $ing['id'] ?>">
              <button type="submit" class="btn btn-sm btn-red">Elimina</button>
            </form>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</section>
<script>
document.querySelectorAll('.btn-edit-ing').forEach(btn => {
  btn.onclick = () => {
    document.getElementById('ingId').value = btn.dataset.id;
    document.getElementById('ingName').value = btn.dataset.name;
  };
});
</script>

<?php else: ?>
<section>
  <form method="post" class="admin-form" style="margin-bottom:2rem;">
    <input type="hidden" name="action" value="save_product">
    <input type="hidden" name="id" id="prodId" value="0">
    <div class="form-group"><label>Nome</label><input type="text" name="name" id="prodName" required></div>
    <div class="form-group"><label>Prezzo (€)</label><input type="number" step="0.01" min="0" name="price" id="prodPrice" required></div>
    <div class="form-group">
      <label>Categoria</label>
      <select name="category_id" id="prodCat" required>
        <?php foreach ($categories as $c): ?>
        <option value="<?= (int) $c['id'] ?>"><?= htmlspecialchars($c['name'] . ' (' . $c['department'] . ')') ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="form-group">
      <label>Descrizione (opzionale)</label>
      <input type="text" name="description" id="prodDesc" placeholder="Note prodotto">
    </div>
    <p class="empty-msg" style="margin-bottom:1rem;">I coupon usano il tipo dedotto dalla categoria (es. Bevande → drink, Panini → sandwich).</p>
    <div class="form-group">
      <label><input type="checkbox" name="is_active" id="prodActive" checked> Attivo</label>
    </div>
    <div class="form-actions"><button type="submit" class="btn btn-gold">Salva prodotto</button></div>
  </form>

  <?php if ($manageProductId > 0):
    $mp = null;
    foreach ($products as $p) {
        if ((int) $p['id'] === $manageProductId) { $mp = $p; break; }
    }
  ?>
  <?php if ($mp): ?>
  <div class="pos-ingredient-form">
    <h3 style="color:var(--gold);font-family:'Barlow Condensed',sans-serif;text-transform:uppercase;margin-bottom:1rem;">
      Ingredienti: <?= htmlspecialchars($mp['name']) ?>
    </h3>
    <form method="post">
      <input type="hidden" name="action" value="save_product_ingredients">
      <input type="hidden" name="product_id" value="<?= $manageProductId ?>">
      <?php foreach ($ingredients as $ing):
        $lid = (int) $ing['id'];
        $link = $productLinks[$lid] ?? null;
        $checked = $link !== null;
      ?>
      <div class="pos-ingredient-row">
        <label><input type="checkbox" name="ingredient[<?= $lid ?>]" value="1" <?= $checked ? 'checked' : '' ?>>
          <?= htmlspecialchars($ing['name']) ?></label>
        <label><input type="checkbox" name="removable[<?= $lid ?>]" <?= !empty($link['is_removable']) ? 'checked' : '' ?>> Rimovibile</label>
        <label><input type="checkbox" name="extra[<?= $lid ?>]" <?= !empty($link['is_extra']) ? 'checked' : '' ?>> Extra</label>
        <input type="number" step="0.01" min="0" name="extra_price[<?= $lid ?>]" placeholder="Prezzo extra"
          value="<?= $link ? htmlspecialchars((string) $link['extra_price']) : '0' ?>" style="width:90px;">
      </div>
      <?php endforeach; ?>
      <div class="form-actions" style="margin-top:1rem;">
        <button type="submit" class="btn btn-gold">Salva associazioni</button>
        <a href="?tab=products" class="btn btn-outline">Chiudi</a>
      </div>
    </form>
  </div>
  <?php endif; ?>
  <?php endif; ?>

  <div class="admin-table-wrap">
    <table class="admin-table">
      <thead>
        <tr><th>Nome</th><th>Categoria</th><th>Prezzo</th><th>Stato</th><th>Azioni</th></tr>
      </thead>
      <tbody>
        <?php foreach ($products as $p): ?>
        <tr>
          <td><strong><?= htmlspecialchars($p['name']) ?></strong></td>
          <td><?= htmlspecialchars($p['category_name']) ?> (<?= htmlspecialchars($p['department']) ?>)</td>
          <td>€ <?= number_format((float) $p['price'], 2, ',', '.') ?></td>
          <td><?= (int) $p['is_active'] ? '<span class="status-badge ready">Attivo</span>' : '<span class="status-badge cancelled">Off</span>' ?></td>
          <td class="actions">
            <button type="button" class="btn btn-sm btn-outline btn-edit-prod"
              data-id="<?= (int) $p['id'] ?>"
              data-name="<?= htmlspecialchars($p['name'], ENT_QUOTES) ?>"
              data-price="<?= htmlspecialchars((string) $p['price']) ?>"
              data-cat="<?= (int) $p['category_id'] ?>"
              data-desc="<?= htmlspecialchars($p['description'] ?? '', ENT_QUOTES) ?>"
              data-active="<?= (int) $p['is_active'] ?>">Modifica</button>
            <a href="?tab=products&manage_product=<?= (int) $p['id'] ?>" class="btn btn-sm btn-outline">Ingredienti</a>
            <form method="post" style="display:inline">
              <input type="hidden" name="action" value="toggle_product">
              <input type="hidden" name="id" value="<?= (int) $p['id'] ?>">
              <button type="submit" class="btn btn-sm btn-outline"><?= (int) $p['is_active'] ? 'Disattiva' : 'Attiva' ?></button>
            </form>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</section>
<script>
document.querySelectorAll('.btn-edit-prod').forEach(btn => {
  btn.onclick = () => {
    document.getElementById('prodId').value = btn.dataset.id;
    document.getElementById('prodName').value = btn.dataset.name;
    document.getElementById('prodPrice').value = btn.dataset.price;
    document.getElementById('prodCat').value = btn.dataset.cat;
    document.getElementById('prodDesc').value = btn.dataset.desc || '';
    document.getElementById('prodActive').checked = btn.dataset.active === '1';
  };
});
</script>
<?php endif; ?>

</div></main>
<script>
document.getElementById('posNavToggle')?.addEventListener('click', () => {
  document.getElementById('posNavLinks')?.classList.toggle('open');
});
</script>
</body>
</html>
