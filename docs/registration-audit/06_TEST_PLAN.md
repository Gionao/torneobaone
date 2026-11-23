# 06 — Piano test

Non eseguito in questa fase. Estendere test auth attuali (`tests/auth/staff_auth_test.php`, `tests/auth/authorization_matrix_test.php`) con suite isolate su DB disposable e fixture sintetiche. Interrompere i test se la variabile DB non indica allowlist disposable; niente email reali, SMTP mock/Mailtrap sandbox solo su ambiente test.

## Unit
- Calcolo età alla data submission (16, 17, 18; giorno prima/del compleanno; timezone Europe/Rome; data invalida/futura) e soglia capitano 18.
- Normalizzazione email (spazi, maiuscole; alias plus/dot non collassati), CF formato/checksum, date e indirizzi.
- State machine: player pending fino a email e consensi; minore pending finché link+OTP+consensi; team registered solo 8–10 completi + quota completa + approvazione.
- Revoca foto registra evento nuovo, non muta grant storico né stato iscrizione.
- Token entropy/scadenza/type/scope e OTP expiry/tentativi.

## Integrazione database
- Unicità persona/edizione e divieto di due team, incluse richieste parallele; CF duplicato dopo normalizzazione.
- Concorrenza cap squadra e cap roster (9ª/10ª/11ª); sostituzione non supera limiti.
- Atomicità signup capitano+squadra+membership e submit registrazione; rollback su errore; retry idempotente.
- Token single-use: due POST simultanei producono un solo consumo/transizione; scaduto/invalidato/consumato respinto.
- FK, cascade/restrict e conservazione evidenze alla sostituzione/ritiro.
- Migrazioni upgrade da entrambe le varianti storiche su snapshot sintetici, controllo conteggi e rollback/forward recovery.

## API/integrazione email
- SMTP mock: inviti individuali, verify, parent link e OTP, conferma e riepilogo; failure non perde record e consente resend manuale.
- Verificare link non contiene informazioni personali; link 72h e codice 10m; Mailtrap di test, nessun destinatario reale.
- Scanner GET non consuma autorizzazioni; conferma esplicita POST.

## End-to-end
- Capitano maggiorenne registra account, verifica email, entra, completa dati/consensi come player, invita roster; squadra non approvata anche se pagamento totale.
- Adulto completa dati, verifica email, accetta regole/privacy/liability, nega foto e resta ammesso; email conferma riepilogo coerente.
- Minore 16/17: campi genitore completi, email diversa, link 72h + OTP; pending prima di tutti i consensi; foto scelta solo dal genitore.
- Carta: PDF precompilato con ref univoco, firma, staff valida e audit visibile; nessuna immagine ID memorizzata.
- Sostituzione prima deadline e blocco dopo deadline; storico conserva partecipante precedente.
- Responsive e UI coerente con componenti/brand esistenti.

## Negativi / sicurezza
- **Impersonificazione capitano:** usare email account capitano come giocatore, auto-invito e forgiare `id_squadra`/`id_capitano`; server respinge.
- **Duplicati:** stesso CF nella stessa edizione, stessa persona su altro team, casing/spazi diversi, submit concorrenti; uno solo accettato.
- **Genitore:** stesso indirizzo email del minore (anche solo maiuscole), parent link per altro giocatore, OTP errato/scaduto/riusato, brute force oltre soglia; tutti negati e rate limited.
- Token random/forgiato, invito di un’altra squadra, invalidato, expired, consumed; respinti senza leak di stato/PII.
- Forged POST senza CSRF, GET per revoca/approvazione, IDOR tra capitani/team, ruolo staff POS/archivio/partite su registrazioni, segreteria su configurazione riservata; 403/no mutation.
- XSS stored/reflected su nomi, note e UA; payload SQLi su input, encoding; output escaped.
- Manipolazione hidden fields, client age, checkbox, importo pagamento, stato team, consent version/document ID; server ricalcola e autorizza.
- Race doppio invito, creazione squadre all’ultimo slot, due approvazioni e revoca; invarianti consistenti.

## Authorization/regressione
Testare ogni route GET/POST per non autenticato, capitano proprietario, capitano altro team, segreteria, amministratore torneo e ogni ruolo non autorizzato. Matrice permessi backend indipendente da navbar. Regression: login capitano/staff, squadre, giocatori e eventi partita, pagamenti manuali, configurazione, storico, `database/schema_complete.sql` vs schema installato, PHP/DB target AlterVista. Nessun test deve colpire produzione.
