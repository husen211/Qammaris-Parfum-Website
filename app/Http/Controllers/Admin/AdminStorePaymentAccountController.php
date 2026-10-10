<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Orders\StorePaymentAccounts;
use App\Exceptions\OrderValidationFailed;
use App\Http\Controllers\Controller;
use App\Models\StorePaymentAccount;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/** ORD-04: Super Admin manages the official accounts shown in payment instructions. */
class AdminStorePaymentAccountController extends Controller
{
    public function __construct(private readonly StorePaymentAccounts $accounts) {}

    public function index()
    {
        $accounts = StorePaymentAccount::query()->orderByRaw('deactivated_at IS NOT NULL')->orderBy('id')->get();
        $names = User::query()->whereIn('id', $accounts->flatMap(fn ($a) => [$a->created_by, $a->verified_by, $a->deactivated_by])->filter()->unique())->pluck('name', 'id');

        return view('admin.orders.payment-accounts', ['accounts' => $accounts, 'names' => $names]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate(['bank_name' => ['required', 'string', 'max:60'], 'account_number' => ['required', 'string', 'max:40'], 'account_holder' => ['required', 'string', 'max:100']]);

        return $this->attempt(fn () => $this->accounts->add($request->user(), $data['bank_name'], $data['account_number'], $data['account_holder']),
            'Rekening ditambahkan. Verifikasi dulu sebelum dipakai.');
    }

    public function verify(Request $request, StorePaymentAccount $account): RedirectResponse
    {
        $data = $request->validate(['retyped_number' => ['required', 'string', 'max:40'], 'confirmed' => ['accepted']],
            ['confirmed.accepted' => 'Centang konfirmasi bahwa rekening ini milik toko.']);

        return $this->attempt(fn () => $this->accounts->verify($request->user(), $account, $data['retyped_number']), 'Rekening terverifikasi dan dipakai di instruksi pembayaran.');
    }

    public function deactivate(Request $request, StorePaymentAccount $account): RedirectResponse
    {
        return $this->attempt(fn () => $this->accounts->deactivate($request->user(), $account), 'Rekening dinonaktifkan.');
    }

    private function attempt(callable $change, string $success): RedirectResponse
    {
        try {
            $change();
        } catch (OrderValidationFailed $invalid) {
            return back()->withInput()->withErrors($invalid->fields)->with('error', $invalid->getMessage());
        }

        return redirect()->route('admin.orders.payment-accounts.index')->with('success', $success);
    }
}
