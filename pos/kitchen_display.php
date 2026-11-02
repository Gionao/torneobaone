<?php
// pos/kitchen_display.php — KDS Cucina v2
// Identico a bar_display ma con department=kitchen.
declare(strict_types=1);
require_once dirname(__DIR__) . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth_check.php';
require_once __DIR__ . '/includes/role_check.php';
require_role(['admin', 'cucina']);

$department   = 'kitchen';
$displayTitle = 'Cucina';
$apiDept      = 'kitchen';
$posNavActive = 'kitchen';
?>
<!DOCTYPE html>
<html lang="it">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= htmlspecialchars($displayTitle) ?> — POS</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Barlow+Condensed:wght@600;700;800&family=Barlow:wght@400;500;600&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?= SITE_URL ?>/assets/css/tokens.css?v=redesign-1">
  <link rel="stylesheet" href="<?= SITE_URL ?>/assets/css/style.css">
  <link rel="stylesheet" href="<?= POS_URL ?>/assets/css/pos.css">
</head>
<body class="pos-display-body">

  <?php require __DIR__ . '/includes/navbar.php'; ?>
  <h1 class="pos-display-title"><?= htmlspecialchars($displayTitle) ?></h1>

  <div class="pos-kds-tabs" id="kdsTabs">
    <button class="pos-kds-tab active" data-tab="active">Ordini attivi</button>
    <button class="pos-kds-tab" data-tab="history">Storico</button>
  </div>

  <div id="tabActive">
    <div class="pos-kds-grid" id="kdsGrid"><p class="empty-msg centered">Caricamento…</p></div>
  </div>

  <div id="tabHistory" class="pos-hidden">
    <div class="pos-kds-history-filters">
      <input type="date" id="historyDate" value="<?= date('Y-m-d') ?>">
      <button type="button" class="btn btn-outline btn-sm" id="btnLoadHistory">Carica</button>
    </div>
    <div class="pos-kds-grid" id="historyGrid"><p class="empty-msg centered">Seleziona una data e premi Carica.</p></div>
  </div>

<script>
(function () {
  'use strict';
  const DEPT    = '<?= $apiDept ?>';
  const POS_URL = '<?= POS_URL ?>';
  let polling   = false;
  const grid    = document.getElementById('kdsGrid');
  const hGrid   = document.getElementById('historyGrid');

  function escapeHtml(s) {
    const d = document.createElement('div');
    d.textContent = s ?? '';
    return d.innerHTML;
  }
  function modTags(mods) {
    return mods.map(m => {
      const cls    = m.action === 'removed' ? 'removed' : 'added';
      const prefix = m.action === 'removed' ? 'NO ' : '+ ';
      return '<span class="pos-mod-tag ' + cls + '">' + escapeHtml(prefix + m.ingredient_name) + '</span>';
    }).join('');
  }
  async function apiPost(url, body) {
    const res  = await fetch(url, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(body),
    });
    const json = await res.json();
    if (!json.success) throw new Error(json.error || 'Errore');
    return json.data;
  }

  function orderHeader(o) {
    const time  = new Date(o.created_at).toLocaleTimeString('it-IT', { hour: '2-digit', minute: '2-digit' });
    const where = o.is_takeaway
      ? '<span class="pos-badge-takeaway">ASPORTO</span>'
      : (o.table_number ? 'Tavolo <strong>' + escapeHtml(o.table_number) + '</strong>' : '—');
    return '<div class="kds-num">#' + o.daily_number + '</div>' +
      '<div class="kds-time">' + time + '</div>' +
      '<div class="kds-customer"><strong>' + escapeHtml(o.customer_name || '—') + '</strong> · ' + where + '</div>';
  }

  function render(orders) {
    if (!orders.length) {
      grid.innerHTML = '<p class="empty-msg centered">Nessun ordine in attesa.</p>';
      return;
    }
    grid.innerHTML = orders.map(o => {
      const allReady  = o.items.length > 0 && o.items.every(i => i.status === 'ready');
      const itemsHtml = o.items.map(item => {
        const ready    = item.status === 'ready';
        const noteHtml = item.item_note
          ? '<div class="kds-item-note">📝 ' + escapeHtml(item.item_note) + '</div>' : '';
        return '<div class="pos-kds-item' + (ready ? ' item-ready' : '') + '">' +
          '<div class="item-title">' + item.quantity + '× ' + escapeHtml(item.product_name) + '</div>' +
          noteHtml +
          (item.modifications.length ? '<div class="pos-mod-tags">' + modTags(item.modifications) + '</div>' : '') +
          (!ready ? '<button type="button" class="btn btn-sm btn-gold item-ready-btn" data-item-id="' + item.id + '">Segna pronto</button>' : '') +
          '</div>';
      }).join('');

      return '<article class="pos-kds-card' + (allReady ? ' all-ready' : '') + '">' +
        orderHeader(o) + itemsHtml +
        (allReady ? '<button type="button" class="btn btn-sm btn-green btn-deliver" data-order-id="' + o.id + '">✔ Consegnato</button>' : '') +
        '</article>';
    }).join('');

    grid.querySelectorAll('.item-ready-btn').forEach(btn => {
      btn.onclick = async () => {
        btn.disabled = true;
        try {
          await apiPost('api/update_order_status.php', { type: 'item', id: parseInt(btn.dataset.itemId, 10), status: 'ready' });
          load();
        } catch (e) { alert(e.message); btn.disabled = false; }
      };
    });

    grid.querySelectorAll('.btn-deliver').forEach(btn => {
      btn.onclick = async () => {
        btn.disabled = true;
        try {
          await apiPost('api/update_order_status.php', { type: 'order', id: parseInt(btn.dataset.orderId, 10), status: 'delivered' });
          load();
        } catch (e) { alert(e.message); btn.disabled = false; }
      };
    });
  }

  function renderHistory(orders) {
    if (!orders.length) {
      hGrid.innerHTML = '<p class="empty-msg centered">Nessun ordine completato per la data selezionata.</p>';
      return;
    }
    hGrid.innerHTML = orders.map(o => {
      const itemsHtml = o.items.map(i => {
        const noteHtml = i.item_note ? '<div class="kds-item-note">📝 ' + escapeHtml(i.item_note) + '</div>' : '';
        return '<div class="pos-kds-item item-ready"><div class="item-title">' + i.quantity + '× ' + escapeHtml(i.product_name) + '</div>' + noteHtml + '</div>';
      }).join('');

      return '<article class="pos-kds-card all-ready pos-history-card">' +
        orderHeader(o) +
        '<span class="status-badge delivered" style="margin-bottom:.5rem;display:inline-block;">Consegnato</span>' +
        itemsHtml +
        '<button type="button" class="btn btn-sm btn-outline btn-reopen" data-order-id="' + o.id + '">↩ Riapri</button>' +
        '</article>';
    }).join('');

    hGrid.querySelectorAll('.btn-reopen').forEach(btn => {
      btn.onclick = async () => {
        if (!confirm('Riaprire questo ordine?')) return;
        btn.disabled = true;
        try {
          await apiPost('api/reopen_order.php', { order_id: parseInt(btn.dataset.orderId, 10) });
          loadHistory();
        } catch (e) { alert(e.message); btn.disabled = false; }
      };
    });
  }

  async function load() {
    if (polling) return;
    polling = true;
    try {
      const res  = await fetch('api/get_active_orders.php?department=' + DEPT);
      const json = await res.json();
      if (json.success) render(json.data || []);
      else if (res.status === 401) window.location.href = POS_URL + '/login.php';
    } catch (e) { /* polling */ }
    finally { polling = false; }
  }

  async function loadHistory() {
    const date = document.getElementById('historyDate').value;
    hGrid.innerHTML = '<p class="empty-msg centered">Caricamento…</p>';
    try {
      const res  = await fetch('api/get_completed_orders.php?department=' + DEPT + '&date=' + encodeURIComponent(date));
      const json = await res.json();
      if (json.success) renderHistory(json.data || []);
      else hGrid.innerHTML = '<p class="empty-msg centered">' + escapeHtml(json.error) + '</p>';
    } catch (e) {
      hGrid.innerHTML = '<p class="empty-msg centered">Errore caricamento storico.</p>';
    }
  }

  document.getElementById('kdsTabs').querySelectorAll('.pos-kds-tab').forEach(btn => {
    btn.addEventListener('click', () => {
      document.querySelectorAll('.pos-kds-tab').forEach(b => b.classList.remove('active'));
      btn.classList.add('active');
      const tab = btn.dataset.tab;
      document.getElementById('tabActive').classList.toggle('pos-hidden', tab !== 'active');
      document.getElementById('tabHistory').classList.toggle('pos-hidden', tab !== 'history');
    });
  });
  document.getElementById('btnLoadHistory').onclick = loadHistory;

  load();
  setInterval(load, 5000);
})();
</script>
</body>
</html>
