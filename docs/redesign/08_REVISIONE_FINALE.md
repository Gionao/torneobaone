# Revisione finale del redesign — Torneo Baone

**Data:** 3 novembre 2026  
**Confronto:** `redesign` (`388665e`) rispetto a `master` (`4ba506c`)  
**Perimetro:** 121 file cambiati, con 4.543 inserimenti e 765 rimozioni. Revisione statica del codice e verifiche HTTP locali non distruttive. Nessun file applicativo è stato modificato durante questa revisione.

## 1. Sintesi esecutiva

Il redesign è prevalentemente composto da presentazione, CSS e componenti condivisi. I controlli sul diff non mostrano modifiche a schema/migrazioni SQL, regole di rewrite, endpoint API, autenticazione o helper dei ruoli. Il confronto statico delle firme dei campi dei form PHP modificati non ha rilevato variazioni a metodi, action, nomi, tipi o valori hidden nei template confrontati. Questi risultati supportano la conservazione dei contratti lato server, ma non dimostrano da soli che ogni flusso funzioni end-to-end.

La revisione ha trovato problemi concreti che impediscono di approvare il rilascio: data non valida nel countdown della home; frammenti HTML orfani in tre intestazioni; codice residuo che cerca un ID navbar rimosso nella pagina iscrizioni; shell HTML duplicata nella pagina di login capitano; metrica del portale capitano che classifica in modo errato alcuni stati giocatore. Non sono state eseguite prove browser interattive, visuali o con account/ruoli distinti.

**Decisione: NO-GO per il deployment.** Si può proseguire con correzioni UI circoscritte mantenendo invariati schema, logica, API, autorizzazioni, campi dei form e URL; prima del rilascio vanno risolti i rilievi HIGH e ripetuti i controlli manuali indicati.

## 2. Riscontri

### HIGH — Countdown della home usa una data non interpretabile

**File e posizione:** `index.php:62,93`; `assets/js/main.js` (inizializzazione countdown, circa righe 80–105).

**Problema:** `$data_torneo` è impostata a `2027-07-12Ins 21:00:00`. La stringa contiene `Ins` e non rappresenta una data valida per `Date` in JavaScript. Il valore viene passato all'attributo `data-target` del countdown. Il codice JS calcola i valori senza un controllo che gestisca una data invalida. La home contiene inoltre testi riferiti all’edizione 2026, mentre l’anno predefinito della navbar è 2026.

**Evidenza:** il valore è presente nel sorgente PHP della branch; il controllo statico della routine mostra l’uso del target per calcolare giorni/ore/minuti/secondi senza ramo di errore per `NaN`.

**Rischio:** il countdown pubblico mostra valori non numerici o non aggiornati; l’utente riceve informazioni contraddittorie sulla data dell’edizione.

**Correzione raccomandata:** impostare una data valida e approvata per l’edizione, conservarla in un’unica fonte di configurazione e aggiungere al componente un comportamento leggibile in caso di valore assente/invalido. Verificare la coerenza delle etichette dell’anno senza cambiare URL o logica applicativa.

### HIGH — Testo orfano in tre elementi `<head>`

**File e posizione:** `admin/login.php:50`; `squadra.php:109`; `squadre.php:39`.

**Problema:** dopo il tag favicon è rimasto il frammento letterale `/assets/brand/01_loghi/png/logo_principale.png\">`, privo dell’inizio del tag `<link>`.

**Evidenza:** il testo compare direttamente nei tre sorgenti tra i metadati dell’head. Le pagine rispondono HTTP 200, ma la risposta positiva non valida il markup.

**Rischio:** markup non valido e contenuto inatteso nell’head; i parser HTML possono recuperare il documento in modo diverso e produrre DOM non uniforme. Il sorgente non supera una validazione HTML affidabile.

**Correzione raccomandata:** rimuovere il frammento residuo e validare l’HTML renderizzato delle pagine interessate. Verificare separatamente i link a favicon e touch icon.

### HIGH — Script della pagina iscrizioni usa un selettore navbar non più presente

**File e posizione:** `iscrizione.php:672–676`; `shared/site_nav.php:33`; `assets/js/site-nav.js`.

**Problema:** lo script inline di `iscrizione.php` continua a eseguire `document.getElementById('navbar').style.background`, ma il nuovo header usa `data-site-nav` e non espone `id="navbar"`. La gestione del menu è stata spostata nel nuovo script condiviso.

**Evidenza:** il selettore non trova corrispondenza nel markup del nuovo header. L’accesso a `.style` su `null` genera un errore quando l’handler scroll viene eseguito. Lo stesso blocco script è collocato dopo l’inclusione di `shared/footer.php`, che chiude già body e html.

**Rischio:** errore JavaScript in console e interazione di scroll interrotta su iscrizione; markup del documento non valido per il codice collocato dopo la chiusura del documento.

**Correzione raccomandata:** eliminare o aggiornare il comportamento residuo usando il componente navbar attuale e spostare script/stili prima della chiusura del documento tramite i punti di inclusione previsti. Non ripristinare ID o contratti non più usati senza verificare tutti i consumatori.

### HIGH — Shell HTML duplicata nel login capitano

**File e posizione:** `capitano/login.php:44–68,103–105`; `shared/header.php`; `shared/footer.php:56–57`.

**Problema:** il template di login mantiene il proprio documento HTML e include l’header condiviso all’interno del body; il footer condiviso emette già `</body></html>`, dopo il quale il template ripete entrambe le chiusure.

**Evidenza:** il sorgente contiene l’inclusione del footer seguita da ulteriori chiusure di body e html. La richiesta GET restituisce 200, ma questo non prova che la struttura DOM sia corretta in tutti i browser.

**Rischio:** recupero del markup non uniforme, differenze di layout/accessibilità e possibili problemi agli script o agli elementi successivi nel documento.

**Correzione raccomandata:** far possedere l’intera struttura del documento a un solo layout e mantenere invariati action, metodo, campi e controlli di autenticazione del form. Validare il documento renderizzato dopo la correzione.

### MEDIUM — Contatore capitano etichetta come “in attesa” stati diversi

**File e posizione:** `capitano/dashboard.php:23–29,77–78`; `includes/iscrizione_helper.php:180–182`.

**Problema:** il nuovo valore è calcolato come numero totale giocatori meno quelli completati. Il totale include stati differenti, tra cui `in_attesa_genitore`, `annullato` e `non_registrato`; il risultato viene mostrato come “In attesa di verifica”.

**Evidenza:** query/array usati per `$giocatori` non vengono filtrati per uno specifico stato prima della sottrazione; l’helper elenca gli stati citati.

**Rischio:** il capitano vede una metrica fuorviante e può interpretare una registrazione annullata o in attesa del genitore come pratica in verifica.

**Correzione raccomandata:** contare esplicitamente gli stati che significano “in attesa di verifica”, distinguendo consensi pendenti e annullamenti. Non modificare la macchina a stati o le regole di registrazione.

### MEDIUM — Conformità tipografica e provenienza del logo non completamente dimostrate

**File e posizione:** `shared/site_nav.php:35–39`; `assets/css/style.css:1005,1039`; `assets/brand/README.md`; `assets/brand/07_riferimenti/tavola_brand_approvata.png`.

**Problema:** il riferimento richiesto `assets/brand/brand_identity_reference.png` non è presente nel repository. È disponibile la tavola approvata `tavola_brand_approvata.png`. La tavola indica Barlow/Barlow Condensed e la palette del kit, mentre alcuni testi della navbar condivisa usano Inter/system. Il README del kit descrive alcuni loghi vettoriali come ricostruzioni non tracciate e invita alla verifica/approvazione prima della sostituzione; la navbar usa `logo_principale.png` con un alt che lo definisce “ufficiale”.

**Evidenza:** le regole CSS e la nota di provenienza sono presenti nel repository. A vista, la tavola e l’interfaccia condividono scudo e palette generale, ma non è possibile attestare la provenienza ufficiale del PNG con i soli file disponibili. Esiste inoltre una contraddizione documentale: `docs/redesign/05_AMMINISTRAZIONE.md` dichiara che non è stato introdotto un asset brand ricostruito, mentre il README del kit avverte che i loghi vettoriali sono ricostruzioni.

**Rischio:** incoerenza tipografica fra moduli e possibile dichiarazione non verificata sull’ufficialità del marchio. Il requisito “nessuna ricostruzione non ufficiale” non può essere certificato senza il file di riferimento richiesto o una fonte di approvazione.

**Correzione raccomandata:** fornire/confermare il riferimento originale e la provenienza approvata del PNG usato; uniformare la tipografia dei componenti condivisi ai font del brand oppure documentare l’eccezione. Aggiornare la documentazione affinché descriva gli asset effettivamente utilizzati.

## 3. Verifica dei requisiti critici

| Requisito | Esito del confronto | Limite della verifica |
|---|---|---|
| Schema DB e migrazioni SQL | Nessuna differenza fra `master...redesign` sotto `assets/sql`. | Non sono stati eseguiti dump o migrazioni sul database. |
| Contratti API | Nessuna differenza nei file `pos/api`, `photo/api` e `photo/upload`. | Nessun test di chiamate API o payload runtime. |
| Business logic | Helper di autenticazione, permessi e API non modificati; la maggior parte delle differenze è di presentazione. Il dashboard capitano introduce però una metrica derivata non corretta (rilievo MEDIUM). | Le modifiche a query e template non sono state sottoposte a test end-to-end per ogni caso di business. |
| Autenticazione e autorizzazioni | Nessun diff in `includes/auth.php`, `includes/auth_capitano.php`, `pos/includes/auth_check.php`, `pos/includes/role_check.php` e `photo/includes`. | Non sono stati provati login, logout, sessioni scadute o accessi con ruoli distinti. |
| Campi form e contratti GET/POST | Confronto statico delle firme nei template PHP cambiati: metodi, action, nomi, tipi e valori hidden risultano invariati. | Campi costruiti dinamicamente e round-trip reali richiedono test browser/DB. |
| CSRF | Non risultano modifiche ai file backend e i token/campi hidden rilevati nei form confrontati sono preservati. | Non è stata inviata alcuna POST e non si certifica la validità runtime del token o il rifiuto di token mancanti/scaduti. |
| Selettori e attributi JavaScript | Rilevata una regressione concreta per `#navbar` in `iscrizione.php`. Il nuovo menu usa `data-site-nav`. | Non è stata svolta una suite DOM/browser su ogni pagina. |
| Polling e aggiornamenti dinamici | La modifica a `assets/js/main.js` rimuove il vecchio codice navbar; è stato aggiunto lo script di anteprima media. I file API/upload non sono cambiati. | Non sono stati osservati polling POS, KDS, archivio o aggiornamenti live in browser durante sessione attiva. |
| URL | `.htaccess` non è cambiato; le nuove destinazioni interne fanno uso delle route configurate. | Verificare deployment sotto eventuale sottodirectory/base URL e redirect di autenticazione. |
| Asset originali | Nessun diff sugli asset originali sotto `assets/img`; sono stati aggiunti/usati asset del brand kit. | L’ufficialità del PNG in navbar non è dimostrabile senza il riferimento nominato o fonte approvata. |

## 4. Copertura per moduli e UI

Il redesign cambia shell condivise per sito pubblico, iscrizioni, capitani, amministrazione, telecronaca, archivio privato e POS. Nel diff statico non si rilevano modifiche ai relativi endpoint applicativi o alle guardie server-side elencate sopra. La struttura dei form PHP campionati conserva le firme statiche.

La nuova navigazione usa `shared/site_nav.php` e `assets/js/site-nav.js`; la vecchia gestione menu è stata rimossa da `assets/js/main.js`. Il selettore residuo in `iscrizione.php` è incompatibile con il nuovo header. I file CSS mostrano regole responsive e il menu passa a pannello compatto a breakpoint definiti, ma non è stata fatta una verifica visuale a viewport mobile, tablet e desktop.

Non sono stati verificati empiricamente dimensionamento touch, contrasto con strumenti automatici, ordine/focus da tastiera, screen reader, tabelle scrollabili, modali, stati vuoti/caricamento/errore, né tutte le combinazioni di ruoli e contenuti. Questi aspetti restano test manuali obbligatori e non sono dichiarati superati.

## 5. Test e controlli realmente eseguiti

- `git diff --check master...HEAD`: rileva trailing whitespace nei documenti `docs/redesign/01_AUDIT_TECNICO.md`, `03_SITO_PUBBLICO.md`, `04_ISCRIZIONI_CAPITANI.md`, `05_AMMINISTRAZIONE.md` e `06_ARCHIVIO_MULTIMEDIALE.md` (spazi finali dopo righe Markdown). Nessun file applicativo è stato corretto.
- `php -l` sui 51 file PHP cambiati: tutti superati.
- `node --check` sui 3 file JavaScript cambiati: tutti superati.
- `php tests/email_system_smoke.php`: superato (`email system smoke test passed`).
- Confronto `git diff --quiet`: nessuna differenza in `assets/sql`, `.htaccess`, file auth/role indicati, `pos/api`, `photo/api` e `photo/upload`.
- Confronto statico delle firme dei form PHP modificati: nessuna variazione trovata per method/action/name/type e valori hidden rilevabili staticamente.
- Richieste HTTP GET locali non mutative: `/`, `/squadre.php`, `/squadra.php`, `/admin/login.php`, `/capitano/login.php`, `/iscrizione.php` e `/photo/` hanno risposto HTTP 200. `squadra.php` ha restituito la stessa dimensione della pagina indice squadre nel campione; ciò può dipendere da assenza del parametro e non è stato valutato come regressione senza test con ID valido.
- Non è stato eseguito un browser automatizzato né un controllo screenshot/visuale responsive. Non sono state inviate POST, non sono stati cambiati dati e non sono stati eseguiti test su account autenticati.

## 6. Verifiche manuali richieste prima del rilascio

1. Correggere i rilievi e validare l’HTML renderizzato di home, iscrizione, login admin, login capitano, elenco e dettaglio squadre.
2. Verificare in browser il countdown e tutti i testi dell’edizione con la data ufficiale.
3. Provare menu, focus, Escape, click fuori e breakpoint responsive a 360/390 px, tablet e desktop; controllare overflow orizzontale e target touch.
4. Con account di test per ogni ruolo, provare login/logout/sessione scaduta e navigazione autorizzata: admin, capitano, telecronista, archivio, POS admin/operatore/bar/cucina.
5. Eseguire i flussi di iscrizione e consenso genitore in ambiente di test, verificando campi, errori di validazione, token CSRF, successo, mail e stati; non usare dati di produzione.
6. Provare POS e KDS con aggiornamenti/polling, modifiche ordine, cassa e viste per ruolo; verificare archivio privato con anteprima/download e permessi negati.
7. Verificare risultati torneo, calendario e pagina partita con dati reali di test, inclusi stati live/vuoti e fallback errore.
8. Eseguire validazione accessibilità (tastiera, focus visibile, contrasto, label/errori form e screen reader) e test visuali su mobile/tablet/desktop.
9. Confermare il file di riferimento brand e la provenienza/approvazione degli asset di logo utilizzati; controllare font, palette, favicon e coerenza tra i moduli.
10. Ripetere `git diff --check`, PHP lint, controlli JS e smoke test dopo le correzioni, poi svolgere una regressione end-to-end in ambiente non produttivo.

## 7. Raccomandazioni complessive

- Risolvere prima i quattro rilievi HIGH e la metrica capitano, poi ripetere controlli statici e browser.
- Mantenere il redesign isolato alla presentazione: nessuna variazione a schema, API, logica, ruoli, CSRF, campi form o URL senza una revisione separata e approvazione esplicita.
- Aggiungere una validazione HTML e un controllo JavaScript dei selettori/attributi per le pagine condivise; includere nel collaudo le pagine di autenticazione e iscrizione.
- Integrare smoke test visuali responsive e prove per ruolo per ciascun modulo prima del rilascio.
- Rimuovere gli spazi finali nei documenti di redesign e mantenere documentazione e asset effettivamente usati allineati.

## 8. Raccomandazione di rilascio

**NO-GO.** Le verifiche statiche non evidenziano modifiche a database, API o guardie di autenticazione, ma sono presenti regressioni visibili e markup/script non coerenti con i componenti condivisi. Mancano inoltre test interattivi per POS, registrazioni, risultati, archivio privato, ruoli, accessibilità e layout responsive. Promuovere la branch solo dopo le correzioni e le verifiche manuali sopra elencate.
