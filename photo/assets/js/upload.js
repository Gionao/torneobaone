/* photo/assets/upload.js
   Caricamento a blocchi con ripresa, coda sequenziale e avanzamento. */
(function () {
  'use strict';

  var cfg = window.ARCHIVIO_UPLOAD;
  if (!cfg) { return; }

  var selettoreAlbum = document.getElementById('albumDestinazione');
  var inputFile = document.getElementById('fileInput');
  var zonaDrop = document.getElementById('zonaDrop');
  var avviaBtn = document.getElementById('avviaUpload');
  var svuotaBtn = document.getElementById('svuotaCoda');
  var codaEl = document.getElementById('codaUpload');
  var progressoBox = document.getElementById('progressoGenerale');
  var progressoTesto = document.getElementById('progressoTesto');
  var progressoPercento = document.getElementById('progressoPercento');
  var progressoBarra = document.getElementById('progressoBarra');
  var sospesiEl = document.getElementById('caricamentiSospesi');

  var coda = [];
  var inCorso = false;
  var indiceCorrente = -1;

  function messaggio(messaggio, tipo) {
    var box = document.createElement('div');
    box.className = 'alert alert-' + (tipo === 'error' ? 'error' : tipo === 'info' ? 'info' : 'success');
    box.setAttribute('role', 'status');
    box.textContent = messaggio;
    codaEl.parentNode.insertBefore(box, codaEl);
    if (tipo !== 'error') {
      window.setTimeout(function () { box.remove(); }, 9000);
    }
  }

  function formattaByte(n) {
    if (n < 1024) { return n + ' B'; }
    if (n < 1048576) { return (n / 1024).toFixed(1).replace('.', ',') + ' KB'; }
    if (n < 1073741824) { return (n / 1048576).toFixed(1).replace('.', ',') + ' MB'; }
    return (n / 1073741824).toFixed(2).replace('.', ',') + ' GB';
  }

  function estensione(nome) {
    var parti = String(nome).split('.');
    return parti.length > 1 ? parti.pop().toLowerCase() : '';
  }

  function aggiornaPulsanti() {
    var haFile = coda.some(function (v) { return v.stato === 'attesa' || v.stato === 'errore'; });
    avviaBtn.disabled = inCorso || !haFile || !selettoreAlbum.value;
    svuotaBtn.disabled = inCorso || coda.length === 0;
  }

  function percentualeVoce(voce) {
    if (voce.stato === 'completato') { return 100; }
    if (!voce.dimensione || !voce.inviati) { return 0; }
    return Math.min(99, Math.floor(voce.inviati * 100 / voce.dimensione));
  }

  function creaVoce(voce) {
    var el = document.createElement('div');
    el.className = 'archivio-coda__voce';
    el.dataset.indice = String(voce.indice);

    var testa = document.createElement('div');
    testa.className = 'archivio-coda__testa';
    var nome = document.createElement('span');
    nome.className = 'archivio-coda__nome';
    nome.textContent = voce.file.name;
    var stato = document.createElement('span');
    stato.className = 'archivio-coda__stato';
    stato.textContent = 'In attesa';
    testa.appendChild(nome);
    testa.appendChild(stato);

    var barra = document.createElement('div');
    barra.className = 'archivio-barra-progresso';
    var riempimento = document.createElement('span');
    barra.appendChild(riempimento);

    var meta = document.createElement('div');
    meta.className = 'archivio-coda__errore';
    meta.hidden = true;

    el.appendChild(testa);
    el.appendChild(barra);
    el.appendChild(meta);
    codaEl.appendChild(el);

    voce.elemento = el;
    voce.statoEl = stato;
    voce.barraEl = riempimento;
    voce.erroreEl = meta;
    return el;
  }

  function aggiornaVoce(voce, testoStato) {
    if (!voce.elemento) { return; }
    var p = percentualeVoce(voce);
    voce.barraEl.style.width = p + '%';
    voce.statoEl.textContent = testoStato || (voce.stato === 'completato' ? 'Completato' : p + '%');
    voce.elemento.classList.toggle('ok', voce.stato === 'completato');
    voce.elemento.classList.toggle('errore', voce.stato === 'errore');
    if (voce.errore) {
      voce.erroreEl.hidden = false;
      voce.erroreEl.textContent = voce.errore;
    } else {
      voce.erroreEl.hidden = true;
      voce.erroreEl.textContent = '';
    }
  }

  function aggiornaProgressoGenerale() {
    if (!coda.length) {
      progressoBox.hidden = true;
      return;
    }
    progressoBox.hidden = false;
    var totale = coda.reduce(function (acc, v) { return acc + Math.max(1, v.dimensione); }, 0);
    var inviato = coda.reduce(function (acc, v) {
      if (v.stato === 'completato') { return acc + Math.max(1, v.dimensione); }
      return acc + Math.min(v.inviati || 0, Math.max(1, v.dimensione));
    }, 0);
    var p = Math.min(100, Math.floor(inviato * 100 / totale));
    progressoBarra.style.width = p + '%';
    progressoPercento.textContent = p + '%';
    var completati = coda.filter(function (v) { return v.stato === 'completato'; }).length;
    progressoTesto.textContent = completati + ' di ' + coda.length + ' file completati';
  }

  function aggiungiFile(file) {
    var ext = estensione(file.name);
    if (cfg.formati.indexOf(ext) === -1) {
      messaggio('Formato non supportato per «' + file.name + '». Ammessi: JPG, JPEG, PNG, HEIC, MP4, MOV.', 'error');
      return;
    }
    if (file.size < 1) {
      messaggio('Il file «' + file.name + '» è vuoto.', 'error');
      return;
    }
    if (file.size > cfg.maxBytes) {
      messaggio('Il file «' + file.name + '» supera il limite di ' + formattaByte(cfg.maxBytes) + '.', 'error');
      return;
    }
    var duplicato = coda.some(function (v) {
      return v.file.name === file.name && v.file.size === file.size && v.file.lastModified === file.lastModified;
    });
    if (duplicato) { return; }

    var voce = {
      indice: coda.length,
      file: file,
      dimensione: file.size,
      inviati: 0,
      stato: 'attesa',
      errore: '',
      uuid: '',
      chunkSize: cfg.chunkBytes,
      numeroChunk: 0,
      prossimo: 0,
      allegati: []
    };
    coda.push(voce);
    creaVoce(voce);
    aggiornaVoce(voce, 'In attesa');
    aggiornaProgressoGenerale();
    aggiornaPulsanti();
  }

  function aggiungiElenco(files) {
    Array.prototype.forEach.call(files || [], aggiungiFile);
  }

  inputFile.addEventListener('change', function () {
    aggiungiElenco(inputFile.files);
    inputFile.value = '';
  });

  ['dragenter', 'dragover'].forEach(function (evento) {
    zonaDrop.addEventListener(evento, function (e) {
      e.preventDefault();
      zonaDrop.classList.add('attivo');
    });
  });
  ['dragleave', 'drop'].forEach(function (evento) {
    zonaDrop.addEventListener(evento, function (e) {
      e.preventDefault();
      zonaDrop.classList.remove('attivo');
    });
  });
  zonaDrop.addEventListener('drop', function (e) {
    if (e.dataTransfer && e.dataTransfer.files) {
      aggiungiElenco(e.dataTransfer.files);
    }
  });

  selettoreAlbum.addEventListener('change', aggiornaPulsanti);

  svuotaBtn.addEventListener('click', function () {
    if (inCorso) { return; }
    coda = [];
    codaEl.textContent = '';
    aggiornaProgressoGenerale();
    aggiornaPulsanti();
  });

  function leggiErrore(risposta) {
    return risposta.json().then(function (dati) {
      if (dati && dati.errore) { throw new Error(dati.errore); }
      throw new Error('Risposta non valida dal server.');
    }).catch(function (errore) {
      if (errore instanceof Error && errore.message !== 'Unexpected token' && errore.message.indexOf('JSON') === -1) {
        throw errore;
      }
      throw new Error('Errore di comunicazione con il server (codice ' + risposta.status + ').');
    });
  }

  function richiesta(url, opzioni, timeoutMs) {
    var controller = new AbortController();
    var timer = window.setTimeout(function () { controller.abort(); }, timeoutMs || 120000);
    opzioni = opzioni || {};
    opzioni.signal = controller.signal;
    opzioni.credentials = 'same-origin';
    return fetch(url, opzioni).finally(function () {
      window.clearTimeout(timer);
    });
  }

  function sha256Hex(blob) {
    if (!window.crypto || !window.crypto.subtle || !window.isSecureContext) {
      return Promise.resolve('');
    }
    return blob.arrayBuffer().then(function (buffer) {
      return window.crypto.subtle.digest('SHA-256', buffer);
    }).then(function (hash) {
      return Array.prototype.map.call(new Uint8Array(hash), function (b) {
        return b.toString(16).padStart(2, '0');
      }).join('');
    }).catch(function () {
      return '';
    });
  }

  function statoUpload(uuid) {
    return richiesta(cfg.statusUrl + '?uuid=' + encodeURIComponent(uuid), { method: 'GET' }, 30000)
      .then(function (r) {
        if (!r.ok) { return leggiErrore(r); }
        return r.json();
      })
      .then(function (dati) {
        if (!dati.ok || !dati.upload) { throw new Error(dati.errore || 'Caricamento non trovato.'); }
        return dati.upload;
      });
  }

  function inviaBlocco(voce, indice, tentativo) {
    tentativo = tentativo || 1;
    var inizio = indice * voce.chunkSize;
    var fine = Math.min(voce.dimensione, inizio + voce.chunkSize);
    var blocco = voce.file.slice(inizio, fine);

    return sha256Hex(blocco).then(function (hash) {
      var dati = new FormData();
      dati.append('upload_id', voce.uuid);
      dati.append('indice', String(indice));
      if (hash) { dati.append('sha256', hash); }
      dati.append('chunk', blocco, 'blocco-' + indice + '.bin');

      return richiesta(cfg.chunkUrl, {
        method: 'POST',
        headers: { 'X-CSRF-Token': cfg.csrf },
        body: dati
      }, 180000);
    }).then(function (r) {
      if (!r.ok) { return leggiErrore(r); }
      return r.json();
    }).then(function (dati) {
      if (!dati.ok) { throw new Error(dati.errore || 'Blocco non accettato.'); }
      voce.prossimo = Number(dati.prossimo_blocco || indice + 1);
      voce.inviati = Math.min(voce.dimensione, voce.prossimo * voce.chunkSize);
      aggiornaVoce(voce);
      aggiornaProgressoGenerale();
    }).catch(function (errore) {
      if (errore && errore.name === 'AbortError') {
        throw new Error('Tempo scaduto durante l\'invio del blocco.');
      }
      if (tentativo >= 3) {
        throw errore;
      }
      // Se il blocco è arrivato ma la risposta è andata persa, riallinea
      // lo stato con il server prima di ritentare.
      return statoUpload(voce.uuid).catch(function () { return null; }).then(function (stato) {
        if (stato && Number(stato.chunk_ricevuti) > indice) {
          voce.prossimo = Number(stato.chunk_ricevuti);
          voce.inviati = Math.min(voce.dimensione, voce.prossimo * voce.chunkSize);
          aggiornaVoce(voce);
          aggiornaProgressoGenerale();
          return;
        }
        return new Promise(function (resolve) {
          window.setTimeout(resolve, 700 * tentativo);
        }).then(function () {
          return inviaBlocco(voce, indice, tentativo + 1);
        });
      });
    });
  }

  function caricaFile(voce, albumId) {
    voce.stato = 'caricamento';
    aggiornaVoce(voce, 'Preparazione…');
    aggiornaPulsanti();

    var preparazione;
    if (voce.uuid) {
      preparazione = statoUpload(voce.uuid).then(function (upload) {
        voce.chunkSize = Number(upload.chunk_size);
        voce.numeroChunk = Number(upload.numero_chunk);
        voce.prossimo = Number(upload.chunk_ricevuti);
        voce.inviati = Math.min(voce.dimensione, voce.prossimo * voce.chunkSize);
      });
    } else {
      var datiInit = new FormData();
      datiInit.append('album_id', String(albumId));
      datiInit.append('nome', voce.file.name);
      datiInit.append('dimensione', String(voce.dimensione));
      preparazione = richiesta(cfg.initUrl, {
        method: 'POST',
        headers: { 'X-CSRF-Token': cfg.csrf },
        body: datiInit
      }, 60000).then(function (r) {
        if (!r.ok) { return leggiErrore(r); }
        return r.json();
      }).then(function (dati) {
        if (!dati.ok || !dati.upload) { throw new Error(dati.errore || 'Impossibile iniziare il caricamento.'); }
        voce.uuid = dati.upload.uuid;
        voce.chunkSize = Number(dati.chunk_size);
        voce.numeroChunk = Number(dati.numero_chunk);
        voce.prossimo = Number(dati.prossimo_blocco || 0);
        voce.inviati = 0;
      });
    }

    return preparazione.then(function () {
      var passo = function () {
        if (voce.prossimo >= voce.numeroChunk) { return Promise.resolve(); }
        var indice = voce.prossimo;
        aggiornaVoce(voce, 'Invio blocco ' + (indice + 1) + ' di ' + voce.numeroChunk);
        return inviaBlocco(voce, indice).then(passo);
      };
      return passo();
    }).then(function () {
      aggiornaVoce(voce, 'Verifica finale…');
      var dati = new FormData();
      dati.append('upload_id', voce.uuid);
      return richiesta(cfg.completeUrl, {
        method: 'POST',
        headers: { 'X-CSRF-Token': cfg.csrf },
        body: dati
      }, 300000);
    }).then(function (r) {
      if (!r.ok) { return leggiErrore(r); }
      return r.json();
    }).then(function (dati) {
      if (!dati.ok) { throw new Error(dati.errore || 'Il caricamento non è stato completato.'); }
      voce.stato = 'completato';
      voce.inviati = voce.dimensione;
      aggiornaVoce(voce, 'Completato');
      aggiornaProgressoGenerale();
      if (dati.avviso) {
        messaggio(dati.avviso, 'info');
      }
      rimuoviSospeso(voce.uuid);
    }).catch(function (errore) {
      voce.stato = 'errore';
      voce.errore = (errore && errore.message ? errore.message : 'Errore sconosciuto.') +
        (voce.uuid ? ' Il caricamento resta sospeso e può essere ripreso.' : '');
      aggiornaVoce(voce);
      aggiornaProgressoGenerale();
      if (voce.uuid) {
        aggiungiSospeso(voce);
      }
    });
  }

  avviaBtn.addEventListener('click', function () {
    if (inCorso) { return; }
    var albumId = parseInt(selettoreAlbum.value, 10) || 0;
    if (!albumId) {
      messaggio('Scegli la raccolta di destinazione prima di avviare il caricamento.', 'error');
      return;
    }
    var daCaricare = coda.filter(function (v) { return v.stato === 'attesa' || v.stato === 'errore'; });
    if (!daCaricare.length) { return; }

    inCorso = true;
    indiceCorrente = 0;
    aggiornaPulsanti();

    var sequenza = Promise.resolve();
    daCaricare.forEach(function (voce) {
      sequenza = sequenza.then(function () {
        voce.errore = '';
        aggiornaVoce(voce, 'In attesa');
        return caricaFile(voce, albumId);
      });
    });
    sequenza.finally(function () {
      inCorso = false;
      indiceCorrente = -1;
      aggiornaPulsanti();
      aggiornaProgressoGenerale();
    });
  });

  window.addEventListener('beforeunload', function (e) {
    if (inCorso) {
      e.preventDefault();
      e.returnValue = '';
      return '';
    }
  });

  // ---------------------------------------------------------
  //  Caricamenti sospesi
  // ---------------------------------------------------------

  function rimuoviSospeso(uuid) {
    if (!uuid) { return; }
    var el = sospesiEl.querySelector('[data-upload="' + uuid + '"]');
    if (el) { el.remove(); }
    if (!sospesiEl.querySelector('.archivio-sospeso')) {
      sospesiEl.innerHTML = '<span class="archivio-muted archivio-testo-piccolo">Nessun caricamento in sospeso.</span>';
    }
  }

  function aggiungiSospeso(voce) {
    if (!voce.uuid || sospesiEl.querySelector('[data-upload="' + voce.uuid + '"]')) { return; }
    var vuoto = sospesiEl.querySelector('.archivio-muted');
    if (vuoto) { vuoto.remove(); }

    var el = document.createElement('div');
    el.className = 'archivio-sospeso';
    el.dataset.upload = voce.uuid;
    el.dataset.nome = voce.file.name;
    el.dataset.dimensione = String(voce.dimensione);

    var nome = document.createElement('div');
    nome.className = 'archivio-sospeso__nome';
    nome.textContent = voce.file.name;

    var meta = document.createElement('div');
    meta.className = 'archivio-sospeso__meta';
    meta.textContent = formattaByte(voce.inviati || 0) + ' di ' + formattaByte(voce.dimensione);

    var azioni = document.createElement('div');
    azioni.className = 'archivio-sospeso__azioni';

    var riprendi = document.createElement('button');
    riprendi.type = 'button';
    riprendi.className = 'btn btn-sm btn-gold riprendi';
    riprendi.dataset.upload = voce.uuid;
    riprendi.textContent = 'Riprendi';

    var annulla = document.createElement('button');
    annulla.type = 'button';
    annulla.className = 'btn btn-sm btn-outline annulla-sospeso';
    annulla.dataset.upload = voce.uuid;
    annulla.textContent = 'Annulla';

    var input = document.createElement('input');
    input.type = 'file';
    input.className = 'file-ripresa';
    input.dataset.upload = voce.uuid;
    input.accept = '.jpg,.jpeg,.png,.heic,.mp4,.mov';
    input.hidden = true;

    azioni.appendChild(riprendi);
    azioni.appendChild(annulla);
    azioni.appendChild(input);
    el.appendChild(nome);
    el.appendChild(meta);
    el.appendChild(azioni);
    sospesiEl.appendChild(el);
    collegaSospeso(el);
  }

  function collegaSospeso(el) {
    var uuid = el.dataset.upload;
    var pulsante = el.querySelector('.riprendi');
    var input = el.querySelector('.file-ripresa');
    var annulla = el.querySelector('.annulla-sospeso');
    if (!uuid || !pulsante || !input || !annulla) { return; }

    pulsante.addEventListener('click', function () {
      if (inCorso) { return; }
      input.click();
    });

    input.addEventListener('change', function () {
      var file = input.files && input.files[0];
      input.value = '';
      if (!file) { return; }
      var attesoNome = el.dataset.nome || '';
      var attesoDimensione = parseInt(el.dataset.dimensione, 10) || 0;
      if (file.name !== attesoNome || (attesoDimensione && file.size !== attesoDimensione)) {
        messaggio('Il file selezionato non corrisponde a quello sospeso. Seleziona «' + attesoNome + '».', 'error');
        return;
      }

      inCorso = true;
      aggiornaPulsanti();
      var voce = {
        indice: coda.length,
        file: file,
        dimensione: file.size,
        inviati: 0,
        stato: 'caricamento',
        errore: '',
        uuid: uuid,
        chunkSize: cfg.chunkBytes,
        numeroChunk: 0,
        prossimo: 0
      };
      coda.push(voce);
      creaVoce(voce);
      caricaFile(voce, 0).finally(function () {
        inCorso = false;
        aggiornaPulsanti();
        aggiornaProgressoGenerale();
      });
    });

    annulla.addEventListener('click', function () {
      if (inCorso) { return; }
      if (!window.confirm('Annullare definitivamente questo caricamento sospeso?')) { return; }
      var dati = new FormData();
      dati.append('upload_id', uuid);
      richiesta(cfg.abortUrl, {
        method: 'POST',
        headers: { 'X-CSRF-Token': cfg.csrf },
        body: dati
      }, 60000).then(function (r) {
        if (!r.ok) { return leggiErrore(r); }
        return r.json();
      }).then(function () {
        rimuoviSospeso(uuid);
        messaggio('Caricamento annullato.', 'success');
      }).catch(function (errore) {
        messaggio(errore.message || 'Impossibile annullare il caricamento.', 'error');
      });
    });
  }

  Array.prototype.forEach.call(document.querySelectorAll('.archivio-sospeso'), collegaSospeso);

  // Ripulisce la selezione se l'utente cambia raccolta dopo aver caricato.
  aggiornaPulsanti();
})();
