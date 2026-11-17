<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Orders\BiteshipCourier;
use App\Actions\Orders\ChangeOnlineOrder;
use App\Actions\Orders\OnlineOrderAdjustments;
use App\Actions\Orders\OnlineOrderChangeRequests;
use App\Actions\Orders\OnlineOrderCustomers;
use App\Actions\Orders\OnlineOrderDetails;
use App\Actions\Orders\OnlineOrderFulfillment;
use App\Actions\Orders\RecordOnlineOrderMoney;
use App\Actions\Orders\ShippingQuotes;
use App\Exceptions\InvalidOrderTransition;
use App\Exceptions\OnlineOrderRejected;
use App\Exceptions\OrderRevisionConflict;
use App\Exceptions\OrderValidationFailed;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ChangeOrderRequest;
use App\Http\Requests\Admin\OnlineOrderV2DetailsRequest;
use App\Models\Customer;
use App\Models\OnlineOrder;
use App\Models\OnlineOrderIssue;
use App\Models\ProductVariant;
use App\Models\StorePaymentAccount;
use App\Support\InquiryWhatsApp;
use App\Support\OnlineOrderMessages;
use App\Support\OnlineOrderMoney;
use App\Support\OnlineOrderState;
use App\Support\OnlineOrderStepper;
use App\Support\OrderActor;
use App\Support\OrderApi\AppEmployees;
use App\Support\OrderTaskLink;
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
use RuntimeException;

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
        $order->load(['items.product.primaryImage', 'events.actor', 'issues', 'payments.recorder', 'adjustments', 'changeRequests', 'customer.addresses', 'claims', 'costs']);
        $customerUrl = route('orders.customer.show', $order->customer_token_encrypted);
        $taskLink = OrderTaskLink::for($order);
        $groupMessage = $messages->staffGroupV2($order, $taskLink['url'], $taskLink['in_app']);
        $inviteMessage = $messages->customerInvite($order, $customerUrl);
        $totals = OnlineOrderMoney::totals($order);
        $totalCents = Rupiah::minorUnits($order->customerTotal());
        $openIssues = $order->issues->where('status', 'open')->count();
        $paymentText = $messages->paymentInstructions($order);
        // ORD-08: "Uang siapa" depends on who is booking (Owner: GoPay/kasir/customer; Staff Order: App employees).
        $viewer = OrderActor::user(auth()->user());
        $courierCost = $order->costs->first(fn ($cost) => $cost->isWebsiteOrigin());
        $shipped = in_array($order->fulfillment, ['local_delivery', 'intercity'], true);
        // Fresh from the App on every page view, so renamed or new employees show at once (cached list = fallback).
        $employees = $shipped ? AppEmployees::list(refresh: true) : ['employees' => [], 'stale' => false];

        return response()->view('admin.orders.v2.show', [
            // ORD-04 stepper: derived from backend state only; the domain refuses steps out of order.
            'steps' => OnlineOrderStepper::steps($order),
            'currentStep' => OnlineOrderStepper::current($order),
            'totalFinal' => OnlineOrderStepper::totalIsFinal($order),
            'paymentText' => $paymentText,
            'paymentChatUrl' => $order->customer_phone ? $whatsApp->textUrl($order->customer_phone, $paymentText) : null,
            'hasPaymentAccount' => StorePaymentAccount::query()->usable()->exists(),
            'order' => $order,
            'queue' => OnlineOrderState::queue($order, $openIssues),
            'flags' => OnlineOrderState::flags($order),
            'openIssues' => $openIssues,
            'received' => $totals['received'],
            'refunded' => $totals['refunded'],
            'totalCents' => $totalCents,
            // ORD-27: what the customer still holds paid (an overpayment returned after a change is netted out).
            'remainingCents' => max(0, $totalCents - ($totals['received'] - $totals['refunded'])),
            'overpaidCents' => OnlineOrderAdjustments::overpaidCents($order),
            'customerUrl' => $customerUrl,
            'groupMessage' => $groupMessage,
            'groupShareUrl' => $whatsApp->shareUrl($groupMessage),
            'inviteMessage' => $inviteMessage,
            'inviteUrl' => $order->customer_phone ? $whatsApp->textUrl($order->customer_phone, $inviteMessage) : $whatsApp->shareUrl($inviteMessage),
            'customerChatUrl' => $order->customer_phone ? $whatsApp->textUrl($order->customer_phone, 'Halo Kak, terkait pesanan Qammaris '.$order->code.'.') : null,
            'customerMatches' => $order->customer_id ? collect() : $this->customers->matches($order->customer_phone),
            'courierCost' => $courierCost,
            'courierCostPayers' => $shipped ? OnlineOrderFulfillment::courierCostPayers($viewer, $courierCost) : [],
            'courierCostByOwner' => $viewer->isOwner(),
            'employeesStale' => $shipped && ! $viewer->isOwner() && $employees['stale'],
            'biteshipEnabled' => app(BiteshipCourier::class)->enabled(),
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
        $customers = Customer::with('addresses')->whereNull('anonymized_at')
            ->where(fn ($query) => $phone ? $query->where('phone', $phone)->orWhere('name', 'like', $like) : $query->where('name', 'like', $like))
            ->orderBy('name')->limit(8)->get();

        return response()->json(['items' => $customers->map(fn (Customer $customer) => [
            'id' => $customer->id, 'name' => $customer->name, 'phone' => PhoneNumber::local($customer->phone),
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
            'reference' => ['nullable', 'string', 'max:80'],
            // ADM-03 (Owner 2026-10-21): a payment is entered in the cashier (Majoo) before it is marked here.
            'recorded_in_majoo' => ['accepted'],
        ], ['recorded_in_majoo.accepted' => 'Catat dulu pembayaran ini di kasir (Majoo), lalu tandai lunas.'], ['amount' => 'nominal', 'payment_method' => 'metode']);
        $data['confirmation_source'] ??= 'majoo';

        return $this->attempt($order, 'pembayaran', function () use ($request, $order, $data) {
            $updated = $this->money->recordPayment($order, (int) $data['revision'], $request->user(), $this->rupiah($data['amount']), $data['payment_method'], $data['confirmation_source'], $data['reference'] ?? null);
            if ($request->boolean('recorded_in_majoo') && ! $updated->recorded_in_majoo) {
                $this->details->updateByAdmin($updated, $updated->revision, $request->user(), ['recorded_in_majoo' => true]);
            }
        }, 'Pembayaran dicatat.');
    }

    /** ORD-04: Staff Order accepts a website checkout after the WhatsApp chat. */
    public function confirmWebsite(Request $request, OnlineOrder $order): RedirectResponse
    {
        return $this->attempt($order, 'konfirmasi', fn () => $this->fulfillment->confirmWebsiteOrder($order, $this->revision($request), $this->actor($request)), 'Pesanan dikonfirmasi. Lanjutkan ke pembayaran.');
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
            'status' => ['required', Rule::in(['requested', 'arrived'])], 'reference' => ['nullable', 'string', 'max:80']] + self::COST_RULES);

        // ORD-08: booking the courier on the Website also records what it costs and whose money pays it.
        return $this->attempt($order, 'pengiriman', fn () => $this->fulfillment->requestCourier($order, (int) $data['revision'], $this->actor($request), $data['provider'], $data['status'], $data['reference'] ?? null,
            $data['status'] === 'requested' ? $this->courierCost($data) : null),
            $data['status'] === 'arrived' ? 'Kurir ditandai sudah tiba.' : 'Kurir ditandai sudah dipesan. Ongkir kurir dicatat.');
    }

    public function jnt(Request $request, OnlineOrder $order): RedirectResponse
    {
        $data = $request->validate(['revision' => ['required', 'integer'], 'status' => ['nullable', Rule::in(['pickup_requested', 'qr_available', 'picked_up'])],
            'tracking_number' => ['nullable', 'string', 'max:40']] + self::COST_RULES);
        $requestingPickup = ($data['status'] ?? null) === 'pickup_requested' && $order->jnt_status !== 'pickup_requested';

        return $this->attempt($order, 'pengiriman', fn () => $this->fulfillment->recordJnt($order, (int) $data['revision'], $this->actor($request), $data['status'] ?? null, filled($data['tracking_number'] ?? null) ? trim($data['tracking_number']) : null,
            null, $requestingPickup ? $this->courierCost($data) : null),
            ($data['status'] ?? null) === 'picked_up' ? 'Dipickup J&T — pesanan tercatat diserahkan.' : 'Data J&T disimpan.');
    }

    /** ORD-08: correct the courier fee or whose money paid it after the booking. */
    public function updateCourierCost(Request $request, OnlineOrder $order): RedirectResponse
    {
        $data = $request->validate(['revision' => ['required', 'integer']] + self::COST_RULES);

        $cost = $this->courierCost($data);

        return $this->attempt($order, 'pengiriman', fn () => $this->fulfillment->recordCourierCost($order, (int) $data['revision'], $this->actor($request), $cost['amount'], $cost['payer']),
            'Ongkir kurir disimpan.');
    }

    /** ORD-14: book the instant courier through Biteship with the customer's checkout pin. */
    public function bookBiteship(Request $request, OnlineOrder $order, BiteshipCourier $biteship): RedirectResponse
    {
        $data = $request->validate(['revision' => ['required', 'integer'], 'courier' => ['required', Rule::in(['gojek', 'grab'])], 'checked_biteship' => ['nullable', 'boolean']]);

        return $this->attempt($order, 'pengiriman', fn () => $biteship->book($order, (int) $data['revision'], $request->user(), $data['courier'], (bool) ($data['checked_biteship'] ?? false)),
            'Kurir dipesan lewat Biteship. Status driver berubah otomatis.');
    }

    public function refreshBiteship(Request $request, OnlineOrder $order, BiteshipCourier $biteship): RedirectResponse
    {
        return $this->attempt($order, 'pengiriman', function () use ($order, $biteship) {
            try {
                $biteship->refresh($order);
            } catch (\RuntimeException $failure) {
                throw new InvalidOrderTransition('Status belum bisa diperbarui: '.BiteshipCourier::reason($failure->getMessage()));
            }
        }, 'Status kurir diperbarui dari Biteship.');
    }

    public function cancelBiteship(Request $request, OnlineOrder $order, BiteshipCourier $biteship): RedirectResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'max:200']], ['reason.required' => 'Tulis alasan pembatalan kurir.']);

        return $this->attempt($order, 'pengiriman', fn () => $biteship->cancel($order, $request->user(), $data['reason']),
            'Kurir Biteship dibatalkan. Pesan ulang bila perlu.');
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
     * ORD-03: the shipping fee charged to the customer. Who may change it and when is decided in
     * OnlineOrderDetails::updateByAdmin (orders.charge-shipping / orders.finance). What the courier is paid, and
     * whose money, is recorded with the courier booking (ORD-08).
     */
    public function shipping(Request $request, OnlineOrder $order): RedirectResponse
    {
        $data = $request->validate(['revision' => ['required', 'integer'], 'shipping_fee' => ['nullable', 'string', 'max:15'],
            'shipping_payer' => ['nullable', Rule::in(array_keys(OnlineOrder::SHIPPING_PAYERS))]], [], ['shipping_fee' => 'ongkir', 'shipping_payer' => 'ongkir dibayar']);

        return $this->attempt($order, 'pembayaran', function () use ($request, $order, $data) {
            $this->details->updateByAdmin($order, (int) $data['revision'], $request->user(), [
                'shipping_fee' => filled($data['shipping_fee'] ?? null) ? $this->rupiah($data['shipping_fee'], true) : null,
                'shipping_payer' => $data['shipping_payer'] ?? null,
            ]);
        }, 'Ongkir disimpan.');
    }

    /** ORD-26 (Owner 2026-11-16, A): Biteship prices for an order staff filled: instant to the pin, J&T to the postcode. */
    public function priceShipping(Request $request, OnlineOrder $order, ShippingQuotes $quotes): RedirectResponse
    {
        return $this->attempt($order, $this->ongkirSection($request), function () use ($request, $order, $quotes) {
            if (! $order->staffPricesShipping() || ! $request->user()->can('orders.charge-shipping', $order)) {
                throw new InvalidOrderTransition('Ongkir pesanan ini tidak bisa dihitung otomatis (sudah ada pembayaran, kurir dipesan, atau titik/kode pos belum ada).');
            }
            $scoped = $quotes->forOrder($order);
            $items = ShippingQuotes::orderItems($order);
            $cart = ShippingQuotes::orderCart($order);
            try {
                $pin = $order->deliveryPin();
                $options = $order->fulfillment === 'local_delivery'
                    ? $scoped->instant($items, $cart, $pin['latitude'], $pin['longitude'])
                    : $scoped->jntToPostcode($items, $cart, (string) $order->postcode);
            } catch (RuntimeException) {
                throw new InvalidOrderTransition('Biteship belum menjawab. Coba lagi sebentar, atau isi ongkir manual.');
            }
            if ($options === []) {
                throw new InvalidOrderTransition('Tidak ada kurir yang menjangkau tujuan ini. Isi ongkir manual.');
            }
            session()->flash('staff_shipping_quotes', ['order' => $order->id, 'options' => array_map([ShippingQuotes::class, 'public'], $options)]);
        }, 'Pilih ongkir di bawah.');
    }

    /** ORD-26 (A): the option staff chose becomes the ongkir; the price is the server's own, never the browser's. */
    public function useShippingQuote(Request $request, OnlineOrder $order, ShippingQuotes $quotes): RedirectResponse
    {
        $data = $request->validate(['revision' => ['required', 'integer'], 'option' => ['required', 'string', 'regex:/^[A-Za-z0-9]{20}$/']]);

        return $this->attempt($order, $this->ongkirSection($request), function () use ($request, $order, $quotes, $data) {
            $quote = $quotes->forOrder($order)->resolve($data['option'], ShippingQuotes::orderCart($order), (string) $order->fulfillment);
            if ($quote === null) {
                throw new InvalidOrderTransition('Harga ongkir ini sudah lewat 30 menit atau pesanan berubah. Hitung lagi.');
            }
            $this->details->priceByAdmin($order, (int) $data['revision'], $request->user(), $quote);
        }, 'Ongkir disimpan dari harga Biteship.');
    }

    /** ORD-27 "Ubah pesanan": products and delivery on one page, with what the change does to the total. */
    public function edit(OnlineOrder $order)
    {
        abort_unless($order->isV2(), 404);
        $order->load(['items.product.primaryImage', 'adjustments']);
        $totals = OnlineOrderMoney::totals($order);

        return response()->view('admin.orders.v2.edit', [
            'order' => $order,
            'changeable' => ChangeOnlineOrder::changeable($order),
            'paidCents' => $totals['received'] - $totals['refunded'],
            'totalCents' => Rupiah::minorUnits($order->customerTotal()),
            'pricesShipping' => ShippingQuotes::pricesOrders(),
        ])->header('Cache-Control', 'no-store, private');
    }

    /** ORD-27: Biteship prices for the edited products and destination, kept for this order only. */
    public function quoteChange(Request $request, OnlineOrder $order, ShippingQuotes $quotes): JsonResponse
    {
        $data = $request->validate([
            'items' => ['required', 'array', 'min:1', 'max:30'],
            'items.*.line_id' => ['nullable', 'string', 'regex:/^[0-9A-HJKMNP-TV-Z]{26}$/'],
            'items.*.variant_id' => ['nullable', 'integer', 'min:1'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:99'],
            'mode' => ['required', 'in:instant,jnt'],
            'latitude' => ['exclude_unless:mode,instant', 'required', 'numeric', 'between:-90,90'],
            'longitude' => ['exclude_unless:mode,instant', 'required', 'numeric', 'between:-180,180'],
            'postcode' => ['exclude_unless:mode,jnt', 'required', 'regex:/^[0-9]{5}$/'],
        ]);
        abort_unless($order->isV2() && ShippingQuotes::pricesOrders(), 409);
        $lines = array_values(array_map(fn ($item) => [
            'line_id' => ($item['line_id'] ?? null) ?: null,
            'variant_id' => isset($item['variant_id']) ? (int) $item['variant_id'] : null,
            'quantity' => (int) $item['quantity'],
        ], $data['items']));
        $kept = $order->items()->get()->keyBy('line_id');
        $variants = ProductVariant::with('product')->whereIn('id', array_filter(array_column($lines, 'variant_id')))->get()->keyBy('id');
        $items = [];
        foreach ($lines as $line) {
            $source = $line['line_id'] ? $kept->get($line['line_id']) : $variants->get($line['variant_id']);
            if (! $source) {
                return response()->json(['options' => [], 'reason' => 'cart'], 409);
            }
            $items[] = [
                'product_name' => $line['line_id'] ? $source->product_name : $source->product->name,
                'price' => (string) ($line['line_id'] ? $source->unit_price : $source->price),
                'quantity' => $line['quantity'],
            ];
        }
        $cart = ChangeOrderRequest::cartOf($lines);
        $scoped = $quotes->forOrder($order);
        if ($data['mode'] === 'instant') {
            return response()->json($scoped->answer(['mode' => 'instant', 'latitude' => $data['latitude'], 'longitude' => $data['longitude']], $items, $cart));
        }
        try {
            $options = $scoped->jntToPostcode($items, $cart, $data['postcode']);
        } catch (RuntimeException) {
            return response()->json(['options' => [], 'reason' => 'unavailable']);
        }

        return response()->json(['options' => array_map([ShippingQuotes::class, 'public'], $options), 'reason' => $options ? null : 'no_courier']);
    }

    public function change(ChangeOrderRequest $request, OnlineOrder $order, ChangeOnlineOrder $change, OnlineOrderMessages $messages, InquiryWhatsApp $whatsApp): RedirectResponse
    {
        $data = $request->validated();
        try {
            $changed = $change->handle($order, (int) $data['revision'], $request->user(), $request->lines(), $request->shipping(), $data['note'] ?? null);
        } catch (OrderRevisionConflict) {
            return redirect()->route('admin.orders.v2.edit', $order)
                ->with('change_error', 'Pesanan ini baru saja diubah orang lain atau di tab lain. Data terbaru sudah ditampilkan, periksa lalu ulangi.');
        } catch (OrderValidationFailed|InvalidOrderTransition|OnlineOrderRejected $error) {
            return redirect()->route('admin.orders.v2.edit', $order)->withInput()->with('change_error', $error->getMessage());
        }
        if ($changed->revision === $order->revision) {
            return redirect()->route('admin.orders.show', $order)->with('order_notice', ['section' => 'ringkasan', 'type' => 'success', 'message' => 'Tidak ada yang berubah.']);
        }
        $message = $messages->orderChanged($changed->load('items'), route('orders.customer.show', $changed->customer_token_encrypted));

        return redirect()->to(route('admin.orders.show', $order).'#ringkasan')->with('order_change', [
            'message' => $message,
            'chat_url' => $changed->customer_phone ? $whatsApp->textUrl($changed->customer_phone, $message) : $whatsApp->shareUrl($message),
        ]);
    }

    /** ORD-27: the store returned what the customer paid above the changed total. */
    public function returnOverpayment(Request $request, OnlineOrder $order): RedirectResponse
    {
        $data = $request->validate([
            'revision' => ['required', 'integer'],
            'refund_method' => ['required', Rule::in(array_keys(OnlineOrder::PAYMENT_METHODS))],
            'reference' => ['nullable', 'string', 'max:80'],
        ]);

        return $this->attempt($order, 'kelebihan', fn () => $this->money->returnOverpayment($order, (int) $data['revision'], $request->user(), $data['refund_method'], $data['reference'] ?? null),
            'Pengembalian kelebihan bayar dicatat.');
    }

    private function ongkirSection(Request $request): string
    {
        return $request->input('_section') === 'konfirmasi' ? 'konfirmasi' : 'pembayaran';
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
        // ADM-04: "Poin member" is a discount whose reason is always the member points, so none is typed.
        $data = $request->validate(['revision' => ['required', 'integer'], 'direction' => ['required', Rule::in(['discount', 'member_points', 'surcharge'])], 'amount' => ['required', 'string', 'max:15'],
            'reason' => ['exclude_if:direction,member_points', 'required', 'string', 'max:300']],
            ['reason.required' => 'Tulis alasan penyesuaian.'], ['amount' => 'nominal']);
        $reason = $data['direction'] === 'member_points' ? 'Poin member' : $data['reason'];

        return $this->attempt($order, 'harga', fn () => $this->adjustments->request($order, (int) $data['revision'], $request->user(),
            ($data['direction'] === 'surcharge' ? '' : '-').$this->rupiah($data['amount']), $reason), 'Penyesuaian harga diterapkan. Total pesanan sudah diperbarui.');
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

    private const COST_RULES = ['cost_amount' => ['nullable', 'string', 'max:15'], 'cost_payer' => ['nullable', 'string', 'max:70']];

    /**
     * Raw "ongkir ke kurir" and "uang siapa"; an empty or malformed amount becomes 0 so the domain operation reports it
     * after its own state checks (unpaid, wrong step).
     *
     * @return array{amount: int, payer: string}
     */
    private function courierCost(array $data): array
    {
        $amount = preg_replace('/^rp\s*/i', '', trim((string) ($data['cost_amount'] ?? '')));
        $amount = preg_match('/^\d{1,3}(\.\d{3})+$/', $amount) ? str_replace('.', '', $amount) : $amount;

        return ['amount' => preg_match('/^\d{1,9}$/', $amount) ? (int) $amount : 0, 'payer' => (string) ($data['cost_payer'] ?? '')];
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
