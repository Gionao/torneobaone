# Gestionale Torneo Baone

Applicazione PHP e MySQL per la gestione del torneo di calcio a 5 di Baone.
Comprende il sito pubblico, le iscrizioni, il pannello organizzativo, il POS e
un archivio privato di foto e video. I moduli condividono la configurazione e
il database, ma hanno aree e responsabilità distinte.

## Guide dei moduli

- [Gestione torneo e iscrizioni](admin/README.md)
- [POS](pos/README.md)
- [Archivio foto e video](photo/README.md)
- [Struttura completa del progetto](STRUTTURA_FILE.md)

## Requisiti

- PHP con PDO MySQL; per le funzionalità email è richiesto PHPMailer
  (`composer install`).
- MySQL 8 o compatibile.
- Apache con `mod_rewrite` e `AllowOverride`, oppure regole equivalenti per il
  server web usato.
- Per l'archivio: `fileinfo`, `gd`, `zip` e `exif` abilitati in PHP.

## Installazione locale

1. Clonare o copiare il progetto mantenendo la struttura delle cartelle.
2. Configurare il server web affinché la root del progetto sia raggiungibile.
   In ambiente Laragon gli URL locali predefiniti sono basati su
   `http://torneobaone.test`.
3. Installare le dipendenze:

   ```sh
   composer install
   ```

4. Creare le tabelle nel database:

   ```sh
   mysql -u utente -p < assets/sql/database_schema.sql
   ```

   Lo schema combinato crea torneo, POS e drive nel database
   `my_torneobaone`. In alternativa, applicare nell'ordine gli schemi per
   modulo descritti in [STRUTTURA_FILE.md](STRUTTURA_FILE.md).
5. Impostare le variabili d'ambiente database. Il valore predefinito è
   `localhost`, database `my_torneobaone`, utente `root` e password vuota:
   non usare questi valori predefiniti in produzione.
6. Verificare che siano raggiungibili le pagine del sito e i login dei moduli.
   La configurazione degli URL e del database è in `includes/config.php`.

Gli script in `assets/sql/database_insert_torneo.sql` e
`assets/sql/database_insert_pos.sql` inseriscono dati dimostrativi e vanno
usati solo in sviluppo o test. `database_insert_photo.sql` è intenzionalmente
vuoto. Non importare gli schemi completi sopra un database già popolato come
sostituto di una migrazione: verificare struttura e dati e predisporre un
backup prima di qualsiasi modifica.

## Configurazione

Le variabili d'ambiente principali sono:

| Variabile | Scopo |
| --- | --- |
| `APP_ENV`, `DEBUG_MODE` | Ambiente e visualizzazione degli errori. In produzione usare `APP_ENV=production` e `DEBUG_MODE=false`. |
| `DB_HOST`, `DB_NAME`, `DB_USER`, `DB_PASS`, `DB_CHARSET` | Connessione MySQL condivisa; il database predefinito è `my_torneobaone`. |
| `SITE_URL`, `SITE_NAME` | URL e nome del sito. |
| `ADMIN_PATH`, `POS_URL`, `TELECRONISTA_URL`, `ARCHIVIO_URL` | URL delle aree riservate. |
| `SMTP_HOST`, `SMTP_PORT`, `SMTP_USERNAME`, `SMTP_PASSWORD`, `SMTP_ENCRYPTION` | Server per l'invio delle email. |
| `MAX_SQUADRE` | Numero massimo di squadre registrabili; predefinito 20. |

Impostare i segreti nell'ambiente del server e non nel repository. La
configurazione dettagliata è descritta in `includes/config.php`.

## Database e dati

Il database condiviso contiene le tabelle torneo/iscrizioni, POS e drive. Gli
schemi di installazione sono in `assets/sql/`; la migrazione
`migration_architettura_001.sql` è una migrazione una tantum, da applicare solo
dopo aver verificato i preflight, i vincoli esistenti e un backup affidabile.
`CREATE TABLE IF NOT EXISTS` non aggiorna la struttura di tabelle già
esistenti.

## Backup, test e deploy

Gli strumenti CLI di backup e ripristino sono in `tools/backup.php` e
`tools/restore.php`. Le istruzioni per retention, pianificazione Windows e
ripristino sono in [photo/README.md](photo/README.md). Prima del deploy,
proteggere la cartella dei backup e verificare una prova di ripristino su un
ambiente separato.

Il deploy richiede che gli URL e le regole del web server siano coerenti con
`includes/config.php` e `.htaccess`. Ulteriori dettagli sulla struttura e
sull'hosting sono in [STRUTTURA_FILE.md](STRUTTURA_FILE.md).
