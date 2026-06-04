<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth_check.php';
require_once __DIR__ . '/includes/role_check.php';
require_role(['admin', 'bar']);
$department = 'bar';
$displayTitle = 'Bar';
?>
<!DOCTYPE html>
<html lang="it">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= htmlspecialchars($displayTitle) ?> — POS</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Barlow+Condensed:wght@600;700;800&family=Barlow:wght@400;500;600&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?= SITE_URL ?>/assets/css/style.css">
  <link rel="stylesheet" href="<?= SITE_URL ?>/pos/assets/css/pos.css">
</head>
<body class="pos-display-body">
  <a href="<?= POS_URL ?>/logout.php" class="pos-display-logout">Logout</a>
  <h1 class="pos-display-title"><?= htmlspecialchars($displayTitle) ?></h1>
  <div class="pos-kds-grid" id="kdsGrid"><p class="empty-msg centered">Caricamento…</p></div>

<script>
(function () {
  const API = 'api/get_active_orders.php?department=bar';
  let polling = false;
  const grid = document.getElementById('kdsGrid');

  function escapeHtml(s) {
    const d = document.createElement('div');
    d.textContent = s;
    return d.innerHTML;
  }

  function modTags(mods) {
    return mods.map(m => {
      const cls = m.action === 'removed' ? 'removed' : 'added';
      const prefix = m.action === 'removed' ? 'NO ' : '+ ';
      return '<span class="pos-mod-tag ' + cls + '">' + escapeHtml(prefix + m.ingredient_name) + '</span>';
    }).join('');
  }

  function render(orders) {
    if (!orders.length) {
      grid.innerHTML = '<p class="empty-msg centered">Nessun ordine in attesa.</p>';
      return;
    }
    grid.innerHTML = orders.map(o => {
      const time = new Date(o.created_at).toLocaleTimeString('it-IT', { hour: '2-digit', minute: '2-digit' });
      const allReady = o.items.length > 0 && o.items.every(i => i.status === 'ready');
      const itemsHtml = o.items.map(item => {
        const ready = item.status === 'ready';
        return '<div class="pos-kds-item' + (ready ? ' item-ready' : '') + '">' +
          '<div class="item-title">' + item.quantity + '× ' + escapeHtml(item.product_name) + '</div>' +
          (item.modifications.length ? '<div class="pos-mod-tags">' + modTags(item.modifications) + '</div>' : '') +
          (ready ? '' : '<button type="button" class="btn btn-sm btn-gold item-ready-btn" data-item-id="' + item.id + '">Segna ready</button>') +
          '</div>';
      }).join('');
      return '<article class="pos-kds-card' + (allReady ? ' all-ready' : '') + '">' +
        '<div class="kds-num">#' + o.daily_number + '</div>' +
        '<div class="kds-time">' + time + '</div>' + itemsHtml + '</article>';
    }).join('');

    grid.querySelectorAll('.item-ready-btn').forEach(btn => {
      btn.onclick = async () => {
        const id = parseInt(btn.dataset.itemId, 10);
        btn.disabled = true;
        try {
          const res = await fetch('api/update_order_status.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ type: 'item', id, status: 'ready' })
          });
          const json = await res.json();
          if (!json.success) throw new Error(json.error || 'Errore');
          load();
        } catch (e) {
          alert(e.message);
          btn.disabled = false;
        }
      };
    });
  }

  async function load() {
    if (polling) return;
    polling = true;
    try {
      const res = await fetch(API);
      const json = await res.json();
      if (json.success) render(json.data || []);
      else if (res.status === 401) window.location.href = '<?= POS_URL ?>/login.php';
    } catch (e) { /* polling */ }
    finally { polling = false; }
  }

  load();
  setInterval(load, 5000);
})();
</script>
</body>
</html>
