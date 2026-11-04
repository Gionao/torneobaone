# 09 — Risultati dei test

## Ambiente e perimetro

- Data: 2026-11-04. Branch `feature/iscrizioni`, HEAD `d25b49238f1335066d0428916e2a26953023e35d`.
- PHP CLI 8.5.12; estensioni PDO/PDO MySQL disponibili; PHPMailer presente in `vendor/`; client MySQL presente. La presenza degli strumenti **non** prova che il server/database sia isolato.
- `APP_ENV`, `DB_HOST`, `DB_NAME` e `SMTP_HOST` non sono impostati nell’ambiente esaminato. I default applicativi sono locali, ma non è stata aperta connessione. Nessun database di test creato, nessun dato caricato, nessun server SMTP contattato.
- Nessun destinatario reale, CF reale, dato partecipante o pagamento di produzione usato.

## Esecuzioni

| Prova | Comando | Esito | Evidenza |
|---|---|---|---|
| Sintassi validatore | `php -l includes/registration_validation.php` | PASSED | Nessun errore sintattico. |
| Sintassi test | `php -l tests/registration/validation_test.php` | PASSED | Nessun errore sintattico. |
| Unit deterministici | `php tests/registration/validation_test.php` | PASSED | 13 verifiche: età 16/17/18, compleanno, data futura, email normalizzata senza collassare alias, formato/checksum CF, campi richiesti. |
| Composer manifest | `composer --no-interaction validate --no-check-publish` | PASSED | Manifest valido; warning preesistente: licenza non specificata. |
| Spazi/whitespace Git | `git diff --check` | PASSED | Nessun whitespace error; Git avverte solo conversione LF→CRLF per due file. |
| Suite staff DB | Nessuno | BLOCKED | `tests/auth/staff_auth_test.php` richiede allowlist DB e modifica/inserisce dati; non eseguito senza DB disposable esplicitamente garantito. |
| Migrazione/schema | Nessuno | BLOCKED | Nessun DB disposable predisposto; la migrazione DDL non è stata applicata. |
| E2E / HTTP forgiato | Nessuno | BLOCKED | Workflow non è integrato nelle route; nessun web server isolato. |
| SMTP sandbox | Nessuno | BLOCKED | `SMTP_HOST` non configurato; nessun sandbox identificato. |
| Sicurezza runtime / RBAC | Nessuno | NOT EXECUTED | Nessun endpoint nuovo collegato e nessuna sessione/fixture di test attivata. |
| Regressione UI responsive | Nessuno | NOT EXECUTED | Nessuna browser session o implementazione UI modificata. |

## Tracciabilità requisiti

Riferimenti `R01–R34` rimandano a `02_GAP_ANALYSIS.md`; colonne stato/evidenza descrivono questa validazione e non inferiscono un successo dal solo codice previsto.

| Requisiti audit | Evidenza/test | Stato validazione |
|---|---|---|
| R01–R08 capitano, team, inviti, limite e termine | Nessun test applicativo; signup e inviti non integrati con il validatore nuovo. | BLOCKED |
| R09–R14 dati, età, identità, email genitore | Unit per validatore solo (13 assert); nessuna route o persistence provata. Test esatti 16 e 18, data futura, email casing policy e checksum inclusi. | PARTIAL: unit PASS; workflow BLOCKED |
| R15–R18 OTP, carta, identità check-in | Tabelle soltanto nello schema proposto; nessun comportamento eseguibile da testare. | BLOCKED |
| R19–R24 consensi, stato team, pagamento, sostituzioni/email | Nessun servizio/app state implementato né E2E; pagamenti preesistenti non toccati. | BLOCKED |
| R25 ruoli staff | Vecchi test auth non eseguiti; nessuna nuova route/capability. | NOT EXECUTED |
| R26 chiusura/riapertura | No route/guard implementati. | BLOCKED |
| R27 consensi/versioni/revoca | Schema evento non verificato; nessuna logica di scrittura/lettura. | BLOCKED |
| R28 token single-use/replay | Nessun test token/OTP; utility token legacy non modificata. | NOT EXECUTED |
| R29 duplicati/race | Nessun test DB/concorrenza; constraint schema non validati su motore. | BLOCKED |
| R30 CSRF/XSS/SQLi/IDOR | Nessun test request dinamico o scanner. | NOT EXECUTED |
| R31–R32 resend/SMTP | Nessun invio sandbox o persistenza delivery provati. | BLOCKED |
| R33 privacy/retention | Nessuna cancellazione/retention eseguita. | NOT EXECUTED |
| R34 suite/deploy | Nessun database o hosting target. | BLOCKED |

## Test richiesti ma non eseguiti
Tutti i casi A–L del Prompt 3 non citati esplicitamente fra i 13 unit test sono BLOCKED o NOT EXECUTED. In particolare: creazione account, duplicati, email confirmation, genitore/OTP, paper PDF, condizioni approval, authorization matrix, injection, CSRF, accesso file, privacy deletion, SMTP, migration, responsive. Nessun risultato negativo è stato simulato né classificato PASS.
