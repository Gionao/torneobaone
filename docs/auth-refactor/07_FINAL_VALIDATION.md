# Phase 07 — final validation and release readiness

Validation performed on 2026-11-04. Production databases were not used or modified. All migration and policy-matrix operations below used newly created disposable MySQL 8.0.30 databases (`tb_auth_p07_clean_20261104`, `tb_auth_p07_legacy_20261104`) or the previously allowlisted auth-only database (`tb_auth_migration_test`).

## Release assessment

**NOT READY FOR DEPLOYMENT.** Core authentication tests, the authorization helper matrix, clean schema installation, and a synthetic legacy migration fixture passed. However, application business regression, full media-edition/API tests, browser desktop/mobile comparison, exact imported-production schema validation, and concurrent Super Admin operations remain unexecuted. These are release gates, not assumed passes.

## Database changes and migration results

| Test | Status | Result |
|---|---|---|
| Clean install using `database/schema_complete.sql` | **PASS** | Imported into a new empty disposable DB on MySQL 8.0.30. Found 38 base tables, 11 fixed staff roles, seven triggers, and zero seeded staff accounts. |
| Migration from original-schema fixture | **PASS (synthetic fixture only)** | Recreated `assets/sql/database_schema_torneo.sql` with its hard-coded database selection redirected to a new disposable DB; seeded five staff identities (one generic `admin`, one each `operatore`, `bar`, `cucina`, `telecronista`), one captain, and one tournament fixture. Applied `20261104_unified_staff_auth.sql`. |
| Account/password preservation in that fixture | **PASS** | All five account IDs remained; all five password hashes matched the seeded hash. Captain and tournament fixture counts remained 1 each. |
| Role and POS station migration | **PASS (fixture scope)** | `telecronista -> partite.telecronista`, POS operator/bar/kitchen mappings were created; `Cassa Nord` was retained on the operator assignment. Generic `admin` was reported ambiguous and left unassigned, as intended. |
| Foreign keys / duplicate assignments | **PASS (fixture scope)** | 24 relationship constraints present, zero dangling assignment relationships, zero duplicate `(id_admin, area)` assignments. Database separately rejects duplicate sector assignments and invalid area/role combinations in the policy matrix tests. |
| Archive edition ACL preservation | **NOT RUN** | The original-schema fixture has no media-edition ACL tables/data. No populated archive ACL dump was available. |
| Migration from a real previously imported/updated deployment schema | **NOT RUN** | No trusted populated pre-import/deployment dump was supplied. The migration was also invoked on an already unified disposable schema: prior operations remained duplicate-free and seven triggers remained installed, then MySQL returned the documented `Trigger already exists` error on non-repeatable trigger DDL. |
| Business data-loss/count comparison | **NOT RUN for a real dataset** | The synthetic fixture covered only one tournament and captain; no populated teams, transactions, POS orders, media files, or ACL dataset was available for comprehensive before/after counts. |

## Authentication tests

| Test | Status | Result |
|---|---|---|
| `tests/auth/staff_auth_test.php` | **PASS** | 50 passed, 0 failed on the allowlisted disposable auth DB. Covers correct/incorrect credentials, inactive account rejection, shared staff service, session ID regeneration, forced password replacement, password hash verification, CSRF rejection/acceptance, idle and absolute expiry, and captain session namespace isolation. |
| Current assignment and global-account revocation | **PASS** | Tests resolve current DB account state; cross-session-style fixture verifies sector revocation immediately removes that sector while preserving another assignment; global deactivation invalidates the simulated active session. |
| Actual sector selector and one-sector redirect | **NOT RUN** | Helpers and shared login service were tested, but no browser login flow was executed for each account shape. |
| Login submissions via every legacy login URL | **NOT RUN** | Legacy compatibility is present in code; actual form submissions and redirect-loop behavior were not exercised. |
| Captain login/business flow | **NOT RUN** | Captain namespace isolation is tested; an end-to-end captain login, registration and team workflow was not run. |

## Authorization and delegated administration

| Test | Status | Result |
|---|---|---|
| `tests/auth/authorization_matrix_test.php` | **PASS** | 250 passed, 0 failed. Tests all 11 defined sector roles against all four sector policies; also no-role, disabled identity, Super Admin overrides, sector delegation, cross-sector denial, invalid-sector fail-closed behavior, global-status restrictions, injection-shaped search input, DB uniqueness/FK constraints, and last-Super-Admin demotion. |
| Cross-sector POS/archive/match manager restrictions | **PASS (policy/helper level)** | POS manager cannot manage archive roles; archive manager cannot manage POS roles; match gestore cannot manage tournament staff. |
| Last active Super Admin protection | **PASS (sequential)** | Matrix test confirmed demotion rejection. Separate SQL attempts to deactivate and delete the only active Super Admin were rejected by MySQL triggers; one active Super Admin and guard count 1 remained. |
| Concurrent last-Super-Admin modifications | **NOT RUN** | No multi-connection race test was performed. |
| Full endpoint matrix, horizontal/vertical escalation by direct request | **NOT RUN** | The 250 assertions exercise centralized policy helpers, not every HTML handler, management form, and API under every role. |
| Direct user-management operations across all sectors | **NOT RUN** | A few delegation and assignment operations are covered in the auth suite; the entire Super Admin/tournament/match/POS/archive interface workflow was not run end-to-end. |

## Application/API regression and security

| Test | Status | Result |
|---|---|---|
| Unauthenticated protected-page/API smoke requests | **PASS (limited)** | Local PHP server against disposable DB returned 302 for tournament, payment, match, Super Admin and sector-management HTML routes; 401 for POS and media API examples; 404 for CLI-only media tools. |
| POS create/read APIs with authenticated business roles | **NOT RUN** | Only unauthenticated direct requests were executed; order/product/coupon/station business regression remains required. |
| Archive upload/download/ZIP and edition restrictions | **NOT RUN** | No authenticated media end-to-end workflow or full media ACL schema was available. |
| Tournament, payment, match, live score/event, captain and POS business regression | **NOT RUN** | Not represented as passing based on syntax or guard-level tests. |
| CSRF | **PASS (partial)** | Password-change CSRF failure/success is tested; POS API code centrally rejects invalid POST CSRF. Direct request matrix against every POST endpoint was not run. |
| SQL injection | **PASS (limited)** | Parameterized SQL is used in reviewed auth/assignment paths; matrix test verifies injection-shaped scoped staff search returns no rows. No general penetration/fuzz suite was run. |
| Session fixation | **PASS (unit-level)** | Successful login rotates the session ID. Cookie flags and separate captain/staff names are checked in the auth harness. |
| Open redirects | **PASS (limited)** | External return path/origin rejection is tested; no browser test of every legacy return URL was performed. |
| Unauthorized direct media/API access | **PASS (limited)** | Unauthenticated archive API examples returned 401; object/edition-level requests as unauthorized authenticated users remain **NOT RUN**. |

## UI regression and Git review

| Test | Status | Result |
|---|---|---|
| CSS/JavaScript source diff | **PASS** | `git diff --name-only -- '*.css' '*.js'` returned no paths. No existing stylesheet or JavaScript file is changed by this worktree. |
| PHP/JavaScript syntax | **PASS** | 151 non-vendor PHP files passed `php -l`; five non-vendor JavaScript files passed `node --check`. |
| Existing page DOM/layout comparison | **NOT RUN (pixel/DOM baseline)** | No browser capture of the original clean branch was available. Deletion controls intentionally changed from links to inline POST forms while preserving button classes, icon/label and confirmation behavior. Phase 05 relaxed existing password inputs' `required` attribute; these are intentional markup/validation changes. |
| Desktop/mobile visual comparison | **NOT RUN** | Browser visual comparisons and responsive screenshots were not performed. |
| Final whitespace/diff check | **PASS** | `git diff --check` passed; Git reported CRLF normalization warnings on edited files. |
| Unexpected or unrelated working-tree changes | **NOT FOUND in inspected status** | The worktree contains expected Phase 01–06 auth changes plus the Phase 07 report/test. Everything remains uncommitted. Existing branch is `feature/unified-staff-auth`; HEAD is `a712a0b` and points at `origin/gestioneAdmin`. No merge or push was performed. |

## Remaining issues / release gates

1. Obtain a protected, trusted pre-migration backup of the actual target schema and test the migration on a restored disposable copy. Compare all relevant business tables, FKs, staff hashes/IDs, POS stations, captains and media ACLs before/after.
2. Run browser tests for sector selector, one-role redirects and all legacy login URLs; ensure no loops or cross-host cookie problems.
3. Run role-by-role direct-route and API tests, including authenticated CSRF failures, object-scope checks, upload/download and unauthorized edition IDs.
4. Run complete tournament, registration/payment, captain, match/live event, POS operator/station/order/product/coupon, and archive workflows on disposable data.
5. Perform concurrent last-Super-Admin demotion/deactivation tests with two database connections on the exact target engine/version.
6. Capture desktop/mobile baseline and feature-branch screenshots/DOM checks. CSS/JS source is unchanged, but this is not a substitute for visual comparison.
7. Validate PHP, MySQL/MariaDB behavior and trigger/CHECK enforcement on the actual deployment runtime. MySQL 8.0.30 is the only engine validated here.
8. Review Phase 05 one-time credential display and operational handoff procedure before creating real staff accounts.

## Backup and rollback procedure

No production migration is authorized by this report. Before a separately approved deployment:

1. Record `SELECT VERSION(), DATABASE()` and confirm the exact target. Stop application writes / enable maintenance mode.
2. Make and verify a protected full logical backup including routines, triggers, events and binary values (e.g. `mysqldump --single-transaction --routines --triggers --events --hex-blob DB_NAME > before-staff-auth.sql`). For non-transactional tables, use a consistent maintenance lock. Restore-test the dump into a separate DB.
3. Restore that dump into a disposable database, apply the documented migration there, export its unmapped-role report, and complete the comparisons/test gates above. Resolve every ambiguous `admin` role using approved account history; do not infer Super Admin status.
4. Apply schema changes only under an approved maintenance/change window and retain the verified pre-migration backup. DDL auto-commits; do not rely on transaction rollback or improvised reverse DDL.
5. If rollout validation fails, keep the application in maintenance mode and restore the verified pre-change backup using the organization's tested restore procedure. Reconcile any writes made after cutover before restore. Do not drop legacy columns/tables as a rollback tactic.

## Deployment checklist

- [ ] Backup and restore drill verified; target DB/version confirmed.
- [ ] Migration tested against an exact disposable production-schema copy; all ambiguous accounts resolved.
- [ ] Migration applied in approved window; role report reviewed; first Super Admin bootstrapped securely.
- [ ] Full auth/authorization, media ACL, direct API and business regression suites pass.
- [ ] Concurrent Super Admin trigger checks pass on target engine.
- [ ] Legacy login/URL, desktop/mobile, redirects, and visual regression checks pass.
- [ ] Production PHP/runtime compatibility confirmed; no default credentials; secrets and backup access protected.
- [ ] Rollback owner, maintenance mode and restore steps staffed and rehearsed.
- [ ] Branch diff reviewed and approved; merge/push performed only by an authorized maintainer.

**Final decision: NOT RELEASE READY.** The verified passing subset is substantial but does not satisfy the requested critical application-regression and target-migration gates. No deployment should proceed on the basis of this report alone.
