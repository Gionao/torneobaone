# Redesign iscrizioni e area capitani — Torneo Baone

**Stato:** aggiornamento UI implementato; flussi e regole esistenti mantenuti.  
**Ambito:** pagina informativa iscrizione, completamento invito giocatore, conferma genitore, verifica email, registrazione/login capitano, recupero account e pagine autenticate dashboard/giocatori/pagamenti.

## Direzione UX

- Impostazione smartphone-first, superfici navy e oro, font Barlow/Barlow Condensed e pattern diagonale ufficiale, usando lo scudetto compatto già presente nel Brand Kit.
- Iscrizione informativa con contenuto disposto in una colonna su mobile, passaggi numerati, documenti e contatti esistenti preservati.
- Form su schermi stretti in una colonna, controlli ampi, focus visibile e indicazione esplicita dei campi obbligatori nei form che la prevedono.
- Errori esposti con `role="alert"`, esiti con `role="status"`; aggiunte associazioni `label`/`for` e ID ai controlli dove mancavano, senza cambiare i loro `name`.
- Consenso foto/video esplicitamente facoltativo; privacy e regolamento restano obbligatori dove già richiesto.
- Verifica, conferma genitore, recupero e reset hanno stati visivi distinti senza introdurre una nuova schermata o un nuovo passaggio operativo.
- La dashboard sintetizza stato squadra, giocatori in rosa, iscrizioni completate, giocatori ancora da verificare, minorenni in attesa del genitore e saldo residuo. I valori sono derivati dalle query/dai record che la dashboard già carica; non sono introdotti nuovi calcoli di pagamento.
- Liste giocatori e pagamenti conservano colonne e azioni. La lista giocatori diventa una presentazione a schede su viewport stretti.
- Il testo CTA “È gratuito” è stato rimosso dalla pagina informativa perché la pagina stessa indica un contributo d'iscrizione; il collegamento apre lo stesso modulo esistente.

## Contratti preservati

- Nomi e valori POST restano invariati: `csrf_token`, `token`, `nome_squadra`, `colore_squadra`, `numero_giocatori_previsto`, dati capitano/giocatore, consensi, `action` e `id_giocatore`.
- Nessuna modifica a `required`, `min`, `max`, `maxlength`, metodi e destinazioni dei form, token CSRF/verifica, email, stati, controlli di autorizzazione/sessione o scadenze dei link.
- Nessuna modifica a query, DB, API, routing, calcoli di quota/saldo o operazioni di gestione giocatori.
- I link d'invito continuano a portare a `/iscrizione/giocatore.php`; il reinvio al genitore e le azioni copia/elimina mantengono i form e i token CSRF già presenti.
- La navigazione condivisa mantiene i suoi URL e comportamento. L'asset ufficiale viene selezionato solo tramite flag del layout per le superfici pertinenti; gli altri consumer dei partial non ricevono il nuovo asset/stile.

## File modificati

- `iscrizione.php`
- `iscrizione/giocatore.php`
- `iscrizione/conferma-genitore.php`
- `iscrizione/verifica-email.php`
- `capitano/_header.php`
- `capitano/login.php`
- `capitano/registrati.php`
- `capitano/dashboard.php`
- `capitano/giocatori.php`
- `capitano/pagamenti.php`
- `capitano/password-dimenticata.php`
- `capitano/richiedi-verifica.php`
- `capitano/verifica-email.php`
- `capitano/reset-password.php`
- `shared/site_nav.php` (logo scelto in base al flag di layout)
- `shared/footer.php` (logo scelto in base al flag di layout)
- `assets/css/registration-portal.css` (nuovo)
- `assets/css/captain-portal.css` (nuovo)
- `docs/redesign/04_ISCRIZIONI_CAPITANI.md` (nuovo)

I partial condivisi non caricano questi fogli CSS: sono inclusi solo dalle pagine del flusso interessato o dall'header capitano.

## Verifiche eseguite

- `php -l` superato su tutti i PHP sotto `capitano/` e `iscrizione/`, oltre a `iscrizione.php` e ai partial condivisi toccati.
- `git diff --check` senza errori di whitespace; gli avvisi mostrati da Git riguardano la conversione LF/CRLF su Windows.
- Controllo statico dei form: i nomi dei campi, i campi hidden `token`/`csrf_token`, gli `action` e le condizioni `required` previste sono rimasti presenti. CSS con parentesi graffe bilanciate e selettori nuovi confinati a classi del modulo.
- Chromium locale: `/iscrizione`, `/capitano/login`, `/capitano/verifica-email` rispondono e caricano i rispettivi CSS senza errori console. Il CSS di iscrizione non compare sulla pagina login e viceversa.
- Larghezza browser disponibile: 1000px, nessun overflow orizzontale nei tre template verificabili. Non era disponibile il controllo viewport mobile/tablet.
- Richieste GET senza token hanno mostrato gli stati di token mancante/non valido per verifica capitano e conferma genitore; nessuna azione di scrittura è stata inviata durante i test.
- Le pagine autenticate dashboard, giocatori e pagamenti redirigono al login senza sessione, come previsto.

## Blocco ambiente e test manuali richiesti

Nel checkout manca `vendor/autoload.php`. Le pagine che includono `includes/mailer.php` si interrompono prima del rendering HTML, perciò il browser non ha potuto verificare il CSS o gli stati di queste pagine: registrazione capitano, recupero password, richiesta verifica, completamento invito giocatore e verifica email giocatore. Non sono state installate dipendenze e non sono stati inviati messaggi.

Prima del rilascio, con vendor/configurazione e un account di test autorizzati, verificare:

1. **Registrazione capitano:** campi obbligatori, password minima, limite squadre, consensi, risposta agli errori, creazione account e redirect/login. Non ripetere POST che creino account senza una fixture dedicata.
2. **Verifica email capitano:** token valido, scaduto, già usato e mancante; attivazione account e CTA verso login.
3. **Password:** richiesta con email esistente e non esistente, risposta uniforme, link valido/scaduto e reset con password sotto/sopra il minimo.
4. **Invito giocatore:** link valido/scaduto/consumato, campi e consensi obbligatori, reinvio email, copia link in browser sicuro e fallback clipboard.
5. **Minorenni:** date ai due lati della soglia di maggiore età, campi genitore che compaiono, invio verifica giocatore, approvazione genitore, consenso facoltativo foto e reinvio link dal portale.
6. **Dashboard e pagamenti:** squadra senza giocatori, stati misti (non registrato/invitato/in attesa genitore/completo), importi confermati/in attesa/annullati, quota residua zero e parziale. Confrontare ogni importo con gli stessi record e non modificare transazioni reali.
7. **Viewport e accessibilità:** 360/390px, 768/820px e desktop; navigazione mobile, ordine Tab, focus, zoom 200%, etichette/errori con screen reader e `prefers-reduced-motion`.

I flussi con invio email, token e pagamenti non sono stati provati end-to-end né simulati con modifiche ai dati. Il test browser disponibile copre la resa dei template accessibili, non una sessione capitano autenticata né la validità delle email effettive.
