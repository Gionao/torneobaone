# Phase 05 — staff management interfaces

## Interfaces

- `staff/users.php` is the centralized Super Admin-only management page. It has username/status/sector filters and the requested matrix columns (Utente, Torneo, Partite, POS, Archivio, Stato, Azioni). Each cell shows the role, inactive-assignment status, and POS station where present. Super Admin accounts are highlighted and password-change-required accounts are marked. Actions include account creation/attachment, username edit, account activate/deactivate, Super Admin promotion/demotion, role assignment/revocation, assignment enable/disable, and POS station assignment. Sensitive actions have confirmation dialogs. Existing database triggers remain authoritative for the last active Super Admin.
- `staff/sector-users.php?sector=torneo|partite` is scoped to the sector's manager (or Super Admin). It shows only username, that sector's role/assignment status, search results, and permitted actions. It does not query or render other sector assignments. Tournament administrators manage `segreteria`; match `gestore` manages `telecronista`. Super Admin may additionally appoint the highest role.
- Existing `pos/admin/users.php` keeps its layout and styling. Its existing username/role/station form supports new operator/bar/kitchen identities, exact username reuse, role changes, station changes, and reactivation (reassigning an inactive assignment). The handler refuses POS administrator assignment by a POS administrator. Revocation affects only the POS assignment. A new account receives a server-generated temporary password; the password fields remain visually in place but are optional and explicitly not used.
- Existing `photo/admin/utenti.php` keeps its layout and styling. It supports editor/uploader assignment, existing-account reuse, uploader edition ACL configuration, role changes and sector-only revocation. The same server-generated temporary-password workflow applies to new identities; an existing account's password is never overwritten when attached. The repository validates the uploader assignment and allowed edition IDs server-side.

All new/generated passwords meet the password policy, are hashed immediately, set `must_change_password=1`, are not emailed or stored in plaintext, and appear only in the authenticated response that creates the identity. Responses containing credentials use `Cache-Control: no-store`. Existing accounts are matched by exact username and preserve their existing hash and all unrelated assignments.

## Security and compatibility

- All management POSTs require the shared staff CSRF token. All write handlers re-check Super Admin or sector delegation policy server-side; frontend controls are not authorization boundaries.
- User lookup is limited to active non-Super-Admin identities, requires at least three query characters, caps results at 20, and returns only ID/username plus the requested sector's assignment role/station. It does not return hashes or unrelated assignments.
- Global Super Admin changes lock `staff_security_guard`; database triggers protect the last active Super Admin. No delete-account workflow was added; account identity/FKs are preserved and sector access is revoked instead.
- No existing CSS, component classes, color rules, layout markup, animations, or mobile-navigation code were changed. Existing POS/archive page structures remain as before this phase; only their already-present password inputs' `required` validation was relaxed so existing account reuse/new server-generated credentials can work without user-chosen password input. The global and scoped pages are new and use the existing admin CSS conventions. New/changed labels are Italian.

## Tests

`php tests/auth/staff_auth_test.php` against an allowlisted disposable database passes 48 checks, including scoped lookup, existing account preservation, subordinate assignment, role modification, POS station update, revoke/disable/reactivate, generated policy-compliant temporary credentials and forced password change, cross-sector denial, CSRF rejection, and revocation in an active session. The selected disposable DB is auth-only and lacks media tables, so the archive-edition ACL assertion is skipped; execute it on a full disposable schema for that additional check. Syntax checks passed for all changed PHP files. Unauthenticated smoke requests to all three new management routes returned HTTP 302.

Last-active-Super-Admin trigger behavior and browser-based one-time credential display were not exercised in this Phase 05 run. Full-schema archive edition validation, all-role browser regression, and the production PHP/database compatibility check remain required before deployment.

## Files

New: `staff/sector-users.php`, this document.

Updated: `staff/users.php`, `includes/staff_authorization.php`, `pos/admin/users.php`, `photo/admin/utenti.php`, `tests/auth/staff_auth_test.php`.

No Phase 06 work was started.
