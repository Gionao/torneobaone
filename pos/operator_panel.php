<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth_check.php';
require_once __DIR__ . '/includes/role_check.php';
require_role(['admin', 'operatore']);
$pageTitle = 'POS — Bancone';
$posNavActive = 'operator';
$showStationInQueue = ($_SESSION['pos_admin_role'] ?? '') === 'admin';
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
  <link rel="stylesheet" href="<?= SITE_URL ?>/pos/assets/css/pos.css">
</head>
<body class="pos-body">

<?php require __DIR__ . '/includes/navbar.php'; ?>

<main class="pos-main">
  <h1 class="pos-page-title">Bancone operatore</h1>

  <div class="pos-operator-grid">
    <section class="pos-panel" id="panelNewOrder">
      <div id="orderFormView">
      <h2>Nuovo ordine</h2>
      <div class="pos-order-meta">
        <div class="form-group">
          <label for="customerName">Nome / tavolo</label>
          <input type="text" id="customerName" maxlength="100" required
                 placeholder="es. Mario, Tavolo 5">
        </div>
        <div class="form-group">
          <label for="orderCovers">Coperti</label>
          <input type="number" id="orderCovers" min="1" max="20" value="1" required>
        </div>
      </div>
      <div id="productsArea"><p class="empty-msg">Caricamento menu…</p></div>

      <h2 style="margin-top:1.25rem;">Carrello</h2>
      <ul class="pos-cart-list" id="cartList"></ul>
      <div class="pos-cart-total">
        <span>Totale</span>
        <span id="cartTotal">€ 0,00</span>
      </div>

      <div class="pos-coupon-row">
        <input type="text" id="couponCode" placeholder="Codice coupon" autocomplete="off">
        <button type="button" class="btn btn-outline btn-sm" id="btnVerifyCoupon">Verifica</button>
      </div>
      <div id="couponAlert"></div>

      <div class="pos-confirm-row">
        <button type="button" class="btn btn-gold" id="btnConfirmOrder" disabled>Conferma ordine</button>
      </div>
      </div>
      <div id="orderDoneView" class="pos-order-success pos-hidden">
        <p class="empty-msg">Ordine registrato</p>
        <div class="order-num" id="assignedOrderNum">#—</div>
        <button type="button" class="btn btn-gold" id="btnNewOrder" style="margin-top:1rem;">Nuovo ordine</button>
      </div>
    </section>

    <section class="pos-panel" id="panelQueue">
      <h2>Coda ordini</h2>
      <div id="queueArea"><p class="empty-msg">Nessun ordine in coda.</p></div>
    </section>
  </div>
</main>

<div id="modalRoot"></div>

<script>
document.getElementById('posNavToggle')?.addEventListener('click', () => {
  document.getElementById('posNavLinks')?.classList.toggle('open');
});
</script>
<script>
(function () {
  const API = 'api';
  let products = [];
  let cart = [];
  let activeCoupon = null;
  let queuePolling = false;

  const productsArea   = document.getElementById('productsArea');
  const cartList       = document.getElementById('cartList');
  const cartTotal      = document.getElementById('cartTotal');
  const couponAlert    = document.getElementById('couponAlert');
  const btnConfirm     = document.getElementById('btnConfirmOrder');
  const orderFormView  = document.getElementById('orderFormView');
  const orderDoneView  = document.getElementById('orderDoneView');
  const assignedNum    = document.getElementById('assignedOrderNum');
  const queueArea      = document.getElementById('queueArea');
  const customerName   = document.getElementById('customerName');
  const orderCovers    = document.getElementById('orderCovers');
  const SHOW_STATION   = <?= json_encode($showStationInQueue) ?>;

  function euro(n) {
    return '€ ' + Number(n).toFixed(2).replace('.', ',');
  }

  async function apiFetch(url, options) {
    const res = await fetch(url, options);
    const json = await res.json();
    if (!json.success) throw new Error(json.error || 'Errore server');
    return json.data;
  }

  async function loadProducts() {
    try {
      products = await apiFetch(API + '/get_products.php');
      renderProducts();
    } catch (e) {
      productsArea.innerHTML = '<div class="alert alert-error">' + escapeHtml(e.message) + '</div>';
    }
  }

  function escapeHtml(s) {
    const d = document.createElement('div');
    d.textContent = s;
    return d.innerHTML;
  }

  function renderProducts() {
    const byCat = {};
    products.forEach(p => {
      const key = p.category_name;
      if (!byCat[key]) byCat[key] = { dept: p.department, items: [] };
      byCat[key].items.push(p);
    });
    let html = '';
    Object.keys(byCat).forEach(catName => {
      html += '<div class="pos-category-block"><div class="pos-category-name">' +
        escapeHtml(catName) + ' · ' + byCat[catName].dept + '</div><div class="pos-products-grid">';
      byCat[catName].items.forEach(p => {
        html += '<button type="button" class="pos-product-card" data-id="' + p.id + '">' +
          '<div class="name">' + escapeHtml(p.name) + '</div>' +
          '<div class="price">' + euro(p.price) + '</div></button>';
      });
      html += '</div></div>';
    });
    productsArea.innerHTML = html;
    productsArea.querySelectorAll('.pos-product-card').forEach(btn => {
      btn.addEventListener('click', () => openCustomize(parseInt(btn.dataset.id, 10)));
    });
  }

  function openCustomize(productId) {
    const product = products.find(p => p.id === productId);
    if (!product) return;

    const removable = product.ingredients.filter(i => i.is_removable === 1);
    const extras    = product.ingredients.filter(i => i.is_extra === 1);

    let body = '<h3>' + escapeHtml(product.name) + '</h3>';
    if (removable.length) {
      body += '<div class="modal-section"><h4>Rimuovi ingredienti</h4>';
      removable.forEach(ing => {
        body += '<label class="pos-check-row"><input type="checkbox" data-mod="remove" data-id="' + ing.id + '"> ' +
          escapeHtml(ing.name) + '</label>';
      });
      body += '</div>';
    }
    if (extras.length) {
      body += '<div class="modal-section"><h4>Extra</h4>';
      extras.forEach(ing => {
        body += '<label class="pos-check-row"><input type="checkbox" data-mod="extra" data-id="' + ing.id +
          '" data-price="' + ing.extra_price + '"> ' + escapeHtml(ing.name) +
          ' (+' + euro(ing.extra_price) + ')</label>';
      });
      body += '</div>';
    }

    const overlay = document.createElement('div');
    overlay.className = 'pos-modal-overlay';
    overlay.innerHTML = '<div class="pos-modal">' + body +
      '<div class="pos-modal-actions">' +
      '<button type="button" class="btn btn-gold" id="modalAdd">Aggiungi al carrello</button>' +
      '<button type="button" class="btn btn-outline" id="modalClose">Annulla</button></div></div>';

    overlay.querySelector('#modalClose').onclick = () => overlay.remove();
    overlay.addEventListener('click', e => { if (e.target === overlay) overlay.remove(); });

    overlay.querySelector('#modalAdd').onclick = () => {
      const mods = [];
      overlay.querySelectorAll('input[data-mod="remove"]:checked').forEach(cb => {
        mods.push({ ingredient_id: parseInt(cb.dataset.id, 10), action: 'removed', extra_price: 0 });
      });
      overlay.querySelectorAll('input[data-mod="extra"]:checked').forEach(cb => {
        mods.push({
          ingredient_id: parseInt(cb.dataset.id, 10),
          action: 'added',
          extra_price: parseFloat(cb.dataset.price) || 0
        });
      });
      cart.push({
        key: Date.now() + '-' + Math.random(),
        product_id: product.id,
        product_name: product.name,
        product_kind: product.product_kind || 'sandwich',
        unit_price: product.price,
        quantity: 1,
        coupon_id: null,
        modifications: mods
      });
      overlay.remove();
      renderCart();
    };

    document.getElementById('modalRoot').appendChild(overlay);
  }

  function lineTotal(item) {
    if (item.coupon_id) return 0;
    let t = item.unit_price * item.quantity;
    item.modifications.forEach(m => {
      if (m.action === 'added') t += m.extra_price * item.quantity;
    });
    return t;
  }

  function renderCart() {
    if (!cart.length) {
      cartList.innerHTML = '<li class="empty-msg">Carrello vuoto</li>';
      cartTotal.textContent = euro(0);
      btnConfirm.disabled = true;
      return;
    }
    let total = 0;
    cartList.innerHTML = cart.map((item, idx) => {
      const lt = lineTotal(item);
      total += lt;
      let modsHtml = '';
      item.modifications.forEach(m => {
        const ing = findIngName(item.product_id, m.ingredient_id);
        modsHtml += '<div class="mods">' + (m.action === 'removed' ? '− ' : '+ ') +
          escapeHtml(ing) + (m.extra_price > 0 ? ' (' + euro(m.extra_price) + ')' : '') + '</div>';
      });
      const cls = item.coupon_id ? 'pos-cart-item coupon-free' : 'pos-cart-item';
      return '<li class="' + cls + '"><div><div class="item-name">' + escapeHtml(item.product_name) +
        '</div>' + modsHtml + '</div><div><span class="item-price">' +
        (item.coupon_id ? 'GRATIS' : euro(lt)) + '</span> ' +
        '<button type="button" class="btn-remove" data-idx="' + idx + '" aria-label="Rimuovi">×</button></div></li>';
    }).join('');
    cartTotal.textContent = euro(total);
    btnConfirm.disabled = false;
    cartList.querySelectorAll('.btn-remove').forEach(btn => {
      btn.onclick = () => {
        const i = parseInt(btn.dataset.idx, 10);
        if (cart[i].coupon_id) activeCoupon = null;
        cart.splice(i, 1);
        renderCart();
      };
    });
  }

  function findIngName(productId, ingId) {
    const p = products.find(x => x.id === productId);
    if (!p) return 'Ingrediente';
    const ing = p.ingredients.find(i => i.id === ingId);
    return ing ? ing.name : 'Ingrediente';
  }

  document.getElementById('btnVerifyCoupon').onclick = async () => {
    couponAlert.innerHTML = '';
    const code = document.getElementById('couponCode').value.trim();
    if (!code) return;
    try {
      const data = await apiFetch(API + '/validate_coupon.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ code })
      });
      const restriction = data.product_type_restriction;
      const idx = cart.findIndex(it => !it.coupon_id && (
        restriction === 'any' || it.product_kind === restriction
      ));
      if (idx === -1) {
        couponAlert.innerHTML = '<div class="alert alert-error">Nessun prodotto nel carrello compatibile con questo coupon (' +
          escapeHtml(restriction) + ').</div>';
        return;
      }
      cart[idx].coupon_id = data.id;
      activeCoupon = data;
      couponAlert.innerHTML = '<div class="alert alert-success">Coupon valido: <strong>' + escapeHtml(data.code) +
        '</strong> applicato a <strong>' + escapeHtml(cart[idx].product_name) + '</strong>.</div>';
      renderCart();
    } catch (e) {
      couponAlert.innerHTML = '<div class="alert alert-error">' + escapeHtml(e.message) + '</div>';
    }
  };

  document.getElementById('btnConfirmOrder').onclick = async () => {
    if (!cart.length) return;
    const cName = customerName.value.trim();
    const covers = parseInt(orderCovers.value, 10) || 1;
    if (!cName) {
      couponAlert.innerHTML = '<div class="alert alert-error">Inserisci nome cliente o tavolo.</div>';
      return;
    }
    btnConfirm.disabled = true;
    try {
      const payload = {
        customer_name: cName,
        covers: covers,
        items: cart.map(it => ({
          product_id: it.product_id,
          quantity: it.quantity,
          coupon_id: it.coupon_id,
          modifications: it.modifications.map(m => ({
            ingredient_id: m.ingredient_id,
            action: m.action,
            extra_price: m.extra_price
          }))
        }))
      };
      const data = await apiFetch(API + '/create_order.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload)
      });
      assignedNum.textContent = '#' + data.daily_number;
      orderFormView.classList.add('pos-hidden');
      orderDoneView.classList.remove('pos-hidden');
      cart = [];
      activeCoupon = null;
      document.getElementById('couponCode').value = '';
      customerName.value = '';
      orderCovers.value = '1';
      couponAlert.innerHTML = '';
      loadQueue();
    } catch (e) {
      couponAlert.innerHTML = '<div class="alert alert-error">' + escapeHtml(e.message) + '</div>';
      btnConfirm.disabled = false;
    }
  };

  document.getElementById('btnNewOrder').onclick = () => {
    orderDoneView.classList.add('pos-hidden');
    orderFormView.classList.remove('pos-hidden');
    renderCart();
    btnConfirm.disabled = !cart.length;
  };

  const statusFlow = {
    pending:   { next: 'preparing', label: '→ Preparing' },
    preparing: { next: 'ready',     label: '→ Ready' },
    ready:     { next: 'delivered', label: '→ Delivered' }
  };

  function renderQueue(orders) {
    if (!orders.length) {
      queueArea.innerHTML = '<p class="empty-msg">Nessun ordine in coda.</p>';
      return;
    }
    const groups = { pending: [], preparing: [], ready: [] };
    orders.forEach(o => {
      if (groups[o.status]) groups[o.status].push(o);
    });
    let html = '';
    ['pending', 'preparing', 'ready'].forEach(st => {
      if (!groups[st].length) return;
      html += '<div class="pos-queue-section"><h3>' + st + '</h3>';
      groups[st].forEach(o => {
        const time = new Date(o.created_at).toLocaleTimeString('it-IT', { hour: '2-digit', minute: '2-digit' });
        const itemsTxt = o.items.map(i => i.quantity + '× ' + i.product_name).join(', ');
        const custLine = '<div class="order-customer"><strong>' + escapeHtml(o.customer_name || '—') +
          '</strong> · ' + (o.covers || 1) + ' coperti</div>';
        const stationBadge = (SHOW_STATION && o.station_name)
          ? '<span class="pos-badge-station">' + escapeHtml(o.station_name) + '</span>' : '';
        html += '<div class="pos-order-card" data-order-id="' + o.id + '"><div class="order-head">' +
          '<span class="order-num-sm">#' + o.daily_number +
          (o.has_coupon ? '<span class="pos-badge-coupon">Coupon</span>' : '') +
          stationBadge +
          '</span><span class="status-badge ' + o.status + '">' + o.status + '</span></div>' +
          '<div class="order-time">' + time + '</div>' + custLine +
          '<div class="order-items-list">' + escapeHtml(itemsTxt) + '</div>' +
          '<div class="order-actions">';
        const flow = statusFlow[o.status];
        if (flow) {
          html += '<button type="button" class="btn btn-sm btn-gold btn-status" data-id="' + o.id +
            '" data-status="' + flow.next + '">' + flow.label + '</button>';
        }
        html += '<button type="button" class="btn btn-sm btn-red btn-cancel" data-id="' + o.id + '">Annulla</button>';
        html += '</div></div>';
      });
      html += '</div>';
    });
    queueArea.innerHTML = html;

    queueArea.querySelectorAll('.btn-status').forEach(btn => {
      btn.onclick = async () => {
        try {
          await apiFetch(API + '/update_order_status.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ type: 'order', id: parseInt(btn.dataset.id, 10), status: btn.dataset.status })
          });
          loadQueue();
        } catch (e) { alert(e.message); }
      };
    });
    queueArea.querySelectorAll('.btn-cancel').forEach(btn => {
      btn.onclick = async () => {
        if (!confirm('Annullare questo ordine?')) return;
        try {
          await apiFetch(API + '/cancel_order.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ order_id: parseInt(btn.dataset.id, 10) })
          });
          loadQueue();
        } catch (e) { alert(e.message); }
      };
    });
  }

  async function loadQueue() {
    if (queuePolling) return;
    queuePolling = true;
    try {
      const orders = await apiFetch(API + '/get_active_orders.php');
      renderQueue(orders);
    } catch (e) { /* silenzioso in polling */ }
    finally { queuePolling = false; }
  }

  loadProducts();
  loadQueue();
  setInterval(loadQueue, 5000);
})();
</script>
</body>
</html>
