# Gestione torneo e iscrizioni

Questa guida descrive la funzionalità torneo, distinta dal POS e dall'archivio
fotografico. Le pagine amministrative sono in `admin/`; il sito pubblico,
l'iscrizione e l'area capitani sono distribuiti tra la root, `iscrizione/` e
`capitano/`.

## Funzionalità

- Gestione di squadre, giocatori e roster.
- Ricezione delle iscrizioni dei capitani e gestione delle informazioni
  collegate alla squadra.
- Gestione di partite, calendario, risultati ed eventi.
- Registrazione delle transazioni e configurazione dei metodi di pagamento.
- Consultazione pubblica di calendario, classifica, partite e aggiornamenti live.
- Area capitano per gestire i giocatori e consultare stato e pagamenti.
- Verifica email e flussi di verifica/reset collegati agli account.

Nello schema attuale le iscrizioni sono rappresentate dai record in `squadre`,
`giocatori` e `capitani`; non esiste una tabella autonoma chiamata
`iscrizioni`.

## Aree e pagine principali

| Percorso | Funzione |
| --- | --- |
| `admin/login.php` | Login amministrativo. |
| `admin/index.php` | Dashboard organizzativa. |
| `admin/squadre/` | Elenco, dettagli e modifica squadre. |
| `admin/giocatori/` | Elenco, modifica e rimozione giocatori. |
| `admin/iscrizioni/` | Consultazione e gestione delle iscrizioni. |
| `admin/partite/` | Calendario, risultati, eventi e gestione partite. |
| `admin/pagamenti/` | Transazioni e metodi di pagamento. |
| `capitano/` | Registrazione, login, dashboard, roster e pagamenti capitano. |
| `iscrizione/` | Verifica email e consenso del genitore. |
| `calendario.php`, `classifica.php`, `partita.php`, `live.php` | Pagine pubbliche del torneo. |
| `telecronista/` | Accesso dedicato e gestione delle partite per telecronisti. |

Gli URL delle aree amministrative e del telecronista sono configurati tramite
`ADMIN_PATH` e `TELECRONISTA_URL` in `includes/config.php`.

## Account e autorizzazioni

- Il ruolo `admin` accede alle funzioni amministrative del torneo.
- Il ruolo `telecronista` è limitato alle funzioni di gestione partite previste
  per la telecronaca.
- I capitani usano account separati dalla tabella condivisa `admin` e accedono
  alla propria area tramite `capitano/`.
- Gli account POS (`operatore`, `bar`, `cucina`) e del drive (`uploader`,
  `editor`) sono gestiti nei rispettivi moduli e non equivalgono a un ruolo
  amministrativo del torneo.

Gli account organizzativi sono registrati nella tabella condivisa `admin`;
assegnare solo i ruoli necessari e non riutilizzare credenziali condivise. Per
un ambiente pubblico sostituire le credenziali iniziali di sviluppo e
configurare i segreti tramite variabili d'ambiente.

## Database

Il modulo usa il database MySQL condiviso, predefinito `my_torneobaone`, e la
connessione PDO comune `includes/db.php`. Le tabelle principali sono:

| Area | Tabelle |
| --- | --- |
| Accessi e torneo | `admin`, `tornei`, `fasi` |
| Squadre e roster | `capitani`, `squadre`, `giocatori` |
| Partite | `partite`, `eventi_partita`, `classifica` |
| Iscrizioni, documenti e consensi | `genitori`, `tipi_documento`, `documenti`, `consensi`, `token_verifica` |
| Pagamenti e configurazione | `metodi_pagamento`, `transazioni`, `impostazioni` |

Lo schema dedicato è `assets/sql/database_schema_torneo.sql`; quello combinato
`assets/sql/database_schema.sql` include anche POS e drive. Consultare il
[README generale](../README.md) per prerequisiti, configurazione e cautele
prima di installare o migrare il database.

Gli script `assets/sql/database_insert_torneo.sql` e
`assets/sql/database_insert_pos.sql` sono esclusivamente per sviluppo/test.
I dati demo del torneo comprendono account e dati fittizi; non importarli in
produzione.

## Configurazione email e limiti

Le email sono configurate centralmente con `SMTP_HOST`, `SMTP_PORT`,
`SMTP_USERNAME`, `SMTP_PASSWORD`, `SMTP_ENCRYPTION`, `MAIL_FROM_ADDRESS`,
`MAIL_FROM_NAME` e `MAIL_REPLY_TO`. Senza un server SMTP configurato, i flussi
che richiedono email di verifica o recupero account potrebbero non completarsi.

Il limite iscrizioni è configurabile tramite `MAX_SQUADRE` (predefinito 20).
Prima di aprire le iscrizioni, verificare il torneo attivo, i metodi di
pagamento, i documenti e i consensi richiesti, la configurazione email e le
pagine pubbliche del calendario/classifica.
