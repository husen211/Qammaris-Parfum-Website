<?php

namespace App\Actions\Users;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use LogicException;

/** Account audit written in the same transaction as the change. Never stores password hashes or tokens. */
class RecordUserAdminChange
{
    public const FIELDS = ['name', 'email', 'username', 'role', 'is_active', 'must_change_password'];

    public function snapshot(User $user): array
    {
        return $user->only(self::FIELDS);
    }

    public function handle(User $user, ?User $actor, string $action, array $before, ?string $note = null, array $extraChanged = []): void
    {
        if (DB::transactionLevel() < 1) {
            throw new LogicException('Account history must commit with the account change.');
        }
        $after = $this->snapshot($user);
        $changed = array_values(array_unique(array_merge(
            array_keys(array_filter($after, fn ($value, $field) => ($before[$field] ?? null) !== $value, ARRAY_FILTER_USE_BOTH)),
            $extraChanged,
        )));
        if ($changed === [] && $action === 'updated') {
            return;
        }

        DB::table('user_admin_changes')->insert([
            'user_id' => $user->id,
            'actor_id' => $actor?->id,
            'action' => $action,
            'changed_fields' => json_encode($changed, JSON_THROW_ON_ERROR),
            'before' => json_encode(array_intersect_key($before, array_flip($changed)), JSON_THROW_ON_ERROR),
            'after' => json_encode(array_intersect_key($after, array_flip($changed)), JSON_THROW_ON_ERROR),
            'note' => $note,
            'created_at' => now(),
        ]);
    }
}
