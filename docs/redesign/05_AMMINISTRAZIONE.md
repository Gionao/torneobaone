# Redesign amministrazione e telecronista — Torneo Baone

**Stato:** shell UI e stili implementati; accessi e comportamenti esistenti conservati.  
**Perimetro:** layout condiviso dell’area admin/telecronista, login telecronista, foglio CSS amministrazione dedicato e stati vuoti per registrazioni e pagamenti.

## Scelte UI

- Shell amministrativa con sidebar navy persistente su desktop, header superiore compatto e navigazione orizzontale scorrevole su tablet.
- Entry point `assets/css/baone-ui.css` collegato soltanto alle superfici amministrazione/telecronista; wrapper `.tb-ui` e componenti Baone UI riusati per navigazione attiva, link-azione, tabelle, alert e login telecronista.
- Stato attivo espresso nel markup con `aria-current="page"`; spaziatura e contrasto aggiornati per dashboard, viste dati, pagine CRUD e moduli.
- Tabelle leggibili con intestazioni, righe alternate, scroll orizzontale contenuto e area azioni flessibile. Nessuna colonna o azione esistente è stata rimossa.
- Badge esistenti conservano testi e classi semantiche; il CSS migliora contrasto, leggibilità e coerenza visiva.
- Form esistenti conservano struttura funzionale e ricevono focus visibile, input più leggibili e dimensioni coerenti.
- Le conferme distruttive restano i `confirm()` nativi già presenti, così non cambiano trigger né flusso delle operazioni.
- Aggiunti messaggi UI per liste vuote di iscrizioni, transazioni e metodi di pagamento. Gli stati di caricamento non sono stati inventati: queste pagine sono renderizzate lato server e non hanno uno stato asincrono preesistente.
- Identità navy/oro e logo già in uso nell’app (`assets/img/logo_torneo.png`); nessun asset ricostruito del Brand Kit è introdotto.

## Ruoli e autorizzazioni verificati

- `admin/_header.php` verifica il ruolo con `is_telecronista()` per costruire la navigazione. Amministrazione vede dashboard, partite, squadre, giocatori, iscrizioni, pagamenti, archivio media e uscita; telecronista vede soltanto Partite e uscita.
- Le pagine di gestione partite chiamano `require_partite_access()`. L’azione Elimina è già nascosta quando `is_telecronista()` è vero; il form condiviso delle partite mantiene i controlli già presenti per il ruolo.
- `telecronista/index.php` richiede sessione admin valida e verifica il ruolo telecronista prima di mostrare la propria area. Gli endpoint `/telecronista/partite/*` includono le viste condivise già protette.
- `require_admin()`, `require_partite_access()`, login, sessioni e redirect non sono stati modificati. La nuova sidebar stampa esclusivamente gli elementi già selezionati dalla configurazione role-aware.
- Il collegamento Archivio media continua a comparire solo nella navigazione amministrativa, senza creare nuovi accessi o modificare l’autorizzazione dell’archivio.

## Funzionalità preservate

- CRUD squadre e giocatori, assegnazione/controllo squadre e collegamento alle rose.
- Flusso partite: pianificazione, modifica, eliminazione autorizzata, risultati e gestione eventi.
- Iscrizioni, metodi e transazioni: campi, stati, importi, azioni, calcoli e workflow invariati.
- Tutte le colonne e azioni delle tabelle sono mantenute. Gli stati vuoti aggiunti sono righe informative e non cambiano query né risultati.
- Form: `name`, `value`, `required`, `method`, `action`, token CSRF, parametri GET/POST e validazione server-side invariati.
- Query SQL, database, calcolo punteggi/classifica, URL, permessi e autenticazione invariati.

## File modificati

- `admin/_header.php` — shell, sidebar role-aware e header compatto; selezione della voce dashboard corretta per gli URL admin/telecronista.
- `admin/_footer.php` — chiusura dei wrapper della shell.
- `admin/iscrizioni/index.php` — messaggio vuoto, senza alterare tabella o dati.
- `admin/pagamenti/transazioni.php` — messaggio vuoto, colonne e azioni conservate.
- `admin/pagamenti/metodi.php` — messaggio vuoto, colonne conservate.
- `admin/squadre/index.php`, `admin/squadre/dettaglio.php`, `admin/giocatori/index.php`, `admin/partite/index.php`, `admin/partite/eventi.php` — applicati i componenti Baone UI a contenitori/tabelle senza cambiare colonne o azioni.
- `telecronista/login.php` — applicazione della pelle dedicata e componenti Baone UI; annuncio accessibile dell’errore.
- `assets/css/admin-workspace.css` — nuovo stile confinato alle pagine che lo includono.
- `docs/redesign/05_AMMINISTRAZIONE.md` — questo report.

Il nuovo CSS viene caricato da `admin/_header.php` (area amministrazione e area telecronista che la riusa) e da `telecronista/login.php`; non è importato globalmente nei partial condivisi del sito.

## Test e verifiche

- `php -l` superato su tutti i file PHP sotto `admin/` e `telecronista/`; `git diff --check` senza errori di whitespace (Git mostra solo avvisi LF/CRLF su Windows).
- Parentesi graffe del nuovo CSS bilanciate.
- Browser locale: login telecronista carica `baone-ui.css` e gli import dei componenti, senza errori console e senza overflow alla larghezza disponibile di 1000px. Le viste autenticate non sono state raggiungibili senza sessione.
- Il browser senza sessione amministrativa è stato reindirizzato alla pagina di login: non è stato possibile visualizzare dashboard e viste autenticate nel browser.
- La pagina di login admin non carica il nuovo CSS, come previsto; la UI di autenticazione admin resta fuori dall’intervento.
- Le azioni distruttive non sono state avviate e non sono stati inviati form di scrittura.

## Rischi e verifiche residue

- Verificare con account di test distinti l’aspetto e i link effettivamente visibili per admin e telecronista, inclusi casi URL pulito e `.php`.
- Provare tutte le tabelle con dataset popolati e molte azioni nella stessa riga, in particolare partite/eventi, transazioni e dettaglio squadra.
- Controllare risoluzioni desktop 1280/1440px e tablet 768/820/900px, zoom 200%, tastiera/focus e dialoghi di conferma in Chromium/Firefox.
- I `confirm()` sono dialoghi nativi e non sono personalizzabili via CSS; sono stati mantenuti per non modificare il comportamento CRUD.
- I test autorizzati con sessioni admin/telecronista e dataset di pagamento non sono stati eseguiti; verificare su dati/fixture non produttivi prima del rilascio.
