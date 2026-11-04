# Phase 03 — centralized staff authentication

## Implemented

- `includes/staff_auth.php` is the sole staff credential/session implementation. It reads account state and sector assignments from `admin`, `staff_assegnazioni`, and `staff_ruoli_area` on each request. Legacy role/session values are not authorities.
- `includes/auth.php` now provides backward-compatible names that delegate to the staff service. Tournament and match guards resolve their sector role from the database; match-only roles cannot enter the general tournament guard.
- The four existing sign-in pages (`admin/login.php`, `telecronista/login.php`, `pos/login.php`, `photo/admin/login.php`) retain their existing markup/styles and authenticate through the same service. Any active staff credentials can be used at each entry URL; subsequent server-side guards restrict sector access. Photo `ritorna` accepts only a constrained archive-local path.
- New `staff/login.php`, `staff/index.php`, `staff/change-password.php`, and `staff/logout.php` provide a compatibility entry to the existing admin login view, sector selector, mandatory password-change flow, and common logout. The existing admin login remains the single shared credential form. Super Admin sees all four sector links; multiple sector assignments get a selector; a single sector redirects there; no assignments shows an Italian denial message.
- Staff session cookie: `TBSTAFFSESSID`, HttpOnly, SameSite Lax, Secure under production/HTTPS, root path, shared `.torneobaone.it` domain only in production. Session idle timeout is 30 minutes and absolute lifetime 8 hours; session IDs rotate after login and every five minutes. Logout clears only the staff namespace and preserves unrelated session keys. Captain authentication remains in `includes/auth_capitano.php` with its existing cookie/session keys and account lifecycle.
- Authentication failures are generic and inactive accounts are rejected. Login attempts are limited per session (five per 15 minutes); login POSTs require same-host Origin/Referer outside CLI. Existing hashes verify as-is and are rehashed only when `password_needs_rehash()` requests it.
- Forced password change uses `admin.must_change_password`; passwords must be 12–72 bytes with upper/lowercase and a digit, confirmation and staff CSRF token must pass, and the submitted password cannot equal the current password. The flag clears only on successful database update.
- POS and media authentication adapters use the live staff principal. POS station and current role are loaded from sector assignments, never session-stored role values. Media uploader/editor/admin semantics and edition checks remain in the media layer, backed by the new assignment role.

## Schema/deployment gate

The PHP service intentionally fails closed if `admin.must_change_password` is absent. Phase 01/02 confirmed the configured development database has the new sector tables but was **not** modified during Phase 02; it therefore still lacks this column. Phase 02 migration was only run on disposable copies. Apply and validate the Phase 02 migration on a controlled target before enabling this code. No production or configured development DB writes were performed in Phase 03. The development DB had zero staff users during Phase 02 inspection, so no real account login can be exercised against it.

Central auth tests use disposable MySQL database `tb_auth_migration_test`, not the configured development database. The unit/integration test validates assignments, active status, password-change flag and session state against a Phase 02 schema. Production cross-host session storage/cookies and actual AlterVista PHP/database support remain to be verified.

## Executable tests

After creating a disposable database from the Phase 02 schema/migration and installing the test-only fixture tables, set test environment variables. PowerShell example:

```powershell
$env:TB_AUTH_TEST_MODE = '1'
$env:TB_AUTH_TEST_DB = 'tb_auth_migration_test'
$env:TB_AUTH_TEST_DB_HOST = '127.0.0.1'
$env:TB_AUTH_TEST_DB_USER = 'root'
$env:TB_AUTH_TEST_DB_PASSWORD = ''
php tests/auth/staff_auth_test.php
```

The script refuses web execution and database names outside its disposable allowlist; it creates and removes only uniquely named test staff rows. Phase 03 execution on MySQL 8.0.30 passed 24 checks: unauthenticated state, valid/invalid login, legacy password hash, session ID regeneration, multi-sector resolution, POS station lookup, disabled login and mid-session revocation, temporary password gating/change and CSRF, idle/absolute expiration, logout, captain key preservation, same-host origin, and unsafe return rejection. A PHP built-in-server smoke test returned HTTP 200 for the four existing login pages and HTTP 302 for `/staff/login.php` (to the established login view) and protected admin, POS and media pages without authentication. No full browser/hosted cross-subdomain test was run.

## Files changed

Core/auth/session: `includes/staff_auth.php` (new), `includes/auth.php`, `includes/config.php`.

Entry points and guards: `admin/login.php`, `telecronista/login.php`, `pos/login.php`, `photo/admin/login.php`, `admin/partite/delete.php`, `pos/includes/auth_check.php`, `pos/includes/role_check.php`, `photo/includes/media_auth.php`.

Shared staff identity consumers/logout: `admin/pagamenti/inserisci.php`, `photo/admin/_header.php`, `photo/includes/media_repository.php`, `pos/index.php`, `pos/logout.php`, `pos/operator_panel.php`, `pos/admin/orders_history.php`, `pos/admin/users.php`, `pos/api/create_order.php`, `pos/includes/navbar.php`.

New routes/tests/docs: `staff/login.php`, `staff/index.php`, `staff/change-password.php`, `staff/logout.php`, `tests/auth/staff_auth_test.php`, this document.

No captain PHP files, existing CSS, or existing login-page HTML/CSS layouts were modified.

## Remaining integration (not performed in Phase 03)

- Run the Phase 02 schema migration through approved operational steps before rollout, then map ambiguous old `admin` roles manually as already documented in `database/migrations/README.md`.
- Sector user-management pages `pos/admin/users.php` and `photo/admin/utenti.php` plus `photo/includes/media_repository.php` CRUD still contain legacy `admin.role` SQL. They are not authentication sources, but they need the Phase 04 scoped assignment-management refactor; new-account creation there does not yet populate the normalized assignment model/temporary flag.
- Finish endpoint-by-endpoint capabilities and object/station scope review in Phase 04; Phase 03 changed only guards needed to route authentication through the shared principal. Existing business authorization policies outside those wrappers need broader regression coverage.
- Login rate limiting is currently per session and can be bypassed by clearing cookies; a shared rate-limit store is a security hardening follow-up if supported hosting permits it.
- Verify same-host Origin/Referer availability, TLS termination, shared PHP session storage across subdomains, inactivity expectations, and SameSite behavior on AlterVista.
- Review the new staff login/selector/password pages in a browser for visual fit without changing the existing UI/UX.
