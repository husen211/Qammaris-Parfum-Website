<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Cost of an order (contract §8.5). origin "app": reported by Qammaris App, keyed by the real App expense ID.
 * origin "website" (ORD-08): the courier cost Staff Order records on the Website; the App owns its reimbursement.
 */
class OnlineOrderCost extends Model
{
    protected $fillable = [
        'expense_ref', 'origin', 'kind', 'status', 'amount', 'funding', 'advanced_by_name', 'reimbursement_status', 'reimbursement_amount', 'proof', 'waiver',
        'reimbursement_updated_at', 'source_version', 'reported_by_app_user_id', 'reported_by_name', 'reported_at', 'recorded_by_user_id',
    ];

    protected $casts = [
        'amount' => 'decimal:2', 'reimbursement_amount' => 'decimal:2', 'funding' => 'array', 'waiver' => 'array',
        'reimbursement_updated_at' => 'datetime', 'reported_at' => 'datetime', 'source_version' => 'integer',
    ];

    public function isWebsiteOrigin(): bool
    {
        return $this->origin === 'website';
    }

    /** Unpaid staff reimbursement: an open obligation that never blocks completion (Owner R8). */
    public function reimbursementOpen(): bool
    {
        return $this->status === 'active' && in_array($this->reimbursement_status, ['awaiting_proof', 'submitted', 'approved'], true);
    }
}
