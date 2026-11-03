# Baone UI — sistema di design condiviso

**Versione:** 1.0 (fondazioni)

**Ambito:** solo layer CSS riutilizzabile e pagina showcase; non è stato avviato il redesign dei moduli applicativi.

**Stato:** additivo, esplicito e non collegato ai layout di produzione.

## 1. Obiettivo e principi

Baone UI è un sistema di fondazioni visuali per le interfacce Torneo Baone. Traduce l'identità navy/oro in tokens, tipografia, scale e componenti di base con classi `tb-*`. Le regole sono confinate sotto `.tb-ui` e non rimpiazzano i fogli legacy: le pagine esistenti non caricano il sistema finché non scelgono esplicitamente il nuovo entry point.

Principi:

- identità sportiva premium con Barlow Condensed per titoli/risultati e Barlow per testo e moduli;
- contrasto leggibile, gerarchia tipografica e testo di stato oltre al solo colore;
- controlli touch di almeno 44px, focus visibile, tastiera e `prefers-reduced-motion`;
- selettori component-scoped; niente reset globale di `body`, `button`, `.btn`, `.card`, `table` o `input`;
- nessuna dipendenza runtime o di build aggiunta; componenti basati su HTML/CSS nativi;
- adozione incrementale, mantenendo invariati flussi, form, API, ruoli, route e JavaScript applicativo.

## 2. File e architettura

| File | Contenuto |
|---|---|
| `assets/css/baone-ui.css` | Entry point che importa, in ordine, tokens, fondazioni e componenti. |
| `assets/css/brand/tokens.css` | Variabili `--tb-*` per colori, semantica, font, type scale, spacing, radius, ombre, larghezze, target e motion. Dichiarate solo su `.tb-ui`. |
| `assets/css/brand/foundation.css` | Base tipografica, box sizing confinato, link, immagini, focus e skip link. |
| `assets/css/components/layout.css` | Container, stack, cluster, griglie, intestazioni pagina/sezione, divider e motivo diagonale. |
| `assets/css/components/actions.css` | Varianti button, dimensioni, stati disabled e icon button. |
| `assets/css/components/forms.css` | Form, righe, label/hint/error, input, select, textarea, checkbox e radio. |
| `assets/css/components/surfaces.css` | Card, superfici, statistiche e tabellone match. |
| `assets/css/components/data.css` | Tabelle, scroll orizzontale, struttura card su mobile e classi numeriche/rank. |
| `assets/css/components/feedback.css` | Badge, alert, stati vuoto/errore e spinner. |
| `assets/css/components/navigation.css` | Pattern nav responsive HTML nativo con `details`/`summary`. |
| `assets/css/components/overlays.css` | Dialog HTML nativo e motion ridotto. |
| `docs/redesign/baone-ui-showcase.html` | Showcase autonomo, con dati fittizi, dialog di prova e collegamento esplicito al CSS. |

L'ordine dell'entry point è intenzionale: i tokens devono precedere ogni componente. Per una pagina PHP già pronta alla migrazione è sufficiente collegare `assets/css/baone-ui.css` nell'head e racchiudere **solo l'area migrata** in un wrapper `.tb-ui`. Per pagine interamente migrate si può applicare il wrapper al contenitore principale. Non includere il foglio in modo globale dai layout comuni prima di aver verificato ogni consumer.

## 3. Identità e tokens

I colori ufficiali sono conservati nei valori esatti:

| Token | Valore | Uso |
|---|---|---|
| `--tb-navy` | `#0D1B2A` | Blu notte / identità primaria. |
| `--tb-steel` | `#122338` | Blu acciaio / superfici. |
| `--tb-gold` | `#C9A84C` | Oro torneo, azione primaria. |
| `--tb-gold-light` | `#E8C76A` | Evidenza, link e stati selezionati. |
| `--tb-white` | `#F0F4F8` | Testo chiaro. |
| `--tb-slate` | `#6B7A8F` | Neutro brand. |
| `--tb-danger` | `#E4572E` | Token semantico danger (non usato da solo come testo su navy). |
| `--tb-success` | `#2E7D32` | Token semantico success. |

Sono aggiunti token per surface, testo muted, bordi, focus, info/warning, spaziatura, scale tipografiche, radius, ombre, dimensioni e tempi di transizione. I token semanticamente deboli per testo piccolo (es. verde/arancio brand) non sono usati come testo isolato: badge e alert usano tinte chiare su superfici scure e includono una label testuale.

Il sistema usa solo Barlow Condensed e Barlow; la pagina demo li richiede al Google Fonts già utilizzato dal sito. I CSS dichiarano fallback locali per funzionare anche quando la rete esterna non è disponibile.

### Asset grafici

Lo showcase usa il file esistente `assets/img/logo_torneo.png` senza alterarlo. I motivi diagonali sono costruiti con CSS, non ridisegnano lo scudetto. Il repository include `assets/brand/07_riferimenti/tavola_brand_approvata.png`; nel prompt l'immagine era indicata come `brand_identity_reference.png`, nome che non è presente nel checkout. Il README del kit avverte inoltre che gli SVG del kit sono ricostruzioni vettoriali e non tracciati esatti: non vengono introdotti come sostituti del logo corrente. Prima di cambiare logo nei moduli serve una conferma esplicita sull'asset ufficiale da usare.

## 4. Scale e breakpoint

- **Tipografia:** testo 12/14/16/18px; titoli con `clamp()` a tre livelli, line-height compatto per display e line-height 1.6 per body.
- **Spaziatura:** scala 4, 8, 12, 16, 20, 24, 32, 40, 48 e 64px (`--tb-space-*`).
- **Radius:** 6px, 10px, 16px e pill.
- **Ombre:** livelli `sm`, `md`, `lg` su superfici scure.
- **Controlli:** altezza base e target touch 44px; variante compatta disponibile per contenuti secondari.
- **Breakpoint di riferimento:** 40rem (form/tabelle), 48rem (nav/layout tablet), 64rem (griglie desktop), 80rem (larghezza massima contenuto). Non sono un vincolo per componenti locali: le media query rispondono al componente e non ridefiniscono tokens legacy.

## 5. Componenti disponibili

### Layout e intestazioni

- `.tb-container`, `.tb-stack`, `.tb-cluster`, `.tb-grid`, `.tb-grid--auto`, `.tb-grid--2`, `.tb-grid--3`;
- `.tb-page-header`, `__eyebrow`, `__title`, `__description`, `__actions`;
- `.tb-section-heading`, `.tb-divider`, `.tb-stripe-mark`.

### Azioni

- `.tb-button` primario; varianti `--secondary`, `--quiet`, `--danger`, `--small`, `--large`, `--full`;
- stati nativi `:hover`, `:active`, `:focus-visible`, `:disabled` e `aria-disabled`;
- `.tb-icon-button` con target minimo touch.

Usare `<button>` per azioni e `<a>` per navigazione; la classe non cambia semantica o comportamento del controllo.

### Form

- `.tb-form`, `.tb-form-row`, `.tb-form-row--2`, `.tb-field`, `.tb-label`, `.tb-hint`, `.tb-field__hint`, `.tb-field__error`;
- `.tb-input`, `.tb-select`, `.tb-textarea`, `.tb-check`, `.tb-radio`;
- per un errore usare `aria-invalid="true"` e associare il messaggio via `aria-describedby` nel markup della pagina.

I componenti non cambiano `name`, `id`, `required`, `value`, `method`, `action`, logica di validazione né listener JS. Le classi si aggiungono agli elementi mantenendo invariati gli attributi esistenti.

### Superfici, match e dati

- `.tb-card` con `--raised`, `--accent`, `__header`, `__title`, `__body`, `__footer`;
- `.tb-stat-card` per metriche;
- `.tb-match-card` per squadre e punteggio, con nome e punteggio non legati a icone o colore;
- `.tb-table-wrap`, `.tb-table`, `--stack`, `__numeric`, `__team`, `.tb-rank`.

Le tabelle possono scorrere su viewport stretti. La modalità `tb-table--stack` passa a righe/card su mobile: ogni `<td>` deve ricevere un `data-label` equivalente alla propria intestazione; le intestazioni restano semanticamente presenti per gli screen reader. Conservare ordinamento, colonne e controlli applicativi.

### Badge, alert e stati

- `.tb-badge` con `--live`, `--success`, `--info`, `--warning`, `--neutral`;
- `.tb-alert` con varianti semantiche; impostare `role="status"` o `role="alert"` secondo urgenza;
- `.tb-state` con `__icon`, `__title`, `__description` e `--error`;
- `.tb-spinner` decorativo, con testo di caricamento e `aria-live` nel contenitore quando lo stato cambia dinamicamente.

### Navigazione e dialoghi

- `.tb-navigation` usa `details` e `summary`, funzionanti senza JS per il menu responsive. Mantiene una lista desktop e una lista mobile; personalizzare l'`aria-label` e l'elemento corrente (`aria-current="page"`).
- `.tb-dialog` è pensato per `<dialog>` nativo aperto con `showModal()`, che fornisce gestione focus e backdrop nativi. I pulsanti di chiusura devono chiamare `.close()` e l'azione distruttiva deve essere esplicita.

Questi pattern sono esempi del nuovo sistema, non sostituti automatici della nav esistente `shared/site_nav.php` e del relativo `site-nav.js`.

## 6. Showcase

Aprire `docs/redesign/baone-ui-showcase.html` tramite il server locale del progetto (ad esempio il virtual host di sviluppo), oppure con un browser che consenta il caricamento dei riferimenti relativi. La pagina mostra nav responsive, header pagina, pulsanti, campi form, card/stat, tabellone, classifica responsive, badge, alert, empty/loading/error, motivo diagonale e dialog. Tutti i dati sportivi nella demo sono fittizi e non vengono inviati/salvati.

La pagina importa il solo entry point Baone UI. Non carica `style.css`, `admin.css`, Bootstrap o script applicativi del torneo; la verifica è quindi una prova isolata dei componenti, non una regressione browser di tutte le viste esistenti.

## 7. Compatibilità e adozione nei moduli

### Come integrare in sicurezza

1. Caricare `assets/css/baone-ui.css` solo sulla pagina pilota, dopo aver verificato l'head/layout che la produce.
2. Racchiudere la UI migrata in `.tb-ui`; evitare wrapper globali nei partial comuni finché esistono viste legacy che condividono gli stessi header/footer.
3. Sostituire classi visuali per piccoli gruppi di elementi, non gli ID/name/data-attribute già usati da JS o PHP.
4. Verificare l'ordine dei CSS: il nuovo layer è namespaced, ma un elemento può ancora avere classi legacy e `tb-*`; testare cascade e contrasto prima di rimuovere le vecchie classi.
5. Mantenere classi/ID e nodi richiesti da rendering dinamico; quando il server/JS crea HTML, applicare le classi Baone UI nel template string senza cambiare payload o selettori.
6. Rimuovere le classi legacy soltanto quando tutte le viste che le usano sono state migrate e le regressioni sono coperte.

### Compatibilità per superficie

| Modulo | Compatibilità e percorso raccomandato |
|---|---|
| Pubblico | Buona per integrazione selettiva: aggiungere il foglio su una pagina pilota e usare i componenti in una shell isolata. `style.css` globale, `shared/header.php` e `footer.php` hanno problemi di ordine/contratto evidenziati nell'audit; non importare il design system dai partial prima di stabilizzarli. Conservare countdown, URL puliti, filtri e selettori `main.js`. |
| Iscrizione/capitano | Form/card/alert sono coperti e mobile-first. Non sostituire i form né i loro `name`, `id`, token, consensi e campi dinamici. Alcune pagine caricano fogli diversi e il login capitano include partial in una struttura documentale non uniforme: integrare dopo aver corretto separatamente il contratto layout. |
| Admin/telecronista | Card, tabelle, alert, stati, form e header sono disponibili. `admin.css` usa classi generiche; mantenere insieme il legacy quando si testa un componente misto e restringere `.tb-ui` al pannello migrato. Preservare ruoli, wrapper telecronista, CSRF, fetch e controlli DOM. |
| Archivio privato `/photo` | Componenti adatti a dashboard, gallerie e stati upload, con classi specializzate da definire nella successiva migrazione. Non rendere pubbliche anteprime/originali; non spostare logica upload, selezione, ZIP, permessi o CSRF. Il nuovo CSS non legge né modifica dati media. |
| POS | Card, badge, alert e target touch possono migliorare progressivamente la cassa/KDS. Non applicare le classi alle stringhe dinamiche senza aggiornare le relative view HTML (solo UI) e verificarle; non toccare poll, API, stati, prezzi, coupon o selettori JS. |
| Live | `.tb-match-card`, type scale e badge sono candidati per una futura vista responsiva. Mantenere query, semantica eventi e refresh 10s; la demo non modifica `live.php`. |

### Bootstrap

Bootstrap non viene importato dal sistema. Nell'app attuale è caricato dal layout capitano e le classi comuni (`.btn`, `.card`, `.row`, `.form-group`) sono già usate con significati locali. Baone UI evita tali nomi e funziona senza Bootstrap. In pagine che continuano a caricarlo, i prefissi `tb-*` limitano conflitti; non aggiungere Bootstrap agli altri moduli per utilizzare i componenti.

## 8. Verifiche e limiti

### Controlli statici eseguibili

- verificare che ogni selettore di componente in `assets/css/brand/` e `assets/css/components/` inizi con `.tb-ui`;
- controllare parentesi graffe, link importati e riferimenti relativi della pagina showcase;
- controllare il diff: nessun file PHP applicativo, JS applicativo, `.htaccess`, SQL, schema, migrazione, asset brand o configurazione deve cambiare in questa fase;
- opzionalmente eseguire validazione CSS/HTML con strumenti già installati, senza introdurre dipendenze.

### Esito dei controlli eseguiti

- CSS importati dal browser locale: entry point, tokens e componenti richiesti hanno risposto `200`; logo demo `assets/img/logo_torneo.png` ha risposto `200`.
- Browser Chromium: pagina caricata senza errori console; i componenti campionati riportano stili calcolati Baone UI; il pulsante demo apre il dialog nativo (`showModal()`). L'overflow orizzontale non è risultato presente nella viewport del test (818px).
- Ricerca repository: `assets/css/baone-ui.css` è referenziato solo dallo showcase e dalla documentazione; i layout applicativi non lo caricano.
- Controllo statico: parentesi graffe CSS bilanciate, import locali presenti, selettori globali legacy non rilevati nei nuovi fogli, whitespace senza errori.
- `php -l tests/email_system_smoke.php`: sintassi valida. L'esecuzione del test smoke non è riuscita perché `vendor/autoload.php` non è presente nel checkout; il test si interrompe prima delle asserzioni PHPMailer. Non sono state installate dipendenze.
- La cattura screenshot del browser non è risultata disponibile nell'ambiente; test visivi multi-viewport, tastiera e reduced motion rimangono verifiche manuali richieste.

### Verifiche browser manuali richieste

- Aprire showcase dal virtual host locale e verificare richieste CSS/font/logo, console e layout a 360/390px, 768/820px e 1280px+.
- Usare tastiera: skip link, nav `details`, ordine focus, pulsanti, apertura dialog, focus iniziale, Tab/Escape e ritorno al trigger.
- Verificare tabella in modalità desktop e mobile con zoom 200%, nomi squadra lunghi e contenuti con più righe.
- Simulare `prefers-reduced-motion: reduce`; controllare focus visibile e contrasto sul tema scuro.
- Prima dell'adozione, confrontare screenshot di pagine legacy rappresentative e ripetere i test funzionali specifici descritti in `01_AUDIT_TECNICO.md`.

Il foglio è volutamente non collegato ai layout esistenti: lo showcase verifica il rendering isolato, non prova l'assenza di regressioni per tutte le pagine dell'applicazione. L'adozione nei moduli richiede test manuali di browser per consumer e viewport; non è dichiarato un test end-to-end globale completato.

## 9. File creati

- `assets/css/baone-ui.css`
- `assets/css/brand/tokens.css`
- `assets/css/brand/foundation.css`
- `assets/css/components/layout.css`
- `assets/css/components/actions.css`
- `assets/css/components/forms.css`
- `assets/css/components/surfaces.css`
- `assets/css/components/data.css`
- `assets/css/components/feedback.css`
- `assets/css/components/navigation.css`
- `assets/css/components/overlays.css`
- `docs/redesign/baone-ui-showcase.html`
- `docs/redesign/02_DESIGN_SYSTEM.md`

Nessun file applicativo preesistente è stato modificato. Gli asset brand preesistenti sono stati consultati/riutilizzati soltanto per la presentazione; non sono stati alterati.
