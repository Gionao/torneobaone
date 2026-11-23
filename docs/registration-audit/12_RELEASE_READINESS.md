# 12 — Prontezza al rilascio

## Decisione

**Staging: NO. Produzione: NO.** La fase 2 non integra i requisiti nelle pagine; nessuna migration è stata verificata; le prove di sicurezza, autorizzazione, email e regressione sono bloccate/non eseguite. Non esiste evidenza per i criteri di accettazione del Prompt 3.

## Blocchi prima dello staging
1. Completare le route e i servizi mancanti documentati in `08_IMPLEMENTATION_REPORT.md`; collegare davvero il validatore e provare ogni transizione.
2. Correggere e verificare migration/preflight, coprire schema completo e varianti supportate, controllare DDL/FK/indici su MySQL 8 e motore target. Verificare backup/restore e migrazione parziale su DB disposable.
3. Rendere funzionali e testare unicità CF/edizione, partecipazioni storiche, limite squadra concorrente, OTP/CSRF/token, consensi, approvazioni e RBAC backend.
4. Predisporre DB test nominato e isolato con credenziali dedicate; verificare che host/database non siano produzione. Nessuna credenziale configurata ora.
5. Configurare sandbox SMTP e testare fallimenti/resend senza destinatari reali.
6. Completare test integrazione, authorization matrix, forged HTTP, E2E adulti/minori/carta/pagamenti e regressione UI 360–1366px; registrare solo risultati realmente acquisiti.
7. Ottenere approvazione privacy/legale per documenti e retention prima dell’uso; non attivare retention non approvata.

## Prerequisiti produzione successivi
Approvazione legale/privacy formale, review tecnica indipendente, versione target AlterVista validata, piano backup/restore/change window, gestione segreti SMTP/DB, migrazione staged con report pre/post, monitoraggio invii e audit, sign-off organizzativo. Nessuno di questi passi è stato avviato.

## Comandi eseguiti in questa validazione
```powershell
php -l includes/registration_validation.php
php -l tests/registration/validation_test.php
php tests/registration/validation_test.php
composer --no-interaction validate --no-check-publish
git diff --check
git status --short --branch
git rev-parse HEAD
```

Non fornire/eseguire ora un comando `mysql < migration.sql`: il target e lo stato DB non sono identificati come disposable e la migration non è pronta. Nessun deploy, push o merge.
