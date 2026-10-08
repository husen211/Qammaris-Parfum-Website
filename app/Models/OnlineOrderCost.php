<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Cost reported by Qammaris App (contract §8.5); keyed by the real App expense ID. */
class OnlineOrderCost extends Model
{
    protected $fillable = [
        'expense_ref', 'kind', 'status', 'amount', 'funding', 'reimbursement_status', 'reimbursement_amount', 'proof', 'waiver',
        'reimbursement_updated_at', 'source_version', 'reported_by_app_user_id', 'reported_by_name', 'reported_at',
    ];

    protected $casts = [
        'amount' => 'decimal:2', 'reimbursement_amount' => 'decimal:2', 'funding' => 'array', 'waiver' => 'array',
        'reimbursement_updated_at' => 'datetime', 'reported_at' => 'datetime', 'source_version' => 'integer',
    ];

    /** Unpaid staff reimbursement: an open obligation that never blocks completion (Owner R8). */
    public function reimbursementOpen(): bool
    {
        return $this->status === 'active' && in_array($this->reimbursement_status, ['awaiting_proof', 'submitted', 'approved'], true);
    }
}
