<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Orders\StorePaymentAccounts;
use App\Exceptions\OrderValidationFailed;
use App\Http\Controllers\Controller;
use App\Models\StoreInfo;
use App\Models\StorePaymentAccount;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;

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

    /**
     * ORD-04: in a test environment only, a Super Admin opts THIS browser in (or out) to the official store number
     * for a short test. The cookie is encrypted, expires after config('store.uat_official_minutes'), and never
     * affects other visitors. Messages keep the "[UAT]" prefix.
     */
    public function uatWhatsapp(Request $request): RedirectResponse
    {
        abort_unless(StoreInfo::usesTestWhatsapp(), 404);
        $on = $request->boolean('on');
        $name = (string) config('store.uat_official_cookie');
        $cookie = $on
            ? Cookie::make($name, '1', (int) config('store.uat_official_minutes'), '/', null, $request->isSecure() || config('session.secure'), true, false, 'lax')
            : Cookie::forget($name);

        return back()->withCookie($cookie)->with('success', $on
            ? 'Browser ini memakai nomor WhatsApp resmi toko selama '.config('store.uat_official_minutes').' menit. Pesan diawali [UAT].'
            : 'Browser ini kembali memakai perlindungan UAT.');
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
