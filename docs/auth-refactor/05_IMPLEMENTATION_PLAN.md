# Unified staff authentication implementation plan

This is a proposal for a later implementation phase. Phase 01 changes documentation only.

## Data model and relationships

Use the imported schema model, subject to production verification and a migration that reconciles actual schema with application compatibility:

- `admin`: canonical staff identity and password hash; `attivo` controls account eligibility; `is_super_admin` is the global override. Preserve existing IDs and FK references.
- `staff_assegnazioni`: one row per `(id_admin, area)`; FK to `admin` and composite FK to `staff_ruoli_area`. Optional `station_name` applies to POS assignment only. One staff member may hold roles in multiple distinct areas.
- `staff_ruoli_area`: allowed role catalog keyed by `(area, ruolo)`; validate in server-side code and FK, never accept arbitrary role strings.
- `media_accesso_edizione`: retain the existing `(id_admin,id_edizione)` scope unchanged for archive uploaders; editor/archive administrator behavior keeps current wider scope.
- Existing `pos_orders.admin_id`, `transazioni.id_organizzatore`, media uploader/log/creator FKs remain attached to the stable canonical `admin.id`. Keep historical POS `station_name` snapshots.
- `capitani` and captain tables/tokens/relations remain independent.
- Do not add/use role-change audit logging. `staff_audit_log` exists in the imported schema but is out of scope for role changes under current requirement.

## Area and role identifiers

Use exactly the seeded `staff_ruoli_area` area and role names:

| Area | Assignable roles | Intended scope |
|---|---|---|
| `torneo` | `amministratore`, `segreteria` | Admin: broad tournament operations; segreteria: registration/payments without global administration |
| `partite` | `gestore`, `telecronista` | Gestore: match/calendar full operations; telecronista: limited match entry/results/events |
| `pos` | `amministratore`, `operatore`, `bar`, `cucina` | Sector administration, cashier/operator at assigned station, bar and kitchen operations |
| `archivio` | `amministratore`, `editor`, `uploader` | Archive admin; editor; uploader with edition scope and own-file restrictions |

`is_super_admin=1` is a global privilege, not an assignment role and not assignable by a sector administrator. Only a current Super Admin can create/promote Super Admin or assign any area’s `amministratore` role. Sector admins can create/update/revoke only subordinate roles in their own area, and cannot view or mutate other sector assignments. Define subordinate roles explicitly per area in policy; never infer scope from display labels.

## Module structure

Proposed modules (names can adapt to repository conventions):

- `includes/staff_auth.php`: canonical session start, login, logout, current principal, password update/temporary password state, session regeneration.
- `includes/staff_authorization.php`: `require_staff_area(area, capability)` and Super Admin guard; DB-current resolution on every request, fail closed on database/unknown role, active account check, assignment lookup, cross-sector management policy.
- `includes/staff_csrf.php` or existing CSRF helpers integrated with central session; require POST for state-changing operations.
- `includes/staff_user_repository.php`: narrow assignment/user management operations with transaction boundaries and explicit allowed roles.
- Keep `includes/auth_capitano.php` separate and unchanged.
- Adapt `includes/auth.php`, `pos/includes/{auth_check,role_check}.php`, `photo/includes/media_auth.php` to compatibility wrappers around shared staff service; remove role authority from session values while preserving display cache only.

## Central login and legacy URL compatibility

- Introduce a single staff login URL/controller, retaining existing Italian design patterns and all user-facing labels/messages in Italian. No visual redesign or markup/class/style changes outside required login reuse.
- Keep `/admin/login.php`, `/telecronista/login.php`, `/pos/login.php`, `/photo/admin/login.php` as compatibility entry points. They may pass a validated intended area/return destination to central login; after authentication route to an allowed sector landing page. Do not trust arbitrary `ritorna`/redirect destinations.
- All logout aliases call shared staff logout, clear the common cookie/session and rotate/expire session state; captain logout stays separate.
- Store canonical staff ID and minimal session metadata only. Resolve `attivo`, `is_super_admin`, assignment roles and station from DB on each request so changes apply next request. Existing sessions that lack the canonical shape must reauthenticate or be safely upgraded only after verified identity.
- Enforce forced password replacement before normal protected access using a persisted temporary-password marker and a dedicated change-password route. Password reset must not grant privileges or retain unsafe sessions.

## Authorization enforcement and UI behavior

- Inventory every page, API, upload handler, tool and write action in `03_AUTHORIZATION_INVENTORY.md`; every protected file gets a server-side guard regardless of hidden navigation/UI.
- Explicit capabilities should replace role comparisons; protect object-level access, including POS order station, media edition/file/upload/ZIP ownership, and team/match operations.
- Current page/menu visibility is presentation only. It must derive from authorized capabilities, but backend enforces independently.
- Recheck staff active status/assignment from DB every request and fail closed. Do not cache assignment across requests. Revocation, role change, password change and account disable should affect next request, including existing sessions.
- Keep the current Italian UI/UX exactly: preserve structures, classes, styles, layout, colors, responsive behavior and interactions. New management forms reuse existing patterns and Italian copy.
- Keep existing business logic and attribution IDs. No business-row rewrite as part of auth migration.
- Preserve current archive distinctions: uploader edition restriction; uploader own-file download/delete; editor structure and ZIP permissions without delete; archive admin full access.
- Ensure JSON APIs respond with appropriate 401/403 JSON and HTML routes use existing Italian error/redirect conventions.

## Migration strategy

1. **Preflight:** capture actual production DDL/version and hosting limits, backups, row counts, account/role inventory, FK references, media edition ACLs, POS stations. Compare with local imported schema and complete SQL; no production assumptions from local DB.
2. **Mapping report:** map old `admin.role` values and POS/media account subsets to one or more reviewed per-area assignments. Since current local actual `admin.role` is absent, use a production dump or pre-upgrade source. Flag ambiguous rows; do not auto-elevate. Determine initial Super Admin through an explicit controlled bootstrap, not role-name inference.
3. **Additive schema reconciliation:** create migration scripts with checks for existing structures and safe transactions/rollback; reconcile schema before running target code. Do not execute `database_schema_completo.sql` against populated DB.
4. **Data migration:** keep `admin.id`/hash/usernames; copy roles into assignment rows; copy POS station into POS assignment; preserve `media_accesso_edizione`; retain all history/FKs. Use dry-run and idempotent inserts. Keep rollback snapshot.
5. **Compatibility deployment:** central login/service first, legacy URL adapters and all guards/API endpoints deployed consistently. Avoid mixed app nodes where old queries and new-only schema coexist. Force invalidation/reauth for legacy sessions.
6. **Verification and cutover:** verify counts, FK integrity, assignment uniqueness, no unknown roles, no unauthorized Super Admin, media ACL parity, POS station mapping, and login paths across all hostnames. Keep rollback window.

## Testing strategy

- Unit/policy matrix: every area-role-capability combination; no assignment; inactive/deleted user; Super Admin; sector manager only own area; unauthorized cross-area view/change; cannot grant admin or Super Admin; temporary password gating.
- Integration tests with realistic schema: current actual table shape, migration idempotency, stable IDs/FKs, role mapping, media access rows, POS stations and order attribution.
- Security tests: CSRF on all mutations, session fixation/regeneration, cookie flags/domain, open redirect, brute force, password hashing/rehash, SQL injection, IDOR, JSON 401/403, fail-closed DB errors, role revocation in a pre-existing session.
- Module regression: team/captain registration/login/reset isolated; admin CRUD and payments; telecronista restrictions; POS create/history/status by role/station; archive edition, upload chunks, download, delete, ZIP restrictions.
- UI regression: screenshot/markup comparison; ensure Italian messages and preserve current frontend structures/styles exactly.
- Hosting compatibility tests on the target AlterVista PHP and DB runtime, including subdomain cookies, `.htaccess`, upload limits, FK/DDL behavior and session persistence.

## Concrete implementation touchpoints

Core: `includes/auth.php`, new central staff auth/authorization/repository modules, `admin/login.php`, `telecronista/login.php`, `pos/login.php`, `photo/admin/login.php`, all logout aliases, `includes/config.php` only if central URL/cookie configuration requires it.

Authorization and users: `admin/**` protected entrypoints, `telecronista/**`, `pos/includes/{auth_check,role_check}.php`, every `pos/**` page/API, `pos/admin/users.php`, `photo/includes/media_auth.php`, `photo/api/_bootstrap.php`, all `photo/admin/**`, `photo/api/**`, `photo/upload/**`, `photo/includes/media_repository.php` user-management methods, and a new Super Admin/sector-scoped management interface.

Database: new versioned migration(s) derived from actual production schema; do not modify `database_schema_completo.sql` as a migration. Captain files under `capitano/**` and `includes/auth_capitano.php` should receive regression coverage, not unified staff auth changes.

## Potential blockers

- Production schema, actual AlterVista PHP/MySQL/MariaDB versions, and deployed data are not available from repository/local environment.
- Actual local DB is already new-model; running current legacy queries against it is incompatible. Need a trustworthy source of pre-import role/station values to map accounts.
- Exact initial Super Admin bootstrap and policy for sector administrators managing lower roles need product/security ownership confirmation before implementation.
- Cross-host session persistence and configured production session storage/cookie behavior require deployment validation.
- No current tournament staff-management UI or standard migration framework exists; new page/API and migration conventions must be selected while preserving visual design.
- Confirm how photo-tools maintenance/cron scripts are invoked on hosting before assigning auth policy.
