<?php
// pos/operator_panel.php — Pannello Cassa v2
// Modifiche: Nome Cliente + Numero Tavolo separati, toggle Asporto,
//            quantità +/- nel carrello, note per prodotto,
//            coupon fixed/percent, rimosso tasto "Delivered" dalla cassa.
declare(strict_types=1);
require_once dirname(__DIR__) . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth_check.php';
require_once __DIR__ . '/includes/role_check.php';
require_role(['admin', 'operatore']);

$pageTitle        = 'POS — Bancone';
$posNavActive     = 'operator';
$showStationInQueue = ($_SESSION['pos_admin_role'] ?? '') === 'admin';

// Leggi impostazioni tariffe
$settings      = $pdo->query("SELECT `key`, `value` FROM pos_settings")->fetchAll(PDO::FETCH_KEY_PAIR);
$coverFeeUnit  = (float) ($settings['cover_fee']    ?? 1.50);
$takeawayFee   = (float) ($settings['takeaway_fee'] ?? 1.00);
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
  <link rel="stylesheet" href="<?= POS_URL ?>/assets/css/pos.css">
</head>
<body class="pos-body">

<?php require __DIR__ . '/includes/navbar.php'; ?>

<main class="pos-main">
  <h1 class="pos-page-title">Bancone operatore</h1>

  <div class="pos-operator-grid">

    <!-- ── Pannello nuovo ordine ──────────────────────────── -->
    <section class="pos-panel" id="panelNewOrder">

      <div id="orderFormView">
        <h2>Nuovo ordine</h2>

        <!-- Metadati ordine -->
        <div class="pos-order-meta">

          <div class="form-group">
            <label for="customerName">Nome cliente</label>
            <input type="text" id="customerName" maxlength="100" required
                   placeholder="es. Mario Rossi">
          </div>

          <!-- Toggle Asporto -->
          <div class="form-group pos-takeaway-row">
            <label class="pos-toggle-label">
              <input type="checkbox" id="isTakeaway">
              <span class="pos-toggle-track"><span class="pos-toggle-thumb"></span></span>
              <span>Ordine da Asporto</span>
            </label>
          </div>

          <!-- Numero tavolo (disabilitato se asporto) -->
          <div class="form-group" id="tableGroup">
            <label for="tableNumber">Numero Tavolo</label>
            <input type="text" id="tableNumber" maxlength="20"
                   placeholder="es. 5 oppure A3">
          </div>

          <!-- Coperti (nascosto se asporto) -->
          <div class="form-group" id="coversGroup">
            <label for="orderCovers">
              Coperti
              <small id="coverFeePreview" style="color:var(--muted)"></small>
            </label>
            <input type="number" id="orderCovers" min="0" max="30" value="1">
          </div>

        </div><!-- /pos-order-meta -->

        <!-- Prodotti per categoria -->
        <div id="productsArea"><p class="empty-msg">Caricamento menu…</p></div>

        <!-- Carrello -->
        <h2 style="margin-top:1.25rem;">Carrello</h2>
        <ul class="pos-cart-list" id="cartList"></ul>

        <div class="pos-cart-total">
          <span>Totale</span>
          <span id="cartTotal">€ 0,00</span>
        </div>

        <!-- Coupon -->
        <div class="pos-coupon-row">
          <input type="text" id="couponCode" placeholder="Codice coupon" autocomplete="off">
          <button type="button" class="btn btn-outline btn-sm" id="btnVerifyCoupon">Verifica</button>
        </div>
        <div id="couponAlert"></div>

        <!-- Conferma -->
        <div class="pos-confirm-row">
          <button type="button" class="btn btn-gold" id="btnConfirmOrder" disabled>
            Conferma ordine
          </button>
        </div>
      </div><!-- /orderFormView -->

      <!-- Vista dopo conferma ordine -->
      <div id="orderDoneView" class="pos-order-success pos-hidden">
        <p class="empty-msg">Ordine registrato</p>
        <div class="order-num" id="assignedOrderNum">#—</div>
        <div id="orderSummaryTotal" style="color:var(--gold);font-size:1.1rem;margin-top:.5rem;"></div>
        <button type="button" class="btn btn-gold" id="btnNewOrder" style="margin-top:1rem;">
          Nuovo ordine
        </button>
      </div>

    </section><!-- /panelNewOrder -->

    <!-- ── Coda ordini ────────────────────────────────────── -->
    <section class="pos-panel" id="panelQueue">
      <h2>Coda ordini</h2>
      <div id="queueArea"><p class="empty-msg">Nessun ordine in coda.</p></div>
    </section>

  </div><!-- /pos-operator-grid -->
</main>

<div id="modalRoot"></div>

<script>
(function () {
  'use strict';

  const API          = 'api';
  const COVER_FEE    = <?= json_encode($coverFeeUnit) ?>;
  const TAKEAWAY_FEE = <?= json_encode($takeawayFee) ?>;
  const SHOW_STATION = <?= json_encode($showStationInQueue) ?>;

  let products    = [];
  let cart        = [];      // { key, product_id, product_name, product_kind,
                             //   unit_price, quantity, coupon_id,
                             //   discount_type, discount_value,
                             //   item_note, modifications }
  let activeCoupon = null;
  let queuePolling = false;

  // ── DOM refs ────────────────────────────────────────────────
  const productsArea   = document.getElementById('productsArea');
  const cartList       = document.getElementById('cartList');
  const cartTotalEl    = document.getElementById('cartTotal');
  const couponAlert    = document.getElementById('couponAlert');
  const btnConfirm     = document.getElementById('btnConfirmOrder');
  const orderFormView  = document.getElementById('orderFormView');
  const orderDoneView  = document.getElementById('orderDoneView');
  const assignedNum    = document.getElementById('assignedOrderNum');
  const orderSummaryTotal = document.getElementById('orderSummaryTotal');
  const queueArea      = document.getElementById('queueArea');
  const customerName   = document.getElementById('customerName');
  const tableNumber    = document.getElementById('tableNumber');
  const isTakeaway     = document.getElementById('isTakeaway');
  const orderCovers    = document.getElementById('orderCovers');
  const tableGroup     = document.getElementById('tableGroup');
  const coversGroup    = document.getElementById('coversGroup');
  const coverFeePreview = document.getElementById('coverFeePreview');

  // ── Utilità ─────────────────────────────────────────────────
  function euro(n) {
    return '€ ' + Number(n).toFixed(2).replace('.', ',');
  }
  function escapeHtml(s) {
    const d = document.createElement('div');
    d.textContent = s ?? '';
    return d.innerHTML;
  }
  async function apiFetch(url, options) {
    const res  = await fetch(url, options);
    const json = await res.json();
    if (!json.success) throw new Error(json.error || 'Errore server');
    return json.data;
  }

  // ── Toggle Asporto ──────────────────────────────────────────
  isTakeaway.addEventListener('change', updateTakeawayUI);
  orderCovers.addEventListener('input', updateCoverPreview);

  function updateTakeawayUI() {
    const ta = isTakeaway.checked;
    tableGroup.style.opacity   = ta ? '1' : '1';
    coversGroup.style.display  = ta ? 'none' : '';
    tableNumber.disabled       = false;
    if (ta) {
      tableNumber.placeholder = 'Asporto';
      orderCovers.value = '0';
    } else {
      tableNumber.placeholder = 'es. 5 oppure A3';
      if (parseInt(orderCovers.value, 10) < 1) orderCovers.value = '1';
    }
    updateCoverPreview();
    updateTotal();
  }
  function updateCoverPreview() {
    if (isTakeaway.checked) {
      coverFeePreview.textContent = '';
      return;
    }
    const covers = parseInt(orderCovers.value, 10) || 0;
    if (covers > 0 && COVER_FEE > 0) {
      coverFeePreview.textContent = '(+ ' + euro(covers * COVER_FEE) + ' coperto)';
    } else {
      coverFeePreview.textContent = '';
    }
  }

  // ── Prodotti ────────────────────────────────────────────────
  async function loadProducts() {
    try {
      products = await apiFetch(API + '/get_products.php');
      renderProducts();
    } catch (e) {
      productsArea.innerHTML = '<div class="alert alert-error">' + escapeHtml(e.message) + '</div>';
    }
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
        escapeHtml(catName) + ' · ' + byCat[catName].dept + '</div>' +
        '<div class="pos-products-grid">';
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

  // ── Modal personalizzazione ─────────────────────────────────
  function openCustomize(productId) {
    const product = products.find(p => p.id === productId);
    if (!product) return;

    const removable = product.ingredients.filter(i => i.is_removable === 1);
    const extras    = product.ingredients.filter(i => i.is_extra === 1);

    let body = '<h3>' + escapeHtml(product.name) + '</h3>';
    if (removable.length) {
      body += '<div class="modal-section"><h4>Rimuovi ingredienti</h4>';
      removable.forEach(ing => {
        body += '<label class="pos-check-row"><input type="checkbox" data-mod="remove" data-id="' +
          ing.id + '"> ' + escapeHtml(ing.name) + '</label>';
      });
      body += '</div>';
    }
    if (extras.length) {
      body += '<div class="modal-section"><h4>Extra</h4>';
      extras.forEach(ing => {
        body += '<label class="pos-check-row"><input type="checkbox" data-mod="extra" data-id="' +
          ing.id + '" data-price="' + ing.extra_price + '"> ' + escapeHtml(ing.name) +
          ' (+' + euro(ing.extra_price) + ')</label>';
      });
      body += '</div>';
    }

    const overlay = document.createElement('div');
    overlay.className = 'pos-modal-overlay';
    overlay.innerHTML = '<div class="pos-modal">' + body +
      '<div class="pos-modal-actions">' +
      '<button type="button" class="btn btn-gold" id="modalAdd">Aggiungi al carrello</button>' +
      '<button type="button" class="btn btn-outline" id="modalClose">Annulla</button>' +
      '</div></div>';

    overlay.querySelector('#modalClose').onclick = () => overlay.remove();
    overlay.addEventListener('click', e => { if (e.target === overlay) overlay.remove(); });

    overlay.querySelector('#modalAdd').onclick = () => {
      const mods = [];
      overlay.querySelectorAll('input[data-mod="remove"]:checked').forEach(cb => {
        mods.push({ ingredient_id: parseInt(cb.dataset.id, 10), action: 'removed', extra_price: 0 });
      });
      overlay.querySelectorAll('input[data-mod="extra"]:checked').forEach(cb => {
        mods.push({ ingredient_id: parseInt(cb.dataset.id, 10), action: 'added',
          extra_price: parseFloat(cb.dataset.price) || 0 });
      });
      cart.push({
        key:           Date.now() + '-' + Math.random(),
        product_id:    product.id,
        product_name:  product.name,
        product_kind:  product.product_kind || 'sandwich',
        unit_price:    product.price,
        quantity:      1,
        coupon_id:     null,
        discount_type: null,
        discount_value: 0,
        item_note:     '',
        modifications: mods,
      });
      overlay.remove();
      renderCart();
    };

    document.getElementById('modalRoot').appendChild(overlay);
  }

  // ── Calcolo riga carrello ───────────────────────────────────
  function lineDiscount(item) {
    if (!item.coupon_id) return 0;
    if (item.discount_type === 'percent') {
      return Math.round(item.unit_price * item.discount_value) / 100;
    }
    return Math.min(item.discount_value, item.unit_price);
  }
  function lineTotal(item) {
    let t = item.unit_price * item.quantity;
    item.modifications.forEach(m => {
      if (m.action === 'added') t += m.extra_price * item.quantity;
    });
    t -= lineDiscount(item) * item.quantity;
    return Math.max(0, t);
  }

  function updateTotal() {
    const ta     = isTakeaway.checked;
    const covers = ta ? 0 : (parseInt(orderCovers.value, 10) || 0);
    let total    = cart.reduce((s, i) => s + lineTotal(i), 0);
    total       += ta ? TAKEAWAY_FEE : covers * COVER_FEE;
    cartTotalEl.textContent = euro(total);
    btnConfirm.disabled = cart.length === 0;
  }

  // ── Render carrello ─────────────────────────────────────────
  function renderCart() {
    if (!cart.length) {
      cartList.innerHTML = '<li class="empty-msg">Carrello vuoto</li>';
      cartTotalEl.textContent = euro(0);
      btnConfirm.disabled = true;
      return;
    }

    cartList.innerHTML = cart.map((item, idx) => {
      const lt    = lineTotal(item);
      const disc  = lineDiscount(item);
      let modsHtml = '';
      item.modifications.forEach(m => {
        const ing = findIngName(item.product_id, m.ingredient_id);
        modsHtml += '<div class="mods">' + (m.action === 'removed' ? '− ' : '+ ') +
          escapeHtml(ing) + (m.extra_price > 0 ? ' (' + euro(m.extra_price) + ')' : '') + '</div>';
      });

      // Badge sconto coupon
      const couponBadge = item.coupon_id
        ? '<span class="pos-badge-coupon">' +
          (item.discount_type === 'percent'
            ? '-' + item.discount_value + '%'
            : '-' + euro(disc)) +
          '</span>' : '';

      return '<li class="pos-cart-item' + (item.coupon_id ? ' coupon-free' : '') + '" data-idx="' + idx + '">' +
        '<div class="cart-item-body">' +
          '<div class="item-name">' + escapeHtml(item.product_name) + couponBadge + '</div>' +
          modsHtml +
          '<div class="pos-note-row">' +
            '<input type="text" class="pos-item-note" data-idx="' + idx + '"' +
            ' value="' + escapeHtml(item.item_note) + '" placeholder="Note (opz.)" maxlength="255">' +
          '</div>' +
        '</div>' +
        '<div class="cart-item-controls">' +
          '<div class="pos-qty-row">' +
            '<button type="button" class="btn-qty btn-minus" data-idx="' + idx + '" aria-label="Diminuisci">−</button>' +
            '<span class="qty-val">' + item.quantity + '</span>' +
            '<button type="button" class="btn-qty btn-plus"  data-idx="' + idx + '" aria-label="Aumenta">+</button>' +
          '</div>' +
          '<span class="item-price">' + euro(lt) + '</span>' +
          '<button type="button" class="btn-remove" data-idx="' + idx + '" aria-label="Rimuovi">×</button>' +
        '</div>' +
      '</li>';
    }).join('');

    // Listener note
    cartList.querySelectorAll('.pos-item-note').forEach(inp => {
      inp.addEventListener('input', () => {
        const i = parseInt(inp.dataset.idx, 10);
        cart[i].item_note = inp.value;
      });
    });

    // Listener quantità
    cartList.querySelectorAll('.btn-minus').forEach(btn => {
      btn.onclick = () => {
        const i = parseInt(btn.dataset.idx, 10);
        cart[i].quantity--;
        if (cart[i].quantity <= 0) {
          if (cart[i].coupon_id) activeCoupon = null;
          cart.splice(i, 1);
        }
        renderCart();
      };
    });
    cartList.querySelectorAll('.btn-plus').forEach(btn => {
      btn.onclick = () => {
        const i = parseInt(btn.dataset.idx, 10);
        cart[i].quantity++;
        renderCart();
      };
    });
    cartList.querySelectorAll('.btn-remove').forEach(btn => {
      btn.onclick = () => {
        const i = parseInt(btn.dataset.idx, 10);
        if (cart[i].coupon_id) activeCoupon = null;
        cart.splice(i, 1);
        renderCart();
      };
    });

    updateTotal();
  }

  function findIngName(productId, ingId) {
    const p = products.find(x => x.id === productId);
    if (!p) return 'Ingrediente';
    const ing = p.ingredients.find(i => i.id === ingId);
    return ing ? ing.name : 'Ingrediente';
  }

  // ── Coupon ──────────────────────────────────────────────────
  document.getElementById('btnVerifyCoupon').onclick = async () => {
    couponAlert.innerHTML = '';
    const code = document.getElementById('couponCode').value.trim();
    if (!code) return;
    try {
      const data = await apiFetch(API + '/validate_coupon.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ code }),
      });

      const restriction = data.product_type_restriction;
      const idx = cart.findIndex(it => !it.coupon_id &&
        (restriction === 'any' || it.product_kind === restriction));

      if (idx === -1) {
        couponAlert.innerHTML = '<div class="alert alert-error">Nessun prodotto nel carrello compatibile con questo coupon (' +
          escapeHtml(restriction) + ').</div>';
        return;
      }

      cart[idx].coupon_id     = data.id;
      cart[idx].discount_type  = data.discount_type;
      cart[idx].discount_value = data.discount_value;
      activeCoupon = data;

      const discLabel = data.discount_type === 'percent'
        ? '-' + data.discount_value + '%'
        : '-' + euro(data.discount_value);

      couponAlert.innerHTML = '<div class="alert alert-success">Coupon <strong>' + escapeHtml(data.code) +
        '</strong> applicato a <strong>' + escapeHtml(cart[idx].product_name) +
        '</strong> (' + escLabel(data) + ').</div>';

      renderCart();
    } catch (e) {
      couponAlert.innerHTML = '<div class="alert alert-error">' + escapeHtml(e.message) + '</div>';
    }
  };

  function escLabel(coupon) {
    return coupon.discount_type === 'percent'
      ? 'sconto ' + coupon.discount_value + '%'
      : 'sconto ' + euro(coupon.discount_value);
  }

  // ── Conferma ordine ─────────────────────────────────────────
  document.getElementById('btnConfirmOrder').onclick = async () => {
    if (!cart.length) return;
    const cName   = customerName.value.trim();
    const tNumber = tableNumber.value.trim();
    const ta      = isTakeaway.checked;
    const covers  = ta ? 0 : (parseInt(orderCovers.value, 10) || 0);

    if (!cName) {
      couponAlert.innerHTML = '<div class="alert alert-error">Inserisci il nome del cliente.</div>';
      return;
    }
    if (!ta && !tNumber) {
      couponAlert.innerHTML = '<div class="alert alert-error">Inserisci il numero del tavolo o seleziona Asporto.</div>';
      return;
    }

    btnConfirm.disabled = true;
    try {
      const payload = {
        customer_name: cName,
        table_number:  ta ? null : tNumber,
        is_takeaway:   ta,
        covers:        covers,
        items: cart.map(it => ({
          product_id:    it.product_id,
          quantity:      it.quantity,
          item_note:     it.item_note || null,
          coupon_id:     it.coupon_id,
          modifications: it.modifications.map(m => ({
            ingredient_id: m.ingredient_id,
            action:        m.action,
            extra_price:   m.extra_price,
          })),
        })),
      };

      const data = await apiFetch(API + '/create_order.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload),
      });

      assignedNum.textContent      = '#' + data.daily_number;
      orderSummaryTotal.textContent = 'Totale: ' + euro(data.total);
      orderFormView.classList.add('pos-hidden');
      orderDoneView.classList.remove('pos-hidden');

      // Reset
      cart         = [];
      activeCoupon = null;
      document.getElementById('couponCode').value = '';
      customerName.value  = '';
      tableNumber.value   = '';
      isTakeaway.checked  = false;
      orderCovers.value   = '1';
      couponAlert.innerHTML = '';
      updateTakeawayUI();
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
  };

  // ── Coda ordini (senza tasto Delivered) ────────────────────
  // La cassa mostra solo pending → preparing; la consegna spetta a cucina/bar.
  const statusFlow = {
    pending:   { next: 'preparing', label: '→ In preparazione' },
    preparing: { next: 'ready',     label: '→ Pronto' },
    // "ready" non ha tasto next in cassa
  };

  function renderQueue(orders) {
    if (!orders.length) {
      queueArea.innerHTML = '<p class="empty-msg">Nessun ordine in coda.</p>';
      return;
    }
    const groups = { pending: [], preparing: [], ready: [] };
    orders.forEach(o => { if (groups[o.status]) groups[o.status].push(o); });

    let html = '';
    ['pending', 'preparing', 'ready'].forEach(st => {
      if (!groups[st].length) return;
      const stLabel = { pending: 'In attesa', preparing: 'In preparazione', ready: 'Pronti' }[st];
      html += '<div class="pos-queue-section"><h3>' + stLabel + '</h3>';
      groups[st].forEach(o => {
        const time   = new Date(o.created_at).toLocaleTimeString('it-IT', { hour: '2-digit', minute: '2-digit' });
        const where  = o.is_takeaway ? '<span class="pos-badge-takeaway">ASPORTO</span>'
          : (o.table_number ? 'Tavolo ' + escapeHtml(o.table_number) : '—');
        const itemsTxt = o.items.map(i => i.quantity + '× ' + i.product_name).join(', ');
        const stationBadge = SHOW_STATION && o.station_name
          ? '<span class="pos-badge-station">' + escapeHtml(o.station_name) + '</span>' : '';

        html += '<div class="pos-order-card" data-order-id="' + o.id + '">' +
          '<div class="order-head"><span class="order-num-sm">#' + o.daily_number +
          (o.has_coupon ? '<span class="pos-badge-coupon">Coupon</span>' : '') +
          stationBadge + '</span>' +
          '<span class="status-badge ' + o.status + '">' + stLabel + '</span></div>' +
          '<div class="order-time">' + time + '</div>' +
          '<div class="order-customer"><strong>' + escapeHtml(o.customer_name || '—') + '</strong> · ' + where + '</div>' +
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
            body: JSON.stringify({ type: 'order', id: parseInt(btn.dataset.id, 10), status: btn.dataset.status }),
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
            body: JSON.stringify({ order_id: parseInt(btn.dataset.id, 10) }),
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
    } catch (e) { /* silenzioso */ }
    finally { queuePolling = false; }
  }

  // ── Init ────────────────────────────────────────────────────
  updateCoverPreview();
  orderCovers.addEventListener('input', updateTotal);
  loadProducts();
  loadQueue();
  setInterval(loadQueue, 5000);

})();
</script>
</body>
</html>
