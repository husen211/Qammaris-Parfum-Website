<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\Orders\OnlineOrderDetailRules;
use Illuminate\Foundation\Http\FormRequest;

class OnlineOrderStoreRequest extends FormRequest
{
    use OnlineOrderDetailRules;

    public function authorize(): bool
    {
        return $this->user()?->role === 'admin';
    }

    public function rules(): array
    {
        $customer = $this->boolean('fill_customer') ? $this->customerDetailRules() : [];

        return [
            'items' => ['required', 'array', 'min:1', 'max:20'],
            'items.*.variant_id' => ['required', 'integer', 'min:1', 'distinct'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:99'],
            'fill_customer' => ['boolean'],
        ] + $customer;
    }

    protected function prepareForValidation(): void
    {
        if ($this->boolean('fill_customer')) {
            $this->normalizeCustomerDetails();
        }
    }

    /** @return array<int, int> */
    public function lines(): array
    {
        return collect($this->validated('items'))->mapWithKeys(fn ($item) => [(int) $item['variant_id'] => (int) $item['quantity']])->all();
    }

    public function customer(): ?array
    {
        return $this->boolean('fill_customer')
            ? $this->safe()->only(['customer_name', 'customer_phone', 'fulfillment', 'address', 'postcode', 'packaging', 'customer_note'])
            : null;
    }

    public function attributes(): array
    {
        return $this->customerDetailAttributes() + ['items' => 'produk', 'items.*.quantity' => 'jumlah'];
    }

    public function messages(): array
    {
        return $this->customerDetailMessages() + [
            'items.required' => 'Pilih minimal satu produk.',
            'items.*.variant_id.distinct' => 'Produk yang sama dipilih dua kali; ubah jumlahnya saja.',
            'items.*.quantity.min' => 'Jumlah minimal 1.',
            'items.*.quantity.max' => 'Jumlah maksimal 99.',
        ];
    }
}
