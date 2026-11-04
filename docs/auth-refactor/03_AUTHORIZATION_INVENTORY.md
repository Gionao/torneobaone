# Authentication and authorization inventory

Paths below are repository-relative. “Target” describes the requested architecture, not implemented behavior. Public/captain endpoints are included to mark intentional access boundaries. Static assets are omitted; all PHP entrypoints within the protected functional families are represented.

## Staff login, shared guards, and user management

| Endpoint/resource | Required auth | Current authorization | Target authorization | Required modifications |
|---|---|---|---|---|
| `admin/login.php`, `telecronista/login.php` | No login | `admin_login()` checks legacy `admin.role`; shared admin session | One staff login, resolve available sectors/roles; retain legacy URLs as redirect/entry adapters | Replace login handlers with central auth route; preserve Italian form/design if login UI remains |
| `pos/login.php` | No login | Independent login against `admin.role`, creates POS-only session | Same central staff identity; resolve POS assignment; preserve station | Replace local verification and session creation with central service |
| `photo/admin/login.php` | No login | Calls shared staff login then media role allowlist | Central login; route only users with archive assignment and enforce edition scope | Adapter to central auth, keep media return URL validation and UI |
| `admin/logout.php`, `pos/logout.php`, `photo/admin/logout.php` | Staff session optional | Different logout behavior/keys | Central logout invalidates unified staff session across sectors; captain unaffected | Delegate shared invalidation, preserve legacy endpoints |
| `includes/auth.php` | Session for guards | Cached `admin_role`; role checks in session | Shared staff identity, DB-current active/superadmin/assignments each request; fail closed | Central resolver/guards and CSRF/session lifecycle |
| `pos/includes/auth_check.php`, `pos/includes/role_check.php` | POS session | POS session-only role; DB reload only if role absent | Central resolver + current POS assignment, station scope each request | Replace with shared sector guard |
| `photo/includes/media_auth.php`, `photo/api/_bootstrap.php` | Archive staff session | DB role reread, role and edition checks; API 401/403 | Shared principal + archive assignment; keep edition ownership checks | Adapt role mapping and preserve edition-level logic |
| `pos/admin/users.php` | POS admin role | Creates/updates/deletes rows and global `admin.role` | POS administrator manages only subordinate POS roles; Super Admin alone grants sector-admin/Super Admin | Replace CRUD targets with assignment records; prevent cross-sector visibility/escalation |
| `photo/admin/utenti.php` | Media admin role | Media admin manages roles including `admin`, edition limits | Archive administrator manages subordinate archive roles and edition scope; Super Admin grants administrator | Scope queries and mutation; preserve edition ACL; no new role-change log |
| No tournament staff user-management page found | N/A | N/A | Super Admin user/assignment administration; sector managers subordinate roles in own area | New backend/interface pages per existing patterns; establish UI separately, preserve existing design |

## Tournament administration and matches

| Endpoint/resource | Required auth | Current authorization | Target authorization | Required modifications |
|---|---|---|---|---|
| `admin/index.php`, `admin/giocatori/{index,form,delete}.php`, `admin/squadre/{index,form,delete,dettaglio}.php`, `admin/iscrizioni/index.php`, `admin/pagamenti/{transazioni,inserisci,modifica,elimina,metodi}.php` | Staff | `require_admin()`; admits any logged non-media/non-telecronista role based on cached session | `torneo` assignment at action-appropriate role, or Super Admin | Replace blanket guard with area/action policy; retain DB business writes and CSRF |
| `admin/partite/{index,form,delete,risultato,eventi}.php` | Staff | `require_partite_access()` in pages (verify per-file); admin and telecronista behavior differs | `partite` role; gestore full match management, telecronista constrained match entry, Super Admin full | Use area guard and explicit capabilities; review all write endpoints, maintain route behavior |
| `telecronista/index.php`, `telecronista/partite/{index,form,risultato,eventi}.php` | Staff | Shared session; subpages include corresponding `admin/partite` scripts | Matches-sector role only; central redirect/adapters | Retain URL aliases, avoid duplicate authorization logic |
| `tools/test-mail.php` | Staff | `require_admin()` | Explicit authorized operational role or Super Admin | Scope access; ensure not public on hosting |

## POS pages and API

| Endpoint/resource | Required auth | Current authorization | Target authorization | Required modifications |
|---|---|---|---|---|
| `pos/index.php`, `operator_panel.php`, `bar_display.php`, `kitchen_display.php` | POS staff | POS keys and hardcoded role allowlists | POS role assignment; operator station-bound, bar/kitchen constrained, POS admin full sector | Replace session-role checks; enforce station scope |
| `pos/admin/menu_management.php`, `coupons.php`, `statistics.php`, `settings.php`, `orders_history.php`, `users.php` | POS staff | role checks in each file; settings read broader than write; admin-only management | POS capabilities; only POS admin manages sector settings/users, operators limited data access | Normalize server-side action checks and query scoping |
| `pos/api/create_order.php`, `cancel_order.php`, `get_products.php`, `validate_coupon.php` | POS staff | `require_role()` allowed roles; order stores session id/station | Role-capability and station authorization; bind order attribution to staff ID and assigned station | Replace guard and ensure every write uses verified principal/station |
| `pos/api/get_active_orders.php`, `get_completed_orders.php`, `update_order_status.php`, `reopen_order.php` | POS staff | allowed-role arrays; status mutation differs by roles | Sector role capability and station/department scopes | Replace role arrays; verify object-level order access |
| `pos/api/track_order.php`, `pos/stato-ordine.php` | Public/customer-facing status | Token/order behavior (verify implementation before modifications) | Preserve public capability/token semantics; do not turn into staff access | Confirm endpoint contract; keep isolated from staff roles |

## Photo/media pages, APIs, upload routes

| Endpoint/resource | Required auth | Current authorization | Target authorization | Required modifications |
|---|---|---|---|---|
| `photo/admin/index.php`, `galleria.php`, `file.php`, `edizioni/*`, `giornate/*`, `album/*`, `cestino.php`, `file_delete.php`, `upload.php`, `impostazioni.php`, `utenti.php` | Media staff; some editor/admin only | `require_media_login/editor/admin`; uploader own files and edition allowlist | Archive assignment with same uploader/editor/admin capability distinction; per-edition allowlist preserved | Translate role resolution; preserve current per-resource checks; `utenti.php` scope management |
| `photo/api/download.php`, `thumb.php` | Media staff | API login and file/edition checks in endpoints/helper | Archive assignment + edition rule; uploader download-own rule retained | Replace authentication source; verify object-level edition checks on every path |
| `photo/api/zip_create.php`, `zip_status.php`, `zip_download.php` | Media staff | Login; editor/admin ZIP creation; owner/admin job checks | Archive role and edition/ownership constraints | Re-check all files included in ZIP against current edition permissions |
| `photo/api/upload_status.php` | Media staff | Login; status filtered by staff ID | Archive uploader/admin capability and edition access | Rebind identity and ensure old job owner handling |
| `photo/upload/{init,chunk,complete,abort}.php` | Media staff + CSRF | API login, CSRF and upload owner checks; abort permits admin exception | Archive upload capability, current role, edition ACL, owner check | Central principal, request-time revocation checks; preserve chunk security |
| `photo/tools/{maintenance.php,process_zip.php}` | Operational/cron/admin | Utility/maintenance protection, not standard user page | Operational secret/cron or Super Admin as appropriate | Inspect deployment invocation before changing guards |
| `photo/index.php`, `photo.php` | Public | Public/media portal | Public access stays public; private files require media auth | Ensure no direct-storage bypass |

## Captain and public endpoints (deliberately separate)

| Endpoint/resource | Required auth | Current authorization | Target authorization | Required modifications |
|---|---|---|---|---|
| `capitano/login.php`, `registrati.php`, `password-dimenticata.php`, `reset-password.php`, `richiedi-verifica.php`, `verifica-email.php`, `logout.php`, `index.php` | Public/session | `auth_capitano.php`, separate captain session/table/tokens | Unchanged, independent of staff | No staff role coupling; regression test isolation |
| `capitano/dashboard.php`, `giocatori.php`, `pagamenti.php` | Verified captain session | Captain/team ownership and CSRF checks | Unchanged | Preserve team ownership constraints |
| `iscrizione/*`, public tournament endpoints | Public/token/captain as implemented | Business/token-specific | Unchanged | Not staff-auth endpoints; maintain business logic |

## Session/security findings

- Staff role revocation is inconsistent: admin and POS cache role in session; media re-reads DB role each request. POS reload is conditional on missing session role. Thus existing sessions may retain removed permissions.
- Session cookie is host-wide `.torneobaone.it` in production, helping cross-subdomain sessions only if hosting/browser configuration agrees. `photo` and POS have distinct URL hosts; SameSite Strict, HTTPS and cookie domain behavior require end-to-end production verification.
- Admin GET deletions (`admin/giocatori/delete.php`, `admin/squadre/delete.php`, `admin/partite/delete.php`) use query-string CSRF token; security method should be addressed during implementation without changing UI layout.
- Captain has separate CSRF/session keys. Do not merge with staff namespace or invalidate captain sessions as part of staff logout.
