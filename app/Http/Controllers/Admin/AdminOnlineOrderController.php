<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Orders\CreateOnlineOrder;
use App\Actions\Orders\OnlineOrderWorkflow;
use App\Exceptions\OnlineOrderRejected;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\OnlineOrderStoreRequest;
use App\Http\Requests\Admin\OnlineOrderUpdateRequest;
use App\Models\OnlineOrder;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Support\CatalogAvailability;
use App\Support\InquiryWhatsApp;
use App\Support\OnlineOrderMessages;
use App\Support\OnlineOrderTimeline;
use App\Support\Rupiah;
use App\Support\SearchMatcher;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AdminOnlineOrderController extends Controller
{
    public const FILTERS = [
        'active' => 'Aktif',
        'awaiting_customer' => 'Menunggu customer',
        'needs_payment' => 'Perlu dibayar',
        'needs_shipping' => 'Perlu disiapkan/dikirim',
        'in_transit' => 'Dalam pengiriman',
        'completed' => 'Selesai',
        'cancelled' => 'Dibatalkan',
        'reimburse' => 'Talangan belum diganti',
        'all' => 'Semua',
    ];

    public function __construct(private readonly OnlineOrderWorkflow $workflow) {}

    public function index(Request $request)
    {
        $filter = array_key_exists((string) $request->query('status'), self::FILTERS) ? (string) $request->query('status') : 'active';
        $search = SearchMatcher::term($request->query('search'));
        $query = OnlineOrder::query()->with('items')->latest('id');

        match ($filter) {
            'active' => $query->whereNotIn('stage', [OnlineOrder::STAGE_COMPLETED, OnlineOrder::STAGE_CANCELLED]),
            'needs_payment' => $query->where('stage', OnlineOrder::STAGE_DETAILS_RECEIVED),
            'needs_shipping' => $query->whereIn('stage', [OnlineOrder::STAGE_PAID, OnlineOrder::STAGE_COURIER_BOOKED]),
            'in_transit' => $query->where('stage', OnlineOrder::STAGE_SHIPPED),
            'reimburse' => $query->where('driver_funding', 'staff_advance')->whereNotNull('staff_advance_amount')->whereNull('staff_reimbursed_at'),
            'all' => null,
            default => $query->where('stage', $filter),
        };
        if ($search !== '') {
            $like = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $search).'%';
            $query->where(fn ($inner) => $inner->where('code', 'like', $like)->orWhere('customer_name', 'like', $like)
                ->orWhere('customer_phone', 'like', $like));
        }

        return view('admin.orders.index', [
            'orders' => $query->paginate(20)->withQueryString(),
            'filter' => $filter,
            'search' => $search,
            'reimburseCount' => OnlineOrder::query()->where('driver_funding', 'staff_advance')->whereNotNull('staff_advance_amount')->whereNull('staff_reimbursed_at')->count(),
        ]);
    }

    public function create(Request $request)
    {
        $oldItems = collect($request->old('items', []))->filter(fn ($item) => is_array($item) && isset($item['variant_id']));
        $variants = ProductVariant::with(['product.brand', 'product.primaryImage'])->whereIn('id', $oldItems->pluck('variant_id')->map(fn ($id) => (int) $id))->get()->keyBy('id');
        $selected = $oldItems->map(fn ($item) => ($variant = $variants->get((int) $item['variant_id'])) ? $this->option($variant) + ['quantity' => (int) ($item['quantity'] ?? 1)] : null)->filter()->values();

        return view('admin.orders.create', ['selected' => $selected]);
    }

    public function store(OnlineOrderStoreRequest $request, CreateOnlineOrder $create)
    {
        try {
            [$order] = $create->handle($request->user(), $request->lines(), $request->customer());
        } catch (OnlineOrderRejected $error) {
            return back()->withInput()->with('error', $error->getMessage());
        }

        return redirect()->route('admin.orders.show', $order)->with('success', 'Pesanan '.$order->code.' dibuat. Salin link lalu kirim ke customer.');
    }

    public function show(OnlineOrder $order, OnlineOrderMessages $messages, InquiryWhatsApp $whatsApp)
    {
        $order->load(['items', 'events.actor']);
        $customerUrl = route('orders.customer.show', $order->customer_token_encrypted);
        $staffUrl = route('orders.staff.show', $order->staff_token_encrypted);
        $groupMessage = $messages->staffGroup($order, $staffUrl);
        $inviteMessage = $messages->customerInvite($order, $customerUrl);

        return response()->view('admin.orders.show', [
            'order' => $order,
            'timeline' => OnlineOrderTimeline::items($order, 'admin'),
            'customerUrl' => $customerUrl,
            'staffUrl' => $staffUrl,
            'groupMessage' => $groupMessage,
            'groupShareUrl' => $whatsApp->shareUrl($groupMessage),
            'inviteMessage' => $inviteMessage,
            'inviteUrl' => $order->customer_phone ? $whatsApp->textUrl($order->customer_phone, $inviteMessage) : $whatsApp->shareUrl($inviteMessage),
            'customerChatUrl' => $order->customer_phone ? $whatsApp->textUrl($order->customer_phone, 'Halo Kak, terkait pesanan Qammaris '.$order->code.'.') : null,
        ])->header('Cache-Control', 'no-store, private')->header('Referrer-Policy', 'no-referrer');
    }

    public function update(OnlineOrderUpdateRequest $request, OnlineOrder $order)
    {
        $data = $request->safe()->except('revision');

        return $this->attempt($order, fn () => $this->workflow->update($order, (int) $request->validated('revision'), $data, $request->user()), 'Detail pesanan disimpan.');
    }

    public function advance(Request $request, OnlineOrder $order)
    {
        $data = $request->validate([
            'from' => ['required', 'string', 'max:24'],
            'to' => ['required', 'string', 'max:24'],
            'payment_method' => ['nullable', Rule::in(array_keys(OnlineOrder::PAYMENT_METHODS))],
            'courier' => ['nullable', Rule::in(array_keys(OnlineOrder::COURIERS))],
            'tracking_number' => ['nullable', 'string', 'max:40', 'regex:/^[A-Za-z0-9-]+$/'],
        ]);

        return $this->attempt($order, fn () => $this->workflow->advance($order, $data['from'], $data['to'], 'admin', $request->user(), extra: $data),
            'Status diperbarui: '.$order->stageLabel($data['to']).'.');
    }

    public function revert(Request $request, OnlineOrder $order)
    {
        $data = $request->validate(['from' => ['required', 'string', 'max:24']]);

        return $this->attempt($order, fn () => $this->workflow->revert($order, $data['from'], $request->user()), 'Langkah terakhir dibatalkan.');
    }

    public function cancel(Request $request, OnlineOrder $order)
    {
        $data = $request->validate(['cancel_reason' => ['required', 'string', 'min:3', 'max:200']], ['cancel_reason.required' => 'Tulis alasan pembatalan.']);

        return $this->attempt($order, fn () => $this->workflow->cancel($order, trim($data['cancel_reason']), $request->user()), 'Pesanan dibatalkan.');
    }

    public function reimburse(Request $request, OnlineOrder $order)
    {
        return $this->attempt($order, fn () => $this->workflow->markReimbursed($order, $request->user()), 'Talangan ditandai sudah diganti.');
    }

    public function regenerateLink(Request $request, OnlineOrder $order)
    {
        $data = $request->validate(['audience' => ['required', Rule::in(['customer', 'staff'])]]);

        return $this->attempt($order, fn () => $this->workflow->regenerateLink($order, $data['audience'], $request->user()),
            $data['audience'] === 'staff' ? 'Link staf baru dibuat; link lama tidak berlaku.' : 'Link customer baru dibuat (berlaku 7 hari); link lama tidak berlaku.');
    }

    public function productSearch(Request $request)
    {
        $term = SearchMatcher::term($request->query('q'));
        if (mb_strlen($term) < 2) {
            return response()->json(['items' => []]);
        }
        $products = Product::query()->published()->search($term)
            ->with(['brand', 'primaryImage', 'activeOffer'])
            ->whereHas('activeOffer', fn ($query) => $query->where('price', '>', 0))
            ->limit(12)->get();

        return response()->json(['items' => $products->map(fn (Product $product) => $this->option($product->activeOffer->setRelation('product', $product)))->values()]);
    }

    private function option(ProductVariant $variant): array
    {
        $product = $variant->product;

        return [
            'variant_id' => $variant->id,
            'name' => $product->name,
            'brand' => $product->brand?->name ?? 'Brand belum diisi',
            'volume' => $variant->volume,
            'price' => Rupiah::format($variant->price),
            'availability' => CatalogAvailability::label($product),
            'image' => $product->primaryImage?->image_url ?? asset('images/product-placeholder.svg'),
        ];
    }

    private function attempt(OnlineOrder $order, \Closure $change, string $success)
    {
        try {
            $change();
        } catch (OnlineOrderRejected $error) {
            return redirect()->route('admin.orders.show', $order)->withInput()->with('error', $error->getMessage());
        }

        return redirect()->route('admin.orders.show', $order)->with('success', $success);
    }
}
