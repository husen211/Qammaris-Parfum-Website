<?php

namespace App\Http\Requests\Admin;

use App\Actions\Orders\CreateOnlineOrder;
use App\Http\Requests\Orders\OnlineOrderDetailRules;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class OnlineOrderStoreRequest extends FormRequest
{
    use OnlineOrderDetailRules;

    public function authorize(): bool
    {
        return (bool) $this->user()?->can('orders.manage');
    }

    public function rules(): array
    {
        $customer = $this->boolean('fill_customer') ? $this->customerDetailRules() : [];

        return [
            'items' => ['required', 'array', 'min:1', 'max:20'],
            'items.*.variant_id' => ['required', 'integer', 'min:1', 'distinct'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:99'],
            'fill_customer' => ['boolean'],
            'submission_token' => ['nullable', 'uuid'],
            // ORD-02d: where the chat came from, and an explicitly chosen repeat customer / saved address.
            'source' => ['nullable', Rule::in(array_keys(CreateOnlineOrder::SOURCES))],
            'customer_id' => ['nullable', 'integer', 'min:1'],
            'new_customer' => ['boolean'],
            'customer_address_id' => ['nullable', 'integer', 'min:1'],
        ] + $customer;
    }

    /** @return array{source: string, customer_id: ?int, new_customer: bool, customer_address_id: ?int} */
    public function options(): array
    {
        $customerId = $this->validated('customer_id');

        return [
            'source' => $this->validated('source') ?? 'whatsapp',
            'customer_id' => $customerId === null ? null : (int) $customerId,
            'new_customer' => $customerId === null && $this->boolean('new_customer') && $this->boolean('fill_customer'),
            'customer_address_id' => $customerId === null || $this->validated('customer_address_id') === null ? null : (int) $this->validated('customer_address_id'),
        ];
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

    public function submissionToken(): ?string
    {
        return $this->validated('submission_token');
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
