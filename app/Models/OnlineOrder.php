<?php

namespace App\Models;

use App\Support\OnlineOrderLegacyState;
use App\Support\OnlineOrderMoney;
use App\Support\OnlineOrderV2State;
use App\Support\OrderApi\OrderWebhookOutbox;
use App\Support\Rupiah;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class OnlineOrder extends Model
{
    public const STAGE_AWAITING_CUSTOMER = 'awaiting_customer';

    public const STAGE_DETAILS_RECEIVED = 'details_received';

    public const STAGE_PAID = 'paid';

    public const STAGE_SHIPPED = 'shipped';

    public const STAGE_COMPLETED = 'completed';

    public const STAGE_CANCELLED = 'cancelled';

    public const FULFILLMENTS = [
        'pickup' => 'Ambil di toko',
        'local_delivery' => 'Kirim dalam Kota Palu',
        'intercity' => 'Kirim ke luar kota',
    ];

    public const PACKAGING = ['paperbag' => 'Pakai paperbag', 'no_paperbag' => 'Tanpa paperbag'];

    public const COURIERS = ['maxim' => 'Maxim', 'gosend' => 'GoSend', 'grab' => 'GrabExpress', 'jnt' => 'J&T', 'other' => 'Kurir lain'];

    public const PAYMENT_METHODS = ['transfer' => 'Transfer bank', 'qris' => 'QRIS', 'cash' => 'Tunai'];

    public const SHIPPING_PAYERS = [
        'added_to_transfer' => 'Ditambahkan ke pembayaran customer',
        'cash_to_driver' => 'Customer bayar langsung ke driver',
        'store_free' => 'Gratis ongkir (ditanggung toko)',
    ];

    public const DRIVER_FUNDING = [
        'cashier_cash' => 'Ambil cash kasir',
        'owner_gopay' => 'Admin kirim ke GoPay staf',
        'staff_advance' => 'Staf talangi dulu, lalu diganti',
    ];

    public const BOOKERS = ['admin' => 'Saya (admin) yang pesan', 'staff' => 'Minta staf pesankan'];

    public const CUSTOMER_LINK_DAYS = 7;

    protected $fillable = [
        'customer_name', 'customer_phone', 'fulfillment', 'address', 'postcode', 'location_url', 'packaging',
        'customer_note', 'courier', 'courier_booked_by', 'tracking_number', 'shipping_fee', 'shipping_payer',
        'driver_funding', 'payment_method', 'recorded_in_majoo', 'staff_note',
    ];

    protected $hidden = ['customer_token_hash', 'customer_token_encrypted', 'staff_token_hash', 'staff_token_encrypted'];

    protected $casts = [
        'customer_token_encrypted' => 'encrypted',
        'staff_token_encrypted' => 'encrypted',
        'customer_link_expires_at' => 'datetime',
        'shipping_fee' => 'decimal:2',
        'staff_advance_amount' => 'decimal:2',
        'staff_reimbursed_at' => 'datetime',
        'recorded_in_majoo' => 'boolean',
        'revision' => 'integer',
        'closed_at' => 'datetime',
        'payment_confirmed_at' => 'datetime',
        'jnt_pickup_requested_at' => 'datetime',
        'jnt_picked_up_at' => 'datetime',
        'handed_over_at' => 'datetime',
        'delivered_at' => 'datetime',
        'refund_due_amount' => 'decimal:2',
        'refund_decided_at' => 'datetime',
        'packed_items' => 'array',
        'courier_requested_at' => 'datetime',
        'keep_until' => 'datetime',
        'keep_stock_confirmed_at' => 'datetime',
    ];

    public const STATE_LEGACY = 'legacy';

    public const STATE_V2 = 'v2';

    protected static function booted(): void
    {
        static::creating(function (OnlineOrder $order): void {
            $order->public_id ??= (string) Str::ulid();
            $order->source ??= 'whatsapp';
            $order->state_model ??= self::STATE_LEGACY;
        });

        // V2 order changed (new or new revision): tell the App through the outbox (ORD-02e, contract §12).
        static::created(function (OnlineOrder $order): void {
            if ($order->isV2()) {
                OrderWebhookOutbox::record($order);
            }
        });
        static::updated(function (OnlineOrder $order): void {
            if ($order->isV2() && $order->wasChanged('revision')) {
                OrderWebhookOutbox::record($order);
            }
        });

        // Legacy rows: ORD-01 screens drive `stage` and the ORD-02 dimensions follow it one way.
        // V2 rows: the V2 operations drive the dimensions and `stage` is derived from them.
        static::saving(function (OnlineOrder $order): void {
            if ($order->state_model === self::STATE_V2) {
                OnlineOrderV2State::normalize($order);

                return;
            }
            $order->forceFill(OnlineOrderLegacyState::dimensions($order->getAttributes(), fn () => now()));
            $basePayment = $order->payment_status;

            if ($order->isDirty('stage') && $order->refund_due_amount === null) {
                // ORD-01 has no refund records, so a cancel after payment is flagged for reconciliation, never assumed.
                if ($order->stage === self::STAGE_CANCELLED && $basePayment === 'paid') {
                    $order->refund_status = 'needs_reconciliation';
                } elseif ($order->getOriginal('stage') === self::STAGE_CANCELLED && $order->refund_status === 'needs_reconciliation') {
                    $order->refund_status = null;
                }
            }
            OnlineOrderMoney::apply($order, $basePayment);
        });
    }

    public function isV2(): bool
    {
        return $this->state_model === self::STATE_V2;
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function claims(): HasMany
    {
        return $this->hasMany(OnlineOrderClaim::class);
    }

    public function costs(): HasMany
    {
        return $this->hasMany(OnlineOrderCost::class)->orderBy('id');
    }

    public function adjustments(): HasMany
    {
        return $this->hasMany(OnlineOrderAdjustment::class)->orderBy('id');
    }

    public function changeRequests(): HasMany
    {
        return $this->hasMany(OnlineOrderChangeRequest::class)->orderBy('id');
    }

    /** Contract keep status: an active keep past its deadline is `expired` (needs action, never auto-cancelled). */
    public function keepState(): ?string
    {
        if ($this->keep_status === 'active' && $this->keep_until !== null && $this->keep_until->isPast()) {
            return 'expired';
        }

        return $this->keep_status;
    }

    public function issues(): HasMany
    {
        return $this->hasMany(OnlineOrderIssue::class)->orderBy('id');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(OnlineOrderPayment::class)->orderBy('id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(OnlineOrderItem::class)->orderBy('id');
    }

    public function events(): HasMany
    {
        return $this->hasMany(OnlineOrderEvent::class)->orderBy('created_at')->orderBy('id');
    }

    public static function tokenHash(string $token): string
    {
        return hash('sha256', $token);
    }

    public static function findByCustomerToken(string $token): ?self
    {
        return self::query()->where('customer_token_hash', self::tokenHash($token))->first();
    }

    public static function findByStaffToken(string $token): ?self
    {
        return self::query()->where('staff_token_hash', self::tokenHash($token))->first();
    }

    /**
     * Ordered steps for this order's fulfillment. Owner keeps manual tracking short: "Dikirim" means the
     * driver/J&T is booked and the parcel leaves; pickup goes straight from paid to collected.
     */
    public function steps(): array
    {
        $steps = [self::STAGE_AWAITING_CUSTOMER, self::STAGE_DETAILS_RECEIVED, self::STAGE_PAID];
        if ($this->fulfillment !== 'pickup') {
            $steps[] = self::STAGE_SHIPPED;
        }
        $steps[] = self::STAGE_COMPLETED;

        return $steps;
    }

    public function nextStage(): ?string
    {
        if ($this->stage === self::STAGE_CANCELLED) {
            return null;
        }
        $steps = $this->steps();
        $index = array_search($this->stage, $steps, true);

        return $index === false ? null : ($steps[$index + 1] ?? null);
    }

    public function stepIndex(?string $stage = null): int
    {
        $index = array_search($stage ?? $this->stage, $this->steps(), true);

        return $index === false ? -1 : $index;
    }

    public function isClosed(): bool
    {
        return in_array($this->stage, [self::STAGE_COMPLETED, self::STAGE_CANCELLED], true);
    }

    public function hasCustomerDetails(): bool
    {
        return $this->customer_name !== null && $this->fulfillment !== null;
    }

    /** Customers may correct their own data until payment is confirmed. */
    public function customerCanEdit(): bool
    {
        return in_array($this->stage, [self::STAGE_AWAITING_CUSTOMER, self::STAGE_DETAILS_RECEIVED], true);
    }

    public function customerLinkUsable(): bool
    {
        if ($this->stage === self::STAGE_AWAITING_CUSTOMER) {
            return $this->customer_link_expires_at->isFuture();
        }

        return $this->closed_at === null || $this->closed_at->gt(now()->subDays(30));
    }

    public function staffLinkUsable(): bool
    {
        // D6: V2 orders are handled by signed-in staff in the Admin PWA (and later the App), never by a bearer link.
        if ($this->isV2()) {
            return false;
        }

        return $this->closed_at === null || $this->closed_at->gt(now()->subDays(7));
    }

    public function stageLabel(?string $stage = null): string
    {
        $stage ??= $this->stage;
        $pickup = $this->fulfillment === 'pickup';
        $jnt = $this->courier === 'jnt';

        return match ($stage) {
            self::STAGE_AWAITING_CUSTOMER => 'Menunggu data customer',
            self::STAGE_DETAILS_RECEIVED => 'Data diterima',
            self::STAGE_PAID => 'Dibayar',
            self::STAGE_SHIPPED => $jnt ? 'Dikirim via J&T' : 'Dikirim',
            self::STAGE_COMPLETED => $pickup ? 'Sudah diambil' : 'Diterima',
            self::STAGE_CANCELLED => 'Dibatalkan',
            default => $stage,
        };
    }

    public function subtotal(): string
    {
        return Rupiah::sum($this->items->map(fn (OnlineOrderItem $item) => $item->lineTotal())->all());
    }

    /** Shipping is only part of the customer's payment when it was added to the transfer. */
    public function customerTotal(): string
    {
        $cents = Rupiah::minorUnits($this->subtotal());
        if ($this->shipping_payer === 'added_to_transfer' && $this->shipping_fee !== null) {
            $cents += Rupiah::minorUnits($this->shipping_fee);
        }
        $cents += $this->approvedAdjustmentCents();

        return Rupiah::decimal($cents);
    }

    /** Sum of Super Admin approved price adjustments (signed, whole cents). */
    public function approvedAdjustmentCents(): int
    {
        if (! $this->exists) {
            return 0;
        }

        return (int) $this->adjustments()->where('status', 'approved')->get(['amount'])
            ->sum(fn (OnlineOrderAdjustment $adjustment) => Rupiah::minorUnits($adjustment->amount));
    }

    public function needsReimbursement(): bool
    {
        return $this->driver_funding === 'staff_advance' && $this->staff_advance_amount !== null && $this->staff_reimbursed_at === null;
    }

    public function itemSummary(): string
    {
        return $this->items->map(fn (OnlineOrderItem $item) => $item->label().' × '.$item->quantity)->implode(', ');
    }
}
