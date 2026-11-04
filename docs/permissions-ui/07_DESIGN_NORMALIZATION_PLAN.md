# Phase 03 — piano di normalizzazione Baone UI

**Stato:** proposta d'implementazione; questa fase è solo audit e non autorizza modifiche applicative.  
**Baseline:** homepage pubblica, con tokens/componenti esistenti come strumenti d'implementazione. Prima di migrare si deve risolvere esplicitamente l'allineamento esatto fra i colori homepage e `--tb-*`, non sostituire la home con uno stile nuovo.

## Obiettivi e vincoli

1. Allineare superfici, form e navigazioni al linguaggio già visibile nella homepage: navy scuro, superfici acciaio, oro, Barlow/Barlow Condensed, card con bordi sobri e focus evidente.
2. Ridurre duplicazioni e cascade globale senza rompere URL rewrite, permessi, endpoint, POST/CSRF, query, stati, selettori JS/DOM, polling, upload, consensi o contenuti privati.
3. Distinguere deliberatamente la densità di console operative POS/admin dalla presentazione pubblica, condividendo primitive/accessibilità ma non forzando identica larghezza, footer o densità.
4. Adozione progressiva: le classi `tb-*` e `baone-ui.css` esistenti sono la base candidata; `style.css` e i fogli locali restano finché i consumer non sono migrati e verificati.

## Design tokens condivisi

Mappare i valori runtime homepage esistenti a token semantici stabili; `assets/css/brand/tokens.css` è il vocabolario candidato, ma vanno risolte queste discrepanze prima della migrazione:

| Token proposto | Baseline homepage da preservare/decidere | Token Baone UI corrente | Nota |
|---|---|---|---|
| canvas / page | pubblico `#091521`; legacy `#0d1b2a` | `--tb-canvas #0A1521`, `--tb-navy #0D1B2A` | Distinguere fondo body dal navy identitario. |
| surface / raised | `#122338`, `#172b40`, sezioni `#101e2d` | `#122338`, `#192D43` | Identificare livelli semantici. |
| gold / highlight | `#c9a84c`, `#e8c76a`, focus `#ffe08a` | gold/light e focus equivalenti | Mantenere oro CTA, testo/active e focus distinti. |
| text / muted | `#f0f4f8`, `#b8c3cf`, `#c4ced8` | `#F0F4F8`, `#B8C3CF`, `#9AA9B9` | Validare contrasto testo piccolo; uniformare muted/subtle. |
| danger/success/info | coral live, legacy red/green e alert locali | `#E4572E`, `#2E7D32`, `#4B8DB8` | Definire testo/surface per contrasto e label non solo cromatica. |
| typography | Barlow/Barlow Condensed | font e scale clamp già presenti | Unificare loading/fallback e risolvere Arial/Inter locali. |
| spacing/sizing | padding pubblico fluido, console più densa | scala 4px, radius 6/10/16, content 76rem, control 44px | Applicare solo dopo mapping per superficie. |

File primari: `assets/css/brand/tokens.css`, `foundation.css`, `baone-ui.css`, `assets/css/public-redesign.css`, `style.css`. Non aggiungere un secondo design system né promuovere token a globali senza consumer inventory e test cascade.

## Componenti condivisi da normalizzare

### Shell, documenti e navigazione

- Mantenere i contratti di `shared/site_nav.php` + `assets/js/site-nav.js` (ID/data attributes/ARIA, doppio DOM, focus/Escape, click esterno); centralizzare visualmente altezza, logo, link, active, action, context, toggle, focus, panel e breakpoint.
- Brand/nav comuni con variante `dense`; non imporre nav pubblica a sidebar admin o display KDS. Admin workspace può conservare sidebar distinta ma condividere primitives di active/touch/focus.
- Preservare `aria-current`, contrasto, target 44px e label accessibile in ciascuna variante desktop/mobile.
- Stabilizzare prima il contratto HTML head/body/footer: `shared/header.php` e `shared/footer.php` hanno comportamenti documentali, non sono partial innocui. Login capitano e CSS/script post-footer sono prerequisiti.
- Preservare menu role/permission-aware già definiti da Phase 02; la visualizzazione non ridefinisce autorizzazioni.

### Template login/auth

- In fase implementativa creare un componente server-rendered condiviso per staff/admin/telecronista/POS/archive/capitano con parametri espliciti: titolo, sottotitolo, logo approvato, campi/copy, alert, second links, link ritorno, noindex e contesto.
- Normalizzare larghezza (card contenuto e variante ampia onboarding), posizione, padding, bordo/radius, logo ratio, tipografia, label, input circa 48px, CTA 44px+, errori/status e narrow mobile.
- Preservare funzionalità specifiche: username/email, toggle password, reset/verifica/registrazione, CSRF, banner archivio privato, generic anti-enumeration staff e redirect. Nessuna modifica ai flussi di autenticazione.
- Configurazione settore non deve comportare fork CSS/markup. Non incorporare automaticamente nav pubblica in ogni login.

### UI primitives

- Layout: `.tb-container`, header e griglie; mantenere profili `public`, `workspace`, `form-wide`, `reading` in relazione ai container esistenti.
- Actions: mappare `.btn-*`, `.qa-btn`, `pos-*` a `.tb-button`; preservare semantica link/button, focus/disabled/loading e target touch.
- Forms: mappare form-group/admin/player/POS/captain a `.tb-field`/input/select/checkbox; standardizzare helper/error/required, `aria-invalid`, `aria-describedby`; preservare `name`/`id`/JS.
- Surfaces: riutilizzare `.tb-card`, `.tb-stat-card`, `.tb-match-card` con varianti home/admin/POS/archive/auth; definire padding/radius senza rimuovere varianti funzionali.
- Data: `.tb-table-wrap` e `.tb-table`; decidere scroll versus stacking. Assicurare caption/header/label e overflow leggibile.
- Feedback: convergere su alert/badge/state/spinner; stati con testo e ruoli assistivi. Applicare anche a HTML generato da JS per POS/archive/home senza cambiare API o selettori.
- Dialog: inventariare prima i dialog JS; usare `<dialog>` conservando focus, Escape, conferma e azioni distruttive esplicite.
- Footer: conservare footer editoriale sul pubblico e dove intenzionale; workspace private/display possono rimanere senza footer marketing.

## Strategia di consolidamento CSS

1. **Baseline/inventory:** acquisire screenshot e matrice classi/caricamento CSS per route quando ambiente disponibile; registrare classi generate da JS.
2. **Contratti HTML:** separare document shell e partial header/footer; validare markup annidato, asset post-documento e dipendenze `#navbar`. Non mischiare correzioni strutturali e redesign estetico nello stesso changeset.
3. **Mappare cascade:** per route documentare ordine di `style.css`, `admin.css`, `public-redesign.css`, Baone UI e CSS locali. Non rimuovere regole legacy finché consumer search e test non sono conclusivi.
4. **Vertical slices:** wrapper `.tb-ui` solo sulla superficie migrata; caricare tokens/foundation/componenti esplicitamente; mappare classi con selettori namespaced. Niente regole globali/reset da partial condivisi.
5. **Estrarre CSS inline:** dopo snapshot e conferma ambito; non lasciare blocchi dopo `</body>`/`</html>`; mantenere DOM IDs/data usati da JS.
6. **Consolidare dopo migrazione:** `admin.css`, `iscrizioni.css`, `pos.css`, `archivio.css`, `captain-portal.css` restano per componenti di dominio. Rimuovere solo primitive duplicate con copertura completa. Ritirare Bootstrap capitano solo dopo inventario classi e regressione.
7. **Font/assets:** standardizzare Barlow/Barlow Condensed, fallback locale e loading; ottenere approvazione logo auth. Nessuna nuova dipendenza icone.
8. **Cache/doc:** versioning coerente (filemtime o sistema esistente), aggiornare showcase e inventory.

Non modificare API, route, `.htaccess`, payload, query business, permessi o schema come parte di normalizzazione CSS. Verificare server rendering, escaping e semantics a ogni slice.

## Strategia responsive

- Riferimenti di test: narrow ≤390px, mobile 390px, tablet 768px, desktop 1440px; test supplementari 320/360, 1024, landscape e zoom 200%. I riferimenti 40/48/64/80rem Baone UI sono component-driven, non sostituzione immediata di tutti i breakpoint esistenti.
- Nav: menu open/closed, focus/restore, Escape, viewport crossing, zoom e target touch.
- Forms: una colonna su mobile, label/error associati, checkbox consensi attivabili, niente colonne troppo strette.
- Data: scroll accessibile o card semanticamente annotate; non nascondere intestazioni senza equivalente.
- POS/KDS: touch, distanza fra azioni, leggibilità a distanza e stati senza hover; simulare rete lenta/errori.
- Archive: gallery e upload progress/resume devono conservare leggibilità e azioni; non esporre/croppare accidentalmente media privati.
- Motion/focus/contrast: reduced motion, focus visibile e status testuale oltre al colore.
- Verificare nessun overflow orizzontale globale; overflow tabella deve essere locale e chiaro.

## Rollout proposto

| Ordine | Slice | Perimetro | Gate di accettazione |
|---:|---|---|---|
| 0 | Baseline/document contracts | Screenshot, route/host, layout head/body/footer e ordine CSS. | Nessun documento annidato; baseline acquisita prima delle modifiche visuali. |
| 1 | Tokens/foundation | Allineamento tokens homepage, focus/typography/container scoped, showcase. | Nessun side effect fuori `.tb-ui`; contrasto validato. |
| 2 | Navigation | Visual shell shared e varianti workspace/captain/POS/archive. | Menu e permessi invariati; mobile/keyboard/active pass. |
| 3 | Auth | Login settore; poi reset/verifica/profile/password. | Login/CSRF/errori/redirect invariati; tutte le viewport. |
| 4 | Pubblico | Squadre/dettaglio, calendario, classifica, partita, live, iscrizione info. | Filtri, empty/non-empty, tables/scoreboard e route pass. |
| 5 | Registrazione/capitano | Consensi, onboarding, dashboard/players/payments. | Token/consensi/validation/fetch invariati; Bootstrap solo se coperto. |
| 6 | Admin workspace | Dashboard, match/team/player/forms/tables/payments. | Permission matrix, CRUD/CSRF e tablet sidebar pass. |
| 7 | Archivio | Dashboard/gallery/upload/structure/users/settings/errors. | ACL/media private, chunk resume, ZIP, delete/restore invariati. |
| 8 | POS | Operator/cart/queue, KDS, backoffice, tracking. | Workflow ordine/coupon/status/API e touch pass; tutti gli stati testati. |
| 9 | Cleanup | Retire CSS legacy/dead selectors, documentare mapping. | Consumer search, console/CSS/HTML regressions e rollback per modulo. |

Consegnare ogni slice separatamente; non iniziare la successiva prima dei gate.

## Visual regression

### Baseline

- Ambiente locale con fixture deterministiche e account di test isolati; registrare browser, font, viewport, route e stato. Non usare media privati in screenshot condivisi.
- Minimo 1440×900, 768×1024, 390×844 e 320×720 per homepage, calendario, standings, team, match, login, admin dashboard/table/form, captain players, POS operator/KDS, archive gallery/upload.
- Stati: base, focus/hover, active navigation, menu mobile aperto, validation error, success, empty, loading, network error, dialog, upload progress, disabled e contenuti lunghi.
- Confrontare screenshot prima/dopo con overlay e diff percettivo; font anti-alias va revisionato, non ignorato automaticamente. Conservare artifacts in CI fuori dal webroot.

### Test complementari

- Browser E2E: response/console/network, overflow, keyboard/focus, nav mobile, `aria-current`, reduced motion, form e stati dinamici, route rewrite/host.
- Backend: authorization/permission e journey; fixtures pulite e contract test API POS/archive. Visual diff non sostituisce sicurezza.
- Accessibility: tool automatici disponibili più review keyboard/contrast/screen reader; screenshot non prova accessibilità.
- Data: zero/many, label lunghi, caratteri italiani, server/network errors, touch e device scaling.
- Modifiche ai markup dinamici: snapshot/assertion HTML server-rendered e stringhe JS rilevanti.

### Limite corrente

Browser disponibile: route locali raggiunte e home verificata alla viewport disponibile 1000×700. Screenshot fallisce con “Screenshot needs a visible tab”; non è presente un controllo emulazione/resize viewport. La baseline screenshot e i test multi-viewport **non sono stati eseguiti nella Phase 03**. Prima di implementare occorre una sessione browser con screenshot e viewport controls; non creare immagini fittizie né dichiarare test mancanti come eseguiti.

## Rischi e decisioni necessarie

1. Confermare stagione/anno: SITE_NAME 2027 vs brand/copy/PDF 2026.
2. Approvare logo auth ufficiale: scudetto compatto, logo principale o `assets/img/logo_torneo.png`.
3. Definire table behavior standings/admin/media su mobile e densità KDS.
4. Acquisire browser screenshot/viewport e fixture non sensibili prima delle modifiche visuali.
5. Mantenere separati access control e visual nav: questa normalizzazione non cambia i permessi.

## File iniziali da considerare

Fondazioni: `assets/css/brand/tokens.css`, `foundation.css`, `assets/css/baone-ui.css`, `assets/css/components/*`, `docs/redesign/baone-ui-showcase.html`.  
Pubblico: `index.php`, `shared/header.php`, `shared/site_nav.php`, `shared/footer.php`, `assets/css/style.css`, `assets/css/public-redesign.css`.  
Shell locali: `admin/_header.php`, `_footer.php`, `admin.css`, `admin-workspace.css`; `capitano/_header.php`, `_footer.php`, `captain-portal.css`; `pos/includes/navbar.php`, `pos/assets/css/pos.css`; `photo/admin/_header.php`, `_footer.php`, `photo/assets/css/archivio.css`; `iscrizioni.css`, `registration-portal.css`.  
Per contratti applicativi consultare `docs/redesign/01_AUDIT_TECNICO.md` e `docs/redesign/02_DESIGN_SYSTEM.md`.
