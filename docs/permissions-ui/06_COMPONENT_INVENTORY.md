# Phase 03 — inventario componenti e pattern

**Scopo:** catalogo source-based dei componenti esistenti utili all'audit e alla futura normalizzazione. Nessun componente applicativo è stato modificato. Le classi `tb-*` sono disponibili ma non implicano che tutti i template le applichino correttamente.

## Sistema visuale e tokens

| Asset/pattern | Contenuto verificato | Uso e maturità | Normalizzazione suggerita |
|---|---|---|---|
| `assets/css/style.css` | Tokens globali `--navy #0d1b2a`, `--navy-mid #122338`, `--navy-light #1a3350`, `--gold #c9a84c`, `--gold-bright #e8c76a`, `--white #f0f4f8`, `--muted rgba(240,244,248,.55)`, red/green, `--nav-h:64px`, radius 10px, shadow e transition; reset universale; font Barlow; `.container` max 1100; buttons `.btn-*`; vecchia e nuova navbar; pubblico/footer/admin utilities e responsive legacy. | Caricato trasversalmente da più superfici, molto legacy/global e ordine-cascade sensibile. | Sorgente di compatibilità da disaccoppiare per superficie e progressivamente mappare su token semantici. Evitare nuovi consumer globali. |
| `assets/css/public-redesign.css` | Vars `--public-*`; sfondo `#091521`, superfici, home hero/sections/card/standings; override calendario/classifica/match/live/footer; breakpoints 700/420 e reduced motion. | Baseline attuale homepage e override pubblico selettivo; non è design token centralizzato. | Conservare il linguaggio, trasferire i valori concordati ai tokens; estrarre regole inline e mantenere selettori DOM. |
| `assets/css/brand/tokens.css` | `--tb-*`: navy/steel/gold/light/white, danger/success/info/warning, canvas/surface/text/border/focus; Barlow/Condensed; scale type, line-height, spacing 4–64px, radius 6/10/16/pill, shadow, content 76rem/reading 48rem, controls/touch 44px, motion. | Solo su `.tb-ui`, niente effetto sui consumer non wrapper. | Usare come catalogo canonico; allineare discrepanze con homepage in una decisione documentata prima di sostituire hardcoded. |
| `assets/css/brand/foundation.css` | Base `.tb-ui`, typography, link/image, focus/skip link, box sizing scoped. | Namespaced; sicuro per adozione selettiva. | Riutilizzare wrapper isolato; verificare interazione coi reset `style.css` e `admin.css`. |
| `assets/css/baone-ui.css` + `assets/css/components/*.css` | Entry import tokens/foundation e componenti layout/actions/forms/surfaces/data/feedback/navigation/overlays; selettori namespaced. | Showcase/doc sono consumatori certi; alcuni layout staff/POS recenti caricano entry point; non universalmente applicato. | Adottare componente per componente e documentare mapping legacy → tb; non collegare globalmente dai partial finché incompatibilità restano. |
| `docs/redesign/02_DESIGN_SYSTEM.md` e showcase | Contratti, scale, usage/compatibilità, esempio statico nav/form/card/table/status/dialog. | Specifica interna, non test end-to-end delle viste produzione. | Aggiornare inventory durante rollout con screenshot baseline e decisioni effettive. |

### Token esistenti consigliati come riferimento

Usare i valori homepage effettivamente osservati come valori candidati: canvas `#091521` (public) / `#0d1b2a` (legacy base); surface `#122338`; raised `#172b40` o token `#192d43`; gold `#c9a84c`, bright `#e8c76a`, highlight/focus `#ffe08a`; text `#f0f4f8`; muted homepage `#b8c3cf` / `#c4ced8`. Il token `--tb-canvas #0A1521` non coincide esattamente con `public-redesign.css`; la palette di errore/successo varia tra `#d94f4f`, `#E4572E`, `#4caf7d`, `#2E7D32` e tinte locali. La normalizzazione deve scegliere valori semantici centralizzati senza abbassare contrasto o cambiare intenzionalmente il tono homepage.

## Componenti condivisi e layout

| Componente | File/classe | Consumatori rilevati | Stato / limiti |
|---|---|---|---|
| Nav renderer comune | `shared/site_nav.php`; `.site-header`, `.nav-shell`, `.brand`, `.nav-links`, `.nav-actions`, `.mobile-panel`, `.menu-toggle`; `assets/js/site-nav.js` | Pubblico, capitano, POS, archive, admin (adattamento/sidebar) | Un’unica definizione dati e renderer; duplica primarie/azioni nel DOM desktop/mobile; breakpoint configurable (820/1060), open/inert/focus gestito JS. Admin workspace non usa il renderer visualmente per la sidebar pur ricevendo config server. |
| Public header | `shared/header.php` | Homepage, calendario/classifica/partita/squadre/dettaglio e iscrizione informativa secondo template | Imposta active e config; `isPublicRedesign` lista pagine ma wrapper/contract d’inclusione non uniforme. |
| Public footer | `shared/footer.php` | Aree pubbliche e login capitano per include problematico | Ricco brand/links/social e chiusura HTML/script; include equivale a layout close, non partial innocuo. |
| Admin workspace | `admin/_header.php`, `_footer.php`; `.admin-shell`, sidebar, topbar, `.admin-main` | Admin e telecronista | Sidebar 248px desktop, topbar 64px, responsive ≤900, tabelle/admin components in override; insieme a `style.css`, `admin.css`, Baone UI. |
| Captain workspace | `capitano/_header.php`, `_footer.php`; `captain-portal.css` | Dashboard, giocatori, pagamenti | Shared nav; CSS specifico; Bootstrap 5.3.8 CDN rimane; mobile table stacking e controls 48px. |
| POS nav | `pos/includes/navbar.php` + `shared/site_nav.php` | POS operator/KDS/admin | Config POS/role e context; CSS legacy `pos-navbar` ancora presente; visuale dipende da POS stylesheet. |
| Archive workspace | `photo/admin/_header.php`, `_footer.php`; `archivio.css` | Dashboard, gallery, upload, file, album/edizioni/giornate, users/settings | Nav dati via shared renderer; componenti media locali e residui vecchia navbar CSS. Privacy/noindex e upload UI speciali da preservare. |
| Auth page | Non esiste un template unico | Admin/telecronista, captain, POS, archive, public registration account flows | HTML/CSS card diversi e feature diverse; login capitano document structure issue. Candidate per shell parametrica, non singolo form funzionale. |
| Sector selector / profile / password | `staff/index.php`, `staff/profile.php`, `staff/change-password.php` | Staff autenticato | Documenti standalone con parte Baone UI, ma shell/nav/feedback non uniforme. |

## UI primitives esistenti

| Categoria | Legacy classi/implementazioni rilevate | Baone UI disponibile | Gap principali e consumer da validare |
|---|---|---|---|
| Container/spacing | `.container`, `.admin-container`, `.pos-main`, `.archivio-container`, `.admin-main`; max width differenti 1100/1120/1240/1400/1500. | `.tb-container`, `.tb-stack`, `.tb-cluster`, `.tb-grid*`, page/section headers. | La larghezza dipende dalla superficie; definire scale content vs workspace, non forzare una misura unica. |
| Buttons/actions | `.btn`, `.btn-gold`, `.btn-outline`, `.btn-red`, `.qa-btn`, `pos-*`, inline CTA, nav actions. | `.tb-button` varianti primary/secondary/quiet/danger/small/large/full; `.tb-icon-button`. | Altezza e padding variano; molte touch area <44, colori danger discordanti; distinguere link/action e disabled. |
| Forms | `.form-group`, `.admin-form`, `.player-form`, POS/captain forms, inline styles; Bootstrap captain. | `.tb-form`, field/label/hint/error, input/select/textarea/check/radio. | Validazione visiva/aria non uniforme; select native/dropdown e checkboxes locali; preserving name/id/autocomplete/JS essenziale. |
| Cards/surfaces | `.stat-card`, `.match-card`, `.cal-card`, `.info-card`, `.pos-panel`, `.archivio-*`, `.auth-card`/`login-card`. | `.tb-card`, `.tb-stat-card`, `.tb-match-card`. | Stesse nozioni con radius, padding, border e shadow diversi. Varianti operative/galleria/login sono legittime e vanno definite. |
| Tables/data | `.admin-table-wrap`, `.admin-table`, `.standings-table`, `.player-table`, archive e POS local. | `.tb-table-wrap`, `.tb-table`, stack mode, numeric/team/rank. | Scroll min-width vs stacking, captions/labels/data-label e header semantics non uniformi; test mobile essenziale. |
| Feedback | `.alert-success/error`, `.empty-msg`, `.archivio-empty`, `.state-pill`, KDS status, inline `innerHTML`. | `.tb-alert`, `.tb-badge`, `.tb-state`, `.tb-spinner`. | Differenze di colore/markup/role; async empty/loading/network error diversi; classi JS dinamiche vanno incluse nell'inventario. |
| Badge/status | `.state-pill`, `.fase-badge`, `.cal-status`, `.role-badge`, POS state labels. | `.tb-badge` semantic variants. | Stato spesso comunicato solo colore; ruolo badge archivio/POS differisce; palette rossi/verdi multipla. |
| Dialog/overlay | Confirm native/JS e overlay locali; Baone showcase include `<dialog>`. | `.tb-dialog` pattern. | Non inventariare un singolo modal attuale trasversale; individuare prima ogni trigger e focus management. |
| Icons | SVG inline shared/public/admin, logo PNG, emoji POS/home, icone login eye. | Nessun set icon font/runtime; `.tb-icon-button`. | Normalizzare stroke, dimensioni e accessibilità; non sostituire asset ufficiali senza approvazione. |
| Footer | `.public-footer` ricco; staff shells senza footer; login POS/archive locali. | Nessun footer universale prescritto. | Definire scope: footer pubblico e footnote/auth context; non aggiungere footer marketing alle app operative. |
| Empty/loading | Home `empty-msg`; admin simple copy; POS `Caricamento…` e polling; archive `archivio-empty` e upload queue. | `.tb-state`, `.tb-spinner`. | Stati JS da tradurre come varianti coerenti con aria-live, titolo e retry/action. |

## Navigazione e menu

| Superficie | Componente/dimensione | Link/active/mobile | Gap |
|---|---|---|---|
| Pubblico | Shared header; redesign nav shell min-height 76px, max 1280; brand logo 48×48; base legacy nav 64px esiste ancora. | Active `aria-current`; mobile JS toggle, doppio markup, breakpoint 820. | Differenza interna legacy/new nav e variabile logo/container. |
| Admin/telecronista | Sidebar 248px + brand image 42px, topbar 64px; dati da staff nav service ma shell propria. | Active `is-current` + `aria-current`; breakpoint ≤900 cambia sidebar orizzontale/scroll; actions/profile/logout. | Nav non è lo stesso renderer visivo responsive, icons inline; mobile sidebar labels/bottom si nascondono. |
| Capitano | Shared renderer dense false; nav height public-style; 3 primary + logout. | Active per path substring; mobile 820px. | Ruolo-specific content appropriato; active matching fragile e include Bootstrap residuo. |
| POS | Shared renderer dense; context username/ruolo/postazione; target new tab per admin. | Voci capability/ruolo, mobile breakpoint 1060. | Styling POS CSS e legacy navbar rules; user/profile control essenziale, cambio area da validare. |
| Archive | Shared renderer dense, menu media-specifico. | Active dal template; logout/actions; mobile breakpoint 1060. | `archivio.css` contiene navbar legacy; auth role-specific link state. |
| Login standalone | Admin niente nav; capitano login include public nav/footer (struttura da correggere); POS login public nav; archive nessuna nav. | Entry point incoerenti. | Il template auth shared dovrebbe esporre scelta `public back link`/sector context controllata, non indiscriminata nav operativa. |

## Responsive breakpoint inventory

| Asset | Breakpoint dichiarati | Uso/effetto |
|---|---|---|
| `assets/css/style.css` | 1060, 900, 820, 768, 700, 560, 420, 390px (più reduced motion) | Legacy public/nav/general, con sovrapposizioni. |
| `assets/css/public-redesign.css` | 700, 420px | Home/public layout; 390 rientra nel secondo, ma non copre tablet con regola dedicata. |
| `site-nav.js` | config 820/1060 | JS nav matcha data breakpoint; necessita test tra breakpoint e orientamento. |
| `admin-workspace.css` | 900, 560px | Sidebar → mobile, forms/tables. |
| `captain-portal.css` | min 640/1024 (40/64rem), max 540px | Overview, form columns, player table mobile cards. |
| `registration-portal.css` | 40rem, 64rem, 390px | Grid e narrow form. |
| `iscrizioni.css` | 540px | Registration shell/forms. |
| `admin.css`, `staff-users.css` | 640px, 900px | Layout/forms/staff list. |
| `pos/assets/css/pos.css` | 1024px e ulteriori media query del modulo | Operator grid e KDS/touch; audit per pagina. |
| Baone UI components | 24rem, 40rem, 48rem, 64rem, 80rem reference | Breakpoint system scoped, non ancora standard operativo. |

## Stati e verifiche future per componenti dinamici

- Match list/standings: empty e score; status/fase/qualificazione non solo colore.
- Admin forms: errore server, validation inline, success flash, delete confirmation, submit/loading/disabled.
- Capitano: email verification, invite pending/accepted, minor consent, payment pending/paid, no players.
- POS: menu fetch loading/fail/empty, cart empty/populated, coupon valid/invalid, order queued/error, KDS active/history/no orders/network unavailable, action in progress.
- Archive: no edition/day/album/files, upload queue idle/progress/paused/retry/failed/completed, ZIP running/ready/failed, file unavailable/403, trash empty.
- Nav: active links desktop/mobile, menu open/closed, Escape/focus restoration, viewport crossing, multi-area action/user controls.
- Dati anomali: label/username/team/album lunghi, numeri elevati, più righe, testi con apostrofi/accenti, zoom 200%.

## Componenti nominati per rollout

1. **Foundation:** token semantici homepage-aligned, typography, focus, container/stack.
2. **Navigation:** solo visual shell/brand/links/action/toggle shared; preservare current role config e menu logic.
3. **Auth shell:** title/subtitle/brand/logo/link context configurabili; form fields e alert composable.
4. **Forms:** label/help/required/error/input/select/check/radio; speciale registration consents e POS numeric/touch.
5. **Surfaces/data:** card/stat/match, table wrapper/stack, status badge.
6. **Feedback:** success/warning/error/info, empty/loading/retry, dialog/focus.
7. **Surface-specific:** POS KDS/order/cart; archive gallery/upload/progress; public scoreboard/registration.
