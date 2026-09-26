# STRUTTURA FILE — Torneo Calcio a 5 Baone 2026
# Da caricare via FTP nella root del tuo sito Altervista

torneo-baone/
│
├── database_schema.sql          ← Importa su phpMyAdmin (non caricare online!)
│
├── includes/
│   ├── config.php               ← Costanti DB (host, nome db, user, pass)
│   ├── db.php                   ← Connessione PDO singleton
│   ├── auth.php                 ← Funzioni sessione / login admin
│   ├── classifica_engine.php    ← Ricalcolo classifica (chiamato dopo ogni risultato)
│   └── functions.php            ← Helper generali (formatta data, ecc.)
│
├── partials/
│   ├── header.php               ← <head> + navbar pubblica
│   ├── footer.php               ← Footer + script JS
│   └── admin_header.php         ← Header pannello admin
│
├── assets/
│   ├── css/
│   │   ├── style.css            ← Stile globale pubblico
│   │   └── admin.css            ← Stile pannello admin
│   ├── js/
│   │   ├── main.js              ← JS front-end (countdown, animazioni)
│   │   └── admin.js             ← JS pannello admin (confirm, AJAX)
│   └── img/
│       ├── logo_torneo.png      ← Logo torneo (caricato da te)
│       └── favicon.ico
│
│── ── SEZIONE PUBBLICA ──────────────────────────────────────
│
├── index.php                    ← Home: presentazione, countdown, social
├── regolamento.php              ← Regolamento leggibile + download PDF
├── calendario.php               ← Partite per fase con data/ora/campo
├── classifica.php               ← Tabella girone in tempo reale
├── partita.php                  ← Dettaglio partita (tabellino, eventi)
│
│── ── PANNELLO ADMIN (protetto da sessione) ─────────────────
│
├── admin/
│   ├── index.php                ← Dashboard admin (statistiche rapide)
│   ├── login.php                ← Form login admin
│   ├── logout.php               ← Distrugge sessione e redirect
│   │
│   ├── squadre/
│   │   ├── index.php            ← Lista squadre
│   │   ├── form.php             ← Aggiungi / modifica squadra
│   │   └── delete.php           ← Elimina squadra (con confirm JS)
│   │
│   ├── giocatori/
│   │   ├── index.php            ← Lista giocatori per squadra
│   │   ├── form.php             ← Aggiungi / modifica giocatore
│   │   └── delete.php
│   │
│   ├── partite/
│   │   ├── index.php            ← Calendario completo
│   │   ├── form.php             ← Crea / modifica partita (data, ora, campo)
│   │   ├── risultato.php        ← Inserimento risultato → trigger ricalcolo
│   │   ├── eventi.php           ← Aggiungi gol/cartellini/MVP a una partita
│   │   └── delete.php
│   │
│   └── impostazioni/
│       └── password.php         ← Cambio password admin
│
│── ── API (opzionale, per aggiornamenti AJAX) ───────────────
│
└── api/
    ├── classifica.php           ← Restituisce JSON classifica (per polling JS)
    └── partite.php              ← Restituisce JSON prossime partite / risultati
