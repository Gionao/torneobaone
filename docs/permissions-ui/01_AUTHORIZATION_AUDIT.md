# Audit autorizzazione — Torneo Baone (Phase 01)

**Perimetro:** audit statico del branch e dei file presenti, senza modifiche applicative o al database. Le conclusioni descrivono il codice attuale al momento dell'audit; non attestano il comportamento di un deployment diverso. I test documentati sono citati come evidenza ma non sono stati rieseguiti in questa fase.

## Sintesi esecutiva

L'autenticazione staff è stata rifattorizzata su un'identità condivisa e assegnazioni per settore. Il caricamento del principal, delle assegnazioni attive e dello stato account è centralizzato in `includes/staff_auth.php`; allowlist, gerarchie e guard sono in `includes/staff_authorization.php`. Le assegnazioni sono rilette dal database per richiesta, così variazioni di ruolo e revoche diventano effettive alla richiesta successiva.

Le tre anomalie richieste sono riproducibili dal flusso statico:

1. **Amministratore torneo → partite:** `require_partite_access()` (`includes/auth.php:46-62`) ammette solo `partite.gestore` o `partite.telecronista` (oltre al Super Admin tramite override centrale). Non contempla un'autorizzazione esplicita del ruolo `torneo.amministratore`. Quindi un amministratore torneo senza assegnazione `partite` riceve 403. La correzione richiesta deve essere una capability cross-settore esplicita, valutata lato server e senza scrivere una seconda assegnazione.
2. **Segreteria → `/admin`:** `admin/index.php` chiama `require_admin()`, che richiede `torneo.amministratore`. La segreteria è ammessa soltanto da `require_tournament_operations()` e solo nelle pagine di iscrizioni/pagamenti transazioni. Perciò l'accesso diretto alla dashboard `/admin` è negato, benché il nuovo requisito chieda una dashboard ristretta.
3. **Gestore → menu troppo ampio:** `admin/_header.php` seleziona il menu ridotto esclusivamente tramite `is_telecronista()`. Un `partite.gestore` non è telecronista, quindi riceve il menu amministrativo completo, con collegamenti a squadre, giocatori, iscrizioni, pagamenti e archivio, nonostante non abbia autorizzazione a queste aree.

La visibilità del menu non costituisce una barriera di sicurezza: le route PHP devono continuare a verificare le capability a ogni richiesta. A oggi il menu admin non è role-aware per tutti i ruoli e la dashboard include collegamenti/azioni non autorizzati per alcune identità.

## Componenti e flussi di identità

| Ambito | Entry point / file | Comportamento osservato |
|---|---|---|
| Login staff condiviso | `admin/login.php`, `telecronista/login.php`, `pos/login.php`, `photo/admin/login.php`, `staff/login.php` | Tutti delegano a `staff_login()` / tabella unificata `admin`; le route storiche fungono da ingressi compatibili. `staff/login.php` reindirizza alla form admin. |
| Principale e sessione staff | `includes/staff_auth.php` | Cookie/sessione `TBSTAFFSESSID`, validazione sessione e timeout idle/assoluto, nonce, rigenerazione ID, CSRF, verifica password e rilettura di utente/ruoli dal DB; fail closed in caso di errore. Assegnazioni caricate da `staff_assegnazioni` con join allowlist `staff_ruoli_area`. |
| Policy comune | `includes/staff_authorization.php` | Settori `torneo`, `partite`, `pos`, `archivio`; ruoli allowlist, gerarchie, Super Admin globale, controlli di accesso e delega. Ruoli/settori sconosciuti negati. |
| Adapter torneo/partite | `includes/auth.php` | Conserva nomi legacy (`require_admin`, `require_partite_access`) ma delega alla policy centrale. Define anche il confine applicativo tra torneo, operazioni segreteria, gestore e telecronista. |
| POS | `pos/includes/auth_check.php`, `pos/includes/role_check.php` | Mappa `torneo`? No: il settore POS usa `pos.amministratore` normalizzato a `admin`; role check locale e controlli di reparto per API. Un Super Admin è mappato ad admin POS. |
| Archivio | `photo/includes/media_auth.php`, `photo/admin/_bootstrap.php`, `photo/api/_bootstrap.php` | Reusa principal staff; aggiunge policy archivio, diritti sui file e ACL delle edizioni per uploader. API hanno 401/403 JSON o risposte binarie/403. |
| Capitani | `includes/auth_capitano.php`, `capitano/*` | Identità distinta su tabella `capitani`, credenziali email/password e namespace `capitano_*`; non consulta assegnazioni staff. Vedere “Isolamento capitano”. |

Il modello previsto di **una assegnazione per settore e più settori per identità** è supportato da `staff_assegnazioni`, `staff_role()` e dal selector `staff/index.php`. Il selector mostra solo i settori assegnati (o tutti al Super Admin), e manda direttamente all'area se ce n'è una sola. Le guard di settore restano però basate sull'assegnazione al singolo settore, tranne il Super Admin. La capability cross-sector per l'amministratore torneo sulle partite **non è presente**.

## Autorizzazione delle pagine torneo e partite

| Risorsa | Guard attuale | Conseguenza / rilievo |
|---|---|---|
| Dashboard `/admin` | `require_admin()` → `torneo.amministratore` | La segreteria non entra; dashboard e quick actions non sono filtrati per capability. |
| Squadre: elenco, form, dettaglio, delete | `require_admin()` | Solo amministratore torneo e Super Admin. La segreteria è esclusa oggi, ma il nuovo requisito **include Teams**: la futura policy deve quindi permetterle le funzioni squadra approvate. |
| Giocatori: elenco, form, delete | `require_admin()` | Segreteria esclusa oggi; requisito la include. |
| Iscrizioni | `require_tournament_operations()` | Amministratore torneo, segreteria e Super Admin; accesso già compatibile. |
| Pagamenti: transazioni, inserisci, modifica, elimina | `require_tournament_operations()` | Amministratore torneo, segreteria e Super Admin; accesso già compatibile. Le mutazioni hanno handler separati e devono mantenere lo stesso guard. |
| Metodi pagamento | `require_admin()` | Segreteria esclusa; configurazione non è fra le funzioni autorizzate, quindi deve restare negata. |
| Partite: index/form/risultato/eventi | `require_partite_access()` | `partite.gestore`, `partite.telecronista`, Super Admin. Amministratore torneo senza assegnazione negato. Telecronista instradato dal path admin al proxy telecronista. |
| Elimina partita | `require_partite_manager()` | `partite.gestore`, Super Admin. Telecronista correttamente escluso lato server. |
| Alias `/telecronista/partite/*` | Include le viste `admin/partite/*` | Mantiene gli stessi guard sulle viste condivise; gli adapter non sono un sistema di autorizzazione separato. |
| `telecronista/index.php` | Login staff + `is_telecronista()` | Non-telecronista viene reindirizzato ad `/admin/index.php`, che a sua volta può negarlo; destinazione non sempre autorizzata. |

Nota: il requisito di Segreteria elenca **Teams, Players, Registrations, Payments**. La policy corrente permette segreteria solo a iscrizioni e pagamenti; le risorse squadre e giocatori vanno abilitate e verificate azione per azione. La configurazione metodi pagamento e funzioni amministrative generali devono restare escluse salvo diversa decisione.

## Altri settori e ruoli

| Settore / ruolo | Comportamento server-side osservato | Incoerenze / punti da verificare nella correzione |
|---|---|---|
| `torneo.amministratore` | Pagine torneo protette; può gestire utenti subordinati `segreteria`; non può nominare amministratori di settore. | Partite negate senza assignment; il requisito chiede permission cross-sector esplicita. La dashboard espone link ad archivio/partite senza valutazione capability. |
| `torneo.segreteria` | Guard specifica per iscrizioni e flussi pagamento; non può gestire assegnazioni utenti. | `/admin`, squadre, giocatori e dashboard negati; deve avere dashboard limitata e le risorse indicate (Teams/Players/Registrations/Payments), non altre funzioni. |
| `partite.gestore` | Gestione partite; può eliminare partite; può gestire assegnazioni telecronista, non amministratore. | Menu generale admin sovraesposto. Requisito UI esplicito: solo Partite, Profilo, Logout. Non risulta una pagina profilo staff dedicata: esiste cambio password, non un profilo completo. |
| `partite.telecronista` | Può accedere alle route match non distruttive; delete protetto dal guard gestore. Redirect verso area dedicata; menu Partite + Esci. | La top action resta etichettata “Esci”, il requisito per gestore chiede “Logout”; allineare i label nel successivo intervento mantenendo l'italiano. |
| `pos.amministratore` | Admin POS, gestione POS e utenti subordinati; non gestisce identità/global Super Admin. | Navbar POS è role-aware e include Operatore, Cucina, Bar, Menu, Coupon, Utenti, Statistiche, Storico. La matrice server deve restare più autorevole. |
| `pos.operatore` | Operazioni cassa, ordini e storico; API limitate e scope stazione applicato in varie operazioni. | Navbar mostra Operatore/Storico. Verificare endpoint riga per riga e il perimetro station, non deducibile solo dal menu. |
| `pos.bar`, `pos.cucina` | Display e operazioni di reparto; API forzano/controllano department; niente amministrazione POS. | Navbar mostra rispettivamente Bar o Cucina. Controllare le transizioni consentite in ogni endpoint status/reopen. |
| `archivio.amministratore` | Accesso completo archivio; gestione utenti subordinati, struttura, impostazioni e cestino. | UI archivio mantiene vecchia nomenclatura interna `admin`; normalizzazione dal ruolo di settore è intenzionale ma duplicata. Il link “Pannello admin” porta a `/admin`, potenzialmente non autorizzato se archivio-only. |
| `archivio.editor` | Struttura edizioni/giornate/album e ZIP; accesso file ampio; esclusa amministrazione utenti e impostazioni. | Header mostra Edizioni, ma non necessariamente ogni pagina secondaria; pagine protette singolarmente. |
| `archivio.uploader` | Upload assegnato e accesso alle edizioni ACL; download/cancellazione limitati ai propri file secondo helper. | Il menu mostra Carica; ACL edizione è distinta dall'assegnazione generale e va preservata. |
| Super Admin | Override globale nel policy helper, accesso a tutte le aree; gestione globale staff; protezione DB dell'ultimo attivo. | Alcune destinazioni/route legacy continuano a fare verifiche locali; i flussi completi browser non risultano coperti. |

## Endpoint, controlli backend e rischi di allineamento

### POS

Le pagine admin POS (`users.php`, `statistics.php`, `settings.php`, `coupons.php`, `menu_management.php`, `orders_history.php`) e le pagine operative (`operator_panel.php`, `bar_display.php`, `kitchen_display.php`) invocano `require_role()` con allowlist locali. Gli endpoint JSON di `pos/api/` eccetto `track_order.php` includono `auth_check.php` e role check. `auth_check.php` emette 401 per API non autenticate, 403 per CSRF/password change/ruolo mancante. `track_order.php` è esplicitamente pubblico per design e cerca ordine con numero giornaliero+data; restituire informazioni ordine a chi conosce/indovina tali valori è un confine di prodotto da rivalutare, non un endpoint staff.

API staff POS osservate: `create_order`, `cancel_order`, `get_products`, `get_active_orders`, `get_completed_orders`, `update_order_status`, `reopen_order`, `validate_coupon`. I controlli di ruolo sono presenti; `update_order_status` applica scope reparto per bar/cucina. `create_order` ricava la station assegnata. I controlli devono essere verificati in browser e con casi IDOR, non solo nei test helper.

### Archivio multimediale

Le pagine `photo/admin/*` caricano il bootstrap e applicano guard locali `require_media_login/editor/admin` o controlli specifici. Le API `photo/api/{zip_status,zip_download,zip_create,upload_status,thumb,download}.php` e i gestori multipart `photo/upload/{init,chunk,complete,abort}.php` riusano l'autenticazione archivio, CSRF per mutazioni, controlli di ruolo/ownership e dove applicabile ACL edizione. `download.php` e `thumb.php` applicano ACL e diritti sul file. ZIP limita editor/admin e proprietario del job. Gli strumenti `photo/tools/{maintenance,process_zip}.php` sono operativi e non equivalgono a route utente: i loro confini di invocazione/deployment vanno verificati.

### Altre route

`tools/test-mail.php` usa `require_admin()`: è quindi legato al settore torneo e va considerato risorsa operativa non presente in menu. Non è stata trovata una API di business torneo distinta dalle azioni POST delle pagine admin.

Le pagine CRUD admin espongono endpoint diretti per scrittura e chiamano il guard nel file endpoint stesso. I form di eliminazione squadra/giocatore/partita/payment hanno CSRF nei POST; i documenti della refactor segnalano legacy delete via GET quale debito, ma il codice corrente dei delete principali osservati usa POST. Verificare gli endpoint reali su host configurato, inclusi URL rewrite.

## HTTP 403, redirect e destinazioni

- `require_sector_access()` (`includes/staff_authorization.php`) assegna status 403 e termina con testo italiano `Accesso non autorizzato.`; non esiste un layout 403 condiviso per gli endpoint admin/staff.
- Le pagine POS distinguono redirect HTML verso login/selector e 401/403 JSON. Archivio HTML usa redirect login e template `photo/admin/_403.php`; API restituiscono JSON, mentre download/thumb possono emettere body testuale/vuoto con status HTTP.
- L'assenza di sessione in `require_admin` e `require_tournament_operations` porta al login admin, mentre guard match porta al login telecronista; `staff_require_authenticated()` può invece usare il login `/staff`. Gli ingressi quindi non condividono un unico ritorno autorizzato.
- Non risulta un return URL generico staff accettato dalla login centrale; il redirect tra aree è principalmente il selector `staff/index.php`. Questo riduce l'open redirect, ma alcuni redirect dopo login sono fissi e possono condurre a una route non consentita (es. telecronista non più riconosciuto → admin index).
- Login archivio limita `ritorna` al path `/photo/admin/...`; `media_redirect_interno()` ancora il redirect all'origine archivio. La policy di return URL va mantenuta allowlisted e verificata dopo modifiche.
- I 403 grezzi non indicano una destinazione alternativa/area disponibile. Valutare UI italiana uniforme e link al selector solo se la destinazione è autorizzata, senza trasformare il 403 in un bypass.

## Cambio area, ruoli e assegnazioni multiple

`staff/index.php` carica il principal DB-current e costruisce card delle aree assegnate; per una sola area reindirizza automaticamente. Non c'è switcher globale persistente nell'header admin: si cambia area tornando al selector /staff, oppure entrando da un URL diretto. La lista di settori accessibili è corretta per assegnazioni multiple, ma i menu interni non aggregano capability in modo coerente: admin header decide quasi solo “telecronista sì/no”; archivio/POS hanno menu locali per i rispettivi ruoli. L'amministratore torneo con accesso esplicito cross-sector alle partite, quando sarà implementato, dovrà vedere Partite pur senza una assignment `partite`; l'attuale selector non dovrebbe creare o simulare un'assegnazione per farlo.

## Isolamento capitano

Confermato staticamente: `includes/auth_capitano.php` autentica account dalla tabella `capitani` e usa chiavi `capitano_*`; lo staff usa il cookie/session name `TBSTAFFSESSID` e chiavi `staff_*`. `staff_logout()` rimuove soltanto chiavi `staff_*` e conserva gli altri namespace; il login capitano usa la propria autenticazione e il logout capitano distrugge la sessione PHP capitano. Il sistema capitano non viene chiamato dalla policy staff e la refactor documenta l'isolamento. Attenzione operativa: il logout capitano azzera l'intero `$_SESSION` della sessione corrente, non selettivamente le chiavi `capitano_*`; essendo il cookie/session ID staff distinto, non dovrebbe invalidare il cookie staff, ma il comportamento con cookie/container di sessione condivisi va testato end-to-end. Nessuna modifica richiesta o eseguita.

## Architettura attuale: centralizzata ma non uniforme

**Centralizzato:** principal e sessione staff, ricarica DB, allowlist settore/ruolo, gerarchia/deleghe, Super Admin, CSRF helper.

**Duplicato/specializzato:** adapter `includes/auth.php`; ruolo locale POS e `require_role`; helper archivio con alias `amministratore` → `admin`, diritti file e ACL edizione; condizioni di navigazione in quattro layout; guard specifici in pagine/endpoint e redirect legacy. La policy per risorsa è quindi distribuita: un helper centrale governa accesso al settore, ma non esiste una tabella centrale di capability per tutte le azioni/page IDs e la UI duplica parte della policy.

Le verifiche documentate in `docs/auth-refactor/07_FINAL_VALIDATION.md` riportano test helper e smoke limitati, ma dichiarano non eseguiti regressione completa pagine/API, browser matrix per tutti i ruoli, prove end-to-end archivio/edizioni e alcune prove sul DB target. L'audit non considera tali verifiche mancanti come passate.

## Correzioni raccomandate per fase successiva (non implementate)

1. Aggiungere una capability esplicita cross-sector `torneo.amministratore → gestione partite` nel policy layer; non creare assignment `partite` e non ampliare implicitamente la permission per ruoli torneo diversi.
2. Autorizzare la dashboard `/admin` alla segreteria con dashboard dati/azioni ristrette e filtrare server-side ciascun link/azione. Includere esattamente Teams, Players, Registrations, Payments secondo requisiti; mantenere esclusa configurazione pagamenti, gestione staff e funzioni non correlate.
3. Definire menu gestore solo Partite, Profilo, Logout; chiarire destinazione/capability “Profilo” (oggi si trova cambio password, non una pagina profilo). Rendere menu per tutti derivato da capability, senza considerarlo enforcement.
4. Inventariare tutte le page/action/API in un registro capability comune, quindi confrontare route guard, link, dashboard quick actions e menu per ogni ruolo/assignment multipla.
5. Standardizzare 401/403 HTML/JSON e redirect verso destinazioni ammesse, preservando return URL allowlist e messaggi italiani.
6. Testare request dirette GET/POST/API per ogni ruolo, cross-sector permission, revoca/role change alla richiesta successiva, account multi-area, archivi per edizione, tenant/stazione POS e capitano isolato.

**Modifiche in questa fase:** solo documentazione; nessun file applicativo, schema o database modificato.
