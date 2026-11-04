# Phase 06 — application integration and legacy authorization review

Audit and implementation performed 2026-11-04. No production data or schema was modified. Existing page styling, CSS classes, visual layout, navigation and business workflows were preserved; changes to deletion controls retain the same button classes/confirm prompts while switching the HTTP method to POST.

## Changes made in Phase 06

- Added `require_tournament_operations()` in `includes/auth.php`, allowing the delegated `torneo.segreteria` role to use the registration overview and payment transaction workflows without granting tournament configuration/admin privileges.
- Applied that guard to `admin/iscrizioni/index.php` and `admin/pagamenti/{transazioni,inserisci,modifica,elimina}.php`. Payment method configuration remains administrator-only.
- Converted destructive GET actions for team, player and match deletion to POST + centralized CSRF validation (`admin/{squadre,giocatori}/delete.php`, `admin/partite/delete.php`). Updated their existing buttons in the corresponding indexes and match form without changing styles.
- Converted match-event deletion in `admin/partite/eventi.php` to POST + CSRF while retaining its existing icon/button presentation.

## Repository-wide findings and replaced checks

- Searches across PHP found no remaining `admin.role` SQL reads/writes, nor legacy `admin_*`/`pos_admin_*` staff role/session keys. Captain session keys remain separate by design.
- Staff login/password/session handling is centralized in `includes/staff_auth.php`; compatibility functions/guards in `includes/auth.php` delegate to current DB state. POS role names are an adapter over current `pos` assignments (`pos/includes/auth_check.php`, `role_check.php`); media uses centralized staff identity plus archive assignment/edition checks.
- Tournament admin routes continue to require `torneo.amministratore`; registrations/payment operations now accept `amministratore` or `segreteria`; payment configuration remains administrator-only.
- Match routes use centralized `partite` roles. Match delete requires `gestore`; telecronista business permissions were not broadened or reduced. Legacy telecronista route files remain adapters to the existing match pages.
- POS APIs are guarded by the shared POS authentication/role adapter; POST APIs enforce CSRF centrally. `pos/api/track_order.php` remains intentionally public and read-only for order tracking. Internal photo ZIP/maintenance scripts explicitly return 404 outside CLI.
- Photo upload endpoints enforce authenticated archive role, POST, CSRF and edition scopes. Archive admin endpoints use centralized archive guards. Existing `media_accesso_edizione` restrictions remain in force.
- Public captain authentication/workflows were not changed. Public registration and verification routes remain public by design.
- Legacy POS/archive user pages use normalized sector assignments, scoped server-side authorization and CSRF; no old `admin.role` writer remains.
- Obsolete auth was not removed where compatibility wrappers/routes remain referenced. Legacy database columns were not removed.

## Compatibility and security decisions

- Legacy login URLs remain usable through shared staff authentication; subsequent access is determined from current account and assignment rows, not cached session role values.
- `pos/api/track_order.php` is the only intentionally unauthenticated POS API identified in this review. It is GET-only and returns a constrained order-status projection. CLI tools are not web endpoints.
- Destructive tournament/match actions now require POST and CSRF. SQL parameterization was confirmed in reviewed access/mutation paths; all dynamic authorization inputs are allowlisted by the centralized policy.
- Super Admin behavior is a global override in shared authorization policy, except explicit CLI-only/public boundaries. Last-active-Super-Admin protection is supplied by schema triggers/guard logic, but trigger behavior was not exercised in this run.
- No page redesign or CSS/style edits were made in Phase 06.

## Validation performed

- Repository searches covered PHP and JavaScript for legacy role SQL, staff session role keys, duplicated credential handlers, auth guards, direct admin queries, GET destructive parameters, CSRF usage and protected route families.
- `tests/auth/staff_auth_test.php` against allowlisted disposable DB `tb_auth_migration_test`: **48 passed, 0 failed**. Archive-edition ACL test was skipped because that disposable DB has no media tables.
- `php -l` passed for all Phase 06 changed PHP files; `git diff --check` passed (Git emitted line-ending normalization warnings only).
- Earlier Phase 05 unauthenticated smoke requests to Super Admin and sector management pages returned HTTP 302.

## Modified files

Phase 06: `includes/auth.php`; `admin/iscrizioni/index.php`; `admin/pagamenti/transazioni.php`; `admin/pagamenti/inserisci.php`; `admin/pagamenti/modifica.php`; `admin/pagamenti/elimina.php`; `admin/giocatori/delete.php`; `admin/giocatori/index.php`; `admin/squadre/delete.php`; `admin/squadre/index.php`; `admin/partite/delete.php`; `admin/partite/index.php`; `admin/partite/form.php`; `admin/partite/eventi.php`.

Previously implemented Phase 01–05 authentication/integration files remain modified/uncommitted in the worktree. Main components include `includes/staff_auth.php`, `includes/staff_authorization.php`, compatibility/login routes under `staff/`, `admin/`, `telecronista/`, `pos/`, and `photo/`, POS and media authorization/API handlers, the unified schema/migration and bootstrap SQL under `database/`, and `tests/auth/staff_auth_test.php`. Phase 05 interface files are documented in `08_PHASE05_STAFF_INTERFACES.md`.

## Remaining limitations and release gates

Phase 06 has **not been declared fully validated for production**. The following require a full disposable environment or browser-level regression before deployment:

1. Run media edition ACL tests with the complete media schema, including uploader access to allowed and denied editions and direct upload/download API requests.
2. Exercise last-active-Super-Admin trigger/locking behavior, concurrent demotion/deactivation, and all Super Admin overrides on the deployment DB engine/version.
3. Browser-test registration/payment with both tournament roles; match create/edit/results/events/delete for both match roles; POS station/operator flows and every API; archive upload/download/ZIP and administration. Confirm no redirect loops across legacy login URLs.
4. Validate against the production PHP/MySQL-compatible runtime and execute the documented schema migration before enabling the application. No migration was applied here.
5. Consider moving the one-time generated credentials in Phase 05 to an explicit short-lived retrieval workflow; current credentials are only rendered in the authenticated no-store response, not persisted or emailed.

The known intentionally public endpoints and skipped tests are listed above. No Phase 07 work was started.
