# ADR-038 — Admin roles, abilities, and user management

Alias: numbered ADR-035 on the ORD-02 branch before main took ADR-035 for the Journal.

2026-10-08. Accepted for ORD-02a (Owner decisions D2, D3, D4, D5, D7, D13). Implemented on review branch `modernization/ord-02-online-order-redesign`; not deployed.

## Problem

The admin had one role (`admin`) checked by `AdminMiddleware`, a few form requests, and order actions. There was no account status, user management, login audit, or session revocation. Staff who only handle WhatsApp orders would otherwise get catalog, blog, and import access.

## Decision

- Roles in `users.role`:
  - `super_admin`: full access, manages accounts, approves money changes;
  - `staff_order`: Pesanan Online only;
  - `admin`: **legacy**, pre-ORD-02 accounts;
  - `customer`.
- The legacy `admin` role keeps today's access (catalog, blog, orders, and order money corrections) **without** user management, so nobody is locked out and nobody gains new power. It can be kept on an existing account but never newly granted.
- **No automatic promotion (D2).** The Owner becomes Super Admin only through `php artisan qammaris:grant-super-admin <email|username> --confirm` after verifying the identity. The command previews without `--confirm`, refuses customer or inactive accounts, can `--create` a new account with a one-time password, and writes an audit row with `actor_id = null`.
- Abilities live in `AuthorizationServiceProvider`. It is registered separately because `AppServiceProvider` returns early in console.

  | Ability | Granted to |
  |---|---|
  | `admin.access` | any active admin role |
  | `dashboard.view`, `catalog.manage`, `blog.manage` | `super_admin`, legacy `admin` |
  | `users.manage` | `super_admin` only |
  | `orders.manage` | `super_admin`, `staff_order`, legacy `admin` |
  | `orders.finance` (shipping charge/funding, reimbursements, step corrections) | `super_admin`, legacy `admin` |
  | `orders.cancel` (order) | full admins always; Staff Order only while the order is unpaid and not handed over (D4) |

- Enforcement is server-side at three layers:
  - every `admin.*` route has `auth` + `admin` + an area `can:` middleware (enforced by a test over the route table);
  - form requests authorize with the same gates;
  - order and user actions re-check gates. Menus only mirror the gates.
- Staff Order may correct recipient/address details until handover but cannot change money fields (D5); the workflow rejects such changes, not just the UI.
- Login accepts email **or** username (D3). No remember-me (D7). Inactive accounts get the same generic error as a wrong password. `last_login_at` is recorded.
- Sessions:
  - `users.auth_version` is bumped on deactivation, role change, and password reset (any by an admin), which revokes every older session regardless of session driver;
  - changing one's own password revokes other sessions and keeps the current one;
  - admin idle timeout is 12 hours (`ADMIN_IDLE_MINUTES`, default 720); `SESSION_LIFETIME` must be ≥ 720.
- A temporary password (random or chosen) is shown once to the Super Admin. The user must change it before using any other admin page.
- The **last active Super Admin** cannot be deactivated or demoted. Every account mutation locks all Super Admin rows in id order, then the target, inside one transaction, so concurrent requests cannot both pass the guard.
- `user_admin_changes` records actor, target, action, the changed field list, and allowlisted before/after (name, email, username, role, status, must-change flag). Passwords and hashes appear only as the field name `password`. There is no cascade or purge.

## Consequences

- Production must run the bootstrap once (with Owner verification) before anyone can manage users. Until then the existing admin accounts work as before.
- `users.email` became nullable for username-only staff (additive column change). `migrate:rollback` drops the new columns but does not make `email` required again.
- Account identity still means "the person holding the login". A shared account cannot be attributed to a physical person; ORD-02a removes the need to share by making per-person accounts easy.
- Customer data access (D13): visible only to active accounts with `orders.manage`; no automatic deletion. See BUSINESS_RULES.
- Not done here: Admin PWA (ORD-02b), new order status model (ORD-02c), App API (ORD-02e). Email-based password reset is not available (SMTP unverified).
