<?php

namespace App\Http\Requests\Orders;

use App\Models\OnlineOrder;
use Illuminate\Validation\Rule;

/** Customer detail rules shared by the customer link and the admin forms. */
trait OnlineOrderDetailRules
{
    protected function customerDetailRules(string $required = 'required'): array
    {
        return [
            'customer_name' => [$required, 'string', 'min:2', 'max:100'],
            'customer_phone' => [$required, 'string', 'regex:/^\+?[0-9]{8,15}$/'],
            'fulfillment' => [$required, Rule::in(array_keys(OnlineOrder::FULFILLMENTS))],
            // Local deliveries use the WhatsApp location; only intercity shipping needs the full address.
            'address' => ['nullable', 'string', 'max:500', 'required_if:fulfillment,intercity', 'min:'.($this->input('fulfillment') === 'intercity' ? 15 : 3)],
            'postcode' => ['nullable', 'string', 'regex:/^[0-9]{5}$/'],
            'packaging' => [$required, Rule::in(array_keys(OnlineOrder::PACKAGING))],
            'customer_note' => ['nullable', 'string', 'max:300'],
        ];
    }

    protected function normalizeCustomerDetails(): void
    {
        $values = [];
        foreach (['customer_name', 'customer_phone', 'address', 'postcode', 'customer_note'] as $field) {
            $value = $this->input($field);
            if (is_string($value)) {
                $value = trim(preg_replace('/\s+/u', ' ', $value) ?? '');
                $value = $field === 'customer_phone' ? preg_replace('/[\s().-]/', '', $value) : $value;
                $values[$field] = $value === '' ? null : $value;
            }
        }
        if ($this->input('fulfillment') === 'pickup') {
            $values['address'] = null;
            $values['postcode'] = null;
        } elseif ($this->input('fulfillment') === 'local_delivery') {
            $values['postcode'] = null;
        }
        $this->merge($values);
    }

    protected function customerDetailAttributes(): array
    {
        return [
            'customer_name' => 'nama penerima',
            'customer_phone' => 'nomor HP',
            'fulfillment' => 'cara menerima pesanan',
            'address' => 'alamat',
            'postcode' => 'kode pos',
            'packaging' => 'pilihan paperbag',
            'customer_note' => 'catatan',
        ];
    }

    protected function customerDetailMessages(): array
    {
        return [
            'required' => 'Isi :attribute.',
            'fulfillment.required' => 'Pilih cara menerima pesanan.',
            'packaging.required' => 'Pilih pakai paperbag atau tidak.',
            'address.required_if' => 'Isi alamat lengkap untuk pengiriman ke luar kota.',
            'min' => ':attribute minimal :min karakter.',
            'max' => ':attribute maksimal :max karakter.',
            'in' => 'Pilih :attribute yang tersedia.',
            'customer_phone.regex' => 'Isi nomor HP yang valid, misalnya 08… atau +62… (8–15 angka).',
            'postcode.regex' => 'Kode pos harus 5 angka.',
        ];
    }
}
