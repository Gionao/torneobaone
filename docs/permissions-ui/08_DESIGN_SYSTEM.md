# Phase 04 — sistema di design condiviso e componenti

## Sintesi

La palette condivisa ora deriva dai valori effettivi della homepage e viene caricata come token CSS; i componenti Baone UI restano namespaced sotto `.tb-ui`. È stato aggiunto un template visuale login PHP configurabile e sono stati migrati i login staff/admin, telecronista, capitano, POS e archivio. I backend di autenticazione, sessioni, verifiche, CSRF, destinazioni e separazione capitano/staff sono mantenuti nei rispettivi handler.

La navigazione continua a usare il renderer esistente `shared/site_nav.php` + `assets/js/site-nav.js`, i menu server-side Phase 02 e la shell sidebar admin già presente. Nessuna permission logic è stata aggiunta al JavaScript e non è cambiata la semantica di autorizzazione.

## Tokens definiti

Fonte canonica: `assets/css/brand/tokens.css`, importata da `assets/css/style.css` e `assets/css/baone-ui.css`. Le variabili sono disponibili su `:root` senza applicare reset o stili globali; le regole visuali Baone restano sotto `.tb-ui`.

| Gruppo | Variabili e valori |
|---|---|
| Brand | `--tb-navy: #0d1b2a`, `--tb-steel: #122338`, `--tb-gold: #c9a84c`, `--tb-gold-light: #e8c76a`, `--tb-gold-dim: #8a6f2e`, `--tb-white: #f0f4f8`, `--tb-slate: #6b7a8f`. |
| Fondi/superfici | `--tb-canvas: #091521`, `--tb-surface: #122338`, `--tb-surface-raised: #172b40`, `--tb-surface-deep: #101e2d`, `--tb-surface-soft: rgba(240,244,248,.045)`. |
| Testo/bordi/stati | `--tb-text: #f0f4f8`, `--tb-text-muted: #b8c3cf`, `--tb-text-subtle: #9aa9b9`, `--tb-border: rgba(240,244,248,.14)`, `--tb-border-strong: rgba(232,199,106,.36)`, `--tb-focus: #ffe08a`; `--tb-danger: #e4572e`, `--tb-success: #2e7d32`, `--tb-info: #4b8db8`, `--tb-warning: #c9a84c`; tinte leggibili `--tb-danger-text`, `--tb-success-text`, `--tb-info-text`. |
| Font | `--tb-font-display: 'Barlow Condensed', 'Arial Narrow', sans-serif`; `--tb-font-body: 'Barlow', Arial, sans-serif`. |
| Scale testo | `--tb-text-xs/sm/md/lg`: .75/.875/1/1.125rem; heading small/medium/large con clamp; `--tb-leading-tight: 1.08`, `--tb-leading-body: 1.6`. |
| Spaziatura | `--tb-space-1/2/3/4/5/6/8/10/12/16`: .25/.5/.75/1/1.25/1.5/2/2.5/3/4rem. |
| Container | `--tb-content-width: 75rem` (1200px), `--tb-public-width: 75rem`, `--tb-nav-width: 80rem` (1280px), `--tb-reading-width: 48rem`, `--tb-workspace-width: 93.75rem` (1500px). |
| Radius/elevazione | `--tb-radius-sm/md/lg/pill`: 6px/10px/16px/999px; `--tb-shadow-sm/md/lg` per superfici a livelli. |
| Dimensioni | `--tb-control-height: 2.875rem` (46px), `--tb-touch-target: 2.75rem` (44px), `--tb-nav-height: 4.75rem` (76px), `--tb-sidebar-width: 15.5rem` (248px), `--tb-topbar-height: 4rem` (64px). |
| Responsive/motion | Riferimenti `--tb-breakpoint-narrow/form/nav/grid/wide`: 24/40/48/64/80rem; le media query CSS restano valori letterali (custom properties CSS non sono valide nei media query standard). Motion 140/200ms e easing condiviso. |

I colori corrispondono alla homepage: `#091521`, `#122338`, `#172b40`, `#101e2d`, oro `#c9a84c/#e8c76a`, testo `#f0f4f8`, muted `#b8c3cf`. `style.css` conserva alias legacy (`--navy`, `--gold`, ecc.) per compatibilità. Le aree operative conservano varianti di densità, non una palette diversa.

## Componenti e utilizzo

### Entry point e compatibilità

- `assets/css/baone-ui.css` importa tokens, foundation e `assets/css/components/{layout,actions,forms,surfaces,data,feedback,navigation,overlays}.css` con cache-busting Phase 04.
- `assets/css/components/auth.css` fornisce la shell login comune; viene caricato esplicitamente dal template PHP.
- Aggiungere `class="tb-ui"` al wrapper/pagina migrata e caricare `baone-ui.css` solo dove richiesto. Evitare di applicare `tb-*` fuori da wrapper `.tb-ui` tranne le variabili root.
- Non rimuovere classi legacy, ID, `name`, `data-*`, listener o markup dinamici durante una migrazione solo visuale.

### Mappa componenti

| Uso | Classi/partial | Note |
|---|---|---|
| Navbar e menu mobile | `shared/site_nav.php`, `.site-header`, `.nav-link`, `.nav-action`, `.menu-toggle`, `.mobile-panel`; `assets/js/site-nav.js` | Config server-side per public, capitano, POS e archivio. La policy staff resta `includes/staff_navigation.php`; admin usa la sidebar workspace condivisa per le viste. Attivo via `aria-current`; JS si occupa solo di interazione/focus responsive, mai dei permessi. |
| Shell login | `shared/auth_page.php`, `.tb-auth-page`, `.tb-auth-layout`, `.tb-auth-card` | Configurazione `$authPage`: `title`, `subtitle`, `eyebrow`, `logo`/`logo_alt`, `page_title`, `action`, `method`, `fields`, `hidden`, `error`, `notice`, `links`, `description`, `footer`, `navigation`, `noindex`, `novalidate`, `show_password_toggle`, `submit`. Se valorizzato, `navigation` è la configurazione server-side di `shared/site_nav.php`; handler e backend rimangono nella pagina chiamante. |
| Campi login | `fields`: `name`, `id`, `label`, `type`, `required`, `value`, `autofocus`, `autocomplete`, `maxlength`, `hint`, `error`, `describedby` | Label esplicita, required nativo, `aria-describedby` per hint/error e `aria-invalid` per error campo. Password non viene ripopolata. Il messaggio generale usa `role=alert` e viene associato al form. |
| Pulsanti | `.tb-button` varianti `--secondary`, `--quiet`, `--danger`, `--small`, `--large`, `--full`; `.tb-icon-button` | `<button>` per azioni, `<a>` per navigazione; focus e stato disabled inclusi. Il CTA auth usa variante full/large. |
| Form/validazione | `.tb-form`, `.tb-form-row`, `.tb-field`, `.tb-label`, `.tb-hint`, `.tb-field__hint`, `.tb-field__error`, `.tb-input`, `.tb-select`, `.tb-textarea`, `.tb-check`, `.tb-radio` | Associare messaggio inline con `aria-describedby`; mantenere gli attributi applicativi originali. |
| Card/superfici | `.tb-card`, `.tb-card--raised/accent`, `.tb-stat-card`, `.tb-match-card` | Varianti utili a home e viste; densità e layout di POS/archive restano specifici. |
| Tabelle | `.tb-table-wrap`, `.tb-table`, `.tb-table--stack`, `__numeric`, `__team`, `.tb-rank` | Wrapper scroll accessibile; stack mobile richiede `data-label` su ogni cella. Intestazioni restano semanticamente presenti. |
| Feedback/stati | `.tb-alert` (`--success/warning/error/info`), `.tb-badge`, `.tb-state` (`--error`), `.tb-spinner` | `role=status` per aggiornamento non urgente, `role=alert` per errore urgente; descrivere lo stato con testo oltre al colore. |
| Dialog | `.tb-dialog`, `__content`, `__header`, `__title`, `__body`, `__actions` | Da usare con `<dialog>.showModal()`; mantenere trigger, focus e handler distruttivi esistenti. |
| Page header/container | `.tb-container`, `.tb-page-header`, `__eyebrow/title/description/actions`, `.tb-section-heading`, `.tb-grid`, `.tb-stack`, `.tb-cluster` | Usare larghezza public/workspace/reading per funzione, non imporre la stessa max-width a ogni modulo. |
| Breadcrumb | `.tb-breadcrumbs` e link con `[aria-current="page"]` | `<nav aria-label="Percorso">`; pagina corrente non linkata dove possibile. |
| Paginazione | `.tb-pagination`, item corrente `aria-current="page"`, item non disponibile `aria-disabled="true"` | Componenti CSS only; la logica/URL paginazione resta al template. |
| Empty/loading | `.tb-state`/`.tb-spinner` | Fornire titolo/descrizione e `aria-live` sul contenitore che cambia; non mostrare solo spinner senza testo. |

### Esempio di configurazione login

```php
$authPage = [
    'title' => 'Accesso al settore',
    'subtitle' => 'Area riservata allo staff.',
    'logo' => SITE_URL . '/assets/brand/01_loghi/png/logo_principale.png',
    'action' => ADMIN_PATH . '/login.php',
    // Opzionale: array config per shared/site_nav.php
    // 'navigation' => ['primary' => [['label' => 'Home', 'href' => SITE_URL . '/']]],
    'error' => $errore,
    'fields' => [
        ['name' => 'username', 'label' => 'Nome utente', 'type' => 'text', 'required' => true, 'autocomplete' => 'username'],
        ['name' => 'password', 'label' => 'Password', 'type' => 'password', 'required' => true, 'autocomplete' => 'current-password'],
    ],
    'links' => [['label' => 'Torna al sito', 'href' => SITE_URL . '/']],
];
require __DIR__ . '/../shared/auth_page.php';
```

Gli array sono configurazione server-side fidata; il partial esegue escaping di contenuti, attributi e URL. Non passare HTML grezzo per messaggi o link. I campi aggiuntivi si aggiungono alla lista `fields`; i campi hidden (es. CSRF) passano da `hidden`.

## File aggiornati in Phase 04

**Nuovi:** `shared/auth_page.php`, `assets/css/components/auth.css`, `docs/permissions-ui/08_DESIGN_SYSTEM.md`.  
**Tokens/componenti:** `assets/css/brand/tokens.css`, `assets/css/style.css`, `assets/css/baone-ui.css`, `assets/css/components/navigation.css`, `assets/css/public-redesign.css`, `assets/css/admin.css`, `assets/css/admin-workspace.css`, `assets/css/captain-portal.css`, `assets/css/registration-portal.css`, `pos/assets/css/pos.css`, `docs/redesign/baone-ui-showcase.html` (cache-busting).  
**Login migrati al partial condiviso:** `admin/login.php`, `telecronista/login.php`, `capitano/login.php`, `pos/login.php`, `photo/admin/login.php`. `staff/login.php` resta alias/redirect al login staff esistente. La logica di login non è stata spostata nel partial.  
**Cache-busting delle stylesheet modificate:** `index.php`, `calendario.php`, `classifica.php`, `live.php`, `iscrizione.php`, `iscrizione/giocatore.php`, `iscrizione/verifica-email.php`, `iscrizione/conferma-genitore.php`, `partita.php`, `squadre.php`, `squadra.php`, `admin/_header.php`, `staff/index.php`, `staff/users.php`, `staff/sector-users.php`, `capitano/_header.php`, `capitano/password-dimenticata.php`, `capitano/registrati.php`, `capitano/richiedi-verifica.php`, `capitano/reset-password.php`, `capitano/verifica-email.php`, `pos/operator_panel.php`, `pos/kitchen_display.php`, `pos/bar_display.php`, `pos/admin/coupons.php`, `pos/admin/orders_history.php`, `pos/admin/menu_management.php`, `pos/admin/statistics.php`, `pos/admin/users.php`.

Nel worktree erano già presenti modifiche non commesse della Phase 02 e i deliverable Phase 03; sono state preservate. I file `admin/_header.php` e `staff/index.php` già coinvolti in Phase 02 sono stati toccati in questa fase solo per versionare il CSS, senza modificare le regole di navigazione/permesso.

## Migrazione pagine esistenti

1. Individuare template/consumatori CSS, markup prodotto da JS, route e flussi prima di cambiare classi.
2. Tenere invariati business logic, auth, CSRF, `method/action`, query, input names/IDs, attributi di validazione, selettori JavaScript e URL rewrite.
3. Per una pagina completa, importare Baone UI nell’head, applicare `.tb-ui` all’area migrata e aggiungere primitive per piccoli gruppi; per componenti globalmente condivisi non aggiungere reset generici.
4. Per login, mantenere il POST handler e la relativa gestione sessione nella pagina; popolare `$authPage` dopo il handler e includere il partial. Scegliere title/logo/action/link, messaggi/status, `fields`, hidden CSRF e autocomplete pertinenti. Usare `noindex` solo dove già richiesto (archivio).
5. Mantenere le varianti funzionali: capitano usa `auth_capitano.php` separato; login staff, POS e archivio conservano i rispettivi helper, sessioni, redirect e CSRF. Il campo password non si ripopola.
6. Per tabelle scegliere scroll oppure stacking solo con fixture reali; aggiungere `data-label` solo insieme alla modalità stack. Per stati JS applicare classi anche ai template string senza cambiare ID/data hooks.
7. Bumpare il query version delle stylesheet quando si aggiornano file con URL versionato; imports tokens/componenti Baone UI hanno `v=phase04`.
8. Eseguire PHP lint, diff whitespace, smoke route/console, keyboard e viewport richieste; documentare ciò che non è stato possibile eseguire.

## Compatibilità e note

- La shell auth non imposta sessioni, non valida credenziali e non effettua redirect. I cinque handler mantengono la logica esistente; non sono stati cambiati schema/database o autorizzazioni.
- Login capitano ora rende un solo documento HTML tramite il template condiviso, evitando il precedente include del public header/footer dentro un documento autonomo. L’autenticazione capitano resta separata.
- Nav public/captain/POS/archive riusa il renderer e JS esistenti; admin usa la variante sidebar già condivisa dal suo header. La visibilità server-side segue la policy Phase 02.
- `@import` e token root richiedono i nuovi query version nei browser con CSS già cacheato. Le variabili responsive sono documentative: non vengono usate direttamente in `@media`.
- Il CSS legacy globale resta necessario per le pagine non ancora migrate. La normalizzazione è additiva e graduale; non si sono rimossi classi duplicate o stylesheet di settore.
- Il renderer login accetta solo campi input configurati, più hidden inputs; widget complessi vanno aggiunti come estensione esplicita del componente, non con HTML non escapato.
- Logo: la shell supporta logo parametrico; le viste ora usano il logo principale Baone salvo login capitano che mantiene lo scudetto compatto. La scelta di un asset ufficiale uniforme resta decisione prodotto da confermare.

## Verifiche eseguite e limiti

- `php -l` su `shared/auth_page.php` e i cinque login migrati: **PASS**.
- `git diff --check`: **PASS**, con soli avvisi Git di conversione CRLF/LF Windows (inclusi file preesistenti modificati nel worktree).
- Browser locale: homepage e cinque login raggiunti alla viewport disponibile **1000×700**; tutte le login mostrano il card condiviso, titolo configurato, form POST e pulsante da 52px; archivio mantiene campo CSRF hidden; nessun errore console osservato. Homepage: token `--tb-canvas` calcolato `#091521`, nav Barlow Condensed e `scrollWidth` 987 a 1000px.
- Non eseguiti invii di credenziali, test di autenticazione, test CRUD, né test autorizzativi: non necessari per il refactor visuale e non si sono usati account reali.
- Screenshot non disponibile (“Screenshot needs a visible tab”); non esiste emulazione viewport in questa sessione. **1440px/768px/390px/narrow mobile non sono stati testati visivamente.** Responsive behavior è stato controllato nel CSS source, non dichiarato come verifica browser.
- Non è stato aggiunto un nuovo framework, non sono state eseguite migrazioni DB e Phase 05 non è iniziata.
