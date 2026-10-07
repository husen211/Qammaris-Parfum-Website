<?php

namespace App\Http\Requests\Orders;

use Illuminate\Foundation\Http\FormRequest;

class CustomerOrderDetailsRequest extends FormRequest
{
    use OnlineOrderDetailRules;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return $this->customerDetailRules();
    }

    protected function prepareForValidation(): void
    {
        $this->normalizeCustomerDetails();
    }

    public function attributes(): array
    {
        return $this->customerDetailAttributes();
    }

    public function messages(): array
    {
        return $this->customerDetailMessages();
    }
}
