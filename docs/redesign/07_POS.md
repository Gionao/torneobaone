# Redesign interfaccia POS — Torneo Baone

## Ambito e direzione

Restyling presentazionale del POS per servizio durante eventi: identità Baone navy/oro, tipografia e fondazioni Baone UI, con priorità a leggibilità immediata, stato ordini e controlli touch. Il layer resta CSS/additivo: non cambia i flussi né i contenuti testuali italiani.

`pos/admin/settings.php` è un endpoint JSON GET/POST protetto e non una pagina di impostazioni. È stato deliberatamente lasciato invariato; non esiste in questo percorso una UI impostazioni da ridisegnare senza ampliare lo scope o inventare un nuovo flusso.

## File modificati

- `pos/operator_panel.php` — carica Baone UI e aggiunge la classe wrapper `tb-ui`; DOM funzionale invariato.
- `pos/bar_display.php`, `pos/kitchen_display.php` — caricamento Baone UI e wrapper `tb-ui`; markup KDS e script inline invariati.
- `pos/admin/menu_management.php`, `coupons.php`, `users.php`, `statistics.php`, `orders_history.php` — caricamento Baone UI e wrapper `tb-ui` sulle viste amministrative.
- `pos/assets/css/pos.css` — layer POS per navbar, cassa, griglia prodotti, carrello, coupon, KDS, storico e amministrazione; target touch, stati/focus e responsive.
- `docs/redesign/07_POS.md` — questo report.

La navbar role-aware in `pos/includes/navbar.php` e il suo markup sono rimasti invariati. Presentazione aggiornata dal CSS POS sui componenti `site-header` condivisi. `settings.php` rimane intatto.

## Preservazione funzionale

- Non modificati API/endpoints, payload, gestione risposte, polling, formule di prezzo, tariffe/coperti/asporto, logiche coupon o regole di business.
- Non modificati autenticazione, autorizzazioni, ruoli, selezione stazione e informazioni di stazione.
- Non modificati gli script inline per caricamento prodotti, personalizzazione/ingredienti, quantità, note, coupon, creazione ordine e polling coda.
- Non modificati i workflow bar/cucina, transizioni di stato, storico completati, riapertura e cancellazione.
- Selettori e attributi DOM funzionali mantenuti, inclusi `productsArea`, `cartList`, `btnConfirmOrder`, `modalRoot`, `kdsGrid`, `historyGrid`, `.btn-status`, `.btn-cancel`, `.item-ready-btn`, `.btn-deliver`, `.btn-reopen` e i relativi `data-*`.
- Baone UI viene caricata esplicitamente nelle pagine con body `.tb-ui`, mantenendone lo scope; nessuna dipendenza a pagamento o runtime aggiunta.

## Verifiche eseguite

- PHP lint richiesto per tutte le pagine POS modificate.
- `git diff --check` per whitespace.
- Controllo statico che i selettori e gli attributi JavaScript critici siano rimasti presenti.
- Verifica CSS per bilanciamento delle parentesi e presenza delle regole responsive/focus/reduced motion.
- Nessun test API o modifica a script di flusso effettuati.

## Checklist touch/usabilità

- [ ] Cassa a 1024×768 e 1366×768: aggiunta prodotto, quantità `−/+`, note, coupon e totale senza scroll imprevedibile.
- [ ] Conferma ordine: azione principale ben distinguibile; controllare doppio tocco e feedback nello stato ordine registrato.
- [ ] Toggle asporto, tavolo e coperti: verificare che i controlli rimangano leggibili e non coprano dati essenziali.
- [ ] Tablet in portrait/landscape: verificare layout singola colonna e raggiungibilità della coda.
- [ ] Bar/cucina: leggibilità a distanza, numero ordine, cliente/tavolo/asporto e note/modifiche; transizioni item pronto e consegna.
- [ ] Ordini attivi e storico: tab, data, caricamento, riapertura e stati distinguibili anche senza affidarsi soltanto al colore.
- [ ] Pagine amministrative: filtri, form, modifiche, eliminazioni con conferma e tabelle scorrevoli senza perdita di azioni.
- [ ] Tastiera/focus visibile, zoom 200%, reduced motion e viewport 768/1024/1280px.

## Controlli manuali ancora necessari / rischi

- Test end-to-end autenticati con account e dati POS autorizzati, in particolare creazione ordine, prezzi/coperti/asporto, coupon, annullamento, polling e riapertura.
- Verificare al banco l’ergonomia con touch reale: pannello coda sticky a desktop e impaginazione a singola colonna sotto 1100px possono richiedere taratura secondo la postazione effettiva.
- Controllare lunghe code e testi lunghi su display KDS, oltre a densità/contrasto in condizioni reali di luce.
- Verificare le pagine e le funzioni di amministrazione su dati di prova; non sono stati inviati form né alterati ordini.
- Nessun account POS e fixture live sono stati usati in questa sessione; non è attestato un test funzionale completo dei workflow. `settings.php` resta API-only; l’eventuale richiesta di una pagina impostazioni è un’attività distinta.
