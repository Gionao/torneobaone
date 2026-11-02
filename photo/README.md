# Archivio multimediale privato — Torneo di Baone

Applicazione PHP/MySQL per conservare foto e video del torneo, organizzati in
**Edizione → Giornata → Album → file**. L'archivio è privato: non esiste una
pagina pubblica, non esiste registrazione autonoma e nessun file viene servito
direttamente dal web.

L'interfaccia e i messaggi sono in italiano. Il codice applicativo dell'archivio
si trova interamente in `photo/`; l'unica integrazione con il resto del progetto
è l'uso della tabella `admin`, della sessione, di `includes/config.php` e dei
collegamenti presenti nel pannello amministrativo.

## Ruoli

| Ruolo | Permessi |
| --- | --- |
| `uploader` (Collaboratore) | Carica file solo nelle edizioni assegnate; vede i contenuti delle edizioni assegnate, ma scarica ed elimina solo i propri file. |
| `editor` | Vede, scarica e organizza tutte le edizioni, giornate e album; crea ZIP. Non carica e non elimina file. |
| `admin` (Amministratore) | Tutti i permessi: utenti, struttura, caricamento, file, cestino, impostazioni e manutenzione. Può anche annullare i caricamenti in sospeso di altri utenti. |

I controlli sono sempre ripetuti lato server. Nascondere un pulsante non è
considerato una protezione.

## Formati e gestione dei file

- Formati ammessi: JPG, JPEG, PNG, HEIC, MP4, MOV.
- Il tipo reale viene verificato con `finfo`; per JPG/PNG vengono controllati
  anche tipo immagine e dimensioni. Il MIME dichiarato dal browser non è mai
  considerato attendibile.
- L'originale viene conservato senza modifiche. La miniatura è generata solo per
  JPG/JPEG/PNG con GD; HEIC e MOV restano scaricabili senza anteprima.
- Nome originale, uploader, data, dimensione, gerarchia e SHA-256 sono salvati
  nel database.
- Se lo SHA-256 corrisponde a un file già presente, l'utente riceve un avviso:
  il caricamento **non** viene rifiutato e il file **non** viene deduplicato.
- I file sono salvati in `storage/media` per impostazione predefinita, fuori
  dalla cartella applicativa e protetti da `.htaccess`. Il percorso può essere
  cambiato dalle impostazioni (anche assoluto, fuori dalla document root).

## Upload a blocchi e ripresa

L'upload avviene a blocchi con avanzamento e verifica SHA-256 di ogni blocco.
Se la connessione si interrompe, lo stato resta in `media_upload` e il file
`.part` viene conservato. L'utente può riprendere scegliendo di nuovo lo stesso
file: il client verifica nome e dimensione e riparte dal blocco successivo.

- Il blocco effettivo è ridotto automaticamente all'80% di
  `upload_max_filesize`/`post_max_size` (minimo 256 KB).
- Un caricamento appartiene all'utente che lo ha avviato; gli altri utenti non
  possono leggere, scrivere, completare o annullare quello stato.
- Al completamento il file viene validato, spostato con un rename atomico,
  registrato nel database e solo allora reso visibile.
- Upload abbandonati da più di 48 ore vengono annullati dalla manutenzione.
- Il limite configurato è 1024 MB per file, ma **non è una garanzia** che
  l'hosting scelto lo supporti. Il limite reale dipende da spazio disco,
  timeout PHP/FastCGI, proxy, CDN e politiche del provider. Prima del deploy
  verificare con un file reale delle dimensioni massime previste.

## ZIP

Editor e amministratori possono creare ZIP di un'edizione, una giornata, un
album o una selezione di file. La generazione richiede l'estensione PHP
`zip` (`ZipArchive`): se manca, l'interfaccia lo segnala e l'operazione viene
rifiutata con un messaggio in italiano, senza bloccare il resto dell'archivio.
La generazione avviene in un job persistente:

- fino a `media_zip_max_file` file e `media_zip_max_mb` MB complessivi;
- suddivisione in parti da `media_zip_parte_mb` MB;
- nomi delle voci ripuliti e resi univoci, senza path traversal;
- parti salvate in `zip_tmp`, con scadenza configurabile e cancellazione
  automatica;
- job bloccati possono essere ripristinati dalla manutenzione.

Il job viene elaborato anche durante il polling dello stato. Per installazioni
con molto traffico è consigliabile eseguire il cron:

```bash
php photo/tools/process_zip.php --max=3
```

## Sicurezza

- Autenticazione condivisa con il pannello del torneo, senza un secondo sistema
  di login e senza registrazione. A ogni richiesta l'account viene riletto dal
  database: eliminare un utente o cambiarne il ruolo invalida subito i vecchi
  permessi nella sessione.
- CSRF su tutte le operazioni di modifica; token verificato con `hash_equals`.
- Query preparate PDO con `ATTR_EMULATE_PREPARES = false`.
- Escape HTML con `htmlspecialchars(..., ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')`.
- Nessun percorso fornito dal client viene usato direttamente: i file sono
  identificati da ID e i percorsi salvati vengono validati segmento per
  segmento.
- Download e miniature passano solo da endpoint PHP autorizzati; lo storage è
  negato dal web tramite `.htaccess` e, se il percorso è nella document root,
  vengono create protezioni aggiuntive.
- Messaggi di errore generici verso l'utente; i dettagli tecnici vanno solo nel
  log del server. Il registro `media_log` non contiene password né contenuti.
- Cookie di sessione `HttpOnly`, `SameSite=Strict`, `Secure` in produzione.
- `X-Content-Type-Options`, `X-Frame-Options`, `Referrer-Policy` e
  `X-Robots-Tag: noindex` sulle pagine dell'archivio.
- Nessun contenuto viene inviato a servizi esterni.

## Installazione e migrazioni

Prerequisiti PHP: `pdo_mysql`, `gd`, `zip`, `exif`, `fileinfo`. MySQL 8 o
compatibile, Apache con `mod_rewrite` e `AllowOverride` abilitato.

Applicare le migrazioni **in ordine**, una sola volta, su un database di
sviluppo/staging e con backup disponibile:

```bash
mysql -u utente -p nome_database < assets/sql/migration_media_001_schema.sql
mysql -u utente -p nome_database < assets/sql/migration_media_002_admin_roles.sql
mysql -u utente -p nome_database < assets/sql/migration_media_003_settings.sql
mysql -u utente -p nome_database < assets/sql/migration_media_004_accessi_selezione.sql
```

Le migrazioni sono additive: creano tabelle, aggiungono i ruoli `uploader` ed
`editor` all'enum esistente e inseriscono le impostazioni con `INSERT IGNORE`.
Non eliminano dati preesistenti.

Gli script `*_down.sql` sono distruttivi e servono solo per test o ripristini
controllati. Non eseguirli in produzione senza autorizzazione e backup
verificato. La migrazione 002 down fallisce se esistono ancora account con i
ruoli `uploader`/`editor`: vanno prima riassegnati o rimossi.

Dopo le migrazioni, creare o assegnare gli account dalla pagina **Utenti**.
Gli account `uploader` devono avere almeno una edizione assegnata.

Se l'archivio foto e le sue tabelle sono già installati ma la colonna
`admin.role` non include ancora `uploader` ed `editor`, applicare una volta
`assets/sql/migration_admin_ruoli_photo.sql` al database del sito. In
phpMyAdmin selezionare il database corretto, aprire **Importa** e caricare
quel file. La modifica conserva gli account e i ruoli esistenti.

## Configurazione

Variabili d'ambiente lette da `includes/config.php`:

- `ARCHIVIO_URL`: URL dell'area riservata; predefinito `SITE_URL/photo/admin`.
- `APP_ENV`, `DEBUG_MODE`, `SITE_URL`.

Impostazioni dell'archivio nella tabella `impostazioni` (modificabili da
**Impostazioni**):

| Chiave | Predefinito | Note |
| --- | --- | --- |
| `media_storage_path` | vuoto | Vuoto = `storage/media` nella radice del progetto. |
| `media_max_upload_mb` | 1024 | Limite applicativo per file; non garantisce la capacità dell'hosting. |
| `media_chunk_mb` | 4 | Ridotto automaticamente in base ai limiti PHP. |
| `media_thumb_max_px` | 480 | Lato massimo miniature. |
| `media_retention_giorni` | 30 | Giorni nel cestino prima dell'eliminazione definitiva. |
| `media_zip_max_file` | 2000 | File massimi per ZIP. |
| `media_zip_max_mb` | 5000 | Dimensione massima totale ZIP. |
| `media_zip_parte_mb` | 1500 | Dimensione massima di una parte. |
| `media_zip_scadenza_ore` | 24 | Ore di disponibilità delle parti ZIP. |

Le variabili d'ambiente con lo stesso nome (maiuscolo) hanno precedenza; i
valori salvati nel database hanno precedenza sui default.

## Manutenzione e cron

```bash
php photo/tools/maintenance.php
php photo/tools/process_zip.php --max=3
```

La manutenzione rimuove upload interrotti da più di 48 ore, file nel cestino
oltre la conservazione configurata, ZIP scaduti, ZIP bloccati e quarantena
vecchia. Può essere eseguita anche da **Impostazioni → Manutenzione**.

Esempio di crontab (adattare percorsi e utente):

```cron
*/15 * * * * /usr/bin/php /percorso/progetto/photo/tools/process_zip.php --max=3
30 3 * * * /usr/bin/php /percorso/progetto/photo/tools/maintenance.php
```

## Backup e ripristino

Backup minimo indispensabile:

1. database MySQL (tabelle `admin`, `impostazioni`, `media_*`);
2. intera cartella dello storage (`storage/media` o il percorso configurato),
   comprese `originali/`, `miniature/`, `upload_tmp/`, `zip_tmp/`,
   `quarantena/` e `log/`.

Senza entrambi non è possibile ricostruire l'archivio: il database contiene i
metadati e i percorsi, lo storage contiene gli originali.

Esempio:

```bash
mysqldump -u utente -p --single-transaction nome_database > backup-db.sql
rsync -a storage/media/ /backup/media/
```

Ripristino: ripristinare il database, ripristinare la cartella storage nello
stesso percorso (o aggiornare `media_storage_path`), verificare i permessi di
scrittura e lanciare la manutenzione. Verificare alcuni download e un
ripristino dal cestino prima di considerare concluso il ripristino.

I backup devono essere conservati fuori dalla document root e non devono essere
accessibili via HTTP.

## Test eseguiti

Ambiente dei test: Apache con PHP 8.4.12 (`pdo_mysql`, `gd`, `zip`, `fileinfo`),
MySQL 8.0.30, database locale di sviluppo e un database separato usa-e-getta
per le migrazioni. Non è stato usato alcun hosting di produzione.

- Lint PHP su tutti i file di `photo/` e controllo sintattico dei due file
  JavaScript.
- Migrazioni 001-004 applicate in ordine; ciclo completo up/down su database
  usa-e-getta, senza errori.
- Login e matrice di autorizzazione: uploader limitato alle edizioni assegnate,
  editor senza accesso a utenti/cestino/impostazioni, uploader senza accesso
  alla struttura, API anonime respinte, pagine admin anonime reindirizzate al
  login. Cambio ruolo ed eliminazione account verificati: i vecchi permessi
  nella sessione vengono invalidati alla richiesta successiva.
- Upload a blocchi: avvio, interruzione dopo il primo blocco, ripresa dal
  blocco successivo, completamento con SHA-256 e miniatura corretti; upload
  reale eseguito anche dall'interfaccia browser con avanzamento e nessun errore
  in console.
- Duplicati: avviso mostrato e file comunque salvato, senza deduplicazione.
- Input ostili: nome con path traversal normalizzato, doppia estensione
  (`evil.php.jpg`) rifiutata, blocco con hash errato rifiutato, nome file con
  HTML escaped in galleria.
- Download: `Content-Disposition`, `ETag`, richiesta `Range` con risposta 206 e
  byte corretti, `Range` non valido 416, `If-None-Match` 304, `HEAD` 200;
  accesso diretto allo storage negato (403).
- Cestino: eliminazione dei propri file da parte dell'uploader, diniego
  sull'eliminazione dei file altrui, editor senza permesso di eliminazione,
  ripristino ed eliminazione definitiva solo admin, con rimozione di originale
  e miniatura.
- ZIP: album, selezione e suddivisione in due parti con archivi validi;
  controllo di proprietà del job, job scaduto/non valido 404, pulizia alla
  scadenza, elaborazione da CLI e recupero di un job bloccato.
- Struttura, utenti e impostazioni: CRUD completo, validazioni, blocco
  dell'eliminazione di contenitori non vuoti, richieste POST senza CSRF
  respinte, ID non validi gestiti senza errori PHP.
- PNG, HEIC e MP4 sintetici accettati con MIME corretto; HEIC/MP4 senza
  miniatura ma scaricabili.

I file HEIC e MP4 usati nei test erano contenitori sintetici, non media reali
codificati da dispositivi. I test non dimostrano la capacità di un hosting
reale: timeout, proxy, spazio disco e limiti del provider vanno verificati
nell'ambiente di produzione scelto. Non è stato testato un singolo file da
1 GB né un caricamento attraverso proxy/CDN reali.

## Struttura

```text
photo/
├── index.php                 # Redirect all'area riservata
├── admin/                    # Interfaccia riservata
│   ├── index.php             # Panoramica
│   ├── login.php, logout.php
│   ├── galleria.php, file.php, file_delete.php
│   ├── upload.php
│   ├── cestino.php
│   ├── utenti.php
│   ├── impostazioni.php
│   ├── edizioni/             # CRUD edizioni
│   ├── giornate/             # CRUD giornate
│   └── album/                # CRUD album
├── api/                      # Download, miniature, stato upload, ZIP
├── upload/                   # Protocollo di upload a blocchi
├── includes/                 # Config, auth, repository, storage, ZIP
├── assets/                   # CSS e JavaScript
└── tools/                    # Cron CLI (manutenzione e ZIP)
```

## Limiti noti

- Non è stato testato su un hosting reale; non viene dichiarata una capacità
  massima verificata. Il limite di 1024 MB è solo un'impostazione applicativa.
- HEIC, MP4 e MOV non hanno anteprima/miniatura server-side: restano
  scaricabili e il file viene conservato senza modifiche.
- La protezione anti brute-force del login condiviso è quella del progetto:
  massimo 5 tentativi in 15 minuti, ma il conteggio è nella sessione. Non è una
  difesa per account o per IP e può essere aggirata cancellando il cookie; per
  la produzione usare anche rate limiting/WAF del provider.
- La generazione ZIP usa `ZipArchive` e legge i file da disco: richiede che lo
  storage sia locale o montato in modo affidabile. L'estensione `zip` deve
  essere abilitata in PHP, altrimenti l'operazione viene rifiutata con un
  messaggio esplicito.
- Un solo job ZIP attivo per utente; job molto grandi possono richiedere il
  cron e un tuning dei timeout. Il polling dello stato elabora i job in modo
  best-effort, non è un worker asincrono.
- Non è integrata una scansione antivirus: i file vengono validati per tipo,
  MIME e (per le immagini) contenuto, ma non analizzati con un motore AV.
- Il progetto non include un sistema di invio email per gli account
  dell'archivio: le credenziali vengono comunicate fuori banda.
