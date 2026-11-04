# 08 — Report implementazione (stato parziale)

## Stato

**Implementazione incompleta — non pronta per staging o produzione.** La richiesta di implementazione è stata autorizzata tramite Prompt 2. Non è stato eseguito alcun deploy, push, merge, accesso a dati reali, invio email o comando SQL. La migrazione è un artefatto non verificato e non va applicata.

Branch `feature/iscrizioni`, HEAD di base `d25b49238f1335066d0428916e2a26953023e35d`; nessun commit più recente e nessuna modifica tracked preesistente al controllo. I report dell’audit erano file untracked e sono stati preservati. Non risultano differenze di codice tra il commit analizzato e l’attuale HEAD.

## Lavoro completato in questa fase

- `includes/registration_validation.php`: funzioni centralizzate per email trim/lowercase (senza eliminare alias), calcolo età su data passata e fuso Roma, validazione del formato/checksum del CF e validazione campi persona.
- `tests/registration/validation_test.php`: test sintetici su confini età, data futura, normalizzazione email e checksum CF. Non contiene dati personali reali.
- `database/migrations/20261105_registration_workflow.sql`: bozza di migrazione additiva per configurazione torneo, dati CF, stato approvazione staff, eventi consensi, challenge genitore, procedura cartacea e audit.
- `database/schema_complete.sql`: aggiunti i relativi campi/tabelle allo schema completo canonico.
- `database/migrations/README.md`: avvertenze e sequenza proposta. La migrazione non è transazionale né ripetibile in caso di esecuzione parziale senza preflight.

## Requisiti non completati

Non sono stati ancora integrati i nuovi servizi nel flusso applicativo. Di conseguenza non sono realizzati: età minima nel signup capitano/player UI, CF nei moduli e persistenza, collegamento capitano-giocatore, inviti e sostituzioni controllati, scadenza e cap squadre amministrativi, unicità effettiva persona/torneo sull’intero dato storico, token/OTP e relative schermate, consensi centralizzati e workflow di revoca, PDF firmabile/QR, approval/stati team ricalcolati, pannello staff e capability specifiche, resend/confirmation email/outbox, retention configurabile e test end-to-end/authorization. Lo schema non è stato confrontato con tutte le installazioni e gli script SQL alternativi (`assets/sql/database_schema_torneo.sql`, `assets/sql/database_schema_completo.sql`).

Le nuove tabelle di approvazione e carta non sono collegate alle route. Il vincolo CF della bozza opera solo dove `id_torneo` è valorizzato; righe legacy senza edizione restano fuori dal vincolo. Sono necessari preflight e decisione manuale sulle collisioni. `schema_complete.sql` è stato modificato solo come schema canonico: le copie legacy SQL non sono state aggiornate.

## Test già eseguiti

- `php -l includes/registration_validation.php` — superato.
- `php -l tests/registration/validation_test.php` — superato.
- `php tests/registration/validation_test.php` — superato, 8 asserzioni.
- `git diff --check` — superato all’ultimo controllo prima delle modifiche documentali finali.
- Non eseguiti: suite staff auth, test database/migrazioni, web/E2E, SMTP sandbox, test hosting. Nessun server database è stato usato.

## Ordine richiesto prima dell’uso

1. Review tecnica della migrazione con versione effettiva target; restore di backup in DB disposable e preflight conflitti. Non eseguire SQL da questo branch su dati reali.
2. Completare servizio dominio/schema e integrazione delle pagine in fasi piccole, incluso captain-player linkage e transaction/lock.
3. Completare OTP, prove consensi, fallback carta e interfaccia staff con test di autorizzazione.
4. Testare entrambe le famiglie schema legacy e il completo; aggiornare coerentemente gli script SQL supportati.
5. Ottenere approvazione DPO/legale per finalità, documenti e retention. Non è attivata alcuna scadenza retention.
6. Eseguire test locali su PHP 8.5/MySQL disposable e poi versione esatta staging/AlterVista, SMTP sandbox soltanto; verificare backup/restore e piano di recovery.
7. Review Git e sign-off separato prima dello staging. Nessun deploy è autorizzato da questo report.

## Comandi riproducibili (solo ambiente disposable)

```powershell
php -l includes/registration_validation.php
php -l tests/registration/validation_test.php
php tests/registration/validation_test.php
git diff --check
git status --short --branch
git diff --stat
```

Per il DB: seguire le procedure di `database/migrations/README.md`, predisponendo e verificando prima un database disposable isolato. Non vengono forniti comandi che puntino al DB configurato nel file locale. SMTP da configurare solo dopo avere creato un ambiente di test e un destinatario sandbox; usare `SMTP_HOST`, `SMTP_PORT`, `SMTP_USERNAME`, `SMTP_PASSWORD`, `SMTP_ENCRYPTION`, `MAIL_FROM_ADDRESS`, `APP_ENV=local` via environment e mai account o destinatari reali. La fase attuale non ha verificato tale configurazione.
