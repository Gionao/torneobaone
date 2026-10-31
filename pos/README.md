# POS

Il modulo POS gestisce il menu e gli ordini del torneo. È implementato in
`pos/` e usa il database condiviso `my_torneobaone`, la tabella comune `admin`
per gli account e le tabelle `pos_*` per menu, ordini e impostazioni.

## Funzionalità

- Inserimento ordini dal pannello operatore e gestione del menu.
- Schermi di preparazione distinti per bar e cucina.
- Aggiornamento dello stato degli ordini e visualizzazione degli ordini
  attivi/completati.
- Storico ordini e statistiche.
- Gestione prodotti, categorie, ingredienti, modifiche, coupon e impostazioni.
- Gestione degli account POS da parte dell'amministratore del POS.

## Accesso e ruoli

Accedere da `POS_URL` (predefinito locale: `http://torneobaone.test/pos`) o
aprire `pos/login.php`.

Gli account sono condivisi con gli altri moduli nella tabella `admin`, ma
possono accedere al POS solo i ruoli seguenti:

| Ruolo | Accesso principale |
| --- | --- |
| `admin` | Pannello operatore, bar, cucina, menu, coupon, utenti, statistiche e storico. |
| `operatore` | Inserimento ordini e storico secondo le autorizzazioni POS. |
| `bar` | Schermo bar e aggiornamento degli ordini consentiti. |
| `cucina` | Schermo cucina e aggiornamento degli ordini consentiti. |

Gli utenti POS si creano dalla sezione **Utenti** del POS con un account
amministratore. Un operatore deve avere il nome cassa (`station_name`).
Eliminare o cambiare il ruolo di un account invalida l'accesso POS al controllo
successivo.

## Database e installazione

In una nuova installazione completa, seguire il [README generale](../README.md)
e importare `assets/sql/database_schema.sql`. Per installare il POS come modulo
separato:

```sh
mysql -u utente -p < assets/sql/database_schema_pos.sql
```

Lo schema seleziona il database `my_torneobaone`, crea le tabelle condivise
minime mancanti e le tabelle POS. L'account `admin` deve comunque essere
predisposto con una password sicura tramite lo schema torneo o la procedura
amministrativa. Per dati dimostrativi, importare
`assets/sql/database_insert_pos.sql` solo in sviluppo/test: crea prodotti e
ordini storici fittizi.

Le tabelle POS principali sono:

- `pos_settings`
- `pos_categories`, `pos_products`, `pos_ingredients`,
  `pos_product_ingredients`
- `pos_coupons`
- `pos_orders`, `pos_order_items`, `pos_order_item_modifications`

Gli ordini conservano la copia dei prezzi applicati e possono restare nello
storico anche quando viene eliminato l'account associato.

## API e organizzazione

- `pos/operator_panel.php`: inserimento e gestione operativa.
- `pos/bar_display.php`, `pos/kitchen_display.php`: monitor di preparazione.
- `pos/admin/`: menu, coupon, utenti, statistiche, storico e impostazioni.
- `pos/api/`: endpoint per prodotti, coupon, ordini e stati.
- `pos/includes/auth_check.php`: sessione e controllo dell'account aggiornato.
- `pos/includes/role_check.php`: autorizzazione per ruolo, anche sulle API.

Le API sono endpoint interni del modulo, non un'API pubblica documentata per
integrazioni esterne. Le pagine e gli endpoint protetti devono essere raggiunti
con una sessione POS valida.

## Configurazione e manutenzione

La connessione usa la configurazione centrale in `includes/config.php`:
`DB_HOST`, `DB_NAME`, `DB_USER`, `DB_PASS` e `DB_CHARSET`. Gli URL sono
configurabili tramite `POS_URL` e `SITE_URL`. Non salvare credenziali reali o
password degli operatori nei file SQL o nel repository.

Prima dell'uso reale, configurare il menu, verificare prezzi e supplementi,
creare account POS individuali e controllare che le postazioni bar/cucina
possano accedere solo alle schermate assegnate. Per backup e ripristino del
database condiviso, consultare il [README generale](../README.md) e la sezione
backup nella guida [photo](../photo/README.md).
