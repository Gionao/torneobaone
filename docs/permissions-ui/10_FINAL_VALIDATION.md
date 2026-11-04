# Phase 06 — validazione finale autorizzazioni e UI/UX

**Data:** 2026-11-04  
**Branch:** `feature/unified-staff-auth` (nessun merge o push)  
**Ambiente:** locale `torneobaone.test`; MySQL locale; browser desktop a 1000×700.  
**Esito:** validazione parziale, non sign-off di rilascio. I test auth d’integrazione e gli smoke test pubblici eseguiti sono passati; la matrice per ruolo, gli URL autenticati, i flussi transazionali e i viewport richiesti non erano verificabili in modo sicuro nell’ambiente disponibile.

## Regole di sicurezza e metodo

- Letti i documenti Phase 01–05 (`01_AUTHORIZATION_AUDIT.md`–`09_UI_IMPLEMENTATION_REPORT.md`).
- Nessun database di produzione o configurazione applicativa predefinita è stato usato dal test auth. È stato eseguito `tests/auth/staff_auth_test.php` soltanto sul database già allowlisted e documentato come disposable `tb_auth_migration_test`, con variabili test impostate per il solo processo CLI.
- La matrice Phase 06 usa `tb_auth_p07_clean_20261104` o `tb_auth_p07_legacy_20261104`; entrambi risultano assenti da MySQL locale. Non sono stati creati database o fixture e il test non è stato eseguito.
- Non sono stati inviati ordini o pagamenti, caricati/eliminati media, modificati account/password reali, né effettuati POST a endpoint applicativi.
- **PASS** significa eseguito e superato; **FAIL** significa eseguito con risultato negativo; **NOT RUN** significa non eseguito o non conclusivo. Nessuna assenza di errore statico è trattata come prova runtime.

## Matrice autorizzazioni

| Ruolo / scenario | Esito | Evidenza o motivo |
|---|---|---|
| Super Admin | NOT RUN | L’override globale viene verificato dalla matrice, ma il DB Phase 07 e l’account fixture non sono disponibili. |
| Tournament Administrator | NOT RUN | Nessuna sessione di ruolo; test capability/navigation/routes della matrice non eseguito. |
| Tournament Secretary | NOT RUN | Nessuna sessione di ruolo; dashboard e accesso `/admin` non provati con fixture. |
| Match Manager | NOT RUN | Nessuna sessione di ruolo; menu ridotto e guard URL non provati via HTTP. |
| Telecronista | NOT RUN | Nessuna sessione di ruolo; route e menu privati non provati. |
| POS Administrator | NOT RUN | Nessuna sessione di ruolo; policy POS non testata via browser/HTTP. |
| POS Operator | NOT RUN | Nessuna sessione di ruolo; azioni operative non eseguite. |
| POS Bar | NOT RUN | Nessuna sessione di ruolo; vista stazione non verificata con sessione. |
| POS Kitchen | NOT RUN | Nessuna sessione di ruolo; vista stazione non verificata con sessione. |
| Archive Administrator | NOT RUN | Nessuna sessione di ruolo; privilegi archivio non provati via route. |
| Archive Editor | NOT RUN | Nessuna sessione di ruolo; privilegi archivio non provati via route. |
| Archive Uploader | NOT RUN | Nessuna sessione di ruolo; ACL edizioni non verificabile: schema media assente dal DB disposable. |
| Multi-sector account | PASS (integrazione limitata) | `staff_auth_test.php`: accessi torneo/POS/archivio con identità condivisa, revoca settore che conserva gli altri, e revoca globale di account inattivo. Non sostituisce smoke HTTP multi-settore. |
| Account staff inattivo | PASS (integrazione limitata) | Il test verifica rifiuto del login e revoca della sessione dopo disattivazione. |
| Account senza assegnazioni | NOT RUN | Coperto solo dalla matrice Phase 07 non eseguibile. |
| Capitani / separazione sessioni | PASS (integrazione limitata) | Namespace e cookie capitano distinti e scadenza staff senza cancellare lo stato capitano verificati dal test auth; workflow capitano completo non provato. |
| Amministratore torneo: gestione match | NOT RUN | Il test dedicato controlla capability e accesso alle route senza assegnazione `partite`, ma non è stato eseguito. |
| Segreteria: accesso `/admin` e solo funzioni permesse | NOT RUN | Capabilities, navigation e route guards Secretary sono nel test matrice assente. |
| Menu Match Manager ridotto | NOT RUN | Aspettativa testata dal file matrice non eseguito; nessuna sessione browser di ruolo. |
| 403 URL non autorizzati / API non autorizzate | NOT RUN | Richiede fixture e richieste autenticate per ciascun ruolo. Non sono state dedotte da menu né da redirect anonimi. |
| Vincoli cross-sector, elevazione privilegi, assegnazioni multi-settore | PASS (integrazione limitata) | Il test staff esegue allowlist dei ruoli settore, confini di delega POS, assegnazione/revoca, ricerca delimitata e revoca immediata. I casi completi di tutte le coppie ruolo/settore restano NOT RUN. |

**Test eseguito:** `tests/auth/staff_auth_test.php` — **50 superati, 0 falliti**. Verifica login/hash legacy, rigenerazione sessione, ruoli allowlist, multi-settore, revoche immediate, disattivazione, delega, password temporanea, CSRF non valido/valido, timeout, separazione capitano, origine e percorso di ritorno. **SKIP esplicito:** ACL edizioni archivio perché lo schema media non è installato nel DB disposable.

## Matrice navigazione

| Area | Esito | Evidenza / limite |
|---|---|---|
| Nav pubblica desktop e link Home/Calendario/Classifica/Squadre/Iscrizione | PASS | Browser a 1000×700; destinazioni pubbliche principali caricate; link coerenti nei template controllati. |
| Stati attivi nav pubblica | NOT RUN | Non è stata completata un’ispezione visuale/DOM comparativa degli stati attivi. |
| Staff, telecronista, POS, archivio autenticati | NOT RUN | Mancano fixture e credenziali di ruolo. |
| Menu segregati per ruolo e assenza di link non autorizzati | NOT RUN | Non deducibile da pagine anonime; matrice non eseguibile. |
| Link rotti in ogni settore / switch settore / logout | NOT RUN | Non eseguiti con sessioni e permessi per ruolo. |
| Menu mobile e responsive | NOT RUN | Nessuna emulazione di viewport o interazione mobile disponibile. |

## Login

| Verifica | Esito | Evidenza / limite |
|---|---|---|
| Pagina login staff (`/admin/login`) carica il form condiviso | PASS | DOM contiene `form` con password e `.tb-auth-card`; zero errori console. |
| Login capitano (`/capitano/login`) con template condiviso | PASS (rendering) | DOM del form condiviso presente; autenticazione non inviata. |
| Login POS (`/pos/login`) con template condiviso | PASS (rendering) | Redirect canonico caricato, form condiviso e navbar presenti; credenziali non inviate. |
| Login archivio (`/photo/admin/login.php`) con template condiviso | PASS (rendering) | DOM del form condiviso presente; autenticazione non inviata. |
| Login telecronista e alias legacy | NOT RUN | Non ispezionati nel browser in questa fase. |
| Validazione credenziali, messaggi, redirect post-login e logout browser | NOT RUN | Richiedono credenziali/account e interazioni auth non eseguite. |
| Gestione password temporanea/sessione/CSRF | PASS (unità/integration test) | Coperti dal test staff: cambio obbligatorio, token errato respinto, token valido accettato; non è un test HTTP end-to-end. |

## Regressione visiva e responsive

| Route / viewport | Esito | Risultato |
|---|---|---|
| Home, squadre, calendario, classifica, iscrizioni, live — 1000×700 | PASS (smoke DOM) | Tutte caricate; zero errori console; larghezza documento osservata 985–1000px. |
| Login staff, capitano, POS, archivio — 1000×700 | PASS (smoke DOM) | Form e componente condiviso presenti; zero errori console. |
| 1440px desktop | NOT RUN | Browser senza emulazione/resizing disponibile. |
| 768px tablet | NOT RUN | Browser senza emulazione/resizing disponibile. |
| 390px mobile | NOT RUN | Browser senza emulazione/resizing disponibile. |
| Narrow mobile | NOT RUN | Browser senza emulazione/resizing disponibile. |
| Screenshot / confronto pixel | NOT RUN | Errore browser: `Screenshot needs a visible tab` anche dopo focus; non è stata prodotta evidenza screenshot. |
| Navigazione mobile / focus dialog | NOT RUN | Nessuna emulazione o verifica con viewport e dialog reali. |

Le misure DOM a 1000×700 non provano assenza di overflow a 390/768/1440 e non sono un confronto screenshot. La Phase 05 aveva già segnalato il disallineamento copy/brand 2026 rispetto alla configurazione 2027; resta aperto e non è stato cambiato.

## Regressione funzionale

| Flusso | Esito | Motivo |
|---|---|---|
| Admin: squadre, giocatori, iscrizioni, pagamenti e staff management | NOT RUN | Nessun account/fixture; le modifiche DB non sarebbero sicure. |
| Match: creazione/modifica, calendario, eventi e live | NOT RUN | Nessuna sessione fixture; nessun dato di gara modificato. |
| POS: ordine, carrello, checkout, stazioni bar/cucina | NOT RUN | Non è stato creato o mutato alcun ordine. |
| Archivio: upload, resume, delete, download, metadati, edizioni/utenti | NOT RUN | Nessun file caricato/eliminato/scaricato; schema media assente nel DB test auth. |
| Capitano: squadra, giocatori, iscrizioni e consensi | NOT RUN | Nessuna sessione e nessun invio/modifica dati. |
| Smoke mailer / PHPMailer presente | PASS | `php tests/email_system_smoke.php` — `email system smoke test passed`; nessuna email spedita. |

## Sicurezza

| Controllo | Esito | Evidenza / limite |
|---|---|---|
| Nessun accesso staff senza autenticazione (servizio auth) | PASS | Verificato nel test d’integrazione staff. |
| Hash legacy e rifiuto password errata/account inattivo | PASS | Verificato nel test d’integrazione staff. |
| CSRF cambio password: token invalido rifiutato | PASS | Verificato nel test d’integrazione; non endpoint HTTP. |
| Origine login e percorso di ritorno esterno | PASS | Origine esterna e ritorno esterno rifiutati dal test. |
| SQL injection-shaped staff search | NOT RUN | Il caso letterale è incluso nella matrice Phase 07 non eseguita; la ricerca delimitata è stata controllata nel test staff, non equivale a pentest. |
| Bypass auth, escalation e richieste dirette cross-sector | NOT RUN | Richiedono richieste autenticate e fixture per ruolo. |
| API non autorizzate / CSRF endpoint ordine/media | NOT RUN | Nessun test HTTP API né POST applicativo. |
| Staleness permessi tra richieste/sessioni | PASS (servizio) | Test staff verifica revoca da altra sessione e disattivazione globale; cross-process/deployment non verificati. |
| Session fixation / cookie production / TLS e host production | NOT RUN | Nessuna verifica di deployment o sessioni reali. |

Nessun finding di sicurezza nuovo è stato dimostrato. I test eseguiti non costituiscono audit penetration né attestazione della configurazione produzione.

## Qualità frontend e controlli repository

| Controllo | Esito | Evidenza |
|---|---|---|
| PHP lint | PASS | `php -l` su 55 file PHP modificati/esistenti nel worktree. |
| `git diff --check` | PASS | Nessun errore whitespace; Git emette warning di conversione LF→CRLF su Windows. |
| CSS parsing Phase 05 | PASS (evidenza Phase 05) | Report Phase 05 documenta 6 stylesheet caricati e analizzati via `CSSStyleSheet.replaceSync`, senza errori. Nessuna modifica CSS Phase 06. |
| Errori console smoke browser | PASS | Zero errori sulle dieci route pubbliche/login aperte. |
| Screenshot, CSS duplication/conflicts, assets mancanti e accessibilità completa | NOT RUN | Nessuna analisi globale automatica/visuale o test assistivo completo. |
| Test frontend package | NOT RUN | Non individuato uno script frontend per il progetto; non è stato eseguito un linter CSS dedicato. |

## File e stato Git

Phase 06 aggiunge questo file: `docs/permissions-ui/10_FINAL_VALIDATION.md`. In questa fase non sono state applicate modifiche applicative o CSS: i test auth e lint non hanno alterato il codice.

Il worktree conteneva già modifiche Phase 02–05 non committate. Sono state preservate; non è stato eseguito reset, checkout, commit, merge o push. Elenco rilevato (`git status`):

- **Auth/navigation:** `includes/auth.php`, `includes/staff_authorization.php`, `includes/staff_navigation.php`, `shared/auth_page.php`, `staff/profile.php`, `staff/index.php`, `staff/change-password.php`, `staff/sector-users.php`, `staff/users.php`, `admin/index.php`, `admin/_header.php`, `admin/login.php`, `telecronista/index.php`, `telecronista/login.php`, `pos/includes/auth_check.php`, `pos/includes/navbar.php`, `photo/includes/media_auth.php`.
- **Admin pages:** `admin/giocatori/{delete,form,index}.php`, `admin/squadre/{delete,dettaglio,form,index}.php`.
- **Login/portali:** `capitano/_header.php`, `capitano/{login,password-dimenticata,registrati,reset-password,richiedi-verifica,verifica-email}.php`, `pos/login.php`, `photo/admin/{_header,login}.php`.
- **Sito pubblico/registrazioni:** `index.php`, `calendario.php`, `classifica.php`, `live.php`, `partita.php`, `squadra.php`, `squadre.php`, `iscrizione.php`, `iscrizione/{conferma-genitore,giocatore,verifica-email}.php`.
- **POS:** `pos/{bar_display,kitchen_display,operator_panel,stato-ordine}.php`, `pos/admin/{coupons,menu_management,orders_history,statistics,users}.php`.
- **CSS/design:** `assets/css/{admin-workspace,admin,baone-ui,captain-portal,public-redesign,registration-portal,style}.css`, `assets/css/brand/tokens.css`, `assets/css/components/{auth,navigation}.css`, `pos/assets/css/pos.css`, `photo/assets/css/archivio.css`.
- **Altri artefatti Phase 02–05:** `tests/auth/authorization_matrix_test.php`, `docs/redesign/baone-ui-showcase.html`, `docs/permissions-ui/01_AUTHORIZATION_AUDIT.md`–`09_UI_IMPLEMENTATION_REPORT.md`.

Questi file erano già presenti come modifiche/untracked prima della validazione Phase 06; non sono stati classificati o rimossi come estranei. Non è emerso un file sicuramente non correlato al lavoro Phase 02–05.

## Rischi aperti e deployment

1. Eseguire la matrice autorizzazioni aggiornata su un `tb_auth_p07_clean_20261104` disposable ripristinabile, con verifica post-run della pulizia fixture; poi smoke HTTP autenticati per ogni ruolo, incluse risposte 403 su URL/API.
2. Fornire account/fixture disposable per le 12 categorie ruolo, multi-settore, inattivi, non assegnati e capitani prima di attestare navigation e flussi reali.
3. Prima del rilascio, testare screenshots e overflow a 1440/768/390/narrow, stati attivi nav, dialog e pagine autenticati con viewport tool disponibile.
4. Eseguire regression POS e archivio solo su ambiente isolato, coprendo checkout/stazioni, upload/resume/download edizioni senza dati reali.
5. Verificare sul target deployment cookie/sessioni, HTTPS/origin, CSRF e supporto runtime/database; i test locali non attestano configurazioni production.
6. Riesaminare il disallineamento anno 2026/2027 con decisione prodotto prima di aggiornare copy o configurazione.
7. I warning LF/CRLF sono conversioni Git del worktree Windows; non sono stati normalizzati per evitare rumore sulle modifiche già presenti.

**Conclusione:** la qualità statica di base e una parte dell’autenticazione centralizzata sono verificate; non è possibile dichiarare l’intero progetto “funzionalmente corretto, sicuro e responsive” sulla base dei test disponibili. Il rilascio resta **NOT READY for full validation sign-off** finché i blocchi elencati non sono coperti in un ambiente disposable e con viewport/test browser adeguati.
