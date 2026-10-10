<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Orders\OnlineOrderAdjustments;
use App\Actions\Orders\OnlineOrderChangeRequests;
use App\Actions\Orders\OnlineOrderCustomers;
use App\Actions\Orders\OnlineOrderDetails;
use App\Actions\Orders\OnlineOrderFulfillment;
use App\Actions\Orders\RecordOnlineOrderMoney;
use App\Exceptions\OnlineOrderRejected;
use App\Exceptions\OrderRevisionConflict;
use App\Exceptions\OrderValidationFailed;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\OnlineOrderV2DetailsRequest;
use App\Models\Customer;
use App\Models\OnlineOrder;
use App\Models\OnlineOrderIssue;
use App\Support\InquiryWhatsApp;
use App\Support\OnlineOrderMessages;
use App\Support\OnlineOrderMoney;
use App\Support\OnlineOrderState;
use App\Support\OrderActor;
use App\Support\PhoneNumber;
use App\Support\Rupiah;
use App\Support\SearchMatcher;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

/**
 * Admin PWA screens for V2 orders (ORD-02d). Every change goes through the V2 domain operations; the ORD-01
 * workflow is never used for a V2 order. Each form posts the revision it was rendered with.
 */
class AdminOnlineOrderV2Controller extends Controller
{
    public function __construct(
        private readonly OnlineOrderFulfillment $fulfillment,
        private readonly RecordOnlineOrderMoney $money,
        private readonly OnlineOrderDetails $details,
        private readonly OnlineOrderCustomers $customers,
        private readonly OnlineOrderAdjustments $adjustments,
        private readonly OnlineOrderChangeRequests $changeRequests,
    ) {}

    public function show(OnlineOrder $order, OnlineOrderMessages $messages, InquiryWhatsApp $whatsApp)
    {
        $order->load(['items', 'events.actor', 'issues', 'payments.recorder', 'adjustments', 'changeRequests', 'customer.addresses', 'claims']);
        $customerUrl = route('orders.customer.show', $order->customer_token_encrypted);
        $groupMessage = $messages->staffGroupV2($order, route('admin.orders.task', $order->public_id));
        $inviteMessage = $messages->customerInvite($order, $customerUrl);
        $totals = OnlineOrderMoney::totals($order);
        $totalCents = Rupiah::minorUnits($order->customerTotal());
        $openIssues = $order->issues->where('status', 'open')->count();

        return response()->view('admin.orders.v2.show', [
            'order' => $order,
            'queue' => OnlineOrderState::queue($order, $openIssues),
            'flags' => OnlineOrderState::flags($order),
            'openIssues' => $openIssues,
            'received' => $totals['received'],
            'refunded' => $totals['refunded'],
            'totalCents' => $totalCents,
            'remainingCents' => max(0, $totalCents - $totals['received']),
            'overpaidCents' => OnlineOrderAdjustments::overpaidCents($order),
            'customerUrl' => $customerUrl,
            'groupMessage' => $groupMessage,
            'groupShareUrl' => $whatsApp->shareUrl($groupMessage),
            'inviteMessage' => $inviteMessage,
            'inviteUrl' => $order->customer_phone ? $whatsApp->textUrl($order->customer_phone, $inviteMessage) : $whatsApp->shareUrl($inviteMessage),
            'customerChatUrl' => $order->customer_phone ? $whatsApp->textUrl($order->customer_phone, 'Halo Kak, terkait pesanan Qammaris '.$order->code.'.') : null,
            'customerMatches' => $order->customer_id ? collect() : $this->customers->matches($order->customer_phone),
        ])->header('Cache-Control', 'no-store, private')->header('Referrer-Policy', 'no-referrer');
    }

    /** JSON for the create form: repeat customers by name or WhatsApp number, with their saved addresses. */
    public function customerSearch(Request $request): JsonResponse
    {
        $term = SearchMatcher::term($request->query('q'));
        if (mb_strlen($term) < 3) {
            return response()->json(['items' => []]);
        }
        $phone = PhoneNumber::normalize($term);
        $like = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $term).'%';
        $customers = Customer::with('addresses')
            ->where(fn ($query) => $phone ? $query->where('phone', $phone)->orWhere('name', 'like', $like) : $query->where('name', 'like', $like))
            ->orderBy('name')->limit(8)->get();

        return response()->json(['items' => $customers->map(fn (Customer $customer) => [
            'id' => $customer->id, 'name' => $customer->name, 'phone' => '0'.substr($customer->phone, 2),
            'addresses' => $customer->addresses->map(fn ($address) => [
                'id' => $address->id, 'label' => $address->label, 'type' => $address->type, 'address' => $address->address, 'postcode' => $address->postcode,
            ])->values(),
        ])->values()]);
    }

    public function updateDetails(OnlineOrderV2DetailsRequest $request, OnlineOrder $order): RedirectResponse
    {
        return $this->attempt($order, 'detail', fn () => $this->details->updateByAdmin($order, (int) $request->validated('revision'), $request->user(), $request->safe()->except('revision')), 'Detail pesanan disimpan.');
    }

    public function regenerateCustomerLink(Request $request, OnlineOrder $order): RedirectResponse
    {
        return $this->attempt($order, 'link', fn () => $this->details->regenerateCustomerLink($order, $request->user()), 'Link customer baru dibuat (berlaku 7 hari); link lama tidak berlaku.');
    }

    public function recordPayment(Request $request, OnlineOrder $order): RedirectResponse
    {
        $data = $request->validate([
            'revision' => ['required', 'integer'], 'amount' => ['required', 'string', 'max:15'],
            // Not named "method": a field called method shadows form.method in the browser DOM.
            'payment_method' => ['required', Rule::in(array_keys(OnlineOrder::PAYMENT_METHODS))],
            // ORD-03: no visible source choice; Majoo when ticked, otherwise "recorded by admin" in the ledger.
            'confirmation_source' => ['nullable', Rule::in(RecordOnlineOrderMoney::CONFIRMATION_SOURCES)],
            'reference' => ['nullable', 'string', 'max:80'], 'recorded_in_majoo' => ['nullable', 'boolean'],
        ], [], ['amount' => 'nominal', 'payment_method' => 'metode']);
        $data['confirmation_source'] ??= $request->boolean('recorded_in_majoo') ? 'majoo' : RecordOnlineOrderMoney::ADMIN_RECORDED;

        return $this->attempt($order, 'pembayaran', function () use ($request, $order, $data) {
            $updated = $this->money->recordPayment($order, (int) $data['revision'], $request->user(), $this->rupiah($data['amount']), $data['payment_method'], $data['confirmation_source'], $data['reference'] ?? null);
            if ($request->boolean('recorded_in_majoo') && ! $updated->recorded_in_majoo) {
                $this->details->updateByAdmin($updated, $updated->revision, $request->user(), ['recorded_in_majoo' => true]);
            }
        }, 'Pembayaran dicatat.');
    }

    public function startPreparation(Request $request, OnlineOrder $order): RedirectResponse
    {
        return $this->attempt($order, 'packing', fn () => $this->fulfillment->startPreparation($order, $this->revision($request), $this->actor($request)), 'Pesanan ditandai sedang disiapkan.');
    }

    public function pack(Request $request, OnlineOrder $order): RedirectResponse
    {
        $data = $request->validate(['revision' => ['required', 'integer'], 'packed' => ['required', 'array'], 'packed.*' => ['nullable', 'integer', 'min:0', 'max:99']],
            ['packed.required' => 'Centang semua barang yang sudah masuk paket.']);
        $items = collect($data['packed'])->map(fn ($quantity, $lineId) => ['line_id' => (string) $lineId, 'quantity' => (int) $quantity])->values()->all();

        return $this->attempt($order, 'packing', fn () => $this->fulfillment->pack($order, (int) $data['revision'], $this->actor($request), $items), 'Packing dikonfirmasi.');
    }

    public function courierResponsibility(Request $request, OnlineOrder $order): RedirectResponse
    {
        $data = $request->validate(['revision' => ['required', 'integer'], 'responsibility' => ['required', Rule::in(['store', 'customer'])]]);

        return $this->attempt($order, 'pengiriman', fn () => $this->fulfillment->setCourierResponsibility($order, (int) $data['revision'], $this->actor($request), $data['responsibility']), 'Pemesan kurir diperbarui.');
    }

    public function courier(Request $request, OnlineOrder $order): RedirectResponse
    {
        $data = $request->validate(['revision' => ['required', 'integer'], 'provider' => ['required', Rule::in(OnlineOrderFulfillment::COURIER_PROVIDERS)],
            'status' => ['required', Rule::in(['requested', 'arrived'])], 'reference' => ['nullable', 'string', 'max:80']]);

        return $this->attempt($order, 'pengiriman', fn () => $this->fulfillment->requestCourier($order, (int) $data['revision'], $this->actor($request), $data['provider'], $data['status'], $data['reference'] ?? null),
            $data['status'] === 'arrived' ? 'Kurir ditandai sudah tiba.' : 'Kurir ditandai sudah dipesan.');
    }

    public function jnt(Request $request, OnlineOrder $order): RedirectResponse
    {
        $data = $request->validate(['revision' => ['required', 'integer'], 'status' => ['nullable', Rule::in(['pickup_requested', 'qr_available', 'picked_up'])],
            'tracking_number' => ['nullable', 'string', 'max:40']]);

        return $this->attempt($order, 'pengiriman', fn () => $this->fulfillment->recordJnt($order, (int) $data['revision'], $this->actor($request), $data['status'] ?? null, filled($data['tracking_number'] ?? null) ? trim($data['tracking_number']) : null),
            ($data['status'] ?? null) === 'picked_up' ? 'Dipickup J&T — pesanan tercatat diserahkan.' : 'Data J&T disimpan.');
    }

    /** ORD-03: optional J&T QR photo, stored privately; never required for pickup. */
    public function jntQr(Request $request, OnlineOrder $order): RedirectResponse
    {
        $data = $request->validate(['revision' => ['required', 'integer'], 'qr' => ['required', 'file', 'mimetypes:image/png,image/jpeg', 'max:2048']],
            ['qr.required' => 'Pilih foto QR J&T.', 'qr.mimetypes' => 'QR harus foto PNG atau JPG.', 'qr.max' => 'Foto QR maksimal 2 MB.']);
        $file = $request->file('qr');

        return $this->attempt($order, 'pengiriman', fn () => $this->fulfillment->storeJntQr($order, (int) $data['revision'], $this->actor($request),
            (string) $file->get(), $file->getMimeType() === 'image/png' ? 'image/png' : 'image/jpeg'), 'QR J&T disimpan.');
    }

    public function showJntQr(OnlineOrder $order): Response
    {
        abort_unless($order->jnt_qr_path && Storage::disk('local')->exists($order->jnt_qr_path), 404);

        return response(Storage::disk('local')->get($order->jnt_qr_path), 200, [
            'Content-Type' => $order->jnt_qr_mime ?: 'image/png', 'Cache-Control' => 'no-store, private', 'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    /**
     * ORD-03: the shipping fee charged to the customer (and, for finance roles, driver funding). Who may change it
     * and when is decided in OnlineOrderDetails::updateByAdmin (orders.charge-shipping / orders.finance).
     */
    public function shipping(Request $request, OnlineOrder $order): RedirectResponse
    {
        $data = $request->validate(['revision' => ['required', 'integer'], 'shipping_fee' => ['nullable', 'string', 'max:15'],
            'shipping_payer' => ['nullable', Rule::in(array_keys(OnlineOrder::SHIPPING_PAYERS))],
            'driver_funding' => ['nullable', Rule::in(array_keys(OnlineOrder::DRIVER_FUNDING))]], [], ['shipping_fee' => 'ongkir', 'shipping_payer' => 'ongkir dibayar']);

        return $this->attempt($order, 'pembayaran', function () use ($request, $order, $data) {
            $changes = ['shipping_fee' => filled($data['shipping_fee'] ?? null) ? $this->rupiah($data['shipping_fee'], true) : null,
                'shipping_payer' => $data['shipping_payer'] ?? null];
            if ($request->has('driver_funding')) {
                $changes['driver_funding'] = $data['driver_funding'] ?? null;
            }
            $this->details->updateByAdmin($order, (int) $data['revision'], $request->user(), $changes);
        }, 'Ongkir disimpan.');
    }

    public function handover(Request $request, OnlineOrder $order): RedirectResponse
    {
        $data = $request->validate(['revision' => ['required', 'integer'], 'handed_to' => ['required', Rule::in(['customer', 'courier', 'customer_courier'])]]);

        return $this->attempt($order, 'pengiriman', fn () => $this->fulfillment->handover($order, (int) $data['revision'], $this->actor($request), $data['handed_to']), 'Pesanan ditandai sudah diserahkan.');
    }

    public function delivery(Request $request, OnlineOrder $order): RedirectResponse
    {
        return $this->attempt($order, 'pengiriman', fn () => $this->fulfillment->confirmDelivery($order, $this->revision($request), $this->actor($request)), 'Ditandai sudah diterima customer.');
    }

    public function openIssue(Request $request, OnlineOrder $order): RedirectResponse
    {
        $data = $request->validate(['type' => ['required', Rule::in(array_keys(OnlineOrderIssue::TYPES))], 'note' => ['required', 'string', 'max:500'],
            'line_id' => ['nullable', 'string', 'size:26'], 'reported_quantity' => ['nullable', 'integer', 'min:0', 'max:99']],
            ['note.required' => 'Tulis kendalanya.'], ['type' => 'jenis kendala']);

        return $this->attempt($order, 'kendala', fn () => $this->fulfillment->openIssue($order, null, $this->actor($request), $data['type'], $data['note'], $data['line_id'] ?? null, isset($data['reported_quantity']) ? (int) $data['reported_quantity'] : null), 'Kendala dicatat.');
    }

    public function resolveIssue(Request $request, OnlineOrder $order, string $issue): RedirectResponse
    {
        $data = $request->validate(['note' => ['nullable', 'string', 'max:500']]);

        return $this->attempt($order, 'kendala', fn () => $this->fulfillment->resolveIssue($order, $issue, null, $this->actor($request), $data['note'] ?? null), 'Kendala ditandai selesai.');
    }

    /** Super Admin frees a task held in Qammaris App (holder away, App down); the reason is kept on the event. */
    public function releaseClaim(Request $request, OnlineOrder $order, string $task): RedirectResponse
    {
        $data = $request->validate(['revision' => ['required', 'integer'], 'reason' => ['required', 'string', 'min:3', 'max:200']],
            ['reason.required' => 'Tulis alasan melepas klaim.'], ['reason' => 'alasan']);

        return $this->attempt($order, 'klaim', fn () => $this->fulfillment->releaseClaim($order, (int) $data['revision'], $this->actor($request), $task, $data['reason']), 'Klaim dilepas.');
    }

    public function keep(Request $request, OnlineOrder $order, string $action): RedirectResponse
    {
        $data = $request->validate(['revision' => ['required', 'integer'], 'hours' => ['nullable', 'integer', 'min:1', 'max:168'], 'reason' => ['nullable', 'string', 'max:200']]);
        $revision = (int) $data['revision'];
        $actor = $this->actor($request);

        return $this->attempt($order, 'keep', fn () => match ($action) {
            'start' => $this->fulfillment->startKeep($order, $revision, $actor, (int) ($data['hours'] ?? 24)),
            'stock' => $this->fulfillment->confirmKeepStock($order, $revision, $actor),
            'extend' => $this->fulfillment->extendKeep($order, $revision, $actor, (int) ($data['hours'] ?? 24)),
            'release' => $this->fulfillment->releaseKeep($order, $revision, $actor, (string) ($data['reason'] ?? '')),
        }, match ($action) {
            'start' => 'Keep dimulai.', 'stock' => 'Stok ditandai sudah dipisahkan.', 'extend' => 'Keep diperpanjang.', 'release' => 'Keep dilepas.',
        });
    }

    public function cancel(Request $request, OnlineOrder $order): RedirectResponse
    {
        $data = $request->validate(['revision' => ['required', 'integer'], 'reason' => ['required', 'string', 'max:200'], 'refund_due' => ['nullable', 'string', 'max:15']],
            ['reason.required' => 'Tulis alasan pembatalan.']);

        return $this->attempt($order, 'batal', fn () => $this->fulfillment->cancel($order, (int) $data['revision'], $this->actor($request), $data['reason'], filled($data['refund_due'] ?? null) ? $this->rupiah($data['refund_due']) : null), 'Pesanan dibatalkan.');
    }

    public function linkCustomer(Request $request, OnlineOrder $order): RedirectResponse
    {
        $data = $request->validate(['revision' => ['required', 'integer'], 'customer_id' => ['nullable', 'integer']]);

        return $this->attempt($order, 'pelanggan', fn () => $this->customers->link($order, (int) $data['revision'], $request->user(), isset($data['customer_id']) ? (int) $data['customer_id'] : null), 'Pesanan dihubungkan ke pelanggan.');
    }

    public function saveAddress(Request $request, OnlineOrder $order): RedirectResponse
    {
        $data = $request->validate(['revision' => ['required', 'integer'], 'label' => ['required', 'string', 'max:40']], ['label.required' => 'Beri nama alamat, misalnya Rumah.']);

        return $this->attempt($order, 'pelanggan', fn () => $this->customers->saveAddress($order, (int) $data['revision'], $request->user(), $data['label']), 'Alamat disimpan untuk pesanan berikutnya.');
    }

    public function useAddress(Request $request, OnlineOrder $order): RedirectResponse
    {
        $data = $request->validate(['revision' => ['required', 'integer'], 'customer_address_id' => ['required', 'integer']]);

        return $this->attempt($order, 'pelanggan', fn () => $this->customers->useAddress($order, (int) $data['revision'], $request->user(), (int) $data['customer_address_id']), 'Alamat tersimpan dipakai untuk pesanan ini.');
    }

    public function requestAdjustment(Request $request, OnlineOrder $order): RedirectResponse
    {
        $data = $request->validate(['revision' => ['required', 'integer'], 'direction' => ['required', Rule::in(['discount', 'surcharge'])], 'amount' => ['required', 'string', 'max:15'], 'reason' => ['required', 'string', 'max:300']],
            ['reason.required' => 'Tulis alasan penyesuaian.'], ['amount' => 'nominal']);

        return $this->attempt($order, 'harga', fn () => $this->adjustments->request($order, (int) $data['revision'], $request->user(),
            ($data['direction'] === 'discount' ? '-' : '').$this->rupiah($data['amount']), $data['reason']), 'Penyesuaian harga diajukan. Menunggu persetujuan Super Admin.');
    }

    public function decideAdjustment(Request $request, OnlineOrder $order, int $adjustment, string $decision): RedirectResponse
    {
        $data = $request->validate(['revision' => ['required', 'integer'], 'note' => ['nullable', 'string', 'max:300']]);

        return $this->attempt($order, 'harga', fn () => $decision === 'approve'
            ? $this->adjustments->approve($order, (int) $data['revision'], $request->user(), $adjustment, $data['note'] ?? null)
            : $this->adjustments->reject($order, (int) $data['revision'], $request->user(), $adjustment, (string) ($data['note'] ?? '')),
            $decision === 'approve' ? 'Penyesuaian harga disetujui.' : 'Penyesuaian harga ditolak.');
    }

    public function decideChange(Request $request, OnlineOrder $order, int $changeRequest, string $decision): RedirectResponse
    {
        $data = $request->validate(['revision' => ['required', 'integer'], 'note' => ['nullable', 'string', 'max:300']]);

        return $this->attempt($order, 'perubahan', fn () => $decision === 'approve'
            ? $this->changeRequests->approve($order, (int) $data['revision'], $request->user(), $changeRequest, $data['note'] ?? null)
            : $this->changeRequests->reject($order, (int) $data['revision'], $request->user(), $changeRequest, (string) ($data['note'] ?? '')),
            $decision === 'approve' ? 'Perubahan diterapkan ke pesanan.' : 'Permintaan perubahan ditolak.');
    }

    /** Super Admin money panel (also used for legacy orders that need reconciliation). */
    public function decideRefund(Request $request, OnlineOrder $order): RedirectResponse
    {
        $data = $request->validate(['revision' => ['required', 'integer'], 'refund_due' => ['required', 'string', 'max:15'], 'reason' => ['required', 'string', 'max:300']]);

        return $this->attempt($order, 'keuangan', fn () => $this->money->decideRefund($order, (int) $data['revision'], $request->user(), $this->rupiah($data['refund_due'], true), $data['reason']), 'Keputusan refund disimpan.');
    }

    public function recordRefund(Request $request, OnlineOrder $order): RedirectResponse
    {
        $data = $request->validate(['revision' => ['required', 'integer'], 'amount' => ['required', 'string', 'max:15'], 'refund_method' => ['required', Rule::in(array_keys(OnlineOrder::PAYMENT_METHODS))], 'reference' => ['nullable', 'string', 'max:80']]);

        return $this->attempt($order, 'keuangan', fn () => $this->money->recordRefund($order, (int) $data['revision'], $request->user(), $this->rupiah($data['amount']), $data['refund_method'], $data['reference'] ?? null), 'Pengembalian dana dicatat.');
    }

    public function reverseEntry(Request $request, OnlineOrder $order, int $entry): RedirectResponse
    {
        $data = $request->validate(['revision' => ['required', 'integer'], 'reason' => ['required', 'string', 'max:300']], ['reason.required' => 'Tulis alasan pembatalan entri.']);

        return $this->attempt($order, 'keuangan', fn () => $this->money->reverse($order, (int) $data['revision'], $request->user(), $entry, $data['reason']), 'Entri dibatalkan. Entri asli tetap terlihat di riwayat.');
    }

    public function reconcile(Request $request, OnlineOrder $order): RedirectResponse
    {
        $data = $request->validate(['revision' => ['required', 'integer'], 'received' => ['required', 'string', 'max:15'], 'refund_due' => ['required', 'string', 'max:15'],
            'already_refunded' => ['required', 'string', 'max:15'], 'note' => ['required', 'string', 'max:300']]);

        return $this->attempt($order, 'keuangan', fn () => $this->money->reconcile($order, (int) $data['revision'], $request->user(), $this->rupiah($data['received']),
            $this->rupiah($data['refund_due'], true), $this->rupiah($data['already_refunded'], true), $data['note']), 'Rekonsiliasi disimpan.');
    }

    private function attempt(OnlineOrder $order, string $section, Closure $change, string $success): RedirectResponse
    {
        // Shown inside the section the page jumps back to, so the result is visible on a phone without scrolling.
        $target = route('admin.orders.show', $order).'#'.$section;
        $notice = fn (string $type, string $message) => ['section' => $section, 'type' => $type, 'message' => $message];
        try {
            $change();
        } catch (OrderRevisionConflict) {
            return redirect()->to($target)->with('order_notice', $notice('conflict', 'Pesanan ini baru saja diubah oleh orang lain atau di tab lain. Data terbaru sudah ditampilkan — periksa dulu, lalu ulangi bila masih perlu.'));
        } catch (OrderValidationFailed $invalid) {
            return redirect()->to($target)->withInput()->withErrors($invalid->fields ?: ['form' => $invalid->getMessage()])->with('order_notice', $notice('error', $invalid->getMessage()));
        } catch (OnlineOrderRejected $rejected) {
            return redirect()->to($target)->withInput()->with('order_notice', $notice('error', $rejected->getMessage()));
        }

        return redirect()->to($target)->with('order_notice', $notice('success', $success));
    }

    private function actor(Request $request): OrderActor
    {
        return OrderActor::user($request->user());
    }

    private function revision(Request $request): int
    {
        return (int) $request->validate(['revision' => ['required', 'integer']])['revision'];
    }

    /** Accepts 150000, 150.000 or Rp 150.000 from staff; returns a plain decimal string or throws a field error. */
    private function rupiah(string $value, bool $allowZero = false): string
    {
        $clean = preg_replace('/^rp\s*/i', '', trim($value));
        $clean = preg_match('/^\d{1,3}(\.\d{3})+$/', $clean) ? str_replace('.', '', $clean) : $clean;
        if (! preg_match('/^\d{1,9}$/', $clean) || (! $allowZero && (int) $clean === 0)) {
            throw new OrderValidationFailed('Nominal harus rupiah bulat, misalnya 150000 atau 150.000.', ['amount' => 'Nominal tidak valid']);
        }

        return $clean;
    }
}
