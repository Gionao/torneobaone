# Audit navigazione e UI permessi — Phase 01

Audit statico dei componenti di navigazione, delle voci e delle condizioni di visibilità. Il menu è un'indicazione UX soltanto: l'accesso effettivo deve restare deciso dai guard PHP/API.

## Componenti di navigazione individuati

| Componente | File | Riutilizzo / condizioni attuali |
|---|---|---|
| Renderer comune desktop/mobile | `shared/site_nav.php` + `assets/js/site-nav.js` | Renderizza config `primary` e `actions` fornita dal chiamante; duplica primary/actions nel pannello mobile. Non valuta permessi. |
| Header pubblico | `shared/header.php` | Crea navigazione pubblica e usa renderer comune. Statico, non role-based. |
| Header admin/telecronista | `admin/_header.php` | Crea manualmente `$siteNavConfig`; scelta binaria `is_telecronista()`, menu admin altrimenti. URL active matching locale. |
| Header POS | `pos/includes/navbar.php` | Configura una lista per `pos_current_role()`; renderer comune; menu per ruolo POS. Aggiunge script fetch CSRF, non è auth. |
| Header archivio | `photo/admin/_header.php` | Lista costruita con `media_ruolo()`, `media_is_editor()`, `media_is_admin()`; renderer comune. |
| Header capitano | `capitano/_header.php` | Config pubblica statica del portale; renderer comune; non verifica ruolo (le pagine fanno auth). |
| Selector staff (non header/menu) | `staff/index.php` | Card per assegnazioni attive o tutte le aree per Super Admin; solo Super Admin vede Gestione staff; link Esci. |
| Pagine di login / wrapper | `admin/login.php`, `telecronista/login.php`, `pos/login.php`, `photo/admin/login.php`, `staff/login.php` | Non sono menu autenticati; conservano ingressi multipli allo stesso account staff. |

La navigazione desktop/mobile è condivisa come **renderer**, non come **policy**. Le liste/condizioni sono duplicate nei singoli layout e alcune pagine costruiscono link/azioni autonomamente.

## Inventario voci e visibilità attuale / attesa

### Area admin e partite — `admin/_header.php`

Condizione attuale: `is_telecronista()` determina il ramo. Se vero, primaria solo Partite; altrimenti vengono mostrate tutte le voci admin senza controllo di capability. Le action di logout sono sempre inserite.

| Voce (etichette correnti) | Destinazione | Visibilità attuale | Visibilità attesa |
|---|---|---|---|
| Dashboard | `/admin/` | Tutti tranne telecronista, inclusi gestore/ruoli potenzialmente non admin | Amministratore torneo, Segreteria (dashboard ristretta), Super Admin; non gestore/telecronista |
| Partite | `/admin/partite/` oppure telecronista `/telecronista/partite/` | Tutti i non-telecronisti, telecronista sì nel ramo dedicato | Gestore, telecronista, Super Admin e amministratore torneo per capability cross-sector esplicita |
| Squadre | `/admin/squadre/` | Tutti i non-telecronisti | Amministratore torneo, Segreteria, Super Admin; gli altri solo se permission assegnata |
| Giocatori | `/admin/giocatori/` | Tutti i non-telecronisti | Amministratore torneo, Segreteria, Super Admin |
| Iscrizioni | `/admin/iscrizioni/` | Tutti i non-telecronisti | Amministratore torneo, Segreteria, Super Admin |
| Pagamenti | `/admin/pagamenti/transazioni` | Tutti i non-telecronisti | Amministratore torneo, Segreteria per funzioni consentite, Super Admin; configurazione metodi nascosta alla segreteria |
| Archivio media | `ARCHIVIO_URL/` | Tutti i non-telecronisti | Solo archivio assegnato/Super Admin; eventuali cross-link espliciti solo se autorizzati |
| Esci (header topbar e action config) | `/admin/logout` | Tutti | Tutti gli staff autenticati dell'area; label richiesto per gestore “Logout” (oggi “Esci”). |
| Brand Admin/Telecronista | `/admin/` o `/telecronista/` | Tutti in base al solo test telecronista | Destinazione ammessa per principal corrente; evitare che gestore con solo settore partite venga mandato a dashboard negata. |

**Gestore richiesto:** menu deve contenere esclusivamente **Partite**, **Profilo**, **Logout**. Attuale: vede Dashboard, Partite, Squadre, Giocatori, Iscrizioni, Pagamenti, Archivio media, Esci. L'ampiezza deriva dal ramo `!is_telecronista()`, non da permission. **Profilo non è una voce/route attualmente individuata**; `/staff/change-password.php` esiste, ma non equivale necessariamente a un profilo.

**Segreteria richiesta:** non ha un menu dedicato oggi: `/admin` è negato prima del rendering. Se ammessa alla dashboard, non riusare la lista admin completa: la dashboard/menu deve mostrare esclusivamente Teams, Players, Registrations, Payments approvati, Profilo (se previsto) e Logout. `Metodi pagamento`, `Partite`, `Archivio media`, gestione staff e altre funzioni non devono comparire.

### POS — `pos/includes/navbar.php`

| Ruolo POS | Voci primarie attuali | Condizione | Atteso |
|---|---|---|---|
| `admin` (anche Super Admin normalizzato) | Operatore, Cucina, Bar, Menu, Coupon, Utenti, Statistiche, Storico | Mappa `pos_current_role()` | Solo voci effettivamente autorizzate alle route POS. Nessun link deve implicare gestione globale staff. |
| `operatore` | Operatore, Storico | Uguale | Coerente con guard e scope station. |
| `bar` | Bar | Uguale | Solo reparto autorizzato. |
| `cucina` | Cucina | Uguale | Solo reparto autorizzato. |
| Tutti | Esci | Action comune | Logout staff condiviso; account multi-settore deve poter rientrare nel selector senza confusione. |

Le destinazioni menu POS non sono una lista centralizzata con `require_role`: devono rimanere coerenti con i guard pagina/API separati.

### Archivio — `photo/admin/_header.php`

| Voce | Condizione attuale | Visibilità attesa |
|---|---|---|
| Panoramica, Galleria | Qualsiasi ruolo archivio autenticato | Tutti i ruoli archivio autorizzati; panoramica solo se la pagina non espone funzioni oltre ruolo. |
| Carica | `uploader` o `admin` | Uploader e archivio amministratore, rispettando ACL album/edizione. |
| Edizioni | `media_is_editor()` (editor/admin) | Editor e archivio amministratore. |
| Cestino, Utenti, Impostazioni | `media_is_admin()` | Solo amministratore archivio / Super Admin normalizzato; mai amministratore torneo per implicazione del nome. |
| Pannello admin | `media_is_admin()` | Attualmente link verso `/admin/`; visibile anche a archivio admin che potrebbe non avere `torneo`, quindi link può finire in 403. Atteso: rimuovere o puntare a un'area autorizzata (es. selector staff). |
| Esci | Tutti | Logout condiviso, corretto per account multi-settore ma UI deve rendere chiaro che chiude la sessione staff. |

### Capitano — `capitano/_header.php`

| Voce | Condizione attuale | Atteso |
|---|---|---|
| Dashboard | Statica in header protetto | Solo sessione capitano valida (guard sulla pagina). |
| Giocatori | Statica | Solo capitano della squadra associata. |
| Pagamenti | Statica | Solo capitano della squadra associata. |
| Esci | Statica | Logout capitano indipendente. |

Queste voci non vanno miscelate nella policy staff; header non esegue authorization e i controlli stanno nelle pagine.

### Pubblico — `shared/header.php`

| Voce | Condizione attuale | Atteso |
|---|---|---|
| Home, Calendario, Classifica, Squadre | Sempre | Pubbliche. |
| Iscrizione, Login | Sempre | Iscrizione pubblica e login capitano; non sono funzioni staff. |

Le pagine pubbliche di calendario applicano una presentazione specifica per telecronista, ma l'header pubblico non cambia in base a sessione staff.

### Selector staff — `staff/index.php`

| Voce | Condizione attuale | Atteso |
|---|---|---|
| Amministrazione torneo, Gestione partite, Punto vendita, Archivio multimediale | Se assegnazione attiva presente; tutte per Super Admin | In generale corretto per assignments. Eccezione: l'amministratore torneo deve poter entrare nelle Partite tramite capability cross-sector senza creare assignment; modellare/linkare esplicitamente tale accesso invece di alterare gli assignment. |
| Gestione staff | Solo `is_super_admin` | Corretto. |
| Esci | Tutti gli staff autenticati | Corretto. |

Per account multi-settore il selector rappresenta assignment, non le capability trasversali: dovrà rendere raggiungibile l'area Partite all'amministratore torneo autorizzato, senza far apparire una falsa assegnazione in tabella.

## Navigazione duplicata e link fuori menu

- Le singole voci sono configurate separatamente in `admin/_header.php`, `pos/includes/navbar.php`, `photo/admin/_header.php`, `capitano/_header.php` e `shared/header.php`; condividono soltanto il renderer `shared/site_nav.php`.
- La dashboard `admin/index.php` ripete azioni rapide indipendenti dal menu: Gestisci squadre, giocatori, partite, Nuova Partita, Nuova Squadra, Nuovo Giocatore, Registra Pagamento, Archivio media, Classifica e Calendario. Nessuna action è filtrata per ruolo. Una futura dashboard segreteria non deve riusare senza filtro questo blocco.
- Elenchi interni ripetono azioni: form/elimina in squadre, giocatori, partite, transazioni pagamenti. Alcune azioni sono condizionate (es. delete partita nascosto a telecronista), ma il controllo backend è separato e necessario.
- La gestione staff è raggiunta da `staff/index.php` / route dirette e non compare nel menu admin; le pagine `staff/sector-users.php` consentono ai gestori di settore gestione subordinati ma non si vede un link corrispondente nella shell admin.
- Per archivio alcune route secondarie sono navigabili da link contestuali (cestino/edizioni/utenti/impostazioni); non tutte le destinazioni hanno una voce primaria. Non è automaticamente un problema se accesso diretto ha guard coerente.
- `admin/pagamenti/metodi.php` è protetta ma non ha una voce dedicata nel menu: la raggiungibilità da altre pagine non è stata individuata. È una pagina autorizzata per admin ma potenzialmente mancante dalla navigazione.
- `admin/partite/risultato.php` e `eventi.php` sono azioni contestuali dall'elenco partite e non voci di menu, comportamento appropriato se i link contestuali sono visibili solo ai ruoli corrispondenti.
- `staff/users.php` è un pannello globale Super Admin e non appare nel menu admin; selector staff espone il collegamento.
- Link da archivio “Pannello admin” e dal brand admin possono condurre a route con permessi di un altro settore non assegnati; le destinazioni non devono essere presunte accessibili solo perché il ruolo di origine è amministrativo.

## Matrice sintetica menu atteso

| Identità/capability | Navigazione applicativa attesa |
|---|---|
| Super Admin | Settori e funzioni globali secondo override; includere gestione staff. |
| `torneo.amministratore` | Dashboard, Squadre, Giocatori, Iscrizioni, Pagamenti, Partite (capability cross-sector); non mostrare POS/Archivio se non assegnati. |
| `torneo.segreteria` | Dashboard ristretta, Teams, Players, Registrations, Payments consentiti, Profilo se implementato, Logout; nessuna funzione estranea. |
| `partite.gestore` | Solo Partite, Profilo, Logout. |
| `partite.telecronista` | Partite e Logout; mantenere funzioni limitate. |
| POS roles | Voci per ruolo e reparto/stazione secondo guard POS. |
| Archivio roles | Voci per ruolo archivio e ACL edizioni; niente collegamenti che presuppongano settore torneo. |
| Multi-settore | Selector aree assegnate più capability cross-sector esplicite; menu locale riflette area/capability corrente; mai creare assignment per comodità UI. |
| Capitano | Header capitano isolato; nessun menu staff. |
| Pubblico | Navigazione pubblica invariata e accessibile. |

## Architettura riusabile raccomandata (non implementata)

1. Mantenere `shared/site_nav.php` come renderer senza logica autorizzativa.
2. Definire un catalogo server-side di **capability** (risorsa/azione → capability) e un resolver unico per principal, ruolo, settore, Super Admin e autorizzazioni cross-sector esplicite. I guard backend chiamano tale resolver.
3. Definire configurazioni di navigazione (voce, label italiano, route, capability, area, ordinamento) in un catalogo condiviso; il builder filtra ogni link con lo stesso resolver. Non duplicare gli allowlist in template.
4. Trattare la visibilità dashboard quick action, bottoni, link contestuali e navigation come proiezioni UX del catalogo; l'handler endpoint ripete sempre controllo capability.
5. Separare label e destinazioni legacy per POS/archivio/telecronista senza usare stringhe alias (`admin`, `amministratore`) come policy implicita.
6. Usare una capability distinta per “profilo” e chiarire se la destinazione è cambio password o pagina profilo; non inventare un link verso route inesistente.
7. Testare snapshot menu per ogni ruolo singolo, multi-assegnazione, Super Admin, assignment disattiva e permission cross-sector; affiancare test HTTP diretto che dimostri che link nascosti non sostituiscono i guard.

**Esito:** renderer condiviso presente, ma generazione menu e condizioni ruolo sono duplicate e incomplete. Admin header è il disallineamento più evidente; POS e archivio sono più granulari; pubblico/capitano sono statici. Nessuna modifica UI o applicativa è stata effettuata in Phase 01.
