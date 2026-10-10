<?php

namespace App\Providers;

use App\Models\OnlineOrder;
use App\Models\User;
use App\Support\OnlineOrderMoney;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

/**
 * ORD-02a role-based abilities. Routes (`can:` middleware), form requests and actions all ask these gates;
 * menus only mirror them. Registered outside AppServiceProvider so console commands see them too.
 */
class AuthorizationServiceProvider extends ServiceProvider
{
    private const FULL_ADMIN = [User::ROLE_SUPER_ADMIN, User::ROLE_LEGACY_ADMIN];

    private const ORDER_ROLES = [User::ROLE_SUPER_ADMIN, User::ROLE_STAFF_ORDER, User::ROLE_LEGACY_ADMIN];

    public function boot(): void
    {
        $has = static fn (User $user, array $roles): bool => $user->is_active !== false && in_array($user->role, $roles, true);

        Gate::define('admin.access', fn (User $user) => $has($user, User::ADMIN_ROLES));
        Gate::define('dashboard.view', fn (User $user) => $has($user, self::FULL_ADMIN));
        Gate::define('catalog.manage', fn (User $user) => $has($user, self::FULL_ADMIN));
        Gate::define('blog.manage', fn (User $user) => $has($user, self::FULL_ADMIN));
        Gate::define('users.manage', fn (User $user) => $has($user, [User::ROLE_SUPER_ADMIN]));
        Gate::define('integrations.manage', fn (User $user) => $has($user, [User::ROLE_SUPER_ADMIN]));

        Gate::define('orders.manage', fn (User $user) => $has($user, self::ORDER_ROLES));
        // Money changes (shipping charge/funding, reimbursements, step corrections) are not Staff Order work.
        Gate::define('orders.finance', fn (User $user) => $has($user, self::FULL_ADMIN));
        // ORD-03 (flag): Staff Order may set the shipping fee charged to the customer while the transaction is not final:
        // V2, open, nothing handed over and no money recorded at all. Afterwards a change is a price adjustment that
        // Super Admin approves. Driver funding stays orders.finance.
        Gate::define('orders.charge-shipping', function (User $user, OnlineOrder $order) use ($has): bool {
            if ($has($user, self::FULL_ADMIN)) {
                return true;
            }

            return $has($user, [User::ROLE_STAFF_ORDER]) && config('orders.simple_ux') && $order->isV2()
                && in_array($order->lifecycle, ['awaiting_customer', 'active'], true) && $order->handover_status === 'pending'
                && OnlineOrderMoney::totals($order)['received'] === 0;
        });
        // Refund decisions, refund payouts, ledger reversals and reconciliation: Super Admin only (Owner 2026-10-09).
        Gate::define('orders.refund', fn (User $user) => $has($user, [User::ROLE_SUPER_ADMIN]));
        // Price adjustments and cost-relevant customer changes need Super Admin approval (plan §5.1, D5).
        Gate::define('orders.approve-adjustment', fn (User $user) => $has($user, [User::ROLE_SUPER_ADMIN]));
        // Owner D4: Staff Order may cancel only while unpaid and not handed over, always with a reason.
        Gate::define('orders.cancel', function (User $user, OnlineOrder $order) use ($has): bool {
            if ($has($user, self::FULL_ADMIN)) {
                return true;
            }

            if (! $has($user, [User::ROLE_STAFF_ORDER])) {
                return false;
            }
            if ($order->isV2()) {
                // No recorded money at all, not just "not fully paid", and not handed over.
                return $order->payment_status === 'unpaid' && $order->handover_status === 'pending'
                    && OnlineOrderMoney::totals($order)['received'] === 0;
            }

            return in_array($order->stage, [OnlineOrder::STAGE_AWAITING_CUSTOMER, OnlineOrder::STAGE_DETAILS_RECEIVED], true);
        });
    }
}
