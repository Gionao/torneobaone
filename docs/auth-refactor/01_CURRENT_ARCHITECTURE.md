# Current authentication architecture

Audit performed on branch `feature/unified-staff-auth` on 2026-11-04. No application behavior, database rows, or schema were changed. Repository tree was inspected excluding generated `vendor/` and binary assets; authentication-relevant modules are listed below.

## Runtime and configuration

- Local runtime: PHP 8.5.12 CLI; connected database reports MySQL 8.0.30. This establishes only the development environment, not the AlterVista runtime.
- `includes/config.php` obtains DB credentials and environment URLs from environment variables, defaulting locally to `my_torneobaone`, `root`, blank password. Production URL constants use separate admin/POS/media hosts. `includes/db.php` uses PDO, native prepared statements and exception mode.
- MariaDB command-line client present is 13.0.2; it is not the connected server (which reports MySQL 8.0.30).
- Composer lock/vendor are present; no authentication framework is apparent. `.htaccess` files exist at root, `includes`, `photo`, `pos`, and nested includes/tools/storage locations.
- No migrations directory or migration framework was found. SQL files: `assets/sql/database_schema_completo.sql`, `database_schema_torneo.sql`, `database_schema_pos.sql`, `database_schema_archivio.sql`, `database_insert.sql`, `database_insert_risultati.sql`.

## Staff systems as implemented in PHP

There is one *intended shared account table* (`admin`) but several independently gated authentication entry points and session conventions:

- Tournament admin and telecronista call `includes/auth.php`: `admin_login()` selects `admin.id, username, password_hash, role`; stores `admin_id`, `admin_username`, `admin_role`, `admin_token`. `require_admin()` and `require_partite_access()` make role decisions from session-cached `admin_role`.
- POS `pos/login.php` independently checks the same `admin` table for `role` and `station_name`; stores `pos_admin_id`, `pos_admin_user`, `pos_admin_role`, `pos_admin_station`. `pos/includes/auth_check.php` only reloads the DB role/station when the role session value is empty, so changed/revoked roles can persist in existing sessions.
- Photo login (`photo/admin/login.php`) calls the general `admin_login()` and shares its session; `photo/includes/media_auth.php` re-reads the account role from DB on every request and checks role membership. Edizione access is separately loaded from `media_accesso_edizione`; permissions are checked per album/file. Photo helpers internally memoize user and edition access during a PHP request.
- Legacy staff role vocabulary overlaps across areas (`admin`, `telecronista`, `uploader`, `editor`, POS `operatore`, `bar`, `cucina`). The singular `admin.role` cannot represent one user’s distinct role in multiple sectors.
- Staff login URLs: `/admin/login.php`, `/telecronista/login.php`, `/pos/login.php`, `/photo/admin/login.php`. Logouts: respective `admin/logout.php`, POS logout, media logout. Photo entry redirects to the media login; admin/telecronista/POS have their own forms.
- Password hashing is `password_hash(..., PASSWORD_BCRYPT, cost 12)` and verify uses `password_verify()`. General staff password update helper `admin_change_password()` is in `includes/auth.php`; photo `utenti.php` and POS `users.php` implement local staff password changes. No central staff temporary-password/first-login flow found.
- General staff sessions configure HttpOnly, SameSite Strict, root path, production domain `.torneobaone.it`, secure only when `ENV=production`; regenerate on successful admin/POS login and periodically (300s) in guards. `admin_logout()` empties and destroys session but does not explicitly expire cookie. Media uses same session functions. POS logout unsets its own keys. Captain sessions are separate by prefixed helpers and values.
- CSRF: general `csrf_token()`/`csrf_verify()` in `includes/auth.php`; media API accepts header or POST token. Some legacy admin destructive actions use GET plus CSRF query token; several POS mutations have local/inconsistent protections. This audit records rather than changes these flows.

## Captain authentication isolation

`includes/auth_capitano.php` defines separate session start/auth/login/logout/CSRF helpers. It authenticates `capitani.email` and `password_hash`, requires email verification and an active team, regenerates session on login, stores captain-specific keys, and provides registration, email verification, forgot/reset password flows. Captain pages live under `capitano/`; no staff `admin` role is used. Preserve this separation and existing business data/foreign keys (`squadre.id_capitano`, tokens, consents).

## Modules and current user management

- Tournament admin: `admin/` pages protect most requests with `require_admin()`; shared operations create/update teams, players, registration and payment records. Transactions reference `admin.id` as organizer.
- Matches: `admin/partite/` supports general admin and telecronista, with proxy routes under `telecronista/partite/` that include admin files; role-dependent differences include delete links and route redirection.
- POS: `pos/` role guards enforce operational role sets; POS orders reference `admin.id`, retain `station_name` snapshots, and history limits operators to their own orders. `pos/admin/users.php` creates/modifies/deletes role-coded rows in shared `admin`; station required for operator.
- Media: `photo/admin/` and `photo/api/` use media-specific guards. Roles distinguish uploader/editor/admin, uploader access is restricted by `media_accesso_edizione`, uploader can download/delete own files, editor can organize/download but not delete, admin is unrestricted. `photo/admin/utenti.php` independently creates/changes/deletes users and manages edition access. `media_log` is an existing content-operation log, not the proposed staff role-change audit log.

## Actual database observations

The configured local DB is accessible and includes 38 tables/views, including the proposed `staff_assegnazioni`, `staff_ruoli_area`, `staff_audit_log`, and the full tournament, POS, and media tables. Actual `admin` columns are `id`, `username`, `password_hash`, `attivo`, `is_super_admin`, timestamps. It has no `role` or `station_name`, both queried throughout legacy PHP. `staff_assegnazioni` has `(id_admin, area)` primary key, `ruolo`, optional `station_name`; `staff_ruoli_area` key is `(area, ruolo)`. `capitani` remains separate.

This indicates the imported schema is already newer than deployed PHP expectations. Requests touching legacy staff queries may fail against this DB; no runtime requests were made. Do not use the old SQL files as migrations or re-import them onto populated data.
