# Database, PHP, and SQL comparison

## Evidence and limits

Inspected the configured database read-only through `includes/db.php`, using `SHOW TABLES`, `SHOW COLUMNS`, and `SELECT VERSION()`. Server is MySQL 8.0.30. Application config points to local database `my_torneobaone`. No rows or schema were modified. SQL reviewed: all files under `assets/sql/`, principally `database_schema_completo.sql`, `database_schema_torneo.sql`, `database_schema_pos.sql`, and `database_schema_archivio.sql`.

## Staff/auth schema comparison

| Concern | Actual DB | Complete SQL | Current PHP expectation |
|---|---|---|---|
| Staff identity | `admin(id, username, password_hash, attivo, is_super_admin, created_at, updated_at)` | Same | `includes/auth.php`, POS login and media repository select/update `role`; POS also uses `station_name` |
| Per-sector role | `staff_assegnazioni(id_admin, area, ruolo, station_name, ...)` | Same | PHP has no use of these assignments found; uses cached/singular `admin.role` |
| Allowed roles | `staff_ruoli_area(area, ruolo, descrizione)` | Seeded sector values | PHP has separate hardcoded allowlists for POS and media; admin and match roles are session comparisons |
| Global admin | `admin.is_super_admin` | Same | No PHP authorization use found |
| Staff change audit | `staff_audit_log` exists | Defined as security log | User explicitly requires no new role-change audit log; this table is in schema but no PHP references found |
| POS station | `staff_assegnazioni.station_name` | Same | `pos/login.php` expects `admin.station_name`; orders store historical station snapshot |
| Media edition scope | `media_accesso_edizione(id_admin,id_edizione,creato_da)` | Same | `media/includes/media_auth.php` and repository use it, joined to legacy singular media `admin.role` |
| Captain | separate `capitani` table | Same | separate auth module and session namespace |

An immediate compatibility blocker is confirmed: several code paths select `role` from `admin`, but the actual imported table has no such field. POS creation inserts `role, station_name`; media account management filters/updates `role`; the SQL complete schema instead models roles in `staff_assegnazioni`. These actions cannot run against actual imported schema in their current form.

## Actual table inventory

Actual tables/views: `admin`, `staff_assegnazioni`, `staff_ruoli_area`, `staff_audit_log`; tournament/business: `tornei`, `fasi`, `squadre`, `giocatori`, `partite`, `eventi_partita`, `classifica`, `capitani`, `genitori`, `tipi_documento`, `documenti`, `consensi`, `token_verifica`, `metodi_pagamento`, `transazioni`, `impostazioni`; POS: `pos_settings`, `pos_categories`, `pos_ingredients`, `pos_products`, `pos_product_ingredients`, `pos_coupons`, `pos_orders`, `pos_order_items`, `pos_order_item_modifications`; media: `media_edizioni`, `media_giornate`, `media_album`, `media_file`, `media_upload`, `media_zip_job`, `media_log`, `media_accesso_edizione`; views `v_capocannonieri`, `v_classifica`.

## SQL file compatibility and differences

- `database_schema_completo.sql` explicitly says it is for a **new empty database**, not a migration; `CREATE TABLE IF NOT EXISTS` does not upgrade existing tables. It defines the new staff schema but retains all business table/FK definitions.
- `database_schema_torneo.sql` is the older tournament schema; `database_schema_pos.sql` and `database_schema_archivio.sql` are modular/older. The media schema header prescribes `database_schema.sql` as prerequisite, but that named file is absent from the inspected repository (the available full schema is named `database_schema_completo.sql`).
- Older schema definitions and the full schema cannot be assumed mutually compatible. `database_schema_pos.sql` defines POS order records without the organizer FK found in complete schema. Use the exact DB inspection and dumps before any upgrade.
- The full schema's `admin` deliberately has neither old `role` nor `station_name`; values are assigned per sector in `staff_assegnazioni`. `staff_audit_log` exists notwithstanding the current task's explicit decision not to add a role-change audit log; do not populate it for staff role changes without authorization/requirements change.
- Code relies on business keys/foreign keys using `admin.id` (transaction organizer, POS order operator, media creator/uploader/logs), so preserve IDs and do not replace/delete staff rows during migration. `media_accesso_edizione` and POS station association must migrate without changing meanings. Captain/business relations are separate and must remain untouched.

## Version and hosting considerations

Development PHP 8.5.12 and MySQL 8.0.30 are observed. Project uses PHP 8 features (`match`, `str_contains`, `never`, union/mixed types in media); target PHP minimum must be verified against actual hosting and AlterVista before rollout. MySQL 8 does not establish available AlterVista MariaDB/MySQL version. Check support for FK constraints, `CHECK` enforcement, timestamp defaults, collations, transactions, and index/DDL locking there. No current AlterVista server probe/config was available in repository evidence.
