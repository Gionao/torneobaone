(() => {
  'use strict';
  const api = document.currentScript.dataset.api;
  document.addEventListener('click', event => {
    const button = event.target.closest('[data-convert-order], [data-cancel-staff-order]');
    if (!button) return;
    event.stopPropagation();
    const cancel = button.hasAttribute('data-cancel-staff-order');
    const toStaff = button.dataset.toStaff === '1';
    const overlay = document.createElement('div');
    overlay.className = 'pos-modal-overlay';
    overlay.innerHTML = `<form class="pos-modal">
      <h3>${cancel ? 'Annulla ordine' : toStaff ? 'Trasforma in ordine staff' : 'Trasforma in ordine normale'}</h3>
      <p>${cancel ? 'L’ordine sarà escluso dai consumi e dagli incassi.' : toStaff ? 'Il totale diventerà 0 €. Eventuali coupon saranno rimossi e resi nuovamente disponibili.' : 'Il totale sarà ricalcolato con i prezzi registrati nell’ordine e le tariffe attuali di coperto e asporto.'}</p>
      ${!cancel && !toStaff ? `<div class="form-group"><label>Nome cliente<input name="customer_name" maxlength="100" required></label></div>
        <div class="form-group"><label><input type="checkbox" name="is_takeaway"> Asporto</label></div>
        <div class="form-group" data-table><label>Numero tavolo<input name="table_number" maxlength="20" required></label></div>
        <div class="form-group" data-covers><label>Coperti<input type="number" name="covers" min="1" max="255" value="1" required></label></div>` : ''}
      <p role="alert" data-error></p>
      <div class="pos-modal-actions"><button type="submit" class="btn btn-gold">Conferma</button>
        <button type="button" class="btn btn-outline" data-close>Chiudi</button></div>
    </form>`;
    const form = overlay.querySelector('form');
    overlay.querySelector('[data-close]').onclick = () => overlay.remove();
    const takeaway = form.elements.is_takeaway;
    if (takeaway) takeaway.onchange = () => {
      for (const name of ['table_number', 'covers']) {
        form.elements[name].disabled = takeaway.checked;
        overlay.querySelector(name === 'covers' ? '[data-covers]' : '[data-table]').hidden = takeaway.checked;
      }
    };
    form.onsubmit = async event => {
      event.preventDefault();
      const submit = form.querySelector('[type=submit]');
      submit.disabled = true;
      try {
        const payload = {order_id: Number(cancel ? button.dataset.cancelStaffOrder : button.dataset.convertOrder), is_staff: toStaff};
        if (takeaway) Object.assign(payload, {
          customer_name: form.elements.customer_name.value.trim(),
          is_takeaway: takeaway.checked,
          table_number: form.elements.table_number.value.trim(),
          covers: Number(form.elements.covers.value),
        });
        const response = await fetch(api + (cancel ? '/cancel_order.php' : '/convert_order.php'), {
          method: 'POST', headers: {'Content-Type': 'application/json'}, body: JSON.stringify(payload),
        });
        const result = await response.json();
        if (!result.success) throw new Error(result.error || 'Operazione non riuscita.');
        window.location.reload();
      } catch (error) {
        overlay.querySelector('[data-error]').textContent = error.message;
        submit.disabled = false;
      }
    };
    document.body.appendChild(overlay);
    (form.querySelector('input:not([type=checkbox])') || form.querySelector('[type=submit]')).focus();
  });
})();
