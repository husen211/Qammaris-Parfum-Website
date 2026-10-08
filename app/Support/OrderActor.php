<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Facades\Gate;

/**
 * Who changes an order: a signed-in Website account, or a Qammaris App employee whose identity and
 * `orders.handle` permission were checked by the App before signing (contract §4).
 */
final class OrderActor
{
    private function __construct(
        public readonly ?User $user,
        public readonly ?string $appUserId,
        public readonly string $displayName,
        public readonly ?string $appRole,
    ) {}

    public static function user(User $user): self
    {
        return new self($user, null, $user->name, null);
    }

    public static function app(string $appUserId, string $displayName, string $appRole): self
    {
        return new self(null, $appUserId, $displayName, $appRole);
    }

    /** The customer through their order link; may only confirm receipt of a delivery. */
    public static function customer(): self
    {
        return new self(null, null, 'Customer', 'customer');
    }

    public function isCustomer(): bool
    {
        return $this->user === null && $this->appRole === 'customer';
    }

    /** Owner-level for override rules: Super Admin on the Website, `owner` in the App. */
    public function isOwner(): bool
    {
        return $this->user ? Gate::forUser($this->user)->allows('orders.refund') : $this->appRole === 'owner';
    }

    public function canManageOrders(): bool
    {
        return $this->user ? Gate::forUser($this->user)->allows('orders.manage') : in_array($this->appRole, ['owner', 'employee'], true);
    }

    public function is(?int $userId, ?string $appUserId): bool
    {
        return $this->user ? $userId !== null && $this->user->id === $userId : $appUserId !== null && $this->appUserId === $appUserId;
    }

    /** @return array<string, mixed> event columns */
    public function eventAttributes(): array
    {
        return match (true) {
            $this->user !== null => ['actor_type' => 'admin', 'actor_user_id' => $this->user->id],
            $this->isCustomer() => ['actor_type' => 'customer'],
            default => ['actor_type' => 'app', 'actor_app_user_id' => $this->appUserId, 'actor_display_name' => $this->displayName],
        };
    }
}
