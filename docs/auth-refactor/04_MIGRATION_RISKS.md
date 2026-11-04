# Migration risks and safeguards

## Critical risks

1. **Imported schema breaks current PHP assumptions.** Current DB `admin` does not contain `role` or `station_name`; legacy auth, POS login/user management, and media user management still query these fields. Authentication flows can fail immediately against the new schema. The actual schema is not a substitute for a migration or application compatibility layer.
2. **Privilege persistence in active sessions.** Staff session roles are cached outside media. A removed role may still pass checks. Any migration must force fresh DB authorization on every protected request; account inactive/deleted, assignment removed, or query error must fail closed.
3. **Elevation across sectors.** Existing user management writes a global `admin.role`. Naive mapping can accidentally give one legacy `admin` role to every sector, allow sector admins to grant themselves admin, or expose cross-sector assignments. Explicit mapping review and server-side ownership checks are prerequisites.
4. **Legacy account ambiguity.** Same username may exist only once in current shared table, but one row can carry sector-specific legacy intent. Map each legacy role to exactly one area assignment unless documented evidence authorizes another. Do not infer all roles from superuser-looking labels.
5. **Unintended account/data deletion.** `admin.id` is referenced by payment organizer, POS orders, media files/uploads/logs, edition creator/access. Deleting or replacing IDs can break audit attribution/FKs. Preserve account IDs and passwords where possible; revoke assignment, not account, when business records require attribution.

## Media-specific risks

- `media_accesso_edizione` scopes uploaders; editor/admin roles bypass it. Preserve the mapping and the scope for user listing, gallery, file details, download/thumb, ZIP generation/download, and chunked upload—not just page navigation.
- Upload records and ZIP jobs may be in progress and reference account IDs. Migration must preserve ownership and ensure subsequent chunks honor current role and edition permission. Reassigning users without deliberate rule can expose existing uploads/downloads.
- Existing `media_log` captures media actions. The requested prohibition is on adding a **staff role-change audit log**. Do not use `staff_audit_log` for role modifications or add another such table/log path.

## POS and tournament risks

- `staff_assegnazioni.station_name` replaces the old account-level `admin.station_name`. Existing `pos_orders.station_name` is historical snapshot data and should not be rewritten; subsequent orders need the current assigned station.
- POS users currently share `admin` with tournament and photo. User CRUD deletion impacts all business FKs; POS managers must not inspect or alter other area assignments.
- Tournament payment rows are attributed via `transazioni.id_organizzatore -> admin.id`; keep actor IDs stable. Team/captain/token data and match/player records are unrelated and must not be altered.
- `telecronista` currently has narrower match behavior but can share the same legacy session with other roles. Map to `partite.telecronista`; never broaden to tournament administrator.

## Deployment/hosting risks

- Local PHP 8.5.12/MySQL 8.0.30 are known; AlterVista versions and restrictions are unknown. Existing source requires modern PHP 8 features, so first establish supported PHP runtime, database engine/version, FK behavior, transactional DDL and max index/row limits.
- Central auth across `admin.torneobaone.it`, `pos.torneobaone.it`, `photo.torneobaone.it`, and `torneobaone.it` relies on cookie domain, HTTPS and same session storage. Confirm DNS/virtual-host paths, TLS, `session.save_path`, proxy HTTPS detection, SameSite behavior, and session sharing before cutover.
- New migration files need idempotency, backups, dry-run report, transactions where supported, maintenance/rollback plan, and compatibility with actual production schema—not just local imported DB.
- `.htaccess` and subdomain/document-root setup may differ on AlterVista. Preserve old URLs via in-app PHP adapters or host rules only after path mapping is confirmed.

## Safe migration principles

1. Back up schema and data, export current staff/account-assignment and referenced counts, then test against a restored copy.
2. Avoid destructive `admin` table replacement. Use additive/compatible rollout and preserve existing `admin.id`, username, hash, and all business FKs.
3. Build a reviewed role mapping by current row and legacy business function; flag unknown/duplicate/invalid roles for manual resolution. Do not grant Super Admin automatically from legacy `admin` role.
4. Use explicit allowlisted `(area, role)` assignments from `staff_ruoli_area`; one role per area as modeled by primary key `(id_admin, area)`. Treat `is_super_admin` as separately controlled, never sector-admin writable.
5. Preserve `media_accesso_edizione` unchanged and validate each uploader's edition set. Preserve POS station mapping and existing order snapshots.
6. Deploy compatibility code and DB migration in a coordinated order with old login aliases. Force active staff sessions to reauthenticate or re-resolve; test revocation mid-session.
7. Rollback by restoring compatible application release and additive schema only where safe; do not delete new fields/tables containing live assignments until separately reviewed.

## Explicit non-goals

No schema/data edits in this phase; no role-change audit log; no merging captain credentials; no deletion or re-keying of business records; no UI redesign.
