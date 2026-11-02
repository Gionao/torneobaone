<?php
// pos/stato-ordine.php — Pagina pubblica tracciamento ordine
// Nessuna autenticazione richiesta.
declare(strict_types=1);
require_once dirname(__DIR__) . '/includes/config.php';

$pageTitle = 'Traccia il tuo ordine';
?>
<!DOCTYPE html>
<html lang="it">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= htmlspecialchars($pageTitle) ?> — <?= htmlspecialchars(SITE_NAME) ?></title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Barlow+Condensed:wght@600;700;800&family=Barlow:wght@400;500;600&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?= SITE_URL ?>/assets/css/tokens.css?v=redesign-1">
  <link rel="stylesheet" href="<?= SITE_URL ?>/assets/css/style.css">
  <link rel="stylesheet" href="<?= POS_URL ?>/assets/css/pos.css">
  <style>
    .track-wrap {
      min-height: 100vh;
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: flex-start;
      padding: 3rem 1rem 4rem;
      background: var(--campo-950);
    }
    .track-logo {
      font-family: 'Barlow Condensed', sans-serif;
      font-size: 1.1rem;
      font-weight: 700;
      color: var(--gold);
      letter-spacing: .1em;
      text-transform: uppercase;
      margin-bottom: 2rem;
      text-align: center;
    }
    .track-box {
      background: var(--navy-mid);
      border: 1px solid rgba(231,169,50,.15);
      border-radius: var(--radius);
      padding: 2rem;
      width: 100%;
      max-width: 480px;
    }
    .track-box h1 {
      font-family: 'Barlow Condensed', sans-serif;
      font-size: 1.6rem;
      font-weight: 800;
      letter-spacing: .06em;
      text-transform: uppercase;
      color: var(--white);
      margin-bottom: 1.25rem;
      text-align: center;
    }
    .track-input-row {
      display: flex;
      gap: .75rem;
      margin-bottom: .5rem;
    }
    .track-input-row input[type="number"] {
      flex: 1;
      font-size: 1.5rem;
      text-align: center;
      font-family: 'Barlow Condensed', sans-serif;
      font-weight: 700;
    }
    .track-result {
      margin-top: 1.5rem;
    }
    .order-track-card {
      background: rgba(255,255,255,.03);
      border: 1px solid rgba(231,169,50,.12);
      border-radius: 10px;
      padding: 1.25rem;
    }
    .order-track-header {
      display: flex;
      align-items: center;
      gap: .75rem;
      margin-bottom: 1rem;
    }
    .order-track-num {
      font-family: 'Barlow Condensed', sans-serif;
      font-size: 2rem;
      font-weight: 800;
      color: var(--gold);
    }
    .order-track-meta {
      font-size: .9rem;
      color: var(--muted);
    }
    .order-track-status {
      font-family: 'Barlow Condensed', sans-serif;
      font-size: 1.1rem;
      font-weight: 700;
      letter-spacing: .04em;
      text-transform: uppercase;
      padding: .4rem 1rem;
      border-radius: 4px;
      margin: .75rem 0 1rem;
      text-align: center;
    }
    .order-track-status.pending   { background:#2a2408;color:#e8c76a; }
    .order-track-status.preparing { background:#0d2a3d;color:#4db6f5; }
    .order-track-status.ready     { background:#0d2a1c;color:#4caf82; }
    .order-track-status.delivered { background:#1a2d1a;color:#a5d6a7; }
    .order-track-status.cancelled { background:#2d1a1a;color:#ef9a9a; }
    .order-track-items li {
      padding: .4rem 0;
      border-bottom: 1px solid rgba(255,255,255,.06);
      font-size: .92rem;
    }
    .order-track-items li:last-child { border-bottom: none; }
    .item-dept-badge {
      font-size: .72rem;
      background: rgba(231,169,50,.1);
      color: var(--gold);
      border-radius: 3px;
      padding: .1rem .4rem;
      margin-left: .5rem;
      text-transform: uppercase;
      letter-spacing: .05em;
    }
    .track-refresh-hint {
      text-align: center;
      font-size: .8rem;
      color: var(--muted);
      margin-top: 1rem;
    }
    .track-back {
      display: block;
      text-align: center;
      margin-top: 1.5rem;
      color: var(--gold);
      text-decoration: none;
      font-size: .9rem;
    }
    .track-back:hover { text-decoration: underline; }
    .progress-steps {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin: 1rem 0 1.25rem;
      position: relative;
    }
    .progress-steps::before {
      content: '';
      position: absolute;
      left: 0; right: 0; top: 50%;
      height: 2px;
      background: rgba(255,255,255,.08);
      z-index: 0;
    }
    .step {
      display: flex;
      flex-direction: column;
      align-items: center;
      gap: .25rem;
      z-index: 1;
      background: var(--campo-950);
      padding: 0 .25rem;
    }
    .step-dot {
      width: 28px; height: 28px;
      border-radius: 50%;
      background: rgba(255,255,255,.06);
      border: 2px solid rgba(255,255,255,.12);
      display: flex; align-items: center; justify-content: center;
      font-size: .7rem;
      color: var(--muted);
      transition: all .3s;
    }
    .step.active .step-dot   { background: var(--gold); border-color: var(--gold); color: #000; }
    .step.done  .step-dot    { background: #4caf82; border-color: #4caf82; color: #fff; }
    .step-label {
      font-size: .65rem;
      text-transform: uppercase;
      letter-spacing: .04em;
      color: var(--muted);
    }
    .step.active .step-label { color: var(--gold); }
    .step.done  .step-label  { color: #4caf82; }
  </style>
</head>
<body>
<div class="track-wrap">
  <div class="track-logo">🥪 <?= htmlspecialchars(SITE_NAME) ?> — Festa dei Panini</div>

  <div class="track-box">
    <h1>Traccia il tuo ordine</h1>

    <div class="track-input-row">
      <input type="number" id="orderNumber" min="1" max="9999"
             placeholder="N° ordine" autofocus>
      <button type="button" class="btn btn-gold" id="btnTrack">Cerca</button>
    </div>

    <div style="text-align:center;font-size:.8rem;color:var(--muted);">
      Il numero è stampato sullo scontrino o comunicato alla cassa.
    </div>

    <div id="trackResult" class="track-result"></div>
  </div>

  <a class="track-back" href="<?= SITE_URL ?>">← Torna al sito del torneo</a>
</div>

<script>
(function () {
  'use strict';

  const btn    = document.getElementById('btnTrack');
  const numInp = document.getElementById('orderNumber');
  const result = document.getElementById('trackResult');

  const stepKeys    = ['pending', 'preparing', 'ready', 'delivered'];
  const stepLabels  = ['In attesa', 'In preparazione', 'Pronto', 'Consegnato'];
  const stepIcons   = ['⏳', '🔥', '✅', '🎉'];

  let pollTimer = null;

  function escapeHtml(s) {
    const d = document.createElement('div');
    d.textContent = s ?? '';
    return d.innerHTML;
  }

  function renderSteps(status) {
    if (status === 'cancelled') return '';
    const idx = stepKeys.indexOf(status);
    return '<div class="progress-steps">' +
      stepKeys.map((key, i) => {
        const cls = i < idx ? 'done' : (i === idx ? 'active' : '');
        return '<div class="step ' + cls + '">' +
          '<div class="step-dot">' + (i < idx ? '✓' : stepIcons[i]) + '</div>' +
          '<div class="step-label">' + stepLabels[i] + '</div>' +
          '</div>';
      }).join('') +
      '</div>';
  }

  async function track() {
    const num = parseInt(numInp.value, 10);
    if (!num || num < 1) {
      result.innerHTML = '<div class="alert alert-error">Inserisci un numero ordine valido.</div>';
      return;
    }
    result.innerHTML = '<p class="empty-msg" style="text-align:center;margin-top:1rem;">Ricerca in corso…</p>';

    try {
      const today = new Date().toISOString().slice(0, 10);
      const res   = await fetch('api/track_order.php?number=' + num + '&date=' + today);
      const json  = await res.json();

      if (!json.success) {
        result.innerHTML = '<div class="alert alert-error">' + escapeHtml(json.error) + '</div>';
        clearInterval(pollTimer);
        return;
      }

      const o = json.data;
      const where = o.is_takeaway
        ? '<span style="color:var(--gold)">ASPORTO</span>'
        : (o.table_number ? 'Tavolo <strong>' + escapeHtml(o.table_number) + '</strong>' : '—');
      const time = new Date(o.created_at).toLocaleTimeString('it-IT', { hour: '2-digit', minute: '2-digit' });

      const itemsHtml = o.items.map(i => {
        const noteHtml = i.item_note
          ? '<span style="color:var(--muted);font-size:.82rem"> — ' + escapeHtml(i.item_note) + '</span>' : '';
        const deptBadge = '<span class="item-dept-badge">' + escapeHtml(i.department) + '</span>';
        return '<li>' + i.quantity + '× <strong>' + escapeHtml(i.product_name) + '</strong>' +
          deptBadge + noteHtml + '</li>';
      }).join('');

      result.innerHTML =
        '<div class="order-track-card">' +
          '<div class="order-track-header">' +
            '<div class="order-track-num">#' + o.daily_number + '</div>' +
            '<div class="order-track-meta">' +
              '<div>' + escapeHtml(o.customer_name) + '</div>' +
              '<div>' + where + ' · ' + time + '</div>' +
            '</div>' +
          '</div>' +

          renderSteps(o.status) +

          '<div class="order-track-status ' + o.status + '">' + escapeHtml(o.status_label) + '</div>' +

          '<ul class="order-track-items" style="list-style:none;padding:0;margin:0;">' +
            itemsHtml +
          '</ul>' +

          (o.status !== 'delivered' && o.status !== 'cancelled'
            ? '<div class="track-refresh-hint">Aggiornamento automatico ogni 10 secondi</div>'
            : '') +
        '</div>';

      // Auto-aggiornamento se ordine non completato
      clearInterval(pollTimer);
      if (o.status !== 'delivered' && o.status !== 'cancelled') {
        pollTimer = setInterval(track, 10000);
      }

    } catch (e) {
      result.innerHTML = '<div class="alert alert-error">Errore di connessione. Riprova.</div>';
    }
  }

  btn.addEventListener('click', track);
  numInp.addEventListener('keydown', e => { if (e.key === 'Enter') track(); });

  // Se c'è un parametro ?n= nell'URL, carica subito
  const urlNum = new URLSearchParams(window.location.search).get('n');
  if (urlNum) {
    numInp.value = urlNum;
    track();
  }
})();
</script>
</body>
</html>
