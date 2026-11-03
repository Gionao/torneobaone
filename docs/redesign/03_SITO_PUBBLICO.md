# Redesign del sito pubblico — Torneo Baone

**Stato:** implementato per le cinque pagine richieste.  
**Perimetro:** `index.php`, `calendario.php`, `classifica.php`, `partita.php`, `live.php`, navigazione/footer condivisi e foglio CSS pubblico dedicato.

## Decisioni di design

- Restyling editoriale navy/oro mobile-first, con tipografia Barlow e Barlow Condensed, gerarchia chiara e componenti riconoscibili per risultati e tabellone.
- Hero della home basato sullo scudetto ufficiale e sulle strisce diagonali del brand. Non è disponibile una fotografia pubblica autorizzata nel repository; le foto dell'archivio privato non sono state usate e non si è simulata una fotografia.
- Lo scudetto compatto del Brand Kit è usato nella navigazione e nel footer solo sulle cinque pagine pubbliche interessate. Le altre superfici che condividono i partial conservano il logo precedente e il loro stile.
- CSS in `assets/css/public-redesign.css`, aggiunto dopo il legacy solo alle cinque pagine richieste. Le classi, gli ID e gli attributi dati esistenti restano disponibili per PHP e JavaScript.
- Il colore delle squadre e i loro logo continuano a provenire dai record esistenti; le card non sostituiscono dati mancanti con contenuti inventati.
- L'edizione visibile nelle viste pubbliche resta quella già indicata nei contenuti (2026). La configurazione `SITE_NAME` riporta 2027: la discrepanza è preesistente e non è stata risolta inventando un'edizione corrente.
- Nessun contenuto sponsor aggiunto: non è stato individuato un insieme approvato di dati/asset sponsor.

## Contenuti e comportamento preservati

### Home

- Prossime partite (massimo 4), match in evidenza, conto alla rovescia, risultati recenti (massimo 3), informazioni torneo e link social esistenti conservati.
- Aggiunta anteprima delle prime quattro posizioni leggendo `classifica` con lo stesso ordinamento/tie-break già usato in `classifica.php`. Se la query non produce dati, la sezione non compare.
- Aggiunto banner live solo per la prima partita non giocata con data/ora raggiunta, coerente con il criterio di selezione iniziale della pagina `live.php`. Il banner rimanda alla pagina live; non inventa un punteggio.
- Restano invariati `#countdown`, `#cd-days`, `#cd-hours`, `#cd-mins`, `#cd-secs`, `#next-match-countdown`, `data-target` e le classi di reveal.

### Calendario

- Filtri fase/stato e query string `fase` / `stato` invariati; restano card cliccabili, loghi, orari, campo, stato partita, punteggio, rigori, vincitore e regola di visibilità per telecronista.
- Le partite continuano a collegare a `partita.php?id=…`. Empty state e messaggio nessun risultato per filtri sono conservati.

### Classifica

- Query e ordinamento non modificati; tutte le colonne esistenti, indicazione posizioni, collegamenti squadra e sezione top 10 marcatori conservati.
- La tabella mantiene tutte le colonne e scorre orizzontalmente sugli schermi stretti anziché eliminare dati.

### Dettaglio partita

- Query partita/eventi, marcatrici, autoreti, rigori, cartellini, espulsioni, MVP, note, etichetta vincitore e redirect esistenti invariati.
- Il tabellone mostra lo stato previsto: punteggio finale se disponibile, `N/D` se conclusa senza punteggio e `In programma` altrimenti.

### Live e navigazione

- Selezione evento per squadra e calcolo punteggio da gol/autoreti non modificati. La selezione della gara e il meta refresh HTML ogni 10 secondi restano invariati.
- La navigazione condivisa mantiene tutte le destinazioni e i percorsi; il comportamento mobile/focus/escape di `assets/js/site-nav.js` non è stato modificato.
- La pagina informativa `iscrizione.php` e il login capitano (`/capitano/login`) sono stati aperti/ispezionati. Il loro markup e CSS non sono stati ridisegnati; i link esistenti rimangono raggiungibili.

## File modificati

- `index.php`
- `calendario.php`
- `classifica.php`
- `partita.php`
- `live.php`
- `shared/header.php`
- `shared/site_nav.php` (asset logo condizionale per le sole cinque pagine)
- `shared/footer.php` (classe e asset logo condizionali, nessuna variazione per gli altri consumer)
- `assets/css/public-redesign.css` (nuovo)
- `docs/redesign/03_SITO_PUBBLICO.md` (nuovo)

Non sono stati modificati API, SQL schema/migrazioni, autenticazione, routing, logica ranking/eventi, database o JavaScript applicativo.

## Test eseguiti

- `php -l` superato per le cinque pagine e i tre partial condivisi.
- `git diff --check` senza errori di whitespace. Git segnala solo la normale conversione LF/CRLF dei file PHP su Windows.
- Browser locale Chromium su home, calendario, classifica, live, dettaglio partita `id=1`, pagina informativa iscrizione e login capitano: nessun errore in console; CSS pubblico risponde `200` sulle cinque viste e non è caricato sulle due pagine d'ingresso iscrizione/capitano. Il dettaglio `id=1` mostra le due squadre e lo stato “In programma”.
- Stato dati osservato nel database locale: calendario popolato con partita programmata; home mostra un match non giocato il cui orario è raggiunto e il banner live; pagina live mostra il punteggio derivato dagli eventi e la nota di aggiornamento automatico. In questa fixture la classifica è vuota, quindi è stato controllato il relativo empty state; non è stato possibile verificare nel browser la variante classifica popolata né un match concluso.
- Verificati in DOM i collegamenti verso filtri calendario, dettaglio partita `id=1`, classifica, diretta e iscrizione. I percorsi puliti della navigazione condivisa restano intatti.
- Viewport browser disponibile durante il test: 1000px. Verificato che non c'è overflow orizzontale in questa larghezza; richieste CSS e logo completate senza errore.
- Nessun test è stato eseguito scrivendo dati. Le route protette non sono state usate per alterare contenuti.

## Verifiche manuali ancora necessarie

- Controllare visualmente a 360/390px, tablet 768/820px e desktop largo. L'ambiente browser usato in questa sessione non esponeva un controllo per emulare/resettare la viewport.
- Verificare con una fixture autorizzata: classifica piena e top scorer, nessuna partita, una partita conclusa con punteggio/rigori/eventi/MVP, partita conclusa senza punteggio e loghi mancanti.
- Verificare tastiera su navigazione mobile (apri/chiudi, Escape, focus ripristinato), link di calendario/classifica e focus visibile; controllare contrasto/zoom 200% e `prefers-reduced-motion`.
- Verificare che il banner home appaia in presenza di una gara iniziata e non compaia senza gare iniziate; controllare la visualizzazione dopo il refresh live a 10s.
- Raccogliere approvazione per una fotografia torneo pubblicabile, se si desidera sostituire la composizione grafica attuale. Nessuna sezione sponsor è stata aggiunta.

## Compatibilità e limite del test

La nuova grafica viene caricata esclusivamente dalle cinque pagine richieste. Il flag di layout nei partial condivisi discrimina i file pubblici esatti; consumer come iscrizione/capitano e moduli privati mantengono asset e classi precedenti. La pagina non modifica i link canonici/route esistenti. Il controllo browser non copre tutte le combinazioni di fixture e viewport; le verifiche elencate sopra restano necessarie prima del rilascio.
