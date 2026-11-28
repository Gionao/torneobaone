# Unified staff authentication schema and migration

## POS: tracciamento privato (28 novembre 2026)

Per i database esistenti eseguire una sola volta [20261128_pos_tracking_token.sql](20261128_pos_tracking_token.sql) prima di distribuire il codice. Per le nuove installazioni lo schema completo include le colonne e il relativo indice. La migrazione conserva il blocco di conversione per gli ordini gia consegnati.

Il codice di tracciamento viene mostrato alla cassa per i nuovi ordini normali e scade dopo 24 ore. Gli ordini creati prima della migrazione non hanno un codice. Gli ordini staff e i pagamenti manuali usano la tabella di audit gia presente nello schema unificato.

## POS: consumi staff (23 novembre 2026)

`20261123_pos_staff_orders.sql` aggiunge `pos_orders.is_staff` con valore predefinito 0: gli ordini esistenti restano normali. Eseguire una sola volta prima del codice POS aggiornato; verificare con `SHOW COLUMNS FROM pos_orders LIKE 'is_staff'` prima di ripeterla. Lo schema completo include già la colonna per le nuove installazioni.

La cassa permette ordini staff gratuiti; i prezzi originali di prodotti ed extra restano nelle righe. Lo storico usa `total_amount` per il totale dovuto. Le statistiche vendite escludono gli ordini staff e mostrano separatamente quantità e valore consumato per intervallo di date. Le trasformazioni sono riservate agli amministratori POS e agli ordini della giornata corrente. Un ordine annullato non e trasformabile. Un ordine normale gia consegnato non puo diventare gratuito. Nel passaggio a staff i coupon sono scollegati e restituiti nella stessa transazione. Gli annullamenti staff sono consentiti solo nella giornata corrente da cassa/storico.

Verifica locale: `php tests/pos/staff_orders_test.php`. Il test usa esclusivamente tabelle temporanee MySQL, senza modificare i dati applicativi.

## Registration workflow extension (not yet production-ready)

`20261105_registration_workflow.sql` is the next one-time migration after `20261104_unified_staff_auth.sql`. It adds registration fields and append-only consent, parent authorization, paper authorization and registration audit tables. It contains DDL and a backfill of `giocatori.id_torneo`. The migration ran successfully on MySQL 8.0.30 against a disposable database initialized from the previous complete schema with no application data. Review `docs/registration-audit/08_IMPLEMENTATION_REPORT.md` before use.

Before execution, restore a protected backup into a disposable database, inspect the exact schema/version and every `ALTER TABLE` target, run the duplicate preflight queries and resolve collisions manually. MySQL DDL auto-commits: the migration is not transactionally rollbackable. Recovery is restore from verified backup or a separately reviewed forward fix; do not apply to production on the basis of this file alone. The unique active tax-code key is limited to rows whose `id_torneo` is populated; orphan/legacy rows with NULL edition are not deduplicated by that index.

The registration migration currently contains no application migration runner and assumes the prior staff tables (`admin`, `tornei`, `documenti`) exist. It is intentionally not advertised as repeat-safe: do not rerun after partial completion without checking each DDL statement.

## Stable identifiers

Database codes are fixed application identifiers, not editable permissions. Keep lowercase ASCII spelling stable in SQL and PHP:

| Sector code | Allowed role codes |
|---|---|
| `torneo` | `amministratore`, `segreteria` |
| `partite` | `gestore`, `telecronista` |
| `pos` | `amministratore`, `operatore`, `bar`, `cucina` |
| `archivio` | `amministratore`, `editor`, `uploader` |

One row per `(id_admin, area)` enforces at most one role per sector. Composite FK `(area, ruolo)` to `staff_ruoli_area` rejects invalid combinations. Role rows are fixed by application/schema releases; no permission designer is intended. POS station is assignment-local (`staff_assegnazioni.station_name`). Media edition scope continues to use `media_accesso_edizione` without changing its primary key/FKs.

## Artifacts

- `database/schema_complete.sql` is the canonical schema for new databases. `assets/sql/database_schema_completo.sql` is an identical copy. Both include the structural changes through `20261128_pos_tracking_token.sql` and create no staff accounts or default passwords. Keep both files synchronized when updating the schema.
- Existing databases use the migration files in date order, starting with the first pending migration. The complete schemas also retain the existing fresh-install foreign key `fk_giocatori_sostituisce`, which sets `giocatori.sostituisce_id` to NULL when the referenced player is deleted.
- `database/migrations/20261104_unified_staff_auth.sql`: additive migration. Existing `admin` IDs/password hashes/legacy `role` and `station_name` columns and all business data/FKs remain. New columns and normalized assignment tables are added only if absent. The role catalog and assignments use duplicate-safe inserts.
- `database/bootstrap_super_admin.php`: local CLI-only, one-time bootstrap to promote an existing active account when no active Super Admin exists. It locks a singleton guard row and makes no default credentials.

The imported schema already contains `staff_audit_log`; the complete schema retains it for compatibility with that schema. No code in this phase writes staff role/assignment changes to it, and no role-change audit log is introduced.

## Migration role review (do not guess)

The old shared `admin.role` values found in repository SQL/PHP conventions include:

- `telecronista` -> `partite.telecronista` (unambiguous)
- `operatore`, `bar`, `cucina` -> matching `pos` role (unambiguous)
- `uploader`, `editor` -> matching `archivio` role (unambiguous)
- `admin` -> **ambiguous**. It may represent tournament, POS, or archive administration depending on deployment and its account-management history. Migration does not assign it and never infers `is_super_admin`.

The historic SQL `assets/sql/database_schema_torneo.sql` declares enum values `admin`, `operatore`, `bar`, `cucina`, `telecronista`; it is unsuitable for this migration or production bootstrap. Runtime media code additionally recognizes `uploader` and `editor`. Any other observed value is reported as unmapped. The migration outputs a frequency report and a per-account report for values requiring review. Export these reports and resolve each account using trusted pre-import records before cutover. Do not invent mappings or translate generic `admin` to every sector.

The Phase 01 development DB has no `admin.role` or `admin.station_name`, so there is no reliable local legacy-role dataset to migrate. On such a database, the migration makes no assignments from guesses and reports that manual reconciliation from a trusted account source is required. This is expected, not evidence that the accounts have no historical roles.

## Backup, dry-run, and restore procedure

MySQL DDL implicitly commits and is not generally rollbackable as one transaction. Do not promise transaction rollback. Before a real migration:

1. Confirm exact DB name, server version and the selected database (`SELECT VERSION(), DATABASE();`). Never use production credentials during a test.
2. Stop writes or place the application in maintenance mode; ensure no concurrent staff management operations occur.
3. Take a full logical backup, including routines, triggers and events, to a protected location. Example (adapt credentials; avoid putting passwords on the command line):

   ```sh
   mysqldump --single-transaction --routines --triggers --events --hex-blob DB_NAME > before-unified-staff-auth.sql
   ```

   For non-transactional tables, use a consistent full backup/maintenance lock. Test that this dump can be restored into a separate database before proceeding.
4. Restore the dump as a disposable copy and run the migration there. Export the migration's role frequency/unmapped reports and compare row counts, account IDs, hashes, station assignments, `media_accesso_edizione`, captains, business tables and FK definitions before/after.
5. Resolve ambiguous values with a reviewed per-account mapping outside this migration. Apply only explicit, approved assignments using the catalog's exact codes. Verify current target assignment conflicts first. Do not update/delete legacy `admin.role` or `station_name` until the new application is deployed and validated in a later phase.
6. If validation fails, discard the disposable copy and restore the pre-migration backup to the test database. On a live database, restore the verified backup under a separately approved recovery procedure; do not attempt ad-hoc DDL reversal.
7. Production execution is a separate operational change and is intentionally not performed here.

`CREATE TABLE IF NOT EXISTS`, role-catalog upserts, and explicit role insertion are repeatable; assignment insertion checks `(id_admin, area)` and never overwrites an existing different assignment. Re-running emits the unmapped report again. MySQL does not support portable `CREATE TRIGGER IF NOT EXISTS`, so on a second run the two final trigger statements report that their names already exist. Treat that expected error as a stop condition, verify both triggers via `SHOW TRIGGERS`, and do not rerun further. The guarded schema/data operations preceding them do not duplicate or overwrite permissions. Existing incompatible same-name tables/FKs are not silently repaired: compare their DDL and stop for manual reconciliation. Helper routines use `IF NOT EXISTS` and no destructive `DROP` is used.

## Commands for disposable validation

Use a disposable local server/database, never the configured application DB unless it is itself an explicitly disposable copy. Example with MySQL client (replace names/credentials):

```sh
mysql -u root -p -e "CREATE DATABASE tb_auth_schema_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
mysql -u root -p tb_auth_schema_test < database/schema_complete.sql
mysql -u root -p -e "CREATE DATABASE tb_auth_migration_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
mysql -u root -p tb_auth_migration_test < /path/to/restored_legacy_fixture.sql
mysql -u root -p tb_auth_migration_test < database/migrations/20261104_unified_staff_auth.sql
# Optional idempotency check: expect only the already-exists trigger error;
# verify assignments are unchanged and both triggers still exist.
mysql -u root -p tb_auth_migration_test < database/migrations/20261104_unified_staff_auth.sql
```

For a real legacy fixture, create a disposable copy from a trusted pre-import dump; do not synthesize role mappings from the new-schema database. Validate:

```sql
SHOW CREATE TABLE admin;
SHOW CREATE TABLE staff_assegnazioni;
SELECT area, ruolo, COUNT(*) FROM staff_assegnazioni GROUP BY area, ruolo;
SELECT id, username, role FROM admin ORDER BY id; -- only when legacy `role` exists
SELECT COUNT(*) FROM media_accesso_edizione;
SELECT COUNT(*) FROM capitani;
SELECT COUNT(*) FROM transazioni;
SELECT COUNT(*) FROM pos_orders;
SHOW CREATE TABLE transazioni;
SHOW CREATE TABLE pos_orders;
```

Also verify invalid role insertion is rejected by the composite FK, duplicate area assignment is rejected by the primary key, and running migration twice does not add or replace assignments. To verify last-Super-Admin protection, in a disposable schema create/promote two test accounts, attempt concurrent demotion/deactivation/deletion of the only active Super Admin, and confirm one active Super Admin always remains; test that removing one while another active Super Admin exists succeeds.

Bootstrap an existing active account only after migration and review, on the intended local/controlled environment:

```sh
php database/bootstrap_super_admin.php <exact-existing-username>
```

The command requires CLI SAPI, the configured database connection and `staff_security_guard`, selects the account by its unique username, serializes the operation, refuses an inactive account or any existing active Super Admin, preserves its password hash and all assignments, and promotes exactly that account. It does not create a username, set a password, expose a shared default credential, or grant any sector role. Protect the local machine and DB credentials; remove bootstrap access/installer permissions after initial use. To promote an existing account later, use an approved Super Admin application operation (Phase 03), not this one-time bootstrap.

## Compatibility notes and known limits

- The current development DB is MySQL 8.0.30 and already has the new role catalog/assignment tables, but it lacks legacy role/station columns. The legacy migration path must be tested against an actual restored pre-import dump to verify every `ENUM` value and its records.
- Phase 02 validation was run on MySQL 8.0.30. A clean install of `schema_complete.sql` succeeded. A full local development-database dump was restored into a separate disposable database and the migration succeeded; that source database had zero staff accounts, assignments, captains, teams, payment rows, POS orders and media edition ACL rows, so it cannot demonstrate preservation of populated imported data. A separate disposable legacy fixture validated preservation of all four account IDs/password hashes, captain row, known-role mapping, POS station and the ambiguous `admin` report.
- On that legacy fixture, re-running the migration left the assignment count at 3 and all 4 original password hashes intact; MySQL then returned the documented `Trigger already exists` error for the non-repeatable trigger DDL. Invalid role/area combinations failed their FK, duplicate account/area assignment failed the primary key, and attempts to demote/deactivate or delete the only active Super Admin were rejected. These tests were sequential; no multi-connection concurrency test was executed.
- `database/schema_complete.sql` is derived from the repository's complete schema, not from a live production dump. Validate all business table definitions against the deployment before a fresh-install decision.
- `staff_security_guard` serializes protection triggers for active Super Admin rows. Database owners can still deliberately alter triggers/guard/schema; application code must also lock the guard row in the same transaction when adding/promoting/deactivating Super Admins. Do not remove or bypass either safeguard.
- MariaDB/AlterVista support was not verified. The schema uses enforced CHECK and trigger behavior; run on the exact target engine before deployment. MySQL 8.0.30 is the only verified development database version from Phase 01.
