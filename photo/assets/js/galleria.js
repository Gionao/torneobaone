/* photo/assets/galleria.js
   Selezione file e generazione di archivi ZIP. */
(function () {
  'use strict';

  var caselle = Array.prototype.slice.call(document.querySelectorAll('.seleziona-file'));
  var numeroEl = document.getElementById('numeroSelezionati');
  var selezionaTutti = document.getElementById('selezionaTutti');
  var scaricaAlbum = document.getElementById('scaricaAlbum');
  var scaricaSelezionati = document.getElementById('scaricaSelezionati');
  var esitoEl = document.getElementById('zipEsito');
  var csrfMeta = document.querySelector('meta[name="csrf-token"]');
  var csrf = csrfMeta ? csrfMeta.getAttribute('content') : '';

  if (!caselle.length && !scaricaAlbum) { return; }

  function aggiornaSelezione() {
    var scelti = caselle.filter(function (c) { return c.checked; });
    if (numeroEl) { numeroEl.textContent = String(scelti.length); }
    caselle.forEach(function (c) {
      var scheda = c.closest('.archivio-file');
      if (scheda) { scheda.classList.toggle('selezionato', c.checked); }
    });
    if (scaricaSelezionati) {
      scaricaSelezionati.disabled = scelti.length === 0;
    }
    if (selezionaTutti) {
      selezionaTutti.textContent = scelti.length === caselle.length && caselle.length > 0
        ? 'Deseleziona tutti'
        : 'Seleziona tutti';
    }
  }

  caselle.forEach(function (c) {
    c.addEventListener('change', aggiornaSelezione);
  });

  if (selezionaTutti) {
    selezionaTutti.addEventListener('click', function () {
      var tutti = caselle.every(function (c) { return c.checked; });
      caselle.forEach(function (c) { c.checked = !tutti; });
      aggiornaSelezione();
    });
  }

  function mostraEsito(testo, tipo) {
    if (!esitoEl) { return; }
    esitoEl.style.display = 'block';
    esitoEl.className = 'archivio-' + (tipo === 'errore' ? 'warning' : 'info');
    esitoEl.textContent = testo;
  }

  function messaggioErrore(risposta) {
    return risposta.json().then(function (dati) {
      throw new Error(dati && dati.errore ? dati.errore : 'Operazione non riuscita.');
    }).catch(function (errore) {
      if (errore instanceof Error && errore.message && errore.message !== 'Unexpected token') {
        throw errore;
      }
      throw new Error('Errore di comunicazione con il server (codice ' + risposta.status + ').');
    });
  }

  function formattaByte(n) {
    if (n < 1024) { return n + ' B'; }
    if (n < 1048576) { return (n / 1024).toFixed(1).replace('.', ',') + ' KB'; }
    if (n < 1073741824) { return (n / 1048576).toFixed(1).replace('.', ',') + ' MB'; }
    return (n / 1073741824).toFixed(2).replace('.', ',') + ' GB';
  }

  function attendi(uuid, urlStatus, tentativi) {
    tentativi = tentativi || 0;
    if (tentativi > 300) {
      mostraEsito('La preparazione dell\'archivio sta impiegando troppo tempo. Riprova più tardi dalla stessa pagina.', 'errore');
      abilitaPulsanti(true);
      return;
    }
    window.setTimeout(function () {
      fetch(urlStatus + '?uuid=' + encodeURIComponent(uuid), {
        credentials: 'same-origin',
        headers: { 'Accept': 'application/json' }
      }).then(function (r) {
        if (!r.ok) { return messaggioErrore(r); }
        return r.json();
      }).then(function (dati) {
        if (!dati.ok) { throw new Error(dati.errore || 'Errore nella preparazione dell\'archivio.'); }
        if (dati.stato === 'completato') {
          mostraEsito('Archivio pronto: ' + dati.numero_file + ' file, ' + formattaByte(Number(dati.dimensione || 0)) + '.', 'ok');
          if (esitoEl) {
            esitoEl.textContent = 'Archivio pronto: ' + dati.numero_file + ' file, ' + formattaByte(Number(dati.dimensione || 0)) + '. ';
            (dati.parti || []).forEach(function (parte) {
              var link = document.createElement('a');
              link.href = parte.url;
              link.className = 'btn btn-sm btn-gold';
              link.style.marginLeft = '.4rem';
              link.textContent = (dati.parti.length > 1 ? 'Scarica parte ' + parte.indice : 'Scarica ZIP');
              esitoEl.appendChild(link);
            });
          }
          abilitaPulsanti(true);
          return;
        }
        if (dati.stato === 'errore') {
          throw new Error(dati.errore || 'Errore nella creazione dell\'archivio.');
        }
        mostraEsito(dati.stato === 'in_corso' ? 'Preparazione dell\'archivio in corso…' : 'Archivio in attesa di elaborazione…', 'info');
        attendi(uuid, urlStatus, tentativi + 1);
      }).catch(function (errore) {
        mostraEsito(errore.message || 'Errore nella preparazione dell\'archivio.', 'errore');
        abilitaPulsanti(true);
      });
    }, tentativi === 0 ? 400 : 1800);
  }

  function abilitaPulsanti(attivo) {
    if (scaricaAlbum) { scaricaAlbum.disabled = !attivo; }
    if (scaricaSelezionati) { scaricaSelezionati.disabled = !attivo || caselle.filter(function (c) { return c.checked; }).length === 0; }
    if (selezionaTutti) { selezionaTutti.disabled = !attivo; }
  }

  function creaArchivio(urlCreate, urlStatus, dati) {
    abilitaPulsanti(false);
    mostraEsito('Richiesta dell\'archivio inviata. Attendi la preparazione…', 'info');
    var body = new FormData();
    Object.keys(dati).forEach(function (chiave) { body.append(chiave, String(dati[chiave])); });
    fetch(urlCreate, {
      method: 'POST',
      credentials: 'same-origin',
      headers: { 'X-CSRF-Token': csrf, 'Accept': 'application/json' },
      body: body
    }).then(function (r) {
      if (!r.ok) { return messaggioErrore(r); }
      return r.json();
    }).then(function (risposta) {
      if (!risposta.ok) { throw new Error(risposta.errore || 'Richiesta non riuscita.'); }
      attendi(risposta.uuid, urlStatus, 0);
    }).catch(function (errore) {
      mostraEsito(errore.message || 'Impossibile creare l\'archivio.', 'errore');
      abilitaPulsanti(true);
    });
  }

  if (scaricaAlbum) {
    scaricaAlbum.addEventListener('click', function () {
      var ambito = scaricaAlbum.dataset.ambito;
      var id = scaricaAlbum.dataset.id;
      var urlCreate = scaricaAlbum.dataset.urlCreate;
      var urlStatus = scaricaAlbum.dataset.urlStatus;
      if (!ambito || !id || !urlCreate || !urlStatus) { return; }
      creaArchivio(urlCreate, urlStatus, { ambito: ambito, id: id });
    });
  }

  if (scaricaSelezionati) {
    scaricaSelezionati.addEventListener('click', function () {
      var scelti = caselle.filter(function (c) { return c.checked; }).map(function (c) { return Number(c.value); });
      if (!scelti.length) {
        mostraEsito('Seleziona almeno un file.', 'errore');
        return;
      }
      var urlCreate = scaricaAlbum ? scaricaAlbum.dataset.urlCreate : null;
      var urlStatus = scaricaAlbum ? scaricaAlbum.dataset.urlStatus : null;
      if (!urlCreate || !urlStatus) { return; }
      creaArchivio(urlCreate, urlStatus, { file_ids: JSON.stringify(scelti) });
    });
  }

  aggiornaSelezione();
})();
