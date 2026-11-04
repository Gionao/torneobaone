# 02 — Gap analysis

Stato riferito al codice statico. `unknown` significa evidenza insufficiente. Severity: critica/alta/media.

| ID | Atteso | Reale e riferimenti | Stato | Severità | Soluzione proposta |
|---|---|---|---|---|---|
| R01 | Solo capitano crea squadre | Self-registration `capitano/registrati.php:22-80`. | conforme | — | Conservare confine, censire tutte le route. |
| R02 | Capitano ≥18 alla registrazione | Data raccolta ma solo `strtotime`, no età: `capitano/registrati.php:30,61-63`. | difettoso | Alta | Validazione server con data submission e test compleanno. |
| R03 | Email capitano verificata | Token e guard `includes/auth_capitano.php:49-75,89-115`; email `capitano/registrati.php:179-200`. | conforme | Media | Rate limit/reinvio atomico. |
| R04 | Capitano nella rosa e registrato come giocatore | Crea solo account/squadra `capitano/registrati.php:74-148`. | mancante | Critica | Collegamento account-persona/membership e flusso dedicato, no auto-invito normale. |
| R05 | 8–10 iscritti completi incluso capitano | Trigger storico max 9 `assets/sql/database_schema_torneo.sql:134-158`; schema recente max 10 `database/schema_complete.sql:271-295`; completamento solo tutti completi `includes/iscrizione_helper.php:58-77`. | difettoso | Critica | Min/max regole edizione, applicazione atomica e schema coerente. |
| R06 | Massimo squadre configurabile amministrativamente | Env/default 20 `includes/config.php:67-75`; conteggio senza transazione `capitano/registrati.php:19-24`. | parziale | Alta | Setting per edizione e lock/concorrenza. |
| R07 | Termine configurabile/enforced | Nessun termine trovato in flusso/schema esaminato. | mancante | Alta | Config per torneo e guard su tutte le mutation route. |
| R08 | Inviti solo capitano, senza impersonificazione | Owner squadra verificato `capitano/giocatori.php:11-21`; candidata email liberamente scelta `:82-125`; nessun controllo email capitano. | parziale | Alta | Bloccare indirizzo account capitano e verificare ownership/stato sul server. |
| R09 | Dati giocatore inclusi CF | Campi form senza CF `iscrizione/giocatore.php:35-59,171-199`. | parziale | Alta | Valutare minimizzazione, raccogliere/normalizzare solo se approvato. |
| R10 | Minimo 16 alla submission | Età contro data torneo `includes/iscrizione_helper.php:15-29`, chiamata `iscrizione/giocatore.php:44-45`; nessuna soglia 16. | difettoso | Critica | Età server-side a submission e rifiuto under-16. |
| R11 | No partecipazione multipla nell’edizione | Nessuna chiave CF/persona su `giocatori` (`assets/sql/database_schema_torneo.sql:115-132`); controllo nome/cognome/email applicativo `capitano/giocatori.php:92-97`. | mancante | Critica | Identificatore edizione/persona univoco con vincolo DB e lock. |
| R12 | Email non prova identità | Verifica email è condizione completamento `includes/iscrizione_helper.php:103-147`; nessun controllo identità. | difettoso | Alta | Separare verifica recapito da identità; comunicare limiti, check-in senza copie. |
| R13 | Dati e dichiarazione genitore | `genitori` solo nome/cognome/email, telefono nullable `assets/sql/database_schema_torneo.sql:246-259`; form `iscrizione/giocatore.php:41-43,63-65`. | mancante | Alta | Campi richiesti e dichiarazione, previa valutazione giuridica dei dati sensibili. |
| R14 | Email genitore diversa | Normalizzazione lowercase senza confronto `iscrizione/giocatore.php:37,43,53-65`. | difettoso | Alta | Confronto canonico server-side. |
| R15 | Link 72h + OTP 10m | Link 72h `iscrizione/verifica-email.php:34-47`; nessun OTP/tabella OTP. | parziale | Critica | OTP hash, TTL/tentativi/limiti, legato a genitore e atleta. |
| R16 | Controlli minore separati incl. partecipazione/liability | Privacy/regolamento/foto `iscrizione/conferma-genitore.php:15-29,39`; liability/partecipazione assenti. | difettoso | Alta | Tipi di evidenza distinti, foto opzionale. |
| R17 | Procedura cartacea PDF/QR/firma/validazione tracciata | Non individuati handler o tabelle. | mancante | Media | Documento precompilato e validazione staff con audit. |
| R18 | No copie ID; check-in verifica identità | Nessun upload documenti nel flusso esaminato, check-in non documentato. | parziale | Media | Formalizzare istruzioni e verificare resto del sistema. |
| R19 | Adulto: email, regole, privacy, liability, scelta foto esplicita | Email/privacy/regolamento e foto positiva `iscrizione/giocatore.php:60-75`; liability e record di rifiuto assenti. | difettoso | Alta | Stati sì/no per controllo, separati e versionati. |
| R20 | Team registered = 8–10 completi + quota + approvazione | Helper ignora conteggio/quota/approvazione `includes/iscrizione_helper.php:58-77`; pagamenti separati `:32-56`. | difettoso | Critica | Regola composita server-side, transizione auditata. |
| R21 | Pagamento non approva automaticamente | Pagamento aggiorna solo importo/stato `admin/pagamenti/inserisci.php:49-63`. | conforme | — | Mantenere stati separati, test regressione. |
| R22 | Approvazione esplicita autorizzati | Flag modificabile da capability teams `admin/squadre/dettaglio.php:6-21`; non integrato nei prerequisiti. | parziale | Alta | Workflow dedicato con controllo condizioni e audit. |
| R23 | Sostituzioni prima chiusura, nuova registrazione | Delete fisico `capitano/giocatori.php:36-40`; nessuna sostituzione. | difettoso | Alta | Storico membership e revoca/invito nuovo senza cancellare evidenze. |
| R24 | Email conferma e riepilogo consensi | Invio verifica/parentale soltanto `iscrizione/verifica-email.php:34-58`. | mancante | Media | Invio post-commit e resend manuale. |
| R25 | Ruoli segreteria/admin; altri esclusi backend | Capability `includes/staff_authorization.php:21-48`; guard iscrizioni `admin/iscrizioni/index.php:8`. | conforme | — | Guard specifico per ogni nuova azione. |
| R26 | Chiusura/riapertura | Nessun termine o audit trovato nel flusso. | mancante | Alta | Config edizione + log di riapertura. |
| R27 | Evidenza consensi complete, revoca immutabile | Schema `consensi` `assets/sql/database_schema_torneo.sql:298-321`; helper solo grant/evita duplicato `includes/iscrizione_helper.php:159-175`. Mancano canale, diniego, revoca/evento. | difettoso | Critica | Registro append-only soggetto/tipo/versione/esito/timestamp/canale e audit revoche. |
| R28 | Token scaduti/usati non riusabili | Hash e query validità `includes/token_helper.php:8-53`; consumo non controlla rowCount; invito riemesso `capitano/giocatori.php:47-50`. | parziale | Alta | Consumo condizionale verificato in transazione e scope forte. |
| R29 | Duplicati/race prevention | Query applicative non atomiche `capitano/giocatori.php:92-106`, contatore senza lock; no vincolo CF. | difettoso | Critica | Vincoli unici, lock/transazioni e idempotency. |
| R30 | CSRF/XSS/SQLi/privilege escalation | PDO prepared + escaping diffusi; CSRF capitano `capitano/giocatori.php:31-33`; form pubblici senza CSRF `iscrizione/giocatore.php:23-27`, `conferma-genitore.php:15-19`. | parziale | Alta | Proteggere sessioni/form, bearer link e test forgery. |
| R31 | Reinvio manuale | Reinvio genitore `capitano/giocatori.php:54-80`; verifica capitano presente; resend giocatore non evidente. | parziale | Media | Endpoint scoped, throttling/audit. |
| R32 | SMTP/Mailtrap e delivery failure | PHPMailer ^6.9 `composer.json:1-5`; validation/config/failure log `includes/mailer.php:12-25,64-124`. Runtime/AlterVista non verificato. | parziale | Media | Prova sandbox controllata solo in test e retry/monitoraggio. |
| R33 | Minimizzazione e retention | Nessuna policy retention; dati CF non ancora raccolti. Necessità legale non verificabile da codice. | sconosciuto | Alta | Decisione titolare/DPO prima di raccogliere CF/indirizzi. |
| R34 | Test registrazione/deploy | Test trovati solo `tests/auth/*`; nessun test registration. Schemi divergenti. | parziale | Alta | Suite e matrice reale hosting/versioni. |
