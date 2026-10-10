<?php

namespace App\Actions\Orders;

use App\Exceptions\ExpenseAlreadyLinked;
use App\Exceptions\InvalidOrderTransition;
use App\Exceptions\OrderActionNotAllowed;
use App\Exceptions\OrderRevisionConflict;
use App\Exceptions\OrderValidationFailed;
use App\Exceptions\ProofRequired;
use App\Exceptions\TaskAlreadyClaimed;
use App\Models\OnlineOrder;
use App\Models\OnlineOrderClaim;
use App\Models\OnlineOrderCost;
use App\Models\OnlineOrderEvent;
use App\Models\OnlineOrderIssue;
use App\Support\OnlineOrderMoney;
use App\Support\OnlineOrderV2State;
use App\Support\OrderActor;
use App\Support\Rupiah;
use Closure;
use DateTimeInterface;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

/**
 * V2 order state operations (ORD-02c slice 3). Shared by the Admin PWA (ORD-02d) and API v1 (ORD-02e).
 *
 * Every operation locks the order, refuses legacy rows, checks the expected revision when given, applies one
 * logical change as one revision with one event, and completes the order when the completion rule holds.
 * Repeating a step that already happened with the same data is a no-op (no revision, no event).
 */
class OnlineOrderFulfillment
{
    public const COURIER_PROVIDERS = ['maxim', 'gosend', 'grab', 'other'];

    private const JNT_RANK = ['not_requested' => 0, 'pickup_requested' => 1, 'qr_available' => 2, 'picked_up' => 3];

    /** Event written by the last applied operation, null for a no-op. */
    public ?OnlineOrderEvent $lastEvent = null;

    private ?array $pendingEvent = null;

    public function startPreparation(OnlineOrder $order, int $revision, OrderActor $actor): OnlineOrder
    {
        return $this->run($order, $revision, $actor, function (OnlineOrder $order) use ($actor): void {
            $this->assertActive($order);
            $this->assertClaim($order, $actor, 'preparation');
            if ($order->preparation_status === 'preparing') {
                return;
            }
            if ($order->preparation_status === 'packed') {
                throw new InvalidOrderTransition('Pesanan ini sudah selesai dipacking.');
            }
            $order->preparation_status = 'preparing';
            $this->record('preparation_started', 'Mulai disiapkan');
        });
    }

    /**
     * Packing is confirmed only with every order line at exactly its ordered quantity (contract §8.2).
     * Missing stock is an issue (`stock_problem`), not a packed order.
     *
     * @param  list<array{line_id: string, quantity: int}>  $packedItems
     */
    public function pack(OnlineOrder $order, int $revision, OrderActor $actor, array $packedItems): OnlineOrder
    {
        return $this->run($order, $revision, $actor, function (OnlineOrder $order) use ($actor, $packedItems): void {
            $this->assertActive($order);
            $this->assertClaim($order, $actor, 'preparation');
            $packed = $this->validatePackedItems($order, $packedItems);
            if ($order->preparation_status === 'packed') {
                if ($order->packed_items === $packed) {
                    return;
                }
                throw new InvalidOrderTransition('Packing sudah dikonfirmasi dengan jumlah lain. Buka kendala bila ada yang salah.');
            }
            if ($order->handover_status === 'handed_over') {
                throw new InvalidOrderTransition('Pesanan sudah diserahkan.');
            }
            $order->packed_items = $packed;
            $order->preparation_status = 'packed';
            $this->record('packed', collect($packed)->sum('quantity').' barang dipacking');
        });
    }

    /**
     * Keep (D9): the customer reserves the items, payment may follow. Default 24 hours. Staff confirm by hand that
     * stock is set aside; past the deadline the order needs action but is never cancelled automatically.
     */
    public function startKeep(OnlineOrder $order, int $revision, OrderActor $actor, int $hours = 24): OnlineOrder
    {
        return $this->run($order, $revision, $actor, function (OnlineOrder $order) use ($hours): void {
            if (! in_array($order->lifecycle, ['awaiting_customer', 'active'], true) || $order->handover_status === 'handed_over' || $order->payment_status !== 'unpaid') {
                throw new InvalidOrderTransition('Keep hanya untuk pesanan yang belum dibayar dan belum diserahkan.');
            }
            if ($order->keep_status === 'active') {
                throw new InvalidOrderTransition('Pesanan ini sudah di-keep. Perpanjang bila perlu.');
            }
            $this->assertKeepHours($hours);
            $order->keep_status = 'active';
            $order->keep_until = now()->addHours($hours);
            $order->keep_stock_confirmed_at = null;
            $order->keep_stock_confirmed_by = null;
            $this->record('keep_started', "Keep {$hours} jam");
        });
    }

    public function confirmKeepStock(OnlineOrder $order, int $revision, OrderActor $actor): OnlineOrder
    {
        return $this->run($order, $revision, $actor, function (OnlineOrder $order) use ($actor): void {
            $this->assertKeepActive($order);
            if ($order->keep_stock_confirmed_at !== null) {
                return;
            }
            $order->keep_stock_confirmed_at = now();
            $order->keep_stock_confirmed_by = $actor->user?->id;
            $this->record('keep_stock_set_aside', 'Stok sudah dipisahkan oleh '.$actor->displayName);
        });
    }

    public function extendKeep(OnlineOrder $order, int $revision, OrderActor $actor, int $hours): OnlineOrder
    {
        return $this->run($order, $revision, $actor, function (OnlineOrder $order) use ($hours): void {
            $this->assertKeepActive($order);
            $this->assertKeepHours($hours);
            $order->keep_until = now()->addHours($hours);
            $this->record('keep_extended', "Keep diperpanjang {$hours} jam");
        });
    }

    /** Ends the keep; the stock is free again. The order itself stays open until cancelled or paid. */
    public function releaseKeep(OnlineOrder $order, int $revision, OrderActor $actor, string $reason): OnlineOrder
    {
        return $this->run($order, $revision, $actor, function (OnlineOrder $order) use ($reason): void {
            $this->assertKeepActive($order);
            $reason = trim($reason);
            if (mb_strlen($reason) < 5) {
                throw new OrderValidationFailed('Tuliskan alasan keep dilepas.', ['reason' => 'Minimal 5 karakter']);
            }
            $order->keep_status = 'released';
            $this->record('keep_released', $reason);
        });
    }

    /**
     * Atomic task claim (contract §8.1). The order row is locked and the (order, task) key is unique. A task held by
     * someone else answers `task_already_claimed`, which takes precedence over `revision_conflict`; the same holder
     * claiming again is a no-op. Claims are App work; Website admins never need one (Admin PWA backup path).
     */
    public function claim(OnlineOrder $order, ?int $revision, OrderActor $actor, string $task): OnlineOrder
    {
        if (! $actor->isApp()) {
            throw new OrderActionNotAllowed('Klaim tugas hanya dari Qammaris App.');
        }

        return $this->run($order, null, $actor, function (OnlineOrder $order) use ($actor, $task, $revision): void {
            if (! in_array($task, OnlineOrderClaim::TASKS, true)) {
                throw new OrderValidationFailed('Tugas tidak dikenal.', ['task' => 'preparation, courier_booking, atau handover']);
            }
            // Locking read: always the latest committed claim, whatever the isolation level.
            $existing = $order->claims()->where('task', $task)->lockForUpdate()->first();
            if ($existing) {
                if ($existing->holder_app_user_id === $actor->appUserId) {
                    return;
                }
                throw new TaskAlreadyClaimed($task, $existing->holder_app_user_id, $existing->holder_display_name, $existing->claimed_at->utc()->format('Y-m-d\TH:i:s\Z'));
            }
            if ($revision !== null && $order->revision !== $revision) {
                throw new OrderRevisionConflict($order->revision);
            }
            $this->assertActive($order);
            $done = match ($task) {
                'preparation' => $order->preparation_status === 'packed',
                'courier_booking' => ! ($order->fulfillment === 'intercity' || ($order->fulfillment === 'local_delivery' && $order->courier_booking_responsibility === 'store'))
                    || $order->handover_status === 'handed_over',
                'handover' => $order->handover_status === 'handed_over',
            };
            if ($done) {
                throw new InvalidOrderTransition("Tugas {$task} tidak diperlukan atau sudah selesai untuk pesanan ini.");
            }
            try {
                $order->claims()->create(['task' => $task, 'holder_app_user_id' => $actor->appUserId, 'holder_display_name' => Str::limit($actor->displayName, 57), 'claimed_at' => now()]);
            } catch (UniqueConstraintViolationException) {
                // Defence in depth; the row lock already serialises claims on this order.
                $holder = $order->claims()->where('task', $task)->lockForUpdate()->firstOrFail();
                throw new TaskAlreadyClaimed($task, $holder->holder_app_user_id, $holder->holder_display_name, $holder->claimed_at->utc()->format('Y-m-d\TH:i:s\Z'));
            }
            $this->record('claimed', "Klaim {$task}");
        });
    }

    /** The holder releases their claim; releasing someone else's needs owner/Super Admin and a reason. */
    public function releaseClaim(OnlineOrder $order, ?int $revision, OrderActor $actor, string $task, ?string $reason = null): OnlineOrder
    {
        return $this->run($order, null, $actor, function (OnlineOrder $order) use ($actor, $task, $revision, $reason): void {
            $claim = $order->claims()->where('task', $task)->lockForUpdate()->first();
            if (! $claim) {
                return;
            }
            $own = $actor->isApp() && $claim->holder_app_user_id === $actor->appUserId;
            if (! $own) {
                if (! $actor->isOwner()) {
                    throw new OrderActionNotAllowed('Klaim milik orang lain hanya bisa dilepas owner.');
                }
                if (mb_strlen(trim((string) $reason)) < 3) {
                    throw new OrderValidationFailed('Tuliskan alasan melepas klaim orang lain.', ['reason' => 'Wajib']);
                }
            }
            if ($revision !== null && $order->revision !== $revision) {
                throw new OrderRevisionConflict($order->revision);
            }
            $claim->delete();
            $this->record('claim_released', "Lepas {$task} dari {$claim->holder_display_name}".($own ? '' : ': '.trim((string) $reason)));
        });
    }

    /** QR from J&T: stored privately; also marks `qr_available` in the same revision (contract §8.4). */
    public function storeJntQr(OnlineOrder $order, int $revision, OrderActor $actor, string $bytes, string $mime): OnlineOrder
    {
        $path = 'order-qr/'.$order->public_id.'/'.Str::ulid().($mime === 'image/png' ? '.png' : '.jpg');
        if (! Storage::disk('local')->put($path, $bytes)) {
            throw new RuntimeException('QR could not be stored.');
        }
        try {
            return $this->run($order, $revision, $actor, function (OnlineOrder $order) use ($actor, $path, $mime): void {
                $this->assertActive($order);
                if ($order->fulfillment !== 'intercity' || $order->jnt_status === 'picked_up') {
                    throw new InvalidOrderTransition('QR J&T hanya untuk pesanan luar kota yang belum dipickup.');
                }
                $this->assertClaim($order, $actor, 'courier_booking');
                $order->jnt_qr_path = $path;
                $order->jnt_qr_mime = $mime;
                if (self::JNT_RANK[$order->jnt_status ?? 'not_requested'] < self::JNT_RANK['qr_available']) {
                    $order->jnt_status = 'qr_available';
                    $order->jnt_pickup_requested_at ??= now();
                }
                $this->record('jnt_qr_available', 'QR J&T diunggah');
            });
        } catch (Throwable $error) {
            Storage::disk('local')->delete($path);
            throw $error;
        }
    }

    /**
     * Cost from Qammaris App keyed by the real expense ID (contract §8.5): one expense, one order; an older or equal
     * source_version is a no-op; funding must add up; reimbursement equals the staff-advance part; approval needs
     * attached proof or an Owner waiver.
     *
     * @param  array{kind: string, status: string, amount: int, funding: list<array{source: string, amount: int}>, reimbursement: array, source_version: int}  $data
     */
    public function upsertCost(OnlineOrder $order, ?int $revision, OrderActor $actor, string $expenseRef, array $data): OnlineOrder
    {
        if (! $actor->isApp()) {
            throw new OrderActionNotAllowed('Biaya dan reimburse dicatat dari Qammaris App.');
        }
        $sources = array_column($data['funding'], 'amount', 'source');
        $fields = [];
        if (count($sources) !== count($data['funding'])) {
            $fields['funding'] = 'Satu sumber dana satu baris';
        } elseif (array_sum($sources) !== $data['amount']) {
            $fields['funding'] = 'Jumlah funding harus sama dengan amount';
        }
        $advance = $sources['staff_advance'] ?? 0;
        $reimbursement = $data['reimbursement'];
        if ($reimbursement['amount'] !== $advance) {
            $fields['reimbursement.amount'] = 'Harus sama dengan bagian staff_advance';
        } elseif (($advance === 0) !== ($reimbursement['status'] === 'not_applicable')) {
            $fields['reimbursement.status'] = 'not_applicable hanya bila tidak ada talangan staf';
        }
        if ($fields) {
            throw new OrderValidationFailed('Data biaya tidak valid.', $fields);
        }
        if (in_array($reimbursement['status'], ['approved', 'paid'], true)
            && ! ($reimbursement['proof'] === 'attached' || ($reimbursement['proof'] === 'waived' && ! empty($reimbursement['waiver'])))) {
            throw new ProofRequired('Reimburse disetujui/dibayar wajib bukti terlampir atau pengecualian Owner.');
        }

        return $this->run($order, $revision, $actor, function (OnlineOrder $order) use ($actor, $expenseRef, $data, $reimbursement): void {
            $existing = OnlineOrderCost::query()->where('expense_ref', $expenseRef)->lockForUpdate()->first();
            if ($existing && $existing->online_order_id !== $order->id) {
                throw new ExpenseAlreadyLinked('Expense ini sudah terhubung ke order lain.');
            }
            if ($existing && $data['source_version'] <= $existing->source_version) {
                return;
            }
            if ($reimbursement['proof'] === 'waived') {
                $this->assertWaiverDecision($existing?->waiver, (array) $reimbursement['waiver'], $actor);
            }
            $attributes = [
                'kind' => $data['kind'], 'status' => $data['status'], 'amount' => Rupiah::decimal($data['amount'] * 100),
                'funding' => $data['funding'], 'reimbursement_status' => $reimbursement['status'],
                'reimbursement_amount' => Rupiah::decimal($reimbursement['amount'] * 100), 'proof' => $reimbursement['proof'],
                'waiver' => $reimbursement['waiver'] ?? null, 'reimbursement_updated_at' => Carbon::parse($reimbursement['updated_at']),
                'source_version' => $data['source_version'],
            ];
            try {
                if ($existing) {
                    $existing->update($attributes);
                } else {
                    $order->costs()->create($attributes + ['expense_ref' => $expenseRef, 'reported_by_app_user_id' => $actor->appUserId,
                        'reported_by_name' => Str::limit($actor->displayName, 57), 'reported_at' => now()]);
                }
            } catch (UniqueConstraintViolationException) {
                throw new ExpenseAlreadyLinked('Expense ini sudah terhubung ke order lain.');
            }
            $this->record($existing ? 'cost_updated' : 'cost_recorded', $data['kind'].' Rp'.number_format($data['amount'], 0, ',', '.').' · reimburse '.$reimbursement['status']);
        });
    }

    /** Store or customer books the local driver. Only while nothing is handed over. */
    public function setCourierResponsibility(OnlineOrder $order, int $revision, OrderActor $actor, string $responsibility): OnlineOrder
    {
        return $this->run($order, $revision, $actor, function (OnlineOrder $order) use ($responsibility): void {
            if ($order->fulfillment !== 'local_delivery' || $order->handover_status !== 'pending') {
                throw new InvalidOrderTransition('Siapa yang memesan kurir hanya bisa diatur untuk kirim dalam kota sebelum diserahkan.');
            }
            if (! in_array($responsibility, ['store', 'customer'], true)) {
                throw new OrderValidationFailed('Pilihan pemesan kurir tidak valid.', ['booking_responsibility' => 'store atau customer']);
            }
            if ($order->courier_booking_responsibility === $responsibility) {
                return;
            }
            if ($order->courier_status !== 'unassigned' && $order->courier_status !== 'not_needed') {
                throw new InvalidOrderTransition('Kurir sudah dipesan toko. Batalkan pesanan kurir dulu di aplikasinya.');
            }
            $order->courier_booking_responsibility = $responsibility;
            $order->courier_status = $responsibility === 'customer' ? 'not_needed' : 'unassigned';
            $order->courier_provider = null;
            $this->record('courier_responsibility', $responsibility === 'customer' ? 'Customer memesan kurir sendiri' : 'Toko memesan kurir');
        });
    }

    public function requestCourier(OnlineOrder $order, int $revision, OrderActor $actor, string $provider, string $status = 'requested', ?string $reference = null): OnlineOrder
    {
        return $this->run($order, $revision, $actor, function (OnlineOrder $order) use ($actor, $provider, $status, $reference): void {
            $this->assertActive($order);
            if ($order->fulfillment !== 'local_delivery' || $order->courier_booking_responsibility !== 'store' || $order->handover_status !== 'pending') {
                throw new InvalidOrderTransition('Kurir lokal hanya dipesan toko untuk kirim dalam kota sebelum diserahkan.');
            }
            $fields = [];
            if (! in_array($provider, self::COURIER_PROVIDERS, true)) {
                $fields['provider'] = 'maxim, gosend, grab, atau other';
            }
            if (! in_array($status, ['requested', 'arrived'], true)) {
                $fields['status'] = 'requested atau arrived';
            }
            if ($reference !== null && mb_strlen($reference) > 80) {
                $fields['reference'] = 'Maksimal 80 karakter';
            }
            if ($fields) {
                throw new OrderValidationFailed('Data kurir tidak valid.', $fields);
            }
            $this->assertClaim($order, $actor, 'courier_booking');
            if ($order->courier_status === 'arrived' && $status === 'requested') {
                throw new InvalidOrderTransition('Kurir sudah tiba.');
            }
            if ($order->courier_status === $status && $order->courier_provider === $provider && ($reference === null || $reference === $order->courier_reference)) {
                return;
            }
            $order->courier_provider = $provider;
            $order->courier_reference = $reference ?? $order->courier_reference;
            $order->courier_status = $status;
            $order->courier_requested_at ??= now();
            $this->record($status === 'arrived' ? 'courier_arrived' : 'courier_requested', ucfirst($provider).($reference ? ' · '.$reference : ''));
        });
    }

    /**
     * J&T: pickup requested, QR available and picked up are separate steps (contract §8.4). Picked up also
     * records the handover in the same revision and event. A tracking number is optional at any time.
     */
    public function recordJnt(OnlineOrder $order, int $revision, OrderActor $actor, ?string $status, ?string $trackingNumber = null, ?DateTimeInterface $occurredAt = null): OnlineOrder
    {
        return $this->run($order, $revision, $actor, function (OnlineOrder $order) use ($actor, $status, $trackingNumber, $occurredAt): void {
            $this->assertActive($order);
            if ($order->fulfillment !== 'intercity') {
                throw new InvalidOrderTransition('J&T hanya untuk pesanan kirim ke luar kota.');
            }
            // Picking up is the handover (handover claim); requesting pickup, QR and tracking are courier booking.
            $this->assertClaim($order, $actor, $status === 'picked_up' && $order->jnt_status !== 'picked_up' ? 'handover' : 'courier_booking');
            if ($status === null && $trackingNumber === null) {
                throw new OrderValidationFailed('Isi status J&T atau nomor resi.', ['status' => 'Wajib bila resi kosong']);
            }
            if ($status !== null && (! array_key_exists($status, self::JNT_RANK) || $status === 'not_requested')) {
                throw new OrderValidationFailed('Status J&T tidak valid.', ['status' => 'pickup_requested, qr_available, atau picked_up']);
            }
            if ($trackingNumber !== null && ! preg_match('/^[A-Za-z0-9-]{1,40}$/', $trackingNumber)) {
                throw new OrderValidationFailed('Nomor resi tidak valid.', ['tracking_number' => 'Huruf, angka, dan tanda minus, maksimal 40']);
            }
            $at = $occurredAt ?? now();
            $notes = [];
            $current = $order->jnt_status ?? 'not_requested';
            if ($status !== null && $status !== $current) {
                if (self::JNT_RANK[$status] < self::JNT_RANK[$current]) {
                    throw new InvalidOrderTransition('Status J&T tidak bisa mundur.');
                }
                if ($status === 'picked_up') {
                    $this->assertPacked($order);
                    $order->jnt_picked_up_at = $at;
                    $this->markHandedOver($order, 'jnt', $at);
                }
                $order->jnt_pickup_requested_at ??= $at;
                $order->jnt_status = $status;
                $notes[] = ['pickup_requested' => 'Pickup J&T diminta', 'qr_available' => 'QR J&T tersedia', 'picked_up' => 'Dipickup J&T'][$status];
            }
            if ($trackingNumber !== null && $trackingNumber !== $order->tracking_number) {
                $order->tracking_number = $trackingNumber;
                $notes[] = 'Resi '.$trackingNumber;
            }
            if ($notes) {
                $this->record($status !== null && $status !== $current ? 'jnt_'.$status : 'jnt_tracking', implode(' · ', $notes));
            }
        });
    }

    public function handover(OnlineOrder $order, int $revision, OrderActor $actor, string $handedTo, ?DateTimeInterface $occurredAt = null): OnlineOrder
    {
        if ($handedTo === 'jnt') {
            // One rule for J&T: picking up is the handover (contract §8.4).
            return $this->recordJnt($order, $revision, $actor, 'picked_up', null, $occurredAt);
        }

        return $this->run($order, $revision, $actor, function (OnlineOrder $order) use ($actor, $handedTo, $occurredAt): void {
            $this->assertActive($order);
            $this->assertClaim($order, $actor, 'handover');
            $allowed = match ($order->fulfillment) {
                'pickup' => ['customer'],
                'local_delivery' => $order->courier_booking_responsibility === 'customer' ? ['customer_courier'] : ['courier'],
                default => [],
            };
            if ($order->handover_status === 'handed_over') {
                if ($order->handed_to === $handedTo) {
                    return;
                }
                throw new InvalidOrderTransition('Pesanan sudah diserahkan.');
            }
            if (! in_array($handedTo, $allowed, true)) {
                throw new InvalidOrderTransition('Penyerahan ini tidak sesuai cara pengiriman pesanan.');
            }
            $this->assertPacked($order);
            $this->markHandedOver($order, $handedTo, $occurredAt ?? now());
            $this->record('handed_over', ['customer' => 'Diambil customer', 'courier' => 'Diserahkan ke kurir', 'customer_courier' => 'Diserahkan ke kurir customer'][$handedTo]);
        });
    }

    /** Optional confirmation that the customer received the parcel; separate from handover (D10). */
    public function confirmDelivery(OnlineOrder $order, int $revision, OrderActor $actor, string $confirmedBy = 'website', ?DateTimeInterface $occurredAt = null): OnlineOrder
    {
        $confirmedBy = $actor->isCustomer() ? 'customer' : $confirmedBy;

        return $this->run($order, $revision, $actor, function (OnlineOrder $order) use ($confirmedBy, $occurredAt): void {
            if ($order->handover_status !== 'handed_over' || $order->fulfillment === 'pickup') {
                throw new InvalidOrderTransition('Konfirmasi diterima hanya untuk paket yang sudah diserahkan ke kurir.');
            }
            if ($order->delivery_status === 'delivered') {
                return;
            }
            $order->delivery_status = 'delivered';
            $order->delivered_at = $occurredAt ?? now();
            $order->delivery_confirmed_by = in_array($confirmedBy, ['customer', 'website', 'app'], true) ? $confirmedBy : 'website';
            $this->record('delivered', 'Diterima customer');
        }, allowCustomer: true);
    }

    public function openIssue(OnlineOrder $order, ?int $revision, OrderActor $actor, string $type, string $note, ?string $lineId = null, ?int $reportedQuantity = null): OnlineOrder
    {
        // API v1 (r4.1) requires Issue.opened_by to be an App user, so a Website-opened issue would make the order
        // unreadable for the App. Until r4.2 (nullable opened_by) is approved, issues come from the App while it is on.
        if (! $actor->isApp() && config('orders_api.enabled') && ! config('orders_api.website_issues')) {
            throw new OrderActionNotAllowed('Selama integrasi Qammaris App aktif, kendala dicatat dari Qammaris App.');
        }

        return $this->run($order, $revision, $actor, function (OnlineOrder $order) use ($actor, $type, $note, $lineId, $reportedQuantity): void {
            if ($order->lifecycle === 'cancelled') {
                throw new InvalidOrderTransition('Pesanan sudah dibatalkan.');
            }
            $fields = [];
            if (! array_key_exists($type, OnlineOrderIssue::TYPES)) {
                $fields['type'] = 'Jenis kendala tidak dikenal';
            }
            $note = trim($note);
            if ($note === '' || mb_strlen($note) > 500) {
                $fields['note'] = 'Wajib diisi, maksimal 500 karakter';
            }
            if ($lineId !== null && ! $order->items()->where('line_id', $lineId)->exists()) {
                $fields['line_id'] = 'Baris tidak ada di pesanan ini';
            }
            if ($reportedQuantity !== null && ($lineId === null || $reportedQuantity < 0 || $reportedQuantity > 99)) {
                $fields['reported_quantity'] = 'Isi 0–99 dan pilih barisnya';
            }
            if ($fields) {
                throw new OrderValidationFailed('Data kendala tidak valid.', $fields);
            }
            $order->issues()->create([
                'type' => $type, 'note' => $note, 'line_id' => $lineId, 'reported_quantity' => $reportedQuantity,
                'opened_by_user_id' => $actor->user?->id, 'opened_by_app_user_id' => $actor->appUserId, 'opened_by_name' => Str::limit($actor->displayName, 57),
            ]);
            $this->record('issue_opened', OnlineOrderIssue::TYPES[$type].': '.$note);
        });
    }

    /** Resolving someone else's issue is owner-level (Super Admin / App owner). */
    public function resolveIssue(OnlineOrder $order, string $issueId, ?int $revision, OrderActor $actor, ?string $note = null): OnlineOrder
    {
        return $this->run($order, $revision, $actor, function (OnlineOrder $order) use ($issueId, $actor, $note): void {
            $issue = $order->issues()->where('public_id', $issueId)->first();
            if (! $issue) {
                throw new OrderValidationFailed('Kendala tidak ditemukan di pesanan ini.', ['issue_id' => 'Tidak ada']);
            }
            if ($issue->status === 'resolved') {
                return;
            }
            if (! $actor->is($issue->opened_by_user_id, $issue->opened_by_app_user_id) && ! $actor->isOwner()) {
                throw new OrderActionNotAllowed('Kendala yang dibuka orang lain hanya bisa diselesaikan Owner/Super Admin.');
            }
            $issue->update([
                'status' => 'resolved', 'resolved_at' => now(), 'resolution_note' => $note === null ? null : Str::limit(trim($note), 497),
                'resolved_by_user_id' => $actor->user?->id, 'resolved_by_app_user_id' => $actor->appUserId,
            ]);
            $this->record('issue_resolved', OnlineOrderIssue::TYPES[$issue->type].' selesai'.($note ? ': '.$note : ''));
        });
    }

    /**
     * ORD-04: Staff Order confirms a website checkout after the WhatsApp chat (stock, ongkir, payment agreed).
     * Only then does the order become active work for payment, packing and the App queue. A Website admin step:
     * opening WhatsApp, the customer or the App never confirms.
     */
    public function confirmWebsiteOrder(OnlineOrder $order, int $revision, OrderActor $actor): OnlineOrder
    {
        if (! $actor->user) {
            throw new OrderActionNotAllowed('Pesanan website dikonfirmasi oleh admin Website.');
        }

        return $this->run($order, $revision, $actor, function (OnlineOrder $order): void {
            if ($order->lifecycle !== 'draft') {
                throw new InvalidOrderTransition('Hanya pesanan website yang menunggu konfirmasi yang bisa dikonfirmasi.');
            }
            $order->lifecycle = 'active';
            $this->record('website_confirmed', 'Pesanan website dikonfirmasi');
        });
    }

    /**
     * Staff Order cancels only orders without any money and not handed over (D4). If money was received, a
     * Super Admin must state how much is returned in the same step (ADR-040); nothing is assumed.
     */
    public function cancel(OnlineOrder $order, int $revision, OrderActor $actor, string $reason, ?string $refundDue = null): OnlineOrder
    {
        if (! $actor->user) {
            throw new OrderActionNotAllowed('Pembatalan hanya dari admin Website.');
        }

        return $this->run($order, $revision, $actor, function (OnlineOrder $order) use ($actor, $reason, $refundDue): void {
            if ($order->lifecycle === 'cancelled') {
                return;
            }
            if ($order->lifecycle === 'completed') {
                throw new InvalidOrderTransition('Pesanan yang sudah selesai tidak dapat dibatalkan.');
            }
            if (! Gate::forUser($actor->user)->allows('orders.cancel', $order)) {
                throw new OrderActionNotAllowed('Pesanan yang sudah ada pembayaran atau sudah diserahkan hanya dapat dibatalkan Super Admin.');
            }
            $reason = trim($reason);
            if (mb_strlen($reason) < 5) {
                throw new OrderValidationFailed('Tuliskan alasan pembatalan.', ['reason' => 'Minimal 5 karakter']);
            }
            $received = OnlineOrderMoney::totals($order)['received'];
            $note = Str::limit($reason, 150);
            if ($received > 0) {
                if (! $actor->isOwner()) {
                    throw new OrderActionNotAllowed('Pesanan dengan pembayaran hanya dapat dibatalkan Super Admin.');
                }
                if ($refundDue === null || ! preg_match('/^\d{1,9}(\.\d{1,2})?$/', $refundDue) || Rupiah::minorUnits($refundDue) > $received) {
                    throw new OrderValidationFailed('Tentukan nominal yang harus dikembalikan (0 sampai '.Rupiah::format(Rupiah::decimal($received)).').', ['refund_due' => 'Wajib']);
                }
                $order->refund_due_amount = Rupiah::decimal(Rupiah::minorUnits($refundDue));
                $order->refund_reason = Str::limit($reason, 297);
                $order->refund_decided_by = $actor->user->id;
                $order->refund_decided_at = now();
                OnlineOrderMoney::apply($order, OnlineOrderMoney::basePaymentFromLedger($order));
                $note .= ' · refund '.Rupiah::format($order->refund_due_amount);
            }
            $order->lifecycle = 'cancelled';
            $order->cancel_reason = Str::limit($reason, 197);
            $order->closed_at = now();
            $this->record('cancelled', $note);
        });
    }

    private function run(OnlineOrder $order, ?int $revision, OrderActor $actor, Closure $change, bool $allowCustomer = false): OnlineOrder
    {
        if (! $actor->canManageOrders() && ! ($allowCustomer && $actor->isCustomer())) {
            throw new OrderActionNotAllowed('Akun ini tidak dapat menangani pesanan.');
        }
        $this->lastEvent = null;
        $this->pendingEvent = null;

        return DB::transaction(function () use ($order, $revision, $actor, $change): OnlineOrder {
            $locked = OnlineOrder::query()->whereKey($order->getKey())->lockForUpdate()->firstOrFail();
            if (! $locked->isV2()) {
                throw new InvalidOrderTransition('Pesanan ini memakai alur lama (ORD-01).');
            }
            if ($revision !== null && $locked->revision !== $revision) {
                throw new OrderRevisionConflict($locked->revision);
            }
            $change($locked);
            if ($this->pendingEvent === null) {
                return $locked;
            }
            if ($locked->lifecycle === 'active' && OnlineOrderV2State::settle($locked)) {
                $this->pendingEvent['note'] = Str::limit($this->pendingEvent['note'].' · pesanan selesai', 197);
            }
            $locked->revision++;
            $locked->save();
            $this->lastEvent = $locked->events()->create($this->pendingEvent + ['stage' => $locked->stage] + $actor->eventAttributes());

            return $locked;
        });
    }

    private function record(string $kind, string $note): void
    {
        $this->pendingEvent = ['kind' => $kind, 'note' => Str::limit($note, 197)];
    }

    /** @return list<array{line_id: string, quantity: int}> in order-line order */
    private function validatePackedItems(OnlineOrder $order, array $packedItems): array
    {
        $ordered = $order->items()->pluck('quantity', 'line_id')->all();
        $given = [];
        $fields = [];
        foreach ($packedItems as $index => $item) {
            $lineId = is_array($item) ? ($item['line_id'] ?? null) : null;
            $quantity = is_array($item) ? ($item['quantity'] ?? null) : null;
            if (! is_string($lineId) || ! array_key_exists($lineId, $ordered)) {
                $fields["packed_items.{$index}.line_id"] = 'Baris tidak ada di pesanan ini';
            } elseif (array_key_exists($lineId, $given)) {
                $fields["packed_items.{$index}.line_id"] = 'Baris disebut dua kali';
            } elseif (! is_int($quantity) || $quantity !== $ordered[$lineId]) {
                $fields["packed_items.{$index}.quantity"] = "Harus {$ordered[$lineId]} sesuai pesanan";
            }
            if (is_string($lineId)) {
                $given[$lineId] = $quantity;
            }
        }
        foreach (array_diff_key($ordered, $given) as $lineId => $quantity) {
            $fields["packed_items.{$lineId}"] = "Baris belum dikonfirmasi ({$quantity})";
        }
        if ($fields) {
            throw new OrderValidationFailed('Jumlah packing harus sama persis dengan pesanan untuk setiap barang.', $fields);
        }

        return array_map(fn ($lineId) => ['line_id' => $lineId, 'quantity' => $ordered[$lineId]], array_keys($ordered));
    }

    private function markHandedOver(OnlineOrder $order, string $handedTo, DateTimeInterface $at): void
    {
        $order->handover_status = 'handed_over';
        $order->handed_to = $handedTo;
        $order->handed_over_at = $at;
    }

    private function assertActive(OnlineOrder $order): void
    {
        if ($order->lifecycle !== 'active') {
            throw new InvalidOrderTransition(match ($order->lifecycle) {
                'cancelled' => 'Pesanan sudah dibatalkan.',
                'completed' => 'Pesanan sudah selesai.',
                default => 'Data customer belum lengkap, pesanan belum bisa diproses.',
            });
        }
    }

    /**
     * Contract r4.2 waiver authorization (Owner decision 2026-10-09). The approver is waiver.by; the request actor and
     * the delivery mode are separate identities. An identical resend of the stored decision is accepted from any App
     * actor (backend sync). A new or changed decision needs a decision_id and must come from that same App Owner, whose
     * ID is on the Website's own allowlist: an Owner name or ID inside the payload alone is never enough.
     *
     * @param  array<string, mixed>|null  $stored
     * @param  array<string, mixed>  $incoming
     */
    private function assertWaiverDecision(?array $stored, array $incoming, OrderActor $actor): void
    {
        $decision = fn (?array $waiver) => $waiver === null ? null : [
            'by' => (string) ($waiver['by']['app_user_id'] ?? ''), 'name' => (string) ($waiver['by']['display_name'] ?? ''),
            'reason' => (string) ($waiver['reason'] ?? ''), 'at' => Carbon::parse((string) ($waiver['at'] ?? 'now'))->utc()->format('Y-m-d\TH:i:s\Z'),
            'decision_id' => $waiver['decision_id'] ?? null,
        ];
        if ($stored !== null && $decision($stored) === $decision($incoming)) {
            return;
        }
        if (($incoming['decision_id'] ?? null) === null) {
            throw new OrderValidationFailed('Keputusan pengecualian bukti yang baru wajib membawa decision_id.', ['reimbursement.waiver.decision_id' => 'Wajib']);
        }
        $approver = (string) ($incoming['by']['app_user_id'] ?? '');
        $authorized = $actor->isApp() && $actor->appRole === 'owner' && $actor->appUserId === $approver
            && in_array($approver, (array) config('orders_api.app_owner_ids'), true);
        if (! $authorized) {
            throw new OrderActionNotAllowed('Pengecualian bukti baru hanya dari Owner App yang terdaftar dan memutuskannya sendiri.',
                ['decision_id' => $incoming['decision_id']]);
        }
    }

    /** App actors must hold the task's claim (contract §7); Website admins are the backup path and are not blocked. */
    private function assertClaim(OnlineOrder $order, OrderActor $actor, string $task): void
    {
        if (! $actor->isApp()) {
            return;
        }
        $claim = $order->claims()->where('task', $task)->lockForUpdate()->first();
        if (! $claim || $claim->holder_app_user_id !== $actor->appUserId) {
            // Details agreed with the App (r4.1 clarification): the task needed and, if someone else holds it, who.
            throw new OrderActionNotAllowed(
                $claim ? "Tugas {$task} sedang dipegang {$claim->holder_display_name}." : "Klaim tugas {$task} dulu sebelum aksi ini.",
                ['task' => $task] + ($claim ? ['holder' => ['app_user_id' => $claim->holder_app_user_id, 'display_name' => $claim->holder_display_name]] : []),
            );
        }
    }

    private function assertKeepActive(OnlineOrder $order): void
    {
        if ($order->keep_status !== 'active') {
            throw new InvalidOrderTransition('Pesanan ini tidak sedang di-keep.');
        }
    }

    private function assertKeepHours(int $hours): void
    {
        if ($hours < 1 || $hours > 168) {
            throw new OrderValidationFailed('Lama keep 1 sampai 168 jam.', ['hours' => '1–168']);
        }
    }

    private function assertPacked(OnlineOrder $order): void
    {
        if ($order->preparation_status !== 'packed') {
            throw new InvalidOrderTransition('Konfirmasi packing dulu sebelum menyerahkan pesanan.');
        }
    }
}
