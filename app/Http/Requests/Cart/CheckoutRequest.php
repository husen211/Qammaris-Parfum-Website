<?php

namespace App\Http\Requests\Cart;

use Illuminate\Foundation\Http\FormRequest;

class CheckoutRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** ORD-04: the checkout saves an order (delivery, paperbag, payment preference); otherwise it only opens WhatsApp. */
    public static function savesOrder(): bool
    {
        return config('orders.website_checkout') && config('orders.v2_enabled');
    }

    public function rules(): array
    {
        $rules = [
            'customer_name' => ['required', 'string', 'min:2', 'max:100'],
            'customer_phone' => ['required', 'string', 'regex:/^\+?[0-9]{8,15}$/'],
            'customer_address' => ['required', 'string', 'min:15', 'max:500'],
            'customer_postcode' => ['nullable', 'string', 'regex:/^[0-9]{5}$/'],
            'customer_note' => ['nullable', 'string', 'max:300'],
            'checkout_quote' => ['required', 'string', 'size:64'],
        ];
        if (! self::savesOrder()) {
            return $rules;
        }

        return array_replace($rules, [
            'delivery' => ['required', 'string', 'in:'.implode(',', array_keys(config('orders.checkout_deliveries')))],
            // Pickup needs no address; delivery addresses are checked like before.
            'customer_address' => ['exclude_if:delivery,pickup', 'required', 'string', 'min:10', 'max:500'],
            'customer_postcode' => ['exclude_if:delivery,pickup', 'nullable', 'string', 'regex:/^[0-9]{5}$/'],
            'district' => ['exclude_if:delivery,pickup', 'nullable', 'string', 'max:80'],
            'subdistrict' => ['exclude_if:delivery,pickup', 'nullable', 'string', 'max:80'],
            'packaging' => ['required', 'string', 'in:paperbag,no_paperbag'],
            'payment_preference' => ['required', 'string', 'in:'.implode(',', array_keys(config('orders.payment_preferences')))],
            'checkout_key' => ['required', 'uuid'],
        ]);
    }

    /** @return array<string, ?string> order details from the validated form */
    public function orderDetails(): array
    {
        $data = $this->validated();

        return [
            'customer_name' => $data['customer_name'],
            'customer_phone' => $data['customer_phone'],
            'fulfillment' => $data['delivery'],
            'address' => $data['customer_address'] ?? null,
            'postcode' => $data['customer_postcode'] ?? null,
            'district' => $data['district'] ?? null,
            'subdistrict' => $data['subdistrict'] ?? null,
            'packaging' => $data['packaging'],
            'customer_note' => $data['customer_note'] ?? null,
            'payment_preference' => $data['payment_preference'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $values = [];
        foreach (['customer_name', 'customer_phone', 'customer_address', 'customer_postcode', 'customer_note', 'district', 'subdistrict'] as $field) {
            $value = $this->input($field);
            if (is_string($value)) {
                $value = trim(preg_replace('/\s+/u', ' ', $value) ?? '');
                $values[$field] = $field === 'customer_phone' ? preg_replace('/[\s().-]/', '', $value) : $value;
            }
        }
        $this->merge($values);
    }

    public function attributes(): array
    {
        return [
            'customer_name' => 'nama penerima',
            'customer_phone' => 'nomor HP',
            'customer_address' => 'alamat lengkap',
            'customer_postcode' => 'kode pos',
            'customer_note' => 'catatan pesanan',
            'checkout_quote' => 'ringkasan pesanan',
            'delivery' => 'cara pengiriman',
            'district' => 'kecamatan',
            'subdistrict' => 'kelurahan',
            'packaging' => 'pilihan paperbag',
            'payment_preference' => 'metode pembayaran',
            'checkout_key' => 'sesi checkout',
        ];
    }

    public function messages(): array
    {
        return [
            'required' => 'Isi :attribute sebelum melanjutkan.',
            'string' => ':attribute harus berupa teks.',
            'min' => ':attribute minimal :min karakter.',
            'max' => ':attribute maksimal :max karakter.',
            'customer_phone.regex' => 'Isi nomor HP yang valid, misalnya 08… atau +62… (8–15 angka).',
            'customer_postcode.regex' => 'Kode pos harus terdiri dari 5 angka.',
            'checkout_quote.size' => 'Buka kembali checkout untuk meninjau ringkasan terbaru.',
            'delivery.required' => 'Pilih cara pengiriman.',
            'packaging.required' => 'Pilih pakai paperbag atau tidak.',
            'payment_preference.required' => 'Pilih metode pembayaran.',
            'in' => 'Pilih :attribute dari pilihan yang tersedia.',
            'checkout_key.uuid' => 'Buka kembali checkout untuk meninjau ringkasan terbaru.',
        ];
    }
}
