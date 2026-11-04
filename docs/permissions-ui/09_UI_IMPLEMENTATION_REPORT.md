# Phase 05 — rapporto di normalizzazione UI/UX

## Esito e perimetro

È stata applicata una slice di normalizzazione visuale per pubblico, registrazione, admin/staff, capitano, POS e archivio, usando i token homepage già introdotti in Phase 04. La normalizzazione riusa le shell e i CSS di settore: non impone il layout editoriale pubblico alle console operative e non elimina CSS legacy non ancora coperti da regressione.

Non sono stati modificati route, query o schema, helper di autenticazione/autorizzazione, sessioni, CSRF, redirect, API, flussi di iscrizione/consenso, processi media, polling, ordini, carrello o checkout. Le modifiche Phase 02 e Phase 04 già presenti nel worktree sono state preservate.

## Moduli e pagine aggiornati

### Componenti condivisi e tokens

- `assets/css/public-redesign.css`: variabili semantiche pubbliche per superficie, testo, bordo, oro e focus; tipografia/container e CTA allineati ai tokens; larghezza del contenitore classifica isolata e scrollabile.
- `assets/css/admin-workspace.css`: workspace collegato ai tokens, superficie/tabella coerente e azioni/link/form con target minimi 44px.
- `assets/css/captain-portal.css`: variabili semantiche per superfici, bordo, muted, testo, font e focus.
- `assets/css/registration-portal.css`: palette, tipografia, focus e target link collegati ai tokens.
- `assets/css/components/auth.css` e `shared/auth_page.php` restano il template comune login introdotto nella Phase 04; non è stato creato un secondo componente concorrente.
- `assets/css/style.css` e `assets/css/baone-ui.css` continuano a fornire tokens e primitive condivise, con CSS locale di settore dove serve densità o semantica specifica.

### Sito pubblico

Aggiornati `index.php`, `squadre.php`, `squadra.php`, `calendario.php`, `classifica.php`, `partita.php`, `live.php`, `iscrizione.php`, `iscrizione/giocatore.php`, `iscrizione/verifica-email.php` e `iscrizione/conferma-genitore.php` per invalidare la cache dei CSS toccati; aggiornato anche `pos/stato-ordine.php` per il tracking pubblico. `classifica.php` marca i contenitori delle tabelle come regioni scorrevoli con nome accessibile e focus da tastiera.

Il footer informativo continua a collegare regolamento/privacy in PDF statici; non è stata trasformata o copiata la documentazione legale.

### Amministrazione staff e match

Aggiornati `admin/_header.php`, `staff/index.php` e `staff/users.php` per la versione del CSS workspace. Tutte le pagine che usano la shell condivisa admin/telecronista ereditano le correzioni visuali da `admin-workspace.css`; la sidebar continua a derivare i link da `includes/staff_navigation.php` e dalle assegnazioni server-side.

Non sono state modificate le pagine/handler di dashboard, squadre, giocatori, iscrizioni, pagamenti o match, né la logica che limita segretari e Match Manager. La resa autenticata non è stata verificabile in browser senza account/fixture di test.

### POS operativo e backoffice

In `pos/assets/css/pos.css` i link nav e le azioni frequenti del carrello sono stati portati a 44px minimo: rimozione, quantità, note, azioni ordine e pulsanti backoffice. I controlli di cassa già più grandi e i bottoni KDS da 48px o superiori sono rimasti tali. Nessuna scorciatoia, controllo di ordine, polling o transizione di stato è stata rimossa.

Aggiornati gli URL CSS a `v=phase05` in `pos/operator_panel.php`, `pos/kitchen_display.php`, `pos/bar_display.php`, `pos/stato-ordine.php`, `pos/admin/coupons.php`, `pos/admin/menu_management.php`, `pos/admin/orders_history.php`, `pos/admin/statistics.php` e `pos/admin/users.php`.

### Capitano e archivio

Aggiornati gli URL `captain-portal.css` a `v=phase05` in `capitano/_header.php`, `capitano/password-dimenticata.php`, `capitano/registrati.php`, `capitano/richiedi-verifica.php`, `capitano/reset-password.php` e `capitano/verifica-email.php`. La relativa dashboard, i giocatori, i pagamenti e il flusso autonomo capitano mantengono il loro markup e l’autenticazione separata.

In `photo/assets/css/archivio.css` i componenti locali usano token per superfici/bordi/muted e focus visibile; il toggle della vecchia nav archivio mantiene un target touch di 44px. Il CSS dell’archivio usa già versione `filemtime` nell’header, quindi non serve cambiare i suoi template per la cache.

## Componenti riusati e duplicazioni

- Navbar: `shared/site_nav.php` + `assets/js/site-nav.js` per pubblico/capitano/POS/archivio; sidebar dedicata admin continua a consumare lo stesso modello di navigazione autorizzata.
- Login: `shared/auth_page.php` per staff, telecronista, capitano, POS e archivio; gli alias legacy continuano a reindirizzare al login centrale esistente.
- Tipografia, colori, superfici, focus, spaziatura e touch target derivano da `assets/css/brand/tokens.css` invece di nuove palette di modulo.
- Nessun blocco CSS legacy è stato rimosso in massa: alcune regole sono duplicate/obsolete, ma non è stato possibile dimostrare copertura completa dei selettori generati da PHP/JS su tutte le pagine e stati. La loro rimozione è rinviata a una migrazione con test dei consumer, non nascosta come pulizia “sicura”.

## Correzioni responsive/accessibilità

- Classifica: overflow orizzontale confinato alla tabella, scorrimento touch e tastiera, regione con nome accessibile e focus visibile.
- Admin: azioni di tabella e uscita, quick actions, form e pulsanti ora hanno target minimi di 44px; la modalità tabella scrollabile resta locale al wrapper.
- POS: controlli incrementa/decrementa/rimuovi e azioni ordine con target 44px; controlli KDS e conferma ordine conservano dimensioni maggiori. Nessuna riduzione di touch target.
- Palette focus e typography nei moduli aggiornati fanno riferimento ai token homepage.

## Verifiche

- PHP lint sull’insieme dei file PHP modificati: **PASS**.
- `git diff --check`: **PASS**; Git mostra avvisi di conversione LF/CRLF per il worktree Windows, senza whitespace error.
- Browser locale a **1000×700**: homepage, directory squadre, calendario, classifica, informazioni iscrizione, live, login POS, login capitano e login archivio caricate. Non sono stati osservati errori console; nessun documento pubblico osservato presentava overflow globale a quella viewport (home 987px, pagine principali 985–1000px). I login hanno il template condiviso; il campo CSRF archivio è presente.
- I sei stylesheet di modulo verificabili dal browser (`public-redesign`, `admin-workspace`, `captain-portal`, `registration-portal`, POS e archivio) hanno risposto HTTP 200 e sono stati analizzati da `CSSStyleSheet.replaceSync` senza errori di parsing.
- Accessibilità markup classifica verificata source-based; route con dati e tabelle vuote non sempre espongono la tabella nel DOM locale.
- Non sono stati inviati login né ordini né upload/download; non si è usato un database di test autorizzato/disposable e non sono state eseguite azioni distruttive. Il PHP test authorization matrix richiede esplicitamente un database disposable configurato e non è stato lanciato.
- Screenshot non acquisibile: il browser ha risposto “Screenshot needs a visible tab” anche dopo `tabs.focus`. Nessun tool di emulazione/resizing viewport è disponibile. I viewport richiesti **1440px, 768px e 390px non sono stati verificati visualmente**; anche i layout autenticati e gli stati operativi con dati sono da validare con fixture.
- Non esiste uno script frontend in `package.json`; `composer.json` non definisce script di test UI.

## Inconsistenze/residui noti

1. Pagine private amministrazione, capitano autenticato, POS autenticato e archivio protetto richiedono sessioni/fixture; non è stata dichiarata una verifica visuale di quelle pagine.
2. Non sono stati ritirati Bootstrap del portale capitano, CSS inline o classi legacy: la rimozione richiederebbe inventario completo dei consumer e copertura E2E.
3. Varianti operative POS e media mantengono CSS locali intenzionali; è stata uniformata la palette/tipografia e l’accessibilità dei controlli mirati, non forzata una densità pubblica.
4. Rimane il disallineamento testuale anno/stagione già rilevato nell’audit (nome configurato 2027 contro copy/brand 2026). Non è stato deciso quale anno aggiornare, quindi non è stato alterato il copy prodotto.
5. La presentazione dei PDF regolamento/privacy è un link e un asset statico, non un layout HTML.

Questi limiti impediscono di attestare una regressione visiva zero o il completamento dei test viewport richiesti, pur avendo completato le modifiche CSS sicure sopra elencate. Phase 06 non è iniziata.
