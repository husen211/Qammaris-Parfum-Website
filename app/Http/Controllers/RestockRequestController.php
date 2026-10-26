<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Support\PhoneNumber;
use App\Support\RestockRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/** WAIT-01: "Kabari saya saat ready" on a sold-out product page. */
class RestockRequestController extends Controller
{
    public function store(Request $request, Product $product): RedirectResponse
    {
        abort_unless(RestockRequests::enabled() && $product->isPubliclyVisible(), 404);
        $back = redirect()->to(route('products.show', $product->slug).'#kabari');
        if ($product->effective_availability !== Product::AVAILABILITY_SOLD_OUT) {
            return $back->with('restock_status', 'available');
        }

        $request->merge(['name' => trim((string) $request->input('name'))]);
        $data = $request->validateWithBag('restock', [
            'name' => ['required', 'string', 'min:2', 'max:60'],
            'whatsapp' => ['required', 'string', 'max:30', function (string $attribute, mixed $value, \Closure $fail) {
                if (! preg_match('/^628\d{7,12}$/', (string) PhoneNumber::normalize($value))) {
                    $fail('Gunakan nomor WhatsApp Indonesia, misalnya 0812 3456 7890.');
                }
            }],
            'website' => ['nullable', 'string', 'max:0'],
        ], [
            'name.required' => 'Isi nama Anda.',
            'name.min' => 'Nama minimal 2 huruf.',
            'name.max' => 'Nama maksimal 60 huruf.',
            'whatsapp.required' => 'Isi nomor WhatsApp Anda.',
            'whatsapp.max' => 'Nomor WhatsApp tidak valid.',
            'website.max' => 'Permintaan tidak dapat diproses.',
        ]);
        RestockRequests::record($product, $data['name'], PhoneNumber::normalize($data['whatsapp']));

        return $back->with('restock_status', 'saved');
    }
}
