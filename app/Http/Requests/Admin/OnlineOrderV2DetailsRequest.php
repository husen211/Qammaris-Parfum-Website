<?php

namespace App\Http\Requests\Admin;

use Illuminate\Support\Arr;

/**
 * V2 order details form: the ORD-01 rules without courier, tracking and payment fields, which V2 changes only
 * through its own operations (and which are therefore never in `validated()`).
 */
class OnlineOrderV2DetailsRequest extends OnlineOrderUpdateRequest
{
    public function rules(): array
    {
        return Arr::except(parent::rules(), ['courier', 'courier_booked_by', 'tracking_number', 'payment_method']);
    }
}
