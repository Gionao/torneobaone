# 04 — Sicurezza e privacy

## Confini di autorizzazione
Staff: policy esplicite (`includes/staff_authorization.php:21-48`), guard route (`includes/auth.php:28-55`); la pagina iscrizioni richiede staff torneo (`admin/iscrizioni/index.php:8`). Segreteria ha capability registrazioni/pagamenti, non configurazione metodi (`includes/staff_authorization.php:29-31`). Nuovi endpoint devono verificare capability e ownership backend; UI/navbar non sono enforcement. Capitano usa sessione HttpOnly/SameSite Strict e verifica email (`includes/auth_capitano.php:9-20,64-87`), CSRF per modifiche in `capitano/giocatori.php:31-33`. Altre route pubbliche non usano sessione/CSRF evidente. Verificare anche route di eliminazione e pagamenti già esistenti prima di estendere.

## Abusi richiesti

| Caso | Evidenza e rischio | Controllo richiesto |
|---|---|---|
| Capitano invita se stesso/impersona altro | Input email libero in `capitano/giocatori.php:83-106`; non confronta email capitano né identity account. | Impedire email account capitano e membership capitano; l’email verificata dimostra solo controllo casella. Non impedisce omonimia/alias o casella condivisa. |
| CF duplicato nell’edizione / multi-team | CF assente da schema/form; duplicato applicativo usa nome/cognome/email e query non atomica. | Normalizzare CF (maiuscolo, no spazi), validare formato/checksum, vincolo DB per torneo e verifica conflitti esistenti. CF non prova da solo identità. |
| Genitore usa email minore | Nessun confronto in `iscrizione/giocatore.php:41-65`. | Confronto case-insensitive canonical address lato server. |
| Token scaduto/invalidato/consumato o cross-player | Token SHA-256 e expiry (`includes/token_helper.php:8-53`); query filtra validità. Consumo non conferma rowCount; link autorizzazione associa entrambi gli ID, buon punto di partenza. | UPDATE atomico con `usato_at IS NULL`, controllo affected rows, transazione e scope esplicito; niente dati personali in URL/log/referrer. |
| OTP brute force/replay | Nessuna implementazione schema/codice trovata. | OTP casuale a bassa entropia solo hashato, 10 min, max tentativi, cooldown/rate limit per destinatario/IP/token, lockout e consumo atomico. Evitare errori rivelatori. |
| Modulo genitore per persona errata | Token ha `id_genitore` e `id_giocatore` (`token_helper.php:24-35`) e route controlla entrambi (`conferma-genitore.php:13-29`); nessuna verifica OTP. | Challenge OTP legata al token e alla coppia genitore/partecipazione; invalidare al cambio email e resend. |
| Accesso/edit non autorizzati | Capitano squadra scoped a `id_squadra` in `capitano/giocatori.php:18,37-45`; staff capability centralizzata. | Ogni read/write query scope team/account, non ID client; test IDOR/capability a ogni endpoint. |
| HTTP forged request | Campi client sono validati server in parte; CSRF assente nei form pubblici osservati. | CSRF session-based per sessioni e conferma POST; link bearer resta sensibile a forwarding/preview scanner, non consumare autorizzazione via GET. |
| Doppio submit/race | Creazione capitano/squadra senza transazione complessiva; contatore squadre/giocatori e duplicati non atomici. | Transazioni, lock, unique constraints e idempotency token; invio email dopo commit con stato resend. |
| SQLi/XSS | PDO native prepares (`includes/db.php:24-28`), query spesso parametrizzate; output htmlspecialchars diffuso. | Review completa delle route e escaping con ENT_QUOTES/charset anche nei frammenti legacy; non fidarsi input. |
| Privilege escalation | Ruoli derivati DB, fail closed in helper staff; responsabilità separata. | Mai usare solo navigazione; capability specifiche per config, documenti e approvazione. Auditor separato dal soggetto approvato. |
| Alterazione consensi/documenti | `registra_consenso` aggiunge solo concessioni e non versione hash/canale; schema consente cancellazione CASCADE. | Log append-only, documenti immutabili con hash/versione; revoca come nuovo evento, non UPDATE/delete. |

## Token ed email
`random_bytes(32)` e hash SHA-256 sono adatti a token bearer ad alta entropia. Link invito dura 168h, email verifica 72h e parent link 72h; validità sempre interrogata sul server. Criticità: `consume_token()` ritorna successo dell’esecuzione SQL, non consumo effettivo (`includes/token_helper.php:48-53`); client può ripetere richieste concorrenti. Link in query string può finire in log/browser history/referrer: impostare no-referrer/no-store, non caricare terze parti, limitare esposizione. Email verification attualmente tramite GET (`iscrizione/verifica-email.php:11-29`) può essere attivata da scanner automatici; preferibile landing + conferma POST.

SMTP: credenziali da env, Mailtrap sandbox vietato in production e mittente `.test` rifiutato (`includes/mailer.php:76-92`); timeout 15s e log senza body (`:95-124`). `vendor/autoload.php` e PHPMailer nel repo. Non c’è prova runtime di configurazione o deliverability AlterVista. No real email inviate durante audit.

## Email, alias e identificazione
Normalizzare email trim + case-fold per confronto applicativo, consapevoli che local-part tecnicamente può essere case-sensitive; per il sistema adottare policy dichiarata coerente e collation/index coerenti. Gmail dot/plus alias e alias provider non consentono prova affidabile di persona; non rimuovere `+tag` o punti in modo generalizzato e non usare email come chiave identitaria. Richiedere CF univoco riduce duplicati ma è dato personale e può essere inserito da terzi: controlli tecnici non garantiscono identità reale. Check-in con visualizzazione/verifica del documento da parte dello staff, senza acquisizione/copia.

## GDPR e decisioni legali
CF è identificativo personale ad alto impatto; indirizzo residenziale e contatti di minori aumentano rischio. Prima raccolta, titolare/DPO/consulente devono validare finalità, base giuridica, necessità/proporzionalità, destinatari, informativa, retention/cancellazione, accessi e gestione diritti. Non assumere che il consenso sia base adeguata per ogni finalità. Non raccogliere certificati medici o copie ID. Privacy e liability attuali sono provvisorie: ogni testo proposto è **BOZZA — richiede approvazione legale/privacy prima dell’uso**.

Foto/video: opt-in separato e facoltativo; il rifiuto non deve bloccare; per minore decide genitore/tutore. Revoca tramite Segreteria come nuovo evento con effetto operativo sulle pubblicazioni future ragionevolmente controllabili, senza annullare iscrizione né cancellare evidenza storica. Definire retention per record iscrizione, token/log email/IP/UA e documenti dopo parere legale. IP/UA sono dati personali e vanno minimizzati/protetti.

Paper fallback: PDF precompilato con codice casuale/QR non enumerabile, firma genitore, validazione staff autenticato e timestamp/attore/esito; non caricare né conservare documento identità. Codice QR non deve esporre dati sensibili.
