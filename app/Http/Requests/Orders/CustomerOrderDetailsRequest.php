<?php

namespace App\Http\Requests\Orders;

use App\Models\OnlineOrder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CustomerOrderDetailsRequest extends FormRequest
{
    use OnlineOrderDetailRules;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        // ORD-03 (flag): the customer's preferred payment method; a preference, never a payment.
        return $this->customerDetailRules() + (config('orders.simple_ux')
            ? ['payment_preference' => ['required', Rule::in(array_keys(config('orders.payment_preferences')))]] : [])
            // ORD-10: asked only for a local courier, and only while the link may still set it.
            + ['shipping_payment' => ['exclude_unless:fulfillment,local_delivery', $this->asksShippingPayment() ? 'required' : 'nullable',
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

    private function order(): ?OnlineOrder
    {
        $token = (string) $this->route('token');

        return strlen($token) === 40 ? OnlineOrder::findByCustomerToken($token) : null;
    }

    protected function prepareForValidation(): void
    {
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
        ];
    }

    private function asksShippingPayment(): bool
    {
        return (bool) $this->order()?->customerSetsShippingPayment();
    }
}
