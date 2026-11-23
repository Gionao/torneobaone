# 10 — Revisione sicurezza

## Esito
Review statica limitata ai file cambiati e ai report Fasi 1/2; nessuna scansione runtime o test di penetrazione. **Non idoneo allo staging.** L’implementazione di Fase 2 ha solo un helper di validazione e tabelle proposte: non ha protetto i flussi web perché non viene richiamato da alcuna pagina (ricerca repository: chiamate solo da test/helper stesso).

## Rilievi

| ID | Severità | Rilievo / evidenza | Stato e intervento |
|---|---|---|---|
| SEC-01 | Critica | Requisiti di registrazione non integrati nelle route; validatore non usato (`includes/registration_validation.php`, ricerca simboli). Tutti i controlli HTTP rimangono quelli auditati in `02_GAP_ANALYSIS.md`. | Aperto; nessun fix in questa fase di test. |
| SEC-02 | Critica | Nessuna implementazione OTP, limiter, link token single-use né conferma POST. Le tabelle di schema non impongono tali invarianti da sole. | Aperto; E2E bloccato. |
| SEC-03 | Critica | Nessun flusso team state approvato né backend guards nuove; pagamenti/approvazione non sono testabili come requisiti compositi. | Aperto. |
| SEC-04 | Alta | Migrazione `20261105_registration_workflow.sql` include DDL sequenziale con auto-commit; non è idempotente e un errore intermedio può lasciare schema parziale. Non validata su database. | Aperto; non eseguire finché non c’è prova disposable, preflight e piano di recovery. |
| SEC-05 | Alta | Chiave `uq_giocatori_cf_torneo_attivi` consente più righe con `id_torneo IS NULL` (NULL nelle chiavi unique MySQL); quindi non prova unicità di righe legacy/orfane. | Esplicitato in README; aperto, richiede bonifica edizione e strategia attiva/cancellata. |
| SEC-06 | Alta | Il preflight duplicati è commentato e raggruppa per `id_squadra`, non per edizione/persona; inoltre il testo dice che unique verrà aggiunta dopo ma la migration la crea prima di quel commento. | Aperto; rendere eseguibile preflight corretto e allineare ordering prima di qualunque migration test. |
| SEC-07 | Alta | FK `consensi_eventi` limita i soggetti ma non vincola `id_giocatore` al genitore/player/edizione dichiarati né verifica esito OTP. | Aperto; validazione nel servizio e modello coerente necessari. |
| SEC-08 | Alta | OTP hash è CHAR(64), ma senza logica di hash/HMAC, rate-limit atomico, contatori robusti o lock DB. | La presenza colonna non è salvaguardia. Aperto. |
| SEC-09 | Media | `registration_normalize_email()` abbassa tutto l’indirizzo. È policy dichiarata e non collassa alias, ma può unire local-part case-sensitive secondo provider. Nessun test route/database per confronto. | Nessun exploit dimostrato; comportamento richiede policy uniforme end-to-end. |
| SEC-10 | Media | CF checksum non verifica identità né genera un controllo esterno; funzione e messaggio di errore dichiarano questo limite. | Unit PASS; serve approvazione privacy e gestione correzioni/omocodia non implementata. |
| SEC-11 | Media | Nessun CSRF/XSS/SQLi/IDOR test runtime; il fatto che PDO usi prepared statements nel helper DB non dimostra tutte le route. | Non verificato. |
| SEC-12 | Media | SMTP sandbox non configurato; nessun messaggio/email log osservato. | Non verificato; nessuna mail spedita. |

## Verifiche positive limitate
- `random_bytes`/hash token legacy descritti nell’audit non sono stati modificati o testati qui.
- Il nuovo helper è puro rispetto a DB/rete e CF input è validato a formato/checksum; test sintetici 13/13 passano.
- Niente credenziali, indirizzi o token reali sono stati esposti o utilizzati.

Nessun rilievo dinamico è dichiarato risolto. Quelli SEC-01–SEC-08 impediscono staging; non trattare tabelle o campi appena aggiunti come controllo di accesso/integrità applicato.
