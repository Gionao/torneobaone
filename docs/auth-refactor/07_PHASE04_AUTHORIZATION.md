# Phase 04 — centralized authorization and delegated sector management

## Policy matrix

| Area | Highest role | Delegable by sector admin | Intended boundary |
|---|---|---|---|
| `torneo` | `amministratore` | `segreteria` | Tournament administration remains highest-role only; Super Admin bypasses sector role checks. |
| `partite` | `gestore` | `telecronista` | Telecronisti cannot use tournament administration routes; the destructive match delete handler requires `gestore`. |
| `pos` | `amministratore` | `operatore`, `bar`, `cucina` | POS role and station are loaded from current assignment; account CRUD only modifies POS assignment, never global identity. |
| `archivio` | `amministratore` | `editor`, `uploader` | Uploader edition ACL remains in `media_accesso_edizione`; editors/admins keep wider existing edition scope. |

Only a Super Admin can grant a sector's highest role, create/promote a Super Admin, alter global account status, or change another account's global password/status. Sector managers can grant/revoke/disable only subordinate assignment rows in their own sector, cannot alter themselves or Super Admin identities, and cannot reset another user's global password. Revocation/disable does not delete the identity or any assignment in another sector. Staff account lookup in sector managers is exact username based on the existing create form and only reports/changes the requested sector assignment; it does not disclose other assignments or profile data. Password is required only when the exact username is not an eligible existing account. New accounts are minimal, inactive Super Admin false, and forced to change password on first login.

## Implementation

- `includes/staff_authorization.php` provides fixed sector/role allowlists, role hierarchy, `current_staff_user()`, `is_super_admin()`, `get_sector_role()`, `can_access_sector()`, `has_sector_role()`, `can_manage_sector_users()`, `can_assign_sector_role()`, and `require_sector_access()`. Assignment mutation and global account functions centralize the delegation checks and use prepared SQL. Unknown sectors/roles fail closed.
- Existing tournament/match guards in `includes/auth.php` call the shared policy. POS role checks resolve the database principal each request and API role checks return 401/403 without redirect. Media authorization derives the normalized archive assignment and reloads edition ACLs from current DB state.
- POS and archive user screens no longer query `admin.role` or delete global accounts. They create a minimum-privilege staff identity when needed, or assign the exact matching active eligible account, update assignment-local role/station, and disable/revoke only that sector assignment. Sector screens cannot reset global passwords. Existing page layout is retained; only hidden CSRF fields were added to the POS mutation forms.
- `staff/users.php` is the new Super Admin-only global account/assignment panel. Global state/super-admin mutations serialize on `staff_security_guard`; the existing last-active-Super-Admin database trigger remains the final invariant. The shared sector selector links to this panel only for Super Admins.
- `staff_assegnazioni.attivo` was added to the new-install schema and made additive/idempotent in the Phase 02 migration artifact. Authorization ignores inactive assignments; reassigning an existing assignment re-enables it. No DB was migrated by this code task.
- POS API POST operations require the shared CSRF token in `X-CSRF-Token`; the existing POS page navigation injects the token into same-origin POST fetches without changing visible UI. Archive API CSRF checks use the same shared token. Existing admin forms already use the compatibility CSRF wrappers; legacy GET-based delete actions remain a compatibility issue to convert in a later integration pass.
- No administrative audit logging was added. Existing staff assignment mutation routes no longer write the legacy media user-management audit entries.

## Tests and validation

Run the disposable database test using the Phase 03 instructions in `06_PHASE03_AUTHENTICATION.md`. The harness ensures `staff_assegnazioni.attivo` exists only in the allowlisted disposable database. Phase 04 currently passes 44 assertions on MySQL 8.0.30 for all role-code allowlists, cross-sector denial, hierarchy/self-promotion denial, subordinate grant, assignment revocation and disable/re-enable, shared-account preservation, current database account/assignment resolution, session revocation, POS station lookup, CSRF rejection and captain namespace isolation. The auth-only disposable DB lacks media tables, so the archive edition ACL test is explicitly skipped there; run the same script against a full disposable schema to execute that conditional assertion. Syntax checks were also run on changed PHP files.

This is not a complete browser matrix for every business endpoint. Remaining verification: exercise each guarded HTML/API endpoint under all deployed roles, run end-to-end POS order flows with the new fetch CSRF header, verify archive editor/uploader upload/download/delete scopes and edition ACLs, and verify active Super Admin trigger behavior on the controlled target before rollout.

## Deployment and schema notes

The Phase 02 migration remains a deployment prerequisite, and the additive `staff_assegnazioni.attivo` column is now part of that artifact. Apply it to a controlled target before deploying these PHP changes. This task did not change the configured development or production database. The application still intentionally fails closed if `admin.must_change_password` is missing.

## Files

- Added: `includes/staff_authorization.php`, `staff/users.php`, `docs/auth-refactor/07_PHASE04_AUTHORIZATION.md`.
- Updated: `includes/auth.php`, `includes/staff_auth.php`, `database/schema_complete.sql`, `database/migrations/20261104_unified_staff_auth.sql`, `staff/index.php`, `pos/admin/users.php`, `pos/includes/auth_check.php`, `pos/includes/navbar.php`, `photo/admin/utenti.php`, `photo/includes/media_auth.php`, `photo/includes/media_repository.php`, `tests/auth/staff_auth_test.php`.
- No CSS, captain authentication, business schema/data, or unrelated business workflows were changed.
