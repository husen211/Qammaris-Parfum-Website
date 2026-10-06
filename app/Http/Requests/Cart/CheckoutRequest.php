<?php

namespace App\Http\Requests\Cart;

use Illuminate\Foundation\Http\FormRequest;

class CheckoutRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'customer_name' => ['required', 'string', 'min:2', 'max:100'],
            'customer_phone' => ['required', 'string', 'regex:/^\+?[0-9]{8,15}$/'],
            'customer_address' => ['required', 'string', 'min:15', 'max:500'],
            'customer_postcode' => ['nullable', 'string', 'regex:/^[0-9]{5}$/'],
            'customer_note' => ['nullable', 'string', 'max:300'],
            'checkout_quote' => ['required', 'string', 'size:64'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $values = [];
        foreach (['customer_name', 'customer_phone', 'customer_address', 'customer_postcode', 'customer_note'] as $field) {
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
        ];
    }
}
