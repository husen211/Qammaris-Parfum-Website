<?php

namespace App\Actions\Orders;

use App\Exceptions\OrderValidationFailed;
use App\Models\StorePaymentAccount;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

/**
 * ORD-04: official bank accounts for payment instructions (Super Admin). Adding is not enough: a separate verification
 * with the re-typed number must pass before customers see it. Accounts are never edited; deactivate and add anew.
 */
class StorePaymentAccounts
{
    public function add(User $actor, string $bank, string $number, string $holder): StorePaymentAccount
    {
        $this->authorize($actor);
        $bank = trim(preg_replace('/\s+/u', ' ', $bank) ?? '');
        $holder = trim(preg_replace('/\s+/u', ' ', $holder) ?? '');
        $number = preg_replace('/[\s.-]/', '', $number) ?? '';
        $fields = array_filter([
            'bank_name' => mb_strlen($bank) < 2 || mb_strlen($bank) > 40 ? 'Nama bank 2–40 karakter' : null,
            'account_number' => preg_match('/^\d{6,20}$/', $number) ? null : 'Nomor rekening 6–20 angka',
            'account_holder' => mb_strlen($holder) < 3 || mb_strlen($holder) > 80 ? 'Nama pemilik 3–80 karakter' : null,
        ]);
        if ($fields) {
            throw new OrderValidationFailed('Periksa data rekening.', $fields);
        }

        return StorePaymentAccount::create(['bank_name' => $bank, 'account_number' => $number, 'account_holder' => $holder, 'created_by' => $actor->id]);
    }

    public function verify(User $actor, StorePaymentAccount $account, string $retypedNumber): StorePaymentAccount
    {
        $this->authorize($actor);

        return DB::transaction(function () use ($actor, $account, $retypedNumber): StorePaymentAccount {
            $account = StorePaymentAccount::query()->whereKey($account->id)->lockForUpdate()->firstOrFail();
            if ($account->deactivated_at !== null) {
                throw new OrderValidationFailed('Rekening ini sudah dinonaktifkan.', ['account' => 'Nonaktif']);
            }
            if (! hash_equals($account->account_number, preg_replace('/[\s.-]/', '', $retypedNumber) ?? '')) {
                throw new OrderValidationFailed('Nomor yang diketik ulang tidak sama. Rekening belum diverifikasi.', ['retyped_number' => 'Tidak sama']);
            }
            $account->forceFill(['verified_at' => $account->verified_at ?? now(), 'verified_by' => $account->verified_by ?? $actor->id])->save();

            return $account;
        });
    }

    public function deactivate(User $actor, StorePaymentAccount $account): StorePaymentAccount
    {
        $this->authorize($actor);
        if ($account->deactivated_at === null) {
            $account->forceFill(['deactivated_at' => now(), 'deactivated_by' => $actor->id])->save();
        }

        return $account;
    }

    private function authorize(User $actor): void
    {
        abort_unless($actor->exists && Gate::forUser($actor)->allows('orders.payment-accounts'), 403);
    }
}
