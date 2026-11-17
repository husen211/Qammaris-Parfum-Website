<?php

namespace App\Http\Requests\Admin;

use App\Actions\Orders\ShippingQuotes;
use App\Models\OnlineOrder;
use App\Support\MapsLink;
use App\Support\Rupiah;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/** ORD-27 "Ubah pesanan": the order's lines after the change and how it is delivered. */
class ChangeOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('orders.manage');
    }

    public function rules(): array
    {
        return [
            'revision' => ['required', 'integer', 'min:1'],
            'items' => ['required', 'array', 'min:1', 'max:30'],
            'items.*.line_id' => ['nullable', 'string', 'regex:/^[0-9A-HJKMNP-TV-Z]{26}$/'],
            'items.*.variant_id' => ['required_without:items.*.line_id', 'nullable', 'integer', 'min:1'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:99'],
            'fulfillment' => ['required', Rule::in(array_keys(OnlineOrder::FULFILLMENTS))],
            'address' => ['nullable', 'string', 'max:500', 'required_if:fulfillment,intercity', 'min:'.($this->input('fulfillment') === 'intercity' ? 15 : 3)],
            'postcode' => ['nullable', 'string', 'regex:/^[0-9]{5}$/'],
            'location_url' => ['nullable', 'string', 'max:500', MapsLink::rule()],
            'shipping_fee' => ['nullable', 'numeric', 'min:0', 'max:10000000', 'regex:'.Rupiah::WHOLE_PRICE_PATTERN],
            'shipping_payer' => ['nullable', Rule::in(array_keys(OnlineOrder::SHIPPING_PAYERS))],
            'shipping_option' => ['nullable', 'string', 'regex:/^[A-Za-z0-9]{20}$/'],
            'note' => ['nullable', 'string', 'max:200'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $values = [];
        foreach (['address', 'postcode', 'location_url', 'shipping_fee', 'shipping_option', 'note'] as $field) {
            $value = $this->input($field);
            if (is_string($value)) {
                $value = trim(preg_replace('/\s+/u', ' ', $value) ?? '');
                $values[$field] = $value === '' ? null : ($field === 'shipping_fee' && preg_match('/^\d{1,3}(\.\d{3})+$/', $value) ? str_replace('.', '', $value) : $value);
            }
        }
        if (isset($values['location_url']) && MapsLink::point($values['location_url'])) {
            $values['location_url'] = MapsLink::normalise($values['location_url']);
        }
        $this->merge($values);
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            $option = $this->input('shipping_option');
            if ($option === null || $validator->errors()->isNotEmpty() || $this->input('fulfillment') === 'pickup') {
                return;
            }
            // The chosen Biteship price must still be one this order was shown, for these products.
            $quote = app(ShippingQuotes::class)->forOrder($this->route('order'))->resolve($option, self::cartOf($this->lines()), $this->input('fulfillment'));
            if ($quote === null) {
                $validator->errors()->add('shipping_option', 'Harga ongkir otomatis sudah tidak berlaku (lewat 30 menit atau produk berubah). Hitung ongkir lagi, atau isi ongkir manual.');
            }
        }];
    }

    /** @return list<array{line_id: ?string, variant_id: ?int, quantity: int}> */
    public function lines(): array
    {
        return array_values(array_map(fn ($item) => [
            'line_id' => ($item['line_id'] ?? null) ?: null,
            'variant_id' => isset($item['variant_id']) ? (int) $item['variant_id'] : null,
            'quantity' => (int) ($item['quantity'] ?? 0),
        ], (array) $this->input('items', [])));
    }

    /** The edited lines as priced by "Hitung ongkir": a changed product or quantity makes its prices invalid. */
    public static function cartOf(array $lines): string
    {
        $key = array_map(fn ($line) => [$line['line_id'] ?: null, $line['line_id'] ? null : $line['variant_id'], (int) $line['quantity']], $lines);

        return hash('sha256', json_encode($key, JSON_THROW_ON_ERROR));
    }

    /** @return array{fulfillment: string, address: ?string, postcode: ?string, location_url: ?string, shipping_fee: ?string, shipping_payer: ?string, quote: ?array, area_label: ?string} */
    public function shipping(): array
    {
        $data = $this->validated();
        $quote = ($data['shipping_option'] ?? null) && $data['fulfillment'] !== 'pickup'
            ? app(ShippingQuotes::class)->forOrder($this->route('order'))->resolve($data['shipping_option'], self::cartOf($this->lines()), $data['fulfillment']) : null;

        return [
            'fulfillment' => $data['fulfillment'],
            'address' => $data['address'] ?? null,
            'postcode' => $data['postcode'] ?? null,
            'location_url' => $data['location_url'] ?? null,
            'shipping_fee' => isset($data['shipping_fee']) ? (string) $data['shipping_fee'] : null,
            'shipping_payer' => $data['fulfillment'] === 'pickup' ? null : ($data['shipping_payer'] ?? null),
            'quote' => $quote,
            'area_label' => null,
        ];
    }

    public function attributes(): array
    {
        return ['items' => 'produk', 'fulfillment' => 'cara menerima', 'address' => 'alamat', 'postcode' => 'kode pos',
            'location_url' => 'titik antar', 'shipping_fee' => 'ongkir', 'shipping_payer' => 'pembayar ongkir', 'note' => 'catatan'];
    }

    public function messages(): array
    {
        return [
            'items.required' => 'Pesanan harus berisi minimal satu produk.',
            'address.required_if' => 'Isi alamat lengkap untuk pengiriman ke luar kota.',
            'postcode.regex' => 'Kode pos harus 5 angka.',
            'min' => ':attribute minimal :min karakter.',
        ];
    }
}
