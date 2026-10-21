<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Orders\AnonymizeCustomer;
use App\Actions\Orders\OnlineOrderCustomers;
use App\Exceptions\OrderActionNotAllowed;
use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\CustomerAddress;
use App\Support\PhoneNumber;
use App\Support\SearchMatcher;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** ORD-05: saved customers for Super Admin and Staff Order; contact data stays inside order access (D13). */
class AdminCustomerController extends Controller
{
    public function index(Request $request): View
    {
        $search = SearchMatcher::term($request->query('search'));
        $query = Customer::query()->whereNull('anonymized_at')->withCount('orders')->withMax('orders', 'created_at')
            ->orderByDesc('orders_max_created_at')->orderByDesc('id');
        if ($search !== '') {
            $like = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $search).'%';
            $digits = preg_replace('/\D+/', '', $search);
            $phone = PhoneNumber::normalize($search);
            $query->where(fn ($inner) => $inner->where('name', 'like', $like)
                ->when(strlen((string) $digits) >= 4, fn ($q) => $q->orWhere('phone', 'like', '%'.($phone ?? $digits).'%')));
        }

        return view('admin.customers.index', ['customers' => $query->paginate(25)->withQueryString(), 'search' => $search]);
    }

    public function show(Customer $customer): View
    {
        abort_if($customer->anonymized_at !== null, 404);

        return view('admin.customers.show', [
            'customer' => $customer,
            'orders' => $customer->orders()->with('items')->limit(50)->get(),
            'addresses' => $customer->addresses()->get(),
        ]);
    }

    public function update(Request $request, Customer $customer): RedirectResponse
    {
        abort_if($customer->anonymized_at !== null, 404);
        $data = $request->validate([
            'name' => ['required', 'string', 'min:2', 'max:100'],
            'note' => ['nullable', 'string', 'max:300'],
        ], [], ['name' => 'nama', 'note' => 'catatan']);
        $customer->update($data);

        return redirect()->route('admin.customers.show', $customer)->with('success', 'Data pelanggan disimpan.');
    }

    public function archiveAddress(Request $request, Customer $customer, CustomerAddress $address, OnlineOrderCustomers $customers): RedirectResponse
    {
        abort_unless($address->customer_id === $customer->id, 404);
        $customers->archiveAddress($address, $request->user());

        return redirect()->route('admin.customers.show', $customer)->with('success', 'Alamat diarsipkan. Pesanan lama tetap menyimpan salinannya.');
    }

    public function anonymize(Request $request, Customer $customer, AnonymizeCustomer $anonymize): RedirectResponse
    {
        $request->validate(['confirm' => ['required', 'in:HAPUS']], ['confirm.*' => 'Ketik HAPUS untuk mengonfirmasi.']);
        try {
            $anonymize->handle($customer, $request->user());
        } catch (OrderActionNotAllowed $error) {
            return redirect()->route('admin.customers.show', $customer)->with('error', $error->getMessage());
        }

        return redirect()->route('admin.customers.index')->with('success', 'Data pelanggan dihapus. Kode dan catatan uang pesanannya tetap ada.');
    }
}
