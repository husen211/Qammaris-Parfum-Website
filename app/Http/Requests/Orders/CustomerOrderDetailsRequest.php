<?php

namespace App\Http\Requests\Orders;

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
            ? ['payment_preference' => ['required', Rule::in(array_keys(config('orders.payment_preferences')))]] : []);
    }

    protected function prepareForValidation(): void
    {
        $this->normalizeCustomerDetails();
    }

    public function attributes(): array
    {
        return $this->customerDetailAttributes() + ['payment_preference' => 'metode pembayaran'];
    }

    public function messages(): array
    {
        return $this->customerDetailMessages() + ['payment_preference.required' => 'Pilih metode pembayaran.'];
    }
}
