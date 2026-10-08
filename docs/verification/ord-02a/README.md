# ORD-02a — RBAC dan manajemen pengguna: verifikasi lokal

Date: 2026-10-08. Owner approval: "lanjutkan ORD-02a … migrasi additive, pengujian akses setiap route/action, pencegahan lockout Super Admin terakhir, tanpa deploy production". Status: IN_REVIEW on `modernization/ord-02-online-order-redesign`. Not deployed.

## Scope implemented

- Migration `2026_10_08_100001_add_admin_access_controls`:
  - users: `username`, `is_active`, `must_change_password`, `auth_version`, `last_login_at`, `created_by_id`, `updated_by_id`;
  - `email` nullable;
  - table `user_admin_changes`.
- `AuthorizationServiceProvider` gates; `AdminMiddleware` (active check, role check, `auth_version` revocation, 12-hour idle, forced password change); login with email/username without remember-me.
- Routes grouped by `can:catalog.manage` / `blog.manage` / `orders.manage` (+ `orders.finance`) / `users.manage`; route names and paths unchanged.
- `ManageAdminUser`, `RecordUserAdminChange`, `qammaris:grant-super-admin`.
- Pengguna & Role UI (list, create, edit, detail with activity, deactivate/activate, reset password) and own password change.
- ORD-01 order code now uses gates:
  - Staff Order: create/update/mark paid;
  - Staff Order cancel only while unpaid and not handed over;
  - money fields and step corrections/reimbursements: Super Admin/legacy admin.
- Menus mirror abilities.

## Checks actually run

- `php artisan test` full suite: **415 passed / 3229 assertions** (396 previous + 19 new).
- New `AdminRbacTest` (8) + `AdminUserManagementTest` (11): 19 tests / 421 assertions:
  - every `admin.*` route has `auth` + `admin` + its area ability (route-table test);
  - Staff Order gets 403 on every non-order admin GET page reachable with fixtures, and on catalog/brand/blog/user mutations (data verified unchanged);
  - customers get 403; inactive accounts are logged out;
  - legacy admin keeps today's access without user management;
  - menus follow abilities;
  - order rules D4/D5 for Staff Order, and order actions reject accounts without the ability;
  - account creation with a one-time password + audit without hash/password;
  - role validation (no legacy/customer/unknown, unique email/username, one of them required);
  - username/email login, last login, no remember cookie;
  - inactive login with a generic error;
  - deactivation and password reset revoke open sessions;
  - forced password change; 12-hour idle expiry;
  - last active Super Admin guard (an inactive second Super Admin does not count);
  - role change audit + `auth_version` bump;
  - bootstrap command preview/confirm/refusals/`--create`;
  - migration does not promote existing admins.
- `node --test tests/js/*.test.mjs`: 31 passed. Pint on changed/new PHP: passed. `npm run build`: passed.
- Migration up → rollback → up on isolated SQLite with a pre-existing legacy admin row: the row kept role `admin` and was not promoted; rollback removed only the new columns/table; re-migration clean.

## Browser evidence

Real Chrome (headless, DevTools Protocol) against the worktree on `127.0.0.1:8124`:
- isolated SQLite with synthetic accounts (`pemilik` Super Admin, Staff Order accounts, one legacy admin, one inactive account);
- cookie sessions and external compiled views, so nothing was written into the repository;
- the DB, passwords and Chrome profile were deleted afterwards;
- the temporary password visible in one screenshot belongs to that deleted DB.

Raw results: [browser-checks.json](browser-checks.json).

| Check | Result |
|---|---|
| Super Admin pages | [list 1440](users-index-1440.png), [list 390](users-index-390.png), [create](users-create-1440.png), [one-time password](users-show-temp-password-1440.png); no horizontal overflow |
| Last Super Admin guard | Deactivating self blocked with a clear message. [390](users-last-super-admin-guard-390.png) |
| New Staff Order on phone | Forced to change password ([390](staff-change-password-390.png)), then lands on Pesanan Online. Mobile menu shows only Pesanan Online / Ganti password / Logout ([390](staff-orders-menu-390.png)); desktop sidebar only Pesanan Online ([1440](staff-orders-1440.png)) |
| Server-side denial | Staff Order GET `/admin/products` and `/admin/users` → 403 |
| Order money lock | Staff Order sees the shipping fee field disabled with an explanation and no correction button. [390](staff-order-show-390.png) |
| Inactive account | Generic "Email/username atau password salah." [390](login-inactive-390.png) |
| Console | No JS exceptions |

## Limitations

- Concurrent last-Super-Admin protection relies on row locks; verified logically and sequentially on SQLite, not under concurrent MySQL load.
- Production admin accounts are unknown; the bootstrap and conversion steps in the [runbook](../../runbooks/ADMIN_ACCESS.md) need the Owner.
- Genuine phone/touch and iPhone Safari not tested.
