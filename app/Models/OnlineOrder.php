<?php

namespace App\Models;

use App\Support\Rupiah;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OnlineOrder extends Model
{
    public const STAGE_AWAITING_CUSTOMER = 'awaiting_customer';

    public const STAGE_DETAILS_RECEIVED = 'details_received';

    public const STAGE_PAID = 'paid';

    public const STAGE_COURIER_BOOKED = 'courier_booked';

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
    ];

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

    /** Ordered steps for this order's fulfillment; pickup has no separate shipping step. */
    public function steps(): array
    {
        $steps = [self::STAGE_AWAITING_CUSTOMER, self::STAGE_DETAILS_RECEIVED, self::STAGE_PAID, self::STAGE_COURIER_BOOKED];
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
            self::STAGE_COURIER_BOOKED => $pickup ? 'Siap diambil' : ($jnt ? 'Pickup J&T diminta' : 'Driver dipesan'),
            self::STAGE_SHIPPED => $jnt ? 'Dipickup J&T' : 'Dikirim',
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
        $amounts = [$this->subtotal()];
        if ($this->shipping_payer === 'added_to_transfer' && $this->shipping_fee !== null) {
            $amounts[] = $this->shipping_fee;
        }

        return Rupiah::sum($amounts);
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
