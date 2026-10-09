<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\Orders\OnlineOrderDetailRules;
use App\Models\OnlineOrder;
use App\Support\Rupiah;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class OnlineOrderUpdateRequest extends FormRequest
{
    use OnlineOrderDetailRules;

    public function authorize(): bool
    {
        return (bool) $this->user()?->can('orders.manage');
    }

    public function rules(): array
    {
        return [
            'revision' => ['required', 'integer', 'min:1'],
            'location_url' => ['nullable', 'string', 'max:500', 'url:https', function ($attribute, $value, $fail) {
                $host = strtolower((string) parse_url((string) $value, PHP_URL_HOST));
                if (! preg_match('/(^|\.)(google\.[a-z.]+|goo\.gl|maps\.app\.goo\.gl)$/', $host)) {
                    $fail('Gunakan link Google Maps (https://maps.google.com/… atau https://maps.app.goo.gl/…).');
                }
            }],
            'courier' => ['nullable', Rule::in(array_keys(OnlineOrder::COURIERS))],
            'courier_booked_by' => ['required', Rule::in(array_keys(OnlineOrder::BOOKERS))],
            'tracking_number' => ['nullable', 'string', 'max:40', 'regex:/^[A-Za-z0-9-]+$/'],
            'shipping_fee' => ['nullable', 'numeric', 'min:0', 'max:10000000', 'regex:'.Rupiah::WHOLE_PRICE_PATTERN],
            'shipping_payer' => ['nullable', Rule::in(array_keys(OnlineOrder::SHIPPING_PAYERS))],
            'driver_funding' => ['nullable', Rule::in(array_keys(OnlineOrder::DRIVER_FUNDING))],
            'payment_method' => ['nullable', Rule::in(array_keys(OnlineOrder::PAYMENT_METHODS))],
            'recorded_in_majoo' => ['boolean'],
            'staff_note' => ['nullable', 'string', 'max:300'],
            // Once the customer has submitted, the core details may be corrected but not cleared.
        ] + $this->customerDetailRules($this->route('order')?->stage === OnlineOrder::STAGE_AWAITING_CUSTOMER ? 'nullable' : 'required');
    }

    protected function prepareForValidation(): void
    {
        $this->normalizeCustomerDetails();
        $values = ['recorded_in_majoo' => $this->boolean('recorded_in_majoo')];
        foreach (['location_url', 'tracking_number', 'staff_note', 'shipping_fee'] as $field) {
            $value = $this->input($field);
            if (is_string($value)) {
                $value = trim($value);
                $values[$field] = $value === '' ? null : ($field === 'shipping_fee' && preg_match('/^\d{1,3}(\.\d{3})+$/', $value) ? str_replace('.', '', $value) : $value);
            }
        }
        $this->merge($values);
    }

    public function attributes(): array
    {
        return $this->customerDetailAttributes() + [
            'location_url' => 'link lokasi', 'courier' => 'kurir', 'tracking_number' => 'nomor resi',
            'shipping_fee' => 'ongkir', 'shipping_payer' => 'pembayar ongkir', 'driver_funding' => 'dana untuk driver',
            'payment_method' => 'metode pembayaran', 'staff_note' => 'catatan untuk staf',
        ];
    }

    public function messages(): array
    {
        return $this->customerDetailMessages() + [
            'location_url.url' => 'Link lokasi harus diawali https://.',
            'tracking_number.regex' => 'Nomor resi hanya berisi huruf, angka, atau tanda minus.',
            'shipping_fee.regex' => 'Ongkir harus rupiah bulat, misalnya 11500.',
            'shipping_fee.numeric' => 'Ongkir harus berupa angka, misalnya 11500.',
        ];
    }
}
