<?php

namespace App\Http\Requests\Orders;

use App\Actions\Orders\ShippingQuotes;
use App\Models\OnlineOrder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class CustomerOrderDetailsRequest extends FormRequest
{
    use OnlineOrderDetailRules;

    /** ORD-26: the server's own option for the choice, resolved once while validating. */
    private ?array $quote = null;

    private ?OnlineOrder $order = null;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        // ORD-03 (flag): the customer's preferred payment method; a preference, never a payment.
        $rules = $this->customerDetailRules() + (config('orders.simple_ux')
            ? ['payment_preference' => ['required', Rule::in(array_keys(config('orders.payment_preferences')))]] : []);
        if ($this->picksShipping()) {
            // ORD-26: the address first, then a priced option, Ambil di toko, or ongkir confirmed by the admin.
            return array_merge($rules, [
                'shipping_option' => ['required', 'string', 'regex:/^(manual|pickup|[A-Za-z0-9]{20})$/'],
                'delivery_area_id' => ['nullable', 'string', 'max:64'],
                'address' => ['nullable', 'string', 'max:500', 'required_unless:fulfillment,pickup', 'min:10'],
            ]);
        }

        // ORD-10: asked only for a local courier, and only while the link may still set it.
        return $rules + ['shipping_payment' => ['exclude_unless:fulfillment,local_delivery', $this->asksShippingPayment() ? 'required' : 'nullable',
            Rule::in(array_keys(OnlineOrder::CUSTOMER_SHIPPING_PAYMENTS))]]
            + $this->pinRules();
    }

    /** ORD-23 (Owner 2026-11-15): the delivery pin is required with "Pengiriman Dalam Kota" while the link asks for it. */
    private function pinRules(): array
    {
        $area = config('orders.shipping_quotes.service_area');
        $asks = (bool) $this->order()?->customerFormAsksPin();

        return [
            'pin_latitude' => ['exclude_unless:fulfillment,local_delivery', $asks ? 'required' : 'nullable', 'numeric', 'between:'.$area['south'].','.$area['north']],
            'pin_longitude' => ['exclude_unless:fulfillment,local_delivery', $asks ? 'required_with:pin_latitude' : 'nullable', 'numeric', 'between:'.$area['west'].','.$area['east']],
        ];
    }

    /** ORD-26: a priced option must still be one the server gave for this order's lines and delivery type. */
    public function after(): array
    {
        return [function (Validator $validator): void {
            $option = (string) $this->input('shipping_option');
            if (! $this->picksShipping() || $validator->errors()->isNotEmpty() || in_array($option, ['manual', 'pickup'], true)) {
                if ($option === 'pickup' && $this->input('fulfillment') !== 'pickup') {
                    $validator->errors()->add('shipping_option', 'Pilih pengiriman lagi.');
                }

                return;
            }
            $order = $this->order();
            $this->quote = app(ShippingQuotes::class)->forOrder($order)->resolve($option, ShippingQuotes::orderCart($order), (string) $this->input('fulfillment'));
            if ($this->quote === null) {
                $validator->errors()->add('shipping_option', 'Pilihan ongkir sudah tidak berlaku (lewat 30 menit atau pesanan berubah). Pilih kurir lagi, lalu kirim.');
            }
        }];
    }

    /**
     * ORD-26: what the customer chose on the order link, or null when the link does not offer priced options.
     *
     * @return array{option: string, quote: ?array, area_label: ?string}|null
     */
    public function shipping(): ?array
    {
        if (! $this->picksShipping()) {
            return null;
        }

        return [
            'option' => (string) $this->input('shipping_option'),
            'quote' => $this->quote,
            'area_label' => ShippingQuotes::areaLabel((string) $this->input('delivery_area_id')),
        ];
    }

    private function picksShipping(): bool
    {
        return (bool) $this->order()?->customerPicksShipping();
    }

    private function order(): ?OnlineOrder
    {
        $token = (string) $this->route('token');

        return $this->order ??= strlen($token) === 40 ? OnlineOrder::findByCustomerToken($token) : null;
    }

    protected function prepareForValidation(): void
    {
        // ORD-26: the shared shipping block names its fields as the checkout does.
        if ($this->picksShipping()) {
            $this->merge(['fulfillment' => $this->input('delivery'), 'address' => $this->input('customer_address')]);
        }
        $this->normalizeCustomerDetails();
    }

    public function attributes(): array
    {
        return $this->customerDetailAttributes() + ['payment_preference' => 'metode pembayaran', 'shipping_payment' => 'metode pembayaran ongkir'];
    }

    public function messages(): array
    {
        return $this->customerDetailMessages() + [
            'payment_preference.required' => 'Pilih metode pembayaran.',
            'shipping_payment.required' => 'Pilih metode pembayaran ongkir.',
            'pin_latitude.required' => 'Tentukan titik antar di peta.',
            'pin_latitude.between' => 'Titik antar harus di Palu dan sekitarnya.',
            'pin_longitude.between' => 'Titik antar harus di Palu dan sekitarnya.',
            'shipping_option.required' => 'Isi kecamatan atau kota tujuan, lalu pilih pengiriman.',
            'shipping_option.regex' => 'Pilih pengiriman lagi.',
            'address.required_unless' => 'Isi alamat lengkap.',
        ];
    }

    private function asksShippingPayment(): bool
    {
        return (bool) $this->order()?->customerSetsShippingPayment();
    }
}
