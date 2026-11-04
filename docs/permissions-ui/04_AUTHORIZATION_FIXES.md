# Phase 02 — correzioni autorizzazione e navigazione

## Modifiche implementate

Nessuna modifica allo schema/database e nessun nuovo ruolo/assignment. I permessi aggiuntivi sono derivati dal principal e dalle assegnazioni esistenti, rilette dal database dal servizio staff a ogni richiesta.

### Autorizzazione

- `includes/staff_authorization.php`: aggiunto catalogo centralizzato `STAFF_ROLE_CAPABILITIES` e resolver `staff_has_capability()`. `torneo.amministratore` riceve esplicitamente `partite.manage` e `partite.delete`, senza assignment `partite`; non riceve `partite.staff` e `can_manage_sector_users('partite')` resta limitato a `partite.gestore` e Super Admin. `torneo.segreteria` riceve dashboard, squadre, giocatori, iscrizioni e pagamenti, ma non metodi pagamento, staff management, match, POS o archivio.
- `includes/auth.php`: nuovo guard `require_tournament_capability()`; i guard match di lettura/scrittura ed eliminazione valutano le capability effettive. Le route di match restano gli handler server-side per le azioni; non esistono API match separate nel codice esaminato.
- `admin/index.php`: accesso con capability dashboard; cards, query KPI e azioni rapide filtrate. Le query partite sono eseguite solo se l'utente ha accesso alle partite. I ruoli superiori mantengono le funzioni precedenti; la segreteria non vede statistiche o azioni non autorizzate.
- `admin/squadre/{index,form,dettaglio,delete}.php` e `admin/giocatori/{index,form,delete}.php`: guard aggiornati per consentire la segreteria, senza elevare il suo ruolo generale. I flussi iscrizioni/pagamenti già passavano da `require_tournament_operations()`; `pagamenti/metodi.php` resta riservato all'amministratore torneo/Super Admin.
- 403 staff HTML in `includes/staff_authorization.php`: messaggio in italiano e collegamento costruito dal principal/capability corrente verso un'area autorizzata o il selector. Nessun parametro utente determina la destinazione. Il 403 resta HTTP 403.
- `pos/includes/auth_check.php`, `photo/includes/media_auth.php`: uno staff autenticato ma senza accesso al settore POS/archivio riceve 403 (JSON per API), non un redirect a un'area alternativa. I flussi non autenticati e i controlli specifici di ruolo/ACL restano attivi.
- `staff/sector-users.php`, `staff/users.php`, `telecronista/index.php`: errori autorizzativi HTML uniformati al 403 staff sicuro; il landing telecronista accetta gestori autorizzati, instradandoli alla gestione partite.

### Navigazione e dashboard

- Nuovo `includes/staff_navigation.php`: genera menu admin, POS e archivio da capability centrali, con voce corrente, azioni/logout e switcher per account multi-settore. Il renderer responsive condiviso `shared/site_nav.php` resta in uso per POS/archivio; la shell admin mantiene il layout sidebar esistente ma i suoi elementi derivano dal medesimo servizio.
- `admin/_header.php`: menu filtrato per capability, inclusi Partite per amministratore torneo, menu ristretto segreteria e sole Partite/Profilo/Logout per il gestore con solo assignment Partite. Il profilo è stato aggiunto come `staff/profile.php`; la shell offre anche “Cambia area” per account multi-settore e gestione staff solo al Super Admin.
- `pos/includes/navbar.php` e `photo/admin/_header.php`: le liste locali hardcoded sono sostituite dal builder condiviso. Le destinazioni archivio “Pannello admin” appaiono soltanto quando la dashboard torneo è autorizzata; lo switcher è disponibile agli account multi-settore.
- `staff/index.php`: il selector mostra anche Partite all'amministratore torneo in virtù della capability, senza rappresentarla come assegnazione `partite`.

### Test aggiornati

- `tests/auth/authorization_matrix_test.php`: aggiunti casi per accesso amministratore torneo alle guard match senza assignment, impossibilità di gestire/assegnare gestori settore, capability e guard della segreteria, menu segreteria/gestore, navigation POS operatore e archivio uploader, multi-settore, cambio area e menu Super Admin.

## Test eseguiti

- `php -l` su tutti i file PHP modificati: **PASS**.
- `git diff --check`: **PASS**; Git ha emesso solo avvisi di conversione LF/CRLF per file del progetto su Windows.
- `tests/auth/staff_auth_test.php`, database disposable allowlisted `tb_auth_migration_test`: **50 superati, 0 falliti**. Il controllo ACL edizioni archivio è stato saltato perché lo schema media non è installato in quel database.
- `tests/auth/authorization_matrix_test.php`: **non eseguibile nell'ambiente disponibile**. Il DB disposable `tb_auth_p07_clean_20261104` non esiste; anche il tentativo con `tb_auth_p07_legacy_20261104` ha confermato che non esiste. Il test è stato esteso ma i nuovi casi helper/navigation richiedono il database disposable.
- Nessun browser test o smoke HTTP autenticato eseguito; non è stato avviato né modificato alcun database.

## File modificati

- `includes/staff_authorization.php`
- `includes/auth.php`
- `includes/staff_navigation.php` (nuovo)
- `admin/_header.php`
- `admin/index.php`
- `admin/squadre/index.php`, `form.php`, `dettaglio.php`, `delete.php`
- `admin/giocatori/index.php`, `form.php`, `delete.php`
- `pos/includes/auth_check.php`, `pos/includes/navbar.php`
- `photo/includes/media_auth.php`, `photo/admin/_header.php`
- `staff/index.php`, `staff/profile.php` (nuovo), `staff/sector-users.php`, `staff/users.php`
- `telecronista/index.php`
- `tests/auth/authorization_matrix_test.php`
- `docs/permissions-ui/04_AUTHORIZATION_FIXES.md`

## Questioni residue

1. Eseguire la matrice aggiornata su un DB disposable `tb_auth_p07_clean_20261104`/`tb_auth_p07_legacy_20261104` dopo averlo provisionato; includere smoke HTTP autenticati per 403, form e URL diretti.
2. La matrice endpoint non ha testato regressione completa dei flussi business torneo, pagamenti, POS o archivio. Il test staff eseguito ha saltato ACL edizioni; queste ACL e i controlli per file/reparto restano da provare su schema completo.
3. `staff_navigation()` centralizza la generazione dei menu, ma i guard specifici POS e media mantengono helper di dominio preesistenti. Mantenere i test di corrispondenza menu/backend quando quei guard cambieranno.
4. “Profilo” mostra identità e assegnazioni proprie e collega al cambio password; non è una funzione di modifica dei dati anagrafici.
5. Non è stata eseguita una prova browser del rendering responsive e dello stato attivo su tutti gli host/URL rewrite configurati.

**Phase 02 completata; Phase 03 non avviata.**
