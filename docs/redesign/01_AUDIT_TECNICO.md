# Audit tecnico e architetturale — Torneo Baone

**Data dell'audit:** 3 novembre 2026  
**Perimetro:** repository alla revisione `17957c1` (`Re design navbar`), inclusa la cartella brand presente nel workspace.  
**Modalità:** sola lettura. Nessuna modifica a codice, database, dati, migrazioni, routing o configurazione applicativa. Questo documento è l'unico deliverable dell'audit.

## 1. Sintesi esecutiva

Torneo Baone è un'applicazione PHP server-rendered, con MySQL via PDO, sessioni PHP e JavaScript vanilla. È composta da sei superfici con responsabilità e contratti distinti: sito pubblico, iscrizioni/capitano, amministrazione torneo, telecronista, archivio privato di foto/video e POS. L'architettura non è un framework monolitico: presenta layout condivisi in alcune aree, ma anche pagine autonome, CSS e JavaScript inline e sottosistemi con proprie API.

La base attuale è funzionale e contiene già una nuova navigazione comune (`shared/site_nav.php` + `assets/js/site-nav.js`) riusata da pubblico, admin, capitano, archivio e POS. Ciò costituisce un buon punto di partenza, ma il refactor non è ancora uniforme: permangono vecchi selettori e regole di navbar, viste autonome e markup legacy, e l'integrazione del nuovo header non rispetta sempre la posizione nel documento. Sono state rilevate discrepanze verificabili, tra cui uno script residuo in `iscrizione.php` che accede a `#navbar`, markup completo incluso dal login capitano già dentro il body e regole CSS del POS non corrispondenti a classi dinamiche presenti nelle viste KDS e nel carrello.

### Misure del repository

Conteggi ottenuti sui file presenti, escludendo `vendor`, `node_modules` e `.git`:

| Indicatore | Conteggio | Nota |
|---|---:|---|
| File PHP | 141 | Comprende pagine, API, helper e strumenti. |
| Righe PHP | circa 18.358 | Conteggio testuale, non LOC semantiche. |
| File CSS applicativi | 5 | `style.css`, `admin.css`, `iscrizioni.css`, `pos.css`, `archivio.css`; il kit brand contiene 2 CSS aggiuntivi. |
| Righe CSS applicative | circa 3.043 | Più 25 righe nei due file CSS brand. |
| File JavaScript applicativi | 4 | `main.js`, `site-nav.js`, `photo/assets/js/galleria.js`, `upload.js`. |
| Righe JS applicative | circa 892 | Esclusi script inline nelle pagine. |
| Blocchi `<style>` in PHP | 19 | Circa 1.222 righe. |
| Blocchi `<script>` inline | 21 | Circa 1.149 righe; molti sono script applicativi sostanziali. |
| Attributi `style="..."` | circa 218 | Distribuiti in viste pubbliche, admin, capitano, media e POS. |

I conteggi sono indicatori di dispersione e non una valutazione automatica di qualità. La differenza tra conteggi da shell e scansioni regex può dipendere da file/line ending e dal trattamento di script/markup.

### Priorità di audit

1. Stabilizzare il contratto del layout e della navigazione prima di migrare altri template.
2. Definire un layer CSS nuovo, prefissato (`tb-`), che possa convivere con classi legacy senza reinterpretare i loro significati.
3. Trattare DOM dinamico, form, URL, ruoli, sessioni e payload API come contratti da congelare.
4. Dare priorità mobile a sito, iscrizione e capitano; desktop/tablet ad admin e archivio; POS desktop/tablet con comandi touch-friendly.
5. Aggiungere controlli di regressione per le funzionalità critiche: al momento l'infrastruttura di test nel repository è molto limitata.

## 2. Architettura corrente

### Stack e rendering

- PHP tradizionale con template PHP/HTML, PDO/MySQL e helper inclusi tramite `require`/`require_once`.
- Rendering prevalentemente server-side; JS vanilla gestisce interazioni, fetch, polling, upload resumable, animazioni e alcune schermate dinamiche.
- Sessioni PHP e controlli di ruolo lato server. Nascondere link o controlli UI non sostituisce l'autorizzazione server-side.
- Apache `mod_rewrite` e `.htaccess` forniscono URL puliti, protezione di percorsi interni e instradamento per sottodominio.
- PHPMailer è la sola dipendenza applicativa dichiarata in `composer.json` (`phpmailer/phpmailer ^6.9`).
- Bootstrap 5.3.8 è importato da CDN solo dal layout capitano (`capitano/_header.php` e `_footer.php`); non è una base comune dell'applicazione.

### Topologia funzionale

| Superficie | Percorsi principali | Responsabilità |
|---|---|---|
| Pubblica | `/`, `/calendario`, `/classifica`, `/partita.php?id=…`, `/squadre`, `/squadra/{id}`, `/iscrizione`, `/live.php` | Informazioni torneo, squadre, calendario, risultati, promozione iscrizioni e live. |
| Iscrizione/giocatore | `/capitano/registrati`, `/iscrizione/giocatore`, `/iscrizione/verifica-email`, `/iscrizione/conferma-genitore` | Registrazione capitano e completamento dati/consensi del giocatore. |
| Capitano | `/capitano/login`, `/capitano/dashboard`, `/capitano/giocatori`, `/capitano/pagamenti` | Gestione squadra e giocatori, stato iscrizione/pagamento e inviti. |
| Admin torneo | `/admin/…` e sottodominio admin | Partite, eventi, risultati, squadre, giocatori, iscrizioni e pagamenti. |
| Telecronista | `/telecronista/…` e area condivisa con admin | Consultazione e gestione partite, risultati ed eventi secondo ruolo. |
| Archivio privato | `/photo/admin/…`, `/photo/api/…`, `/photo/upload/…` | Archivio foto/video privato organizzato Edizione → Giornata → Album → file; upload, galleria, download, ZIP e amministrazione ruoli. Non è un file manager generico. |
| POS | `/pos/…` e sottodominio POS | Operatore cassa, display cucina/bar, menu, coupon, utenti, ordini, statistiche e tracciamento pubblico. |

`photo.php` alla root è un redirect legacy a Google Drive; non è l'ingresso del modulo privato `/photo`. `photo/index.php` è invece un redirect interno verso `ARCHIVIO_URL`. Il sottodominio POS e i relativi percorsi sono anch'essi gestiti da `.htaccess`; non dedurre un cambio di URL dal refactor di layout.

### Routing e sicurezza infrastrutturale

`.htaccess` (HEAD corrente) disabilita directory listing; blocca via HTTP cartelle come `includes`, `tests`, `tools`, `storage`, SQL e componenti privati; blocca i partial `_*.php`; protegge i partial header/footer; applica URL senza `.php`, redirect/canonicalizzazione per index e gestione dei sottodomini. Sono presenti eccezioni esplicite per endpoint e file operativi (tra cui `pos/api`, `photo/api`, `photo/upload`, `pos/admin/settings.php`, `squadra.php`, `classifica_engine.php`). Queste regole sono parte del contratto di navigazione e non vanno «semplificate» durante un redesign.

`includes/config.php` definisce URL e ambiente (`SITE_URL`, `ADMIN_PATH`, `POS_URL`, `TELECRONISTA_URL`, `ARCHIVIO_URL`), impostazioni DB/SMTP e timezone Europe/Rome. Il default `SITE_NAME` indica Baone 2027, mentre interfaccia, `brand_year`, riferimenti e dati/schema consultati presentano ancora 2026: l'incoerenza va censita, non corretta implicitamente nel restyling.

## 3. Inventario delle pagine e dei flussi

Le voci seguenti sono raggruppate per area. Le pagine di API/helper sono inventariate dopo le viste; gli endpoint non sono target di redesign visuale, ma fanno parte dei contratti da preservare.

### 3.1 Sito pubblico

| Vista | File | Layout/asset o comportamento |
|---|---|---|
| Homepage | `index.php` | `shared/header.php` e `shared/footer.php`; `style.css`, `main.js`; sezioni torneo, countdown e link al tracking POS. |
| Calendario | `calendario.php` | Header/footer condivisi; filtri query `fase` e `stato`; CSS specifico in `<style>` collocato dopo il footer. |
| Classifica | `classifica.php` | Header/footer condivisi; calcolo/helper dedicati; CSS pagina inline in fondo. |
| Dettaglio partita | `partita.php` | Header/footer condivisi, dati evento/risultato; CSS inline dopo footer. |
| Elenco squadre | `squadre.php` | Header/footer condivisi; directory e link alle schede. |
| Scheda squadra | `squadra.php` | Header/footer condivisi; URL canonico `/squadra/{id}` riscritto da `.htaccess`; fetch per dati/partite. |
| Iscrizione informativa | `iscrizione.php` | Header/footer condivisi e lungo blocco CSS inline; residua logica scroll che cerca `#navbar`, non presente nel nuovo partial. |
| Live | `live.php` | Pagina autonoma, CSS inline, font Arial e meta refresh ogni 10 secondi; nessun layout condiviso. |
| Motore classifica | `classifica_engine.php` | Supporto applicativo, non vista; percorso protetto/eccezione routing. |

### 3.2 Registrazione, iscrizione giocatore e capitano

| Vista | File | Layout/contratto |
|---|---|---|
| Ingresso area capitano | `capitano/index.php` | Redirect a login o dashboard in base alla sessione. |
| Registrazione capitano | `capitano/registrati.php` | Autonoma; CSS inline; POST di onboarding, email e consensi. |
| Login capitano | `capitano/login.php` | Dal commit navbar include `shared/header.php`/`footer.php`, ma conserva un `<html>/<head>/<body>` autonomo e una propria `.auth-card`; rischio di HTML annidato/struttura invalida e doppio wrapper. |
| Recupero, verifica e reset | `capitano/password-dimenticata.php`, `richiedi-verifica.php`, `reset-password.php`, `verifica-email.php` | Pagine autonome, ciascuna con CSS inline; flussi token/email. |
| Dashboard capitano | `capitano/dashboard.php` | `capitano/_header.php`/`_footer.php`; riepilogo squadra/stati; fetch dinamico. |
| Giocatori capitano | `capitano/giocatori.php` | Layout capitano; CRUD/inviti e interazioni fetch/inline handler. |
| Pagamenti capitano | `capitano/pagamenti.php` | Layout capitano; stato e contenuti pagamento. |
| Redirect/logout | `capitano/logout.php`, `capitano/index.php` | Sessione e redirect; non viste visuali. |
| Completamento iscrizione giocatore | `iscrizione/giocatore.php` | Form tokenizzato con nome, cognome, email, telefono, data nascita, indirizzo, campi genitore e consensi; `style.css` + `iscrizioni.css`; JS per comportamento campi minore. |
| Verifica email giocatore | `iscrizione/verifica-email.php` | Scheda di esito; `style.css` + `iscrizioni.css`. |
| Conferma genitore | `iscrizione/conferma-genitore.php` | Form consensi; carica solo `iscrizioni.css`, ma usa classi `.alert` e `.btn-gold` che non sono definite in quel foglio. |

### 3.3 Amministrazione torneo e telecronista

| Area | File | Funzioni |
|---|---|---|
| Login/logout/dashboard admin | `admin/login.php`, `admin/logout.php`, `admin/index.php` | Autenticazione e overview; login con CSS inline. |
| Partite | `admin/partite/index.php`, `form.php`, `delete.php` | Lista, form, CRUD, selettori squadra e preview dinamica. |
| Risultati/eventi | `admin/partite/risultato.php`, `eventi.php` | Inserimento risultato, stato giocata, shootout, eventi e MVP; fetch e DOM dinamico. |
| Squadre | `admin/squadre/index.php`, `form.php`, `dettaglio.php`, `delete.php` | CRUD, logo/colore, autorizzazione e collegamenti pagamenti; fetch/JS. |
| Giocatori | `admin/giocatori/index.php`, `form.php`, `delete.php` | CRUD e rimozione; lookup asincroni in alcuni form. |
| Iscrizioni | `admin/iscrizioni/index.php` | Riepilogo stati e flusso iscrizione. |
| Pagamenti | `admin/pagamenti/transazioni.php`, `inserisci.php`, `modifica.php`, `elimina.php`, `metodi.php` | Metodi, registrazione, modifica e cancellazione; alcuni fetch/azioni POST. |
| Ingresso telecronista | `telecronista/index.php`, `telecronista/login.php` | Area dedicata, con dashboard e login; login ha CSS inline duplicato con login admin. |
| Compatibilità telecronista | `telecronista/partite/index.php`, `form.php`, `eventi.php`, `risultato.php` | Wrapper di tre righe che includono le viste admin corrispondenti; nav/admin partial condivisi e regole di ruolo restano determinanti. |
| Header storico | `admin_header.php` | File root legacy, distinto da `admin/_header.php`; verificare chiamanti prima di rimuovere o fondere. `.htaccess` lo protegge/non riscrive come pagina normale. |

### 3.4 Archivio privato foto e video (`/photo`)

| Sottoarea | File principali | Funzioni |
|---|---|---|
| Ingresso e sessione | `photo/index.php`, `photo/admin/_bootstrap.php`, `photo/admin/login.php`, `logout.php`, `_403.php` | Redirect, autenticazione condivisa e messaggi di accesso negato. |
| Dashboard | `photo/admin/index.php` | KPI e accesso alla struttura. |
| Galleria e file | `galleria.php`, `file.php`, `file_delete.php`, `cestino.php` | Navigazione album/file, selezione batch, download autorizzato, cestino e ripristino/eliminazione secondo ruolo. |
| Upload | `upload.php`, `photo/assets/js/upload.js` | Upload foto/video a blocchi, avanzamento, code sospese e ripresa; sessione/permessi e validazione lato server. |
| Struttura archivio | `album/{index,form,delete}.php`, `edizioni/{index,form,delete}.php`, `giornate/{index,form,delete}.php` | Gerarchia Edizione → Giornata → Album → file. |
| Utenti/impostazioni | `utenti.php`, `impostazioni.php` | Ruoli, assegnazioni, retention e configurazione storage/ZIP. |
| API download/ZIP/upload | `photo/api/{download,thumb,upload_status,zip_create,zip_download,zip_status}.php`; `photo/upload/{init,chunk,complete,abort}.php` | Payload JSON media con envelope `{ok, errore}`; upload chunked e job ZIP asincroni. |
| Servizi | `photo/includes/media_*.php` | Auth, config, repository, storage, validazione, thumbnail, upload, ZIP e manutenzione; non sono componenti UI. |
| CLI | `photo/tools/process_zip.php`, `maintenance.php` | Cron/processi di manutenzione, esclusi dal redesign visuale. |

Il README specifica che gli originali restano invariati, i file non sono serviti direttamente dal web e download/miniature passano da endpoint PHP autorizzati. L'archivio contiene dati privati e consensi d'uso: mai esporre foto/video pubblicamente come effetto collaterale del redesign.

### 3.5 POS (`/pos`)

| Vista | File | Responsabilità |
|---|---|---|
| Ingresso/login/logout | `pos/index.php`, `pos/login.php`, `pos/logout.php` | Instradamento per ruolo e autenticazione POS. |
| Operatore/cassa | `pos/operator_panel.php` | Composizione ordine, prodotti, carrello, modifiche ingredienti/note, coupon, tavolo/asporto/coperti, conferma ordine e coda; script inline e polling. |
| Display cucina/bar | `pos/kitchen_display.php`, `pos/bar_display.php` | KDS per reparto, avanzamento stato e storico; polling ogni 5 secondi. |
| Storico/menu/coupon/utenti/statistiche | `pos/admin/orders_history.php`, `menu_management.php`, `coupons.php`, `users.php`, `statistics.php`, `settings.php` | Gestione back-office POS. |
| Tracking pubblico | `pos/stato-ordine.php` | Tracciamento ordine anonimo tramite numero; script inline e polling ogni 10 secondi. |
| API | `pos/api/{create_order,track_order,get_products,get_active_orders,get_completed_orders,update_order_status,cancel_order,reopen_order,validate_coupon}.php` | API JSON POS con envelope `{success,data,error}`; endpoint e payload non vanno modificati. |

## 4. Layout, header, footer e navigazione

### Componenti correnti

| Componente | Uso e osservazioni |
|---|---|
| `shared/header.php` | Calcola pagina attiva pubblica e prepara configurazione nav per il partial. Include `shared/site_nav.php`; non è un layout completo con apertura documentale. |
| `shared/footer.php` | Footer pubblico e chiusura `body/html`; carica `assets/js/main.js`. Includerlo prima di CSS/script o markup ulteriore può lasciare stile/script fuori posizione. |
| `shared/site_nav.php` | Renderer configurabile; duplica primarie/azioni in nav desktop e mobile. Genera `<header>` e carica `site-nav.js` al momento dell'include. |
| `admin/_header.php` / `_footer.php` | Layout admin/telecronista; header apre documento e main, include nav comune; footer chiude wrapper/documento. |
| `capitano/_header.php` / `_footer.php` | Layout area capitano con nav comune; Bootstrap 5.3.8 ancora incluso benché la nav corrente sia custom. |
| `photo/admin/_header.php` / `_footer.php` | Layout archivio con meta `noindex` e `csrf-token`; nav generata per ruolo. Footer chiude i wrapper senza altri asset. |
| `pos/includes/navbar.php` | Mappa nav per ruolo POS e delega al renderer comune. POS mantiene CSS locale `pos.css`. |
| `admin_header.php` | Partial legacy separato, da non confondere con `admin/_header.php`. |

### Nav condivisa: opportunità e punti da verificare

`shared/site_nav.php` supporta `id`, `dense`, nome/anno/label brand, label accessibilità, lista `primary`, `actions`, contesto e breakpoint. Markup/JS: `[data-site-nav]`, `data-nav-breakpoint` (`820` o `1060`), `.menu-toggle`, `.mobile-panel`, `aria-expanded`, `aria-hidden`, `inert`, `data-open`, `body.site-nav-open`. `site-nav.js` apre/chiude, chiude su link/click esterno/Escape e su passaggio desktop; sposta focus nel pannello e lo ripristina.

Elementi di attenzione:

- Il renderer emette markup semantico da includere nel body, ma alcuni template chiamano `shared/header.php` prima di `</head>` e altri lo includono dentro body. Va prima normalizzato il contratto pagina/partial senza introdurre layout annidati.
- `capitano/login.php` è la prova più evidente: include il partial dopo `<body>`, ma mantiene documentazione HTML autonoma e poi include un footer che chiude documentazione. Questo crea annidamento/doppia chiusura e il nuovo header può apparire nel punto errato.
- `iscrizione.php` include il footer condiviso prima del suo `<style>`, `<script>` e chiusura documentale; i blocchi sono quindi dopo `</html>`. Inoltre il suo script cerca `#navbar`, ID non emesso dal renderer condiviso.
- La nav mobile ripete gli stessi link nel DOM due volte. Selezioni, focus, `aria-current` e test devono tenere conto di desktop e mobile; non rinominare classi/attributi `data-*` senza aggiornare tutti i chiamanti.
- `main.js` ha perso 72 righe di codice navbar nel commit di redesign; `iscrizione.php` conserva il relativo script inline residuale. Non presumere che lo scroll header funzioni.
- Le regole precedenti `.public-navbar`, `.nav-toggle`, `.nav-backdrop`, `.menu-open` restano in `style.css`, mentre il markup è passato a `.site-header`. Le vecchie regole sono potenzialmente morte/legacy e complicano l'audit visivo.
- Ruolo, visibilità link e protezione route sono concetti distinti: il rendering condizionale in nav non va scambiato per autorizzazione.

## 5. Mappa dipendenze CSS e JavaScript

### CSS applicativo

| Foglio | Aree che lo caricano | Responsabilità / osservazioni |
|---|---|---|
| `assets/css/style.css` (~1.150 righe) | Pubblico, login capitano, admin/capitano, archivio, POS | Tokens legacy `--navy`, `--gold`, reset/base, utilità, pulsanti, contenuti pubblici, vecchia navbar e nuova `.site-header`, footer, animazioni e responsive. È molto ampio e ormai anche cross-area. |
| `assets/css/admin.css` (~329 righe) | Admin, capitano, archivio e alcune login | Layout admin, tabelle/form/alert, stati e stili legacy di navigazione; contiene regole `.captain-*` non più coerenti con la nav shared. |
| `assets/css/iscrizioni.css` (~201 righe) | Flussi giocatore/verifica e pagine che lo caricano | Shell/card, form, consensi e stati; duplica `:root`, `.admin-form`, `.admin-table-wrap`, `.state-pill`, `.btn-outline`; non definisce un set completo comune di alert/button. |
| `photo/assets/css/archivio.css` (~488 righe) | Back-office archivio | Componenti di galleria/upload/struttura e badge ruoli. Locale alla superficie privata; conserva selettori della navbar archivio precedente. |
| `pos/assets/css/pos.css` (~875 righe) | Tutte le pagine POS inclusa tracking/login | Operatore, KDS, coda, login, tracking e back-office POS; contiene selettori legacy navbar e lacune per controlli dinamici del carrello/KDS. |
| `assets/brand/05_ui/brand-tokens.css` (16 righe) | Non risulta caricato dai layout applicativi | Tokens `--tb-*` del brand kit; base separata, non ancora sistema effettivamente applicato. |
| `assets/brand/05_ui/components.css` (9 righe) | Non risulta caricato dai layout applicativi | Esempi prefissati `.tb-button`, `.tb-card`, `.tb-status`; documentati come esempi visivi. |

Font Barlow e Barlow Condensed sono caricati via Google Fonts su numerose pagine, non tramite un unico head condiviso. Il menu nuovo usa esplicitamente `Inter`/system fallback per i link, difformità dalla tipografia brand richiesta. La dipendenza Google Fonts è esterna; valutare continuità e fallback, non introdurre font a pagamento.

### JavaScript esterno e inline

| Script | Caricamento / funzioni |
|---|---|
| `assets/js/main.js` (~107 righe) | Dal footer pubblico; reveal con `IntersectionObserver`, countdown `#countdown`/`#cd-days`/`#cd-hours`/`#cd-mins`/`#cd-secs`, countdown partita `#next-match-countdown`. |
| `assets/js/site-nav.js` (56 righe) | Caricato da `shared/site_nav.php`; controllo pannelli mobile, focus, Escape, click esterno, breakpoint. |
| `photo/assets/js/galleria.js` (~169 righe) | Selezione file, download selezione/album, job ZIP e polling; si aggancia a ID come `numeroSelezionati`, `selezionaTutti`, `scaricaAlbum`, `scaricaSelezionati`, `zipEsito` e classi `.seleziona-file`. |
| `photo/assets/js/upload.js` (~560 righe) | Drop/select file, upload a blocchi, SHA-256, avanzamento, sospensione/ripresa/annullamento; ID di coda/progresso e classi `.riprendi`, `.annulla-sospeso`, `.file-ripresa`. |
| Script inline | Pagine admin, capitano, media, POS e pubblico | Contengono validazioni UI, fetch, polling, rendering dinamico, modali e listener. La loro presenza significa che estrarre CSS/markup non basta per migrare una vista. |

### Sovrapposizioni, duplicazioni e selettori mancanti

- Nei fogli CSS sono stati identificati 19 selettori ripetuti in più file: oltre a `:root`, `body` e reset universali, ricorrono `.admin-form`, `.admin-table-wrap`, `.state-pill` e varianti, `.btn-outline`, `.btn-danger`, `.share-link` e discendenti; `.role-badge`/`.role-badge--admin` sono condivisi tra archivio e POS ma con differenze di dimensione/colore/semantica.
- `assets/css/style.css` dichiara `:root` globale; `iscrizioni.css` ridichiara un sottoinsieme. L'ordine di caricamento può cambiare token o comportamento. Il prefisso `tb-` del kit è l'approccio raccomandato per il nuovo layer.
- Bootstrap non è una dipendenza generale. La presenza di classi `.row`, `.col`, `.card`, `.btn` in viste diverse non implica che Bootstrap sia disponibile: tali nomi si sovrappongono a classi proprie o hanno CSS locale. Il solo caricamento CDN attuale è il layout capitano. Non aggiungere Bootstrap a tutte le aree come scorciatoia.
- `assets/css/pos.css` non definisce classi emesse dinamicamente in `operator_panel.php`: `.cart-item-body`, `.cart-item-controls`, `.pos-qty-row`, `.btn-qty`, `.qty-val` e `.pos-item-note`; `.btn-remove` è stilizzato ma con area cliccabile ridotta. È un problema di usabilità touch per la cassa.
- Le viste KDS emettono `.pos-kds-tabs`, `.pos-kds-tab`, `.pos-kds-history-filters`, `.kds-customer`, `.kds-item-note`, `.btn-green`, `.btn-deliver`, `.pos-history-card` e altre classi dinamiche/di stato che non hanno definizione evidente in `pos.css`; verificare il rendering effettivo di stato attivo/storico e pulsanti.
- Alcune classi del POS sembrano definite ma non più usate dopo la riscrittura della navbar (`.pos-navbar`, `.pos-nav-*`); analogamente in archivio restano `.archivio-navbar`, `.archivio-nav-*`, `.aperto`. Non cancellarle senza conferma d'uso dinamico e test.
- `style.css` ha vecchie regole `.public-navbar` e componenti correlati non corrispondenti al markup attuale. La semplice rimozione è rischiosa finché non sono stati cercati classi generate da script o pagine non comuni.
- Nelle pagine autonome `iscrizione/conferma-genitore.php` le classi `.alert`/`.btn-gold` non hanno il foglio che le definisce. Gli stili delle pagine di login admin/telecronista sono quasi duplicati e ciascuno incorpora circa 130 righe inline.

## 6. JavaScript, DOM e contratti da congelare

Questa sezione elenca i contratti UI più importanti individuati. È un elenco operativo minimo, non un inventario esaustivo di ogni nodo HTML: ogni refactor deve effettuare una ricerca globale dei selettori effettivi prima di cambiare markup.

### Selettori e ID ad alta criticità

| Area | Selettori/ID osservati | Uso |
|---|---|---|
| Nav shared | `[data-site-nav]`, `data-nav-breakpoint`, `.menu-toggle`, `.mobile-panel`, `.mobile-links`, `.mobile-actions`, `body.site-nav-open`, `aria-controls` riferito all'ID configurato | Apertura/chiusura, focus e breakpoint responsive. |
| Countdown pubblico | `#countdown`, `data-target`, `#cd-days`, `#cd-hours`, `#cd-mins`, `#cd-secs`, `#next-match-countdown` | Conteggio aggiornato ogni secondo. |
| Iscrizione giocatore | `#parent-fields` e input nominati; listener di data nascita/consensi | Mostra/nasconde campi genitore e consenso; non alterare hidden state e required senza test logico. |
| POS operatore | `#productsArea`, `#cartList`, `#cartTotal`, `#couponAlert`, `#btnConfirmOrder`, `#orderFormView`, `#orderDoneView`, `#assignedOrderNum`, `#orderSummaryTotal`, `#queueArea`, `#customerName`, `#tableNumber`, `#isTakeaway`, `#orderCovers`, `#tableGroup`, `#coversGroup`, `#coverFeePreview`, `#modalRoot`, `#btnVerifyCoupon`, `#couponCode`, `#btnNewOrder` | Rendering menu/carrello/coda, conferma, coupon, metadati e modali; molti contenuti vengono ricreati in JS. |
| POS KDS | `#kdsGrid`, `#historyGrid`, `#historyDate`, `#kdsTabs`, `#tabActive`, `#tabHistory`, `#btnLoadHistory`; `.item-ready-btn`, `.btn-deliver`, `.btn-reopen`, `.pos-kds-tab`; `data-item-id`, `data-order-id`, `data-tab` | Aggiornamento stato cucina/bar e storico. |
| POS tracking | `#btnTrack`, `#orderNumber`, `#trackResult` | Ricerca e aggiornamento stato dell'ordine. |
| Admin partite | `#id_squadra_casa`, `#id_squadra_osp`, `#sameTeamWarn`, `#matchupPreview`, `#prevCasa`, `#prevOsp`, `#submitBtn`, `#giocatoreSelect`, `#squadraSelect`, `#cbGiocata`, `#scoreFields` | Preview, validazione UI, campi condizionali e caricamento elenco giocatori. |
| Admin squadre | `#colorPicker`, `#colore` | Sincronizzazione selettore colore. |
| Media galleria | `#numeroSelezionati`, `#selezionaTutti`, `#scaricaAlbum`, `#scaricaSelezionati`, `#zipEsito`, `.seleziona-file`, `data-url-create`, `data-url-status` | Selezione e job ZIP/polling. |
| Media upload | `#albumDestinazione`, `#fileInput`, `#zonaDrop`, `#avviaUpload`, `#svuotaCoda`, `#codaUpload`, `#progressoGenerale`, `#progressoTesto`, `#progressoPercento`, `#progressoBarra`, `#caricamentiSospesi` | Upload chunked e stato di ripresa. |

`iscrizione.php` contiene un selettore residuale `document.getElementById('navbar')`; il nuovo header non produce quell'ID. Non correggere implicitamente durante l'audit, ma trattare la rimozione/aggiornamento come task separato e verificato.

### Nomi dei campi di form

Mantenere `name`, `id`, tipo, metodo e valori inviati. Tra i nomi rilevati: `csrf_token`, `id`, `password`, `nome`, `username`, `email`, `id_squadra`, `cognome`, `note`, `action`, `accetta_regolamento`, `accetta_privacy`, `accetta_foto`, `token`, `azione`, `ritorna`, `importo`, `data_pagamento`, `id_metodo_pagamento`, `stato`, `id_giocatore`, `logo_url`, `data_nascita`, `telefono`, `indirizzo`, `descrizione`, `ordine`, `role`, `password_confirm`. Form specifici includono, tra gli altri, `numero_maglia`, `ruolo`, `partita_id`, `add_event`, `tipo`, `nome_libero`, `minuto`, `note_ev`, `id_fase`, `id_squadra_casa`, `id_squadra_osp`, `data_ora`, `campo`, `giocata`, `gol_casa`, `gol_osp`, `shootout_casa`, `shootout_osp`, `autorizzata`, `colore`.

Nel POS operatore i dati ordine sono gestiti principalmente via JS/JSON, non come form HTML tradizionale: devono restare coerenti i nomi JSON `customer_name`, `table_number`, `is_takeaway`, `covers`, `items` e i campi prodotto/righe previsti da `create_order.php`.

### API e stato UI

- POS: envelope `{success, data, error}`; l'helper JS `apiFetch()` si aspetta `success` e `data`. Ordini e righe usano stati `pending`, `preparing`, `ready`, `delivered`, `cancelled`; modifiche ingredienti hanno `removed`/`added`; coupon `fixed`/`percent`.
- Archivio media: envelope JSON `{ok, errore}`. Upload ha protocollo init → chunk (hash per blocco) → complete, con abort e stato/ripresa; ZIP ha create/status/download e può elaborarsi durante polling. I file hanno permessi sensibili e gerarchia archivistica.
- Sito torneo: query e stati visualizzati devono continuare a riflettere i valori DB esistenti, senza introdurre normalizzazioni schema o cambi di flusso.
- Per ogni fetch: mantenere endpoint, verbo, header CSRF dove richiesto, struttura body, risposta, gestione errori e tempi/polling. Eventuali modifiche di presentazione vanno fatte attorno al contratto, non nel contratto.

## 7. Ruoli, accessi e navigazione

### Ruoli osservati

| Superficie | Ruoli/sessione | Accesso e navigazione osservati |
|---|---|---|
| Admin torneo | `admin_*`; ruolo admin, telecronista, uploader, editor | `includes/auth.php` verifica sessione e token, rigenera ID, reindirizza telecronista e ruoli media alle aree dedicate; pagine admin richiedono helper server-side. |
| Telecronista | ruolo `telecronista` e sessione admin condivisa | Header condiviso ma nav limitata a Partite; wrapper telecronista include viste `admin/partite`. Il filtraggio UI non sostituisce i controlli nelle viste/azioni. |
| Archivio | ruolo riletto lato server: `uploader`, `editor`, `admin` | Uploader: edizioni assegnate, download/eliminazione dei propri file; editor: tutte le edizioni, organizza e ZIP ma no upload/eliminazione; admin: piena gestione. Nav condizionale in `photo/admin/_header.php`; ogni endpoint ripete autorizzazione lato server. |
| Capitano | `capitano_id`, `capitano_auth`, namespace `capitano_*` | `require_capitano()` verifica login, email verificata e squadra attiva prima delle pagine riservate. Nav dashboard/giocatori/pagamenti e logout. |
| POS | `pos_admin_id`, `pos_admin_role`, `pos_admin_station` | `require_role()` separa `admin`, `operatore`, `bar`, `cucina`; navbar costruita per ruolo. `create_order` è limitato a admin/operatore; le viste cucina/bar hanno ruoli dedicati. |

Le sessioni admin, capitano e POS hanno prefissi distinti; in produzione i cookie usano dominio `.torneobaone.it`, `HttpOnly`, `SameSite=Strict`, `Secure`. Un restyling non deve rinominare chiavi, modificare redirect, mescolare sessioni o introdurre un nuovo provider di autenticazione.

### Invariante di sicurezza UI

1. Conservare middleware/helper e chiamate di autorizzazione nel punto in cui sono oggi.
2. Conservare CSRF e meta `csrf-token` nelle viste/API che lo usano.
3. Non mostrare dati media privati in superfici pubbliche e non rendere raggiungibile direttamente lo storage.
4. Considerare i link di nav solo presentazione: ogni protezione rimane server-side.
5. Testare accesso anonimo, ruolo insufficiente, cambio ruolo e sessione scaduta per i flussi sensibili.

## 8. Problemi UI/UX e responsive osservati

### Problemi strutturali e coerenza

- Layout non uniforme: pagine pubbliche e login si compongono con modalità diverse; aree autonome non condividono head/footer; alcune viste includono header/footer nel punto sbagliato del documento.
- Stili inline in 19 blocchi `<style>`, circa 218 attributi inline e circa 21 blocchi JS inline rendono difficile tracciare cascade e regressioni.
- CSS globale condiviso tra superfici differenti: `style.css` è già caricato da admin, archivio e POS; selettori base non confinati possono contaminare pagine isolate.
- Duplicazione tra `admin.css` e `iscrizioni.css` e tra login admin/telecronista; badge ruolo condivisi POS/media con stili diversi.
- Due vocabolari di tokens (`--navy`… e `--tb-*`) coesistono, mentre il brand kit non è ancora collegato ai layout.
- Il logo usato dal sito è `assets/img/logo_torneo.png`; il kit brand è separato. Il README dichiara che gli SVG del kit sono ricostruzioni originali e non tracciati esatti, quindi non vanno sostituiti al logo ufficiale senza approvazione. Conservare gli asset ufficiali esattamente come consegnati; niente ridisegno, recolor o deformazione.
- Nel repo la tavola si chiama `assets/brand/07_riferimenti/tavola_brand_approvata.png`; la richiesta menziona `brand_identity_reference.png`. Verificare la corrispondenza visiva con il committente prima di assumerli identici.
- `regole_uso.md` cita `logo_orizzontale_sfondo_scuro.svg`, nome non presente nell'inventario reale; il file disponibile è `logo_esteso.svg`. Anche questo asset è soggetto all'avvertenza di ricostruzione del README.
- Colori hardcoded nei CSS non sempre rispettano tokens o palette piena; la nav shared imposta accenti `#e8b638`/`#f2c653`, differenti dall'oro ufficiale `#C9A84C`/`#E8C76A`. La nav usa `Inter`, non il font display brand.
- Discrepanza anno: `SITE_NAME` = Baone 2027, diversi contenuti e nav = 2026. Non risolvere automaticamente con un anno dedotto dai dati.

### Responsività e accessibilità

- Pubblico: homepage e pagine torneo hanno CSS responsivo già presente, ma breakpoint dispersi e regole nav legacy. `live.php` usa scoreboard flex a dimensioni desktop e non dichiara media query nel blocco inline; su schermi stretti, nomi e punteggio molto grandi possono comprimersi/andare fuori viewport.
- Iscrizione: `iscrizione.php` presenta un lungo layout inline con breakpoint 700px e campi; il login capitano ha link di accesso con layout che può comprimersi su mobile. Le viste sono prioritarie per mobile e vanno controllate su viewport reale, tastiera e zoom.
- Capitano: `admin.css` rende la tabella giocatori in card su <=640px, ma altre tabelle admin hanno min-width 600px e scroll orizzontale; alcune viste capitano non usano `capitano/_header.php` (login/registrazione e flussi account), quindi la coerenza mobile è frammentata.
- Admin: tabelle con min-width/scroll sono comprensibili su desktop/tablet ma richiedono verifica overflow, comandi e leggibilità; `admin-main` e form hanno regole responsive basilari.
- Archivio: CSS prevede breakpoint 860/760/460 e griglia desktop; upload/galleria/file numerosi richiedono verifica di toolbar e comandi su tablet. La priorità è desktop/tablet, senza allargare la superficie pubblica.
- POS: layout operatore scende a una colonna sotto 1024px, ragionevole ma può aumentare lo scrolling in cassa; controlli quantità nel carrello non hanno stili specifici/target touch; `.btn-remove` ha padding minimo. KDS e toolbar necessitano verifica densità, stati e touch.
- Vari breakpoint non uniformi: public CSS include 900, 768, 700, 560, 420, 390, 1060 e 820; admin 640 duplicato; archivio 860/760/460; POS 1024/900/640/520. Il nuovo nav usa 820 e 1060, quindi va testata la transizione e l'assenza di salti.
- Nei template sono stati rilevati 156 `<label>` e circa 78 senza `for`; molti sono label wrapping input, ma occorre verificare l'associazione per input generati e dinamici. Non introdurre refactor DOM che renda inutilizzabili i click label.
- Una ricerca ha trovato poche occorrenze esplicite di skip-link/aria-live; live region, focus, contrasto, riduzione movimento e tastiera vanno inclusi nei test. Il menu ha già alcune buone pratiche (Escape, focus e `inert`); non perderle.

## 9. Pattern riutilizzabili da introdurre (solo proposta)

Il nuovo sistema deve essere incrementale, con prefisso `tb-`, semanticamente neutro e indipendente da Bootstrap. Riutilizzare i partial PHP esistenti solo dopo aver stabilito chiaramente se producono frammenti o documenti completi.

### Design tokens

- Colori richiesti: `--tb-navy: #0D1B2A`, `--tb-steel: #122338`, `--tb-gold: #C9A84C`, `--tb-gold-light: #E8C76A`, `--tb-white: #F0F4F8`; aggiungere con decisione documentata neutri, success/error/warning, superfici, bordi e focus.
- Tipografia: Barlow Condensed per display/heading e Barlow per testo/UI; definire fallback e scala responsive senza font a pagamento.
- Spaziatura, container, radius, elevation, breakpoint e focus ring condivisi; non sovrascrivere tokens globali legacy senza migrazione controllata.
- Riduzione movimento e target touch minimi come regole di base componenti, senza togliere animazioni già significative o countdown.

### Componenti candidati

1. `tb-site-header`/nav responsive con configurazione server-side, test di focus, ruoli e stati attivi; evoluzione del partial corrente senza rompere i suoi attributi finché tutti i consumer non sono aggiornati.
2. `tb-page-shell`, `tb-container`, `tb-page-heading`, `tb-section` e footer pubblico.
3. Pulsanti link/button (`primary`, `secondary`, `danger`, `quiet`, `small`, `loading`) con focus/disabled e dimensioni touch; evitare ambiguità `.btn` fra Bootstrap e classi legacy.
4. Alert/status badge/chip con stati e testo non affidati solo al colore.
5. Form field con label, hint, error, required/disabled e associazione accessibile; preservare `name`, `id`, tipo e form submission.
6. Card/surface/stats, empty state, confirm/action row e breadcrumb.
7. Tabelle responsive riutilizzabili con scroll/accessibilità; mantenere struttura/ordini di colonna necessari alle azioni.
8. Dialog/modal e toast/feedback progress per upload/ordini senza cambiare API o flussi.
9. Media gallery card, selezione, upload queue/progress, stato ZIP e KDS/order card come componenti specializzati, non assimilati a file manager generico.
10. POS-specific product tile, cart line, quantità touch, coupon, ticket/KDS status e tracking timeline.

## 10. Strategia di redesign per modulo

### 10.1 Fondazione e pubblico

- Prima chiarire contratto header/footer e posizione nel documento; correggere poi il pilot header/nav solo con test di tutte le pagine che includono i partial.
- Registrare screenshots desktop/tablet/mobile delle viste pubbliche e delle pagine con query/filter.
- Introdurre tokens `tb-*` come foglio additivo e componenti prefissati; pilotare prima su header/footer pubblico, poi home o calendario come pagina campione.
- Esternalizzare CSS pagina per pagina, portando prima gli stili inline sotto layout corretto. Mantenere query `fase`/`stato`, URL squadra, ID countdown e contenuti generati da DB.
- Portare `live.php` in direzione visuale comune solo dopo test dei casi: nessuna partita, partita programmata, match in corso, eventi, autorete e punteggio.

### 10.2 Iscrizioni e capitano — mobile first

- Separare, senza fondere i flussi di dominio, le pagine informative (`iscrizione.php`), onboarding (`capitano/registrati.php`), gestione capitano e compilazione giocatore/genitore.
- Consolidare pattern card, campi, alert e consensi; le schermate autonome di token e verifica necessitano un set CSS completo senza dipendere accidentalmente da `admin.css`.
- Prima della migrazione testare inviti/email/token, verifica e reset, accesso richiesto, stato squadra, consenso privacy/regolamento/foto, minore e conferma genitore.
- Preservare name dei campi, hidden token, validazione e stato delle caselle. Le pagine che includono header pubblico vanno separate dalle schermate in cui il layout di auth è intenzionalmente minimale, previa decisione UX.

### 10.3 Admin torneo e telecronista — desktop/tablet

- Stabilizzare pagina heading, action row, form, alert, tabella, filtri, stati e card metriche in `tb-*` o componenti versionati.
- Migrare a sezioni omogenee (prima dashboard o lista non critica, poi squadre/giocatori, partite/eventi, pagamenti); non accorpare endpoint e controller come parte del restyling.
- Preservare differenza accessi admin/telecronista e wrapper di compatibilità; verificare ogni POST, CSRF, redirect, fetch, id selezionati e messaggio flash.
- Testare tabelle su tablet, scroll orizzontale e azioni senza perdita di funzioni.

### 10.4 Archivio media — desktop/tablet, privato

- Trattare l'archivio come prodotto specifico per foto/video: gerarchia, metadati, preview e download autorizzati, upload/resume e ZIP; non introdurre semantica generica di file manager.
- Migrare prima il chrome condiviso e le superfici a rischio minore; galleria/upload sono componenti dinamici da affrontare dopo definizione di selettori e test API.
- Conservare `csrf-token`, `noindex`, ruoli, navigazione condizionale, messaggi, selezione, stati upload e job ZIP.
- Non spostare o esporre originali/miniature, non cambiare policy retention, path storage, hash, deduplicazione (che oggi è solo avviso) o formato risposta.
- Verificare licenze/consensi delle foto; nessuna immagine dell'archivio può essere riutilizzata per marketing/pubblico senza autorizzazione.

### 10.5 POS — desktop/tablet e touch-friendly

- Applicare componenti POS senza alterare logica ordine, prezzi, tariffe, coperti, asporto, coupon, ingredienti o stato.
- Pilotare su back-office semplice prima della cassa; l'operatore e KDS hanno impatto operativo elevato.
- Dare target touch coerenti a quantità, rimozione, stato e modali; verificare leggibilità e contrasto da distanza operativa.
- Conservare aggiornamento coda/KDS e tracking, ordine di polling, stati DB, API e schermate di successo.
- Le classi dinamiche non coperte da CSS devono essere prima mappate e verificate in browser; non rimuoverle come «unused».

## 11. Rischi di refactor e mitigazioni

| Rischio | Probabilità / impatto | Mitigazione proposta |
|---|---|---|
| Header/footer include cambia `head/body`, genera markup duplicato o chiusure fuori ordine | Alta / alta | Definire contratto layout (document shell vs partial body), migrare un consumer alla volta, controllare DOM finale e validità HTML. |
| Selettore/id/data attribute dinamico viene rinominato o rimosso | Alta / critica | Matrice DOM per pagina, ricerca repo, test interazione; considerare ID/selector API interna. |
| Cambio `name` o tipo di input altera POST/DB | Media / critica | Snapshot dei form e test submit con payload atteso; vietare modifiche di contratto in PR visuale. |
| Le regole globali `style.css` contaminano moduli o Bootstrap | Alta / alta | Namespace nuovo, evitare `*`, `body`, `.btn`, `.card` globali nei componenti; controllare cascade/ordine CSS. |
| La rimozione dei selettori legacy rompe pagina non vista o markup generato da JS | Media / alta | Prima catalogazione dinamica; non pulire CSS nello stesso passaggio del pilot visuale. |
| Migrazione di view sposta logica PHP/ruoli | Media / critica | Separare PR CSS/markup e PHP, mantenere autorizzazione e preparazione dati invariati; code review diff logico. |
| Polling o timer alterati producono coda/live non aggiornata o traffico eccessivo | Media / alta | Conservare intervalli e condizioni di stop; test di durata/rete/visibilità e comportamento errore. |
| Upload/ZIP media interrotto o stato di ripresa perso | Media / critica | Test reale chunk/resume, file grande, errore server, CSRF, progress e job ZIP; non toccare protocollo/API. |
| Link o action nav mostrati a ruolo sbagliato | Media / alta | Snapshot per ruolo + test diretti endpoint anonimo/ruolo insufficiente. |
| Aggiornamento logo/anno usa asset non approvato o non corrispondente | Media / alta | Reuse esatto asset approvato, gate brand owner per SVG kit non esatto; chiarire 2026/2027 senza supposizioni. |
| Contrasto/focus/motion peggiorano durante cambio estetico | Media / media | Test tastiera, zoom, reduced motion, contrasto e screen reader; focus visibile persistente. |
| Assenza di CI/test UI rende regressioni non rilevate | Alta / alta | Baseline manuale ripetibile, lint e smoke automatizzati senza dipendenze paid; introdurre gradualmente test contratto. |

## 12. Checklist di preservazione funzionale

Da allegare alle PR di redesign come gate. Non è autorizzazione a cambiare alcuno di questi elementi.

### Routing e navigazione

- [ ] Mantenere `/squadre`, `/squadra/{id}`, `/iscrizione`, URL puliti senza `.php`, `/admin`, `/telecronista`, `/photo/admin`, `/pos` e sottodomini come configurati.
- [ ] Conservare query string e redirect per filtri, login, logout, index canonico e URL con `.php`.
- [ ] Conservare comportamento `mod_rewrite`, eccezioni API/upload e protezioni file interne.
- [ ] Controllare link nav/footer per visitatore, admin, telecronista, capitano, media uploader/editor/admin e ruoli POS.

### Auth, ruoli e sessioni

- [ ] Conservare chiavi/prefissi `admin_*`, `capitano_*`, `pos_admin_*` e i controlli helper (`require_admin`, `require_capitano`, `require_role`, media auth).
- [ ] Non modificare cookie `Secure`/`HttpOnly`/`SameSite`, rigenerazione sessione, token, redirect e invalidazione di ruolo.
- [ ] Verificare route ed endpoint direttamente, oltre alla visibilità nav.
- [ ] Conservare CSRF e token markup/API; mantenere `meta[name="csrf-token"]` nell'archivio.

### Form e dati torneo

- [ ] Conservare `name`, `id`, metodo, action, tipo, required/disabled, hidden input, ordine e opzioni di ogni form.
- [ ] Verificare campi nominati in §6 più campi specifici individuati per ogni form; non cambiare logica di consenso/minori, token, email o iscrizione.
- [ ] Conservare filtri calendario `fase`/`stato`, ID match/squadra, stati DB, ordinamenti e contenuti di risultati/eventi.
- [ ] Conservare calcolo classifica e semantica degli eventi (gol, autorete, ammonizione, doppia ammonizione, espulsione, MVP).
- [ ] Nessuna modifica a schema, SQL/migrazioni, dati o enum/status esistenti.

### Contratti POS e archivio

- [ ] POS `{success,data,error}`; body ordine, coupon, endpoint, verbi, stati `pending/preparing/ready/delivered/cancelled` e modifiche ingredienti intatti.
- [ ] POS tariffe `cover_fee`/`takeaway_fee`, coperti, asporto, numero progressivo, totale, coda e tracking non modificati.
- [ ] Media `{ok,errore}`; init/chunk/complete/abort, verifica/hash per chunk, resume, upload ownership, URL/status ZIP e download autorizzato intatti.
- [ ] Conservare formati media ammessi e gerarchia archivio; originali invariati, storage privato, ruoli e retention.
- [ ] Nessun contenuto media privato caricato in pagine pubbliche o servizi terzi.

### Interazioni visuali/temporali

- [ ] Countdown e reveal rispettano i selettori attuali e `prefers-reduced-motion`.
- [ ] Live continua a rappresentare partita/eventi ed esegue refresh ogni 10 secondi, compreso stato senza partita.
- [ ] POS operatore/KDS continua polling ogni 5 secondi; tracking continua ogni 10 secondi; non avviare intervalli duplicati durante rerender.
- [ ] Galleria e upload conservano focus, selection state, progress, ripresa, errori e stati job ZIP.
- [ ] Gli stati non sono comunicati solo via colore; focus, tastiera e target touch restano utilizzabili.

## 13. Roadmap di implementazione suggerita

La roadmap è una proposta successiva all'audit; nessuna fase è stata implementata.

### Fase 0 — baseline e gate

1. Congelare elenco rotte, ruoli, contratti API, nomi form, ID e schermate; versionare la matrice di test.
2. Registrare baseline screenshot e flussi funzionanti su ambiente locale/staging con dati non produttivi.
3. Chiarire brand board effettiva, logo da usare e anno dell'edizione con responsabile prodotto.
4. Eseguire lint PHP, verifiche JS e smoke test già disponibili prima di ogni PR.

### Fase 1 — fondamenta UI non invasive

1. Definire `tb-*` tokens completi a partire dai valori ufficiali già presenti in `assets/brand/05_ui/brand-tokens.css`.
2. Definire componenti accessibili `tb-*` in un CSS separato; evitare selettori legacy/globali.
3. Stabilire un contratto PHP chiaro per document shell, header/nav, footer e pagine autonome.
4. Pilot visuale su header/footer pubblico e testare tutti i consumer della nav comune. In questa fase non cambiare business logic, markup/data contract o URL.

### Fase 2 — sito pubblico mobile-first

Migrare una pagina alla volta (homepage → calendario/classifica → scheda partita/squadra → iscrizione informativa → live), esternalizzando stili e verificando filtri, link, responsive, countdown e refresh.

### Fase 3 — iscrizione e capitano mobile-first

Completare card/form/alert e gerarchia delle pagine; validare iscrizione, email, token, conferme genitore, consensi, giocatori e pagamenti; testare viewport e tastiera.

### Fase 4 — admin e telecronista desktop/tablet

Standardizzare layout, heading, form, tabelle, filtri e stati; migrare prima viste non critiche, poi partite/eventi/risultati e pagamenti, preservando wrapper/permessi.

### Fase 5 — archivio privato desktop/tablet

Completare chrome, dashboard/struttura, galleria, upload e utenti. Per galleria/upload usare prove di integrazione su storage di test e API esistenti; verificare ruoli e privacy.

### Fase 6 — POS desktop/tablet touch-friendly

Migliorare back-office, tracking, KDS e infine cassa; validare ordini reali solo in staging/test. Verificare polling, touch, prezzo/fee/coupon e stato senza cambiare endpoint.

### Fase 7 — pulizia controllata

Solo dopo migrazione e coverage: identificare regole/classes non più usate, duplicati e asset CDN; rimuovere un gruppo alla volta con screenshot/diff e suite completa. Nessuna rimozione feature o dependency senza autorizzazione esplicita.

Ogni fase deve produrre PR piccole e reversibili, un modulo alla volta; separare refactor estetico da cambi PHP funzionali.

## 14. Strategia di test

### Situazione esistente

- Non sono presenti `package.json`, bundler, framework CSS/JS, pipeline CI o suite UI generale.
- `composer.json` dichiara PHPMailer; `tests/email_system_smoke.php` controlla l'esistenza di `send_email()` e della classe PHPMailer, ma non è una suite di regressione UI/domino.
- È presente `tools/test-mail.php`, strumento operativo/manuale, non test automatico visuale.
- `photo/README.md` documenta test approfonditi già eseguiti dal progetto per archivio (lint, migrazioni su DB usa-e-getta, permessi, upload/resume, input ostili, ZIP, interfaccia browser). Non assumere che tutti i test descritti siano automatizzati o riproducibili senza ambiente corrispondente.

### Gate minimi per ogni modulo

1. **Statico:** `php -l` sui PHP toccati; controllo sintassi JS; ricerca di chiamanti/selector e asset; verifica diff che SQL/schema/API/auth/routing non cambino.
2. **DOM/contratti:** controllo document structure (`html/head/body` singoli e ordinati), form name/id/method/action, ID/data-attribute JS, link e asset load.
3. **Visuale responsive:** screenshot a 360/390px, tablet circa 768/820px e desktop circa 1280/1440px; verificare overflow, tabelle, nav, focus e scroll.
4. **Accessibilità:** tastiera completa, focus visible, Escape e focus return nav/dialog, label, contrasto, zoom 200%, reduced motion, annunci di stato.
5. **Ruoli e sessioni:** anonimo, ruolo minimo consentito, ruolo insufficiente, cambio ruolo, sessione scaduta, logout.
6. **Integrazione flussi:** submit form valido/invalido, CSRF, email/token in test, fetch riuscito/errore/time-out e ricaricamento.
7. **Polling:** controllare frequenza, stop condition, error state, duplicati e aggiornamento DOM senza perdere focus/scroll.
8. **Privacy/dati:** media in storage privato, download autorizzato, noindex; usare dati e storage non produttivi.

### Matrice smoke per modulo

| Modulo | Casi smoke indispensabili |
|---|---|
| Pubblico | Tutte le route; calendario filtri fase/stato; classifica; squadra e match; countdown; menu mobile e footer; live con/senza partita ed eventi. |
| Registrazione/capitano | Registrazione; login/logout; email verifica/recupero/reset; accesso non verificato/inattivo; aggiunta giocatore; minore e genitore; consensi; pagamenti/stati. |
| Admin | Login/logout; dashboard; CRUD squadra/giocatore/partita; risultati/eventi; pagamenti; telecronista con accesso consentito e route non consentite; CSRF/errori. |
| Archivio | Ruoli e assegnazioni; navigazione edizioni/giornate/album; upload immagine/video ammesso/non ammesso; interruzione/ripresa; miniatura; selezione e ZIP; download, cestino, retention e noindex. |
| POS | Login per ruolo; menu/coupon; carrello/modifiche/note; tavolo/coperti/asporto; commissioni/totali; crea ordine; coda; KDS bar/cucina; storico; annulla/riapri; tracking; refresh polling. |

### Test visuale e regressione

Per il pilot nav/footer creare prima baseline screenshot delle pagine che includono `shared/site_nav.php`: home, calendario, scheda squadra/partita, login capitano, admin, area capitano, archivio e ogni pagina POS con navbar. Verificare desktop e mobile in particolare i due breakpoint (820/1060), pannello aperto, Escape, click esterno e passaggio breakpoint aperto.

Per ridurre rischio senza introdurre framework a pagamento, è possibile iniziare con checklist manuale ripetibile e piccoli test smoke PHP/JS locali. L'eventuale scelta futura di strumenti open-source va valutata separatamente per manutenzione, installazione e impatto sul deploy; non è prerequisito per il primo pilot.

## 15. Prima attività consigliata

**Task iniziale raccomandato:** fondazione CSS additiva `tb-*` e pilot controllato sul chrome pubblico (header/nav/footer), preceduti dalla definizione del contratto tra document shell e partial.

Motivazione: tocca il punto di massima riusabilità già esistente (`shared/site_nav.php`), crea una base per consolidare identità e responsive senza Bootstrap/framework, permette di validare i tokens del brand e limita il raggio d'impatto rispetto a POS, pagamenti o upload. Prima di considerarlo completato, il pilot deve risolvere/verificare il markup nei consumer attuali, il caricamento di `site-nav.js`, i breakpoint, il logo approvato, i link e l'incompatibilità residua di `#navbar` in `iscrizione.php`.

**Non** iniziare dalla riscrittura di template PHP né dalla pulizia indiscriminata dei CSS: prima stabilire i contratti UI, acquisire baseline e aggiungere test di preservazione.

## 16. Fonti repository consultate

Sono stati esaminati file di routing e struttura (`.htaccess`, `STRUTTURA_FILE.md`), configurazione/auth (`includes/config.php`, `includes/auth.php`, `includes/auth_capitano.php`), layout condivisi e nav, CSS/JS applicativi, viste pubbliche, capitano/iscrizione, admin/telecronista, archivio, POS, schemi SQL (in sola lettura), `composer.json`, smoke test e documentazione brand/archivio. Le raccomandazioni sono ancorate a questi file e ai selettori/payload osservati; le migrazioni e gli schemi sono citati unicamente per definire invarianti e non sono stati modificati.
