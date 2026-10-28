# Struttura del progetto — Torneo Calcio a 5 Baone

Applicazione PHP con database MySQL, area pubblica, pannello amministrativo,
portale capitani, telecronaca e sistema POS. I percorsi qui sotto sono relativi
alla cartella principale del progetto.

## Mappa dei file

```text
.
├── .htaccess                    # Protezione delle directory e instradamento dei sottodomini
├── index.php                    # Homepage
├── calendario.php               # Calendario delle partite
├── classifica.php               # Classifica
├── classifica_engine.php        # Calcolo della classifica
├── partita.php                  # Dettaglio partita
├── live.php                     # Pagina live
├── iscrizione.php               # Avvio iscrizione
├── photo.php                    # Gestione/visualizzazione foto
├── admin_header.php             # Header amministrativo condiviso
├── admin/
│   ├── index.php, login.php, logout.php
│   ├── giocatori/               # Gestione giocatori
│   ├── iscrizioni/              # Gestione iscrizioni
│   ├── pagamenti/               # Metodi e transazioni di pagamento
│   ├── partite/                 # Partite, risultati ed eventi
│   └── squadre/                 # Gestione squadre
├── capitano/                    # Registrazione, accesso e area capitani
├── iscrizione/                  # Moduli e verifica delle iscrizioni
├── telecronista/                # Accesso e gestione telecronaca
├── photo/                       # Archivio foto/video privato
│   ├── admin/                   # Interfaccia riservata (galleria, upload, cestino, utenti)
│   ├── api/                     # Download, miniature e ZIP autorizzati
│   ├── upload/                  # Upload a blocchi con ripresa
│   ├── includes/                # Config, autorizzazione, repository e storage
│   ├── assets/                  # CSS e JavaScript dell'archivio
│   ├── tools/                   # Cron CLI per ZIP e manutenzione
│   └── README.md                # Ruoli, sicurezza, limiti, backup e deploy
├── pos/
│   ├── index.php                # Punto d'ingresso POS
│   ├── admin/                   # Gestione POS
│   ├── api/                     # API degli ordini, prodotti e coupon
│   ├── includes/                # Database e controlli di accesso POS
│   └── assets/                  # Risorse grafiche POS
├── includes/
│   ├── config.php               # Configurazione comune, tramite variabili d'ambiente
│   ├── db.php                   # Connessione PDO al database
│   ├── auth.php                 # Autenticazione amministrativa
│   ├── auth_capitano.php        # Autenticazione capitani
│   ├── classifica_helper.php    # Supporto alla classifica
│   ├── iscrizione_helper.php    # Supporto alle iscrizioni
│   ├── mailer.php               # Invio email con PHPMailer
│   └── token_helper.php         # Gestione token
├── shared/
│   ├── header.php, footer.php   # Layout condiviso del sito
│   └── documentazione/          # Documenti informativi per le edizioni
├── assets/
│   ├── css/, js/, img/          # Stili, script e immagini
│   └── sql/                     # Schemi, dati iniziali e migrazioni SQL
├── storage/
│   ├── email_log/               # Log locali degli invii email
│   └── media/                   # Originali, miniature, upload temporanei e ZIP (escluso da Git)
├── tests/                       # Test e verifiche
├── tools/                       # Strumenti di supporto
├── composer.json, composer.lock # Dipendenze PHP
└── vendor/                      # Dipendenze installate da Composer
```

## Installazione e database

1. Configura PHP con PDO MySQL e installa le dipendenze dalla cartella del
   progetto con `composer install`. PHPMailer è gestito da Composer.
2. Crea/configura il database e importa lo schema adatto all'installazione:
   `assets/sql/database_schema_torneo.sql` crea il database `my_torneobaone`,
   che corrisponde al nome predefinito in configurazione. `database_schema.sql`
   è una variante che crea `torneobaone`: verifica il nome e lo schema prima di
   sceglierla. `database_schema_pos.sql` contiene lo schema aggiuntivo del POS
   e usa `my_torneobaone`.
3. Per un database esistente, applica le migrazioni necessarie una sola volta.
   In particolare `assets/sql/migration_squadre_autorizzazione.sql` aggiunge
   il campo di autorizzazione squadre. `database_insert.sql` contiene dati
   dimostrativi: usalo solo se desideri caricarli. Per l'archivio multimediale
   applica in ordine `migration_media_001_schema.sql` … `migration_media_004_accessi_selezione.sql`
   (vedi `photo/README.md`); sono additive e non eliminano dati.
4. Prima di rendere il sito pubblico, controlla e sostituisci eventuali
   credenziali iniziali previste dagli script SQL.

## Configurazione

`includes/config.php` è il file di configurazione versionato; non richiede un
file `config.local.php` o `.env`. Personalizza l'ambiente impostando le
variabili d'ambiente PHP:

- Ambiente e debug: `APP_ENV` (`production` in produzione), `DEBUG_MODE`.
- Database: `DB_HOST`, `DB_NAME`, `DB_USER`, `DB_PASS`, `DB_CHARSET`.
- Sito e URL: `SITE_NAME`, `SITE_URL`, `ADMIN_PATH`, `POS_URL`,
  `TELECRONISTA_URL`, `ARCHIVIO_URL`; `ADMIN_URL` è accettata come alternativa
  a `ADMIN_PATH`. `ARCHIVIO_URL` punta per impostazione predefinita a
  `SITE_URL/photo/admin`.
- Limite squadre: `MAX_SQUADRE` (intero positivo; predefinito 20).
- Email: `SMTP_HOST`, `SMTP_PORT`, `SMTP_USERNAME`, `SMTP_PASSWORD`,
  `SMTP_ENCRYPTION`, `MAIL_FROM_ADDRESS`, `MAIL_FROM_NAME`, `MAIL_REPLY_TO`.

Se non impostate, vengono usati i fallback presenti in
`includes/config.php`. In produzione imposta le credenziali reali tramite
l'ambiente del server e mantieni `DEBUG_MODE=false`; non inserirle nel
repository.

## Deploy

- Pubblica il progetto mantenendo la struttura delle directory; configura i
  sottodomini `admin.torneobaone.it` e `pos.torneobaone.it` sulla root del
  progetto. Le regole di `.htaccess` instradano la root dei sottodomini alle
  rispettive aree e reindirizzano i percorsi `/admin` e `/pos` del dominio
  principale.
- Il routing e le protezioni definite in `.htaccess` richiedono Apache con
  `mod_rewrite` e `AllowOverride` abilitato. Se il server non supporta
  `.htaccess`, configura regole equivalenti nel web server prima del deploy.
- Verifica che `includes/config.php` e `vendor/` siano presenti e che PHP possa
  scrivere in `storage/email_log/` e in `storage/media/`. Le directory interne,
  i test, gli strumenti e gli script SQL sono protetti dall'accesso HTTP dalle
  regole Apache. Per l'archivio, imposta il cron di
  `photo/tools/process_zip.php` e `photo/tools/maintenance.php` e verifica i
  limiti reali dell'hosting (vedi `photo/README.md`).
