<?php

namespace App\Http\Controllers;

use App\Http\Requests\Cart\AddToCartRequest;
use App\Http\Requests\Cart\CheckoutRequest;
use App\Http\Requests\Cart\UpdateCartRequest;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\StoreInfo;
use App\Support\InquiryWhatsApp;

class CartController extends Controller
{
    public function __construct(private readonly InquiryWhatsApp $inquiryWhatsApp) {}

    public function index()
    {
        $cart = session('cart', []);
        $resolvedItems = $cart === [] ? [] : $this->resolveInquiryItems($cart);
        $hasUnavailableItems = $cart !== [] && $resolvedItems === null;
        $items = $resolvedItems ?? [];
        $estimateTotal = array_sum(array_column($items, 'line_total'));
        $whatsappAvailable = $this->inquiryWhatsApp->hasValidNumber($this->whatsappNumber());

        return view('cart.index', compact(
            'items',
            'estimateTotal',
            'hasUnavailableItems',
            'whatsappAvailable',
        ));
    }

    public function add(AddToCartRequest $request)
    {
        $variant = ProductVariant::with(['product.brand', 'product.primaryImage'])
            ->active()
            ->whereHas('product', fn ($query) => $query->published())
            ->findOrFail($request->variant_id);

        if ($variant->product->effective_availability === Product::AVAILABILITY_SOLD_OUT) {
            return response()->json([
                'success' => false,
                'message' => 'Produk sedang sold out. Gunakan tombol Tanya restock.',
            ], 422);
        }

        $cart = session('cart', []);
        $quantity = (int) $request->quantity;
        $nextQuantity = (int) ($cart[$variant->id]['quantity'] ?? 0) + $quantity;

        if ($nextQuantity > 99) {
            return response()->json([
                'success' => false,
                'message' => 'Jumlah maksimum dalam daftar inquiry adalah 99.',
            ], 422);
        }

        $cart[$variant->id] = [
            'variant_id' => $variant->id,
            'product_id' => $variant->product_id,
            'product_name' => $variant->product->name,
            'slug' => $variant->product->slug,
            'brand_name' => $variant->product->brand?->name ?? 'Brand belum diisi',
            'volume' => $variant->volume,
            'price' => $variant->price,
            'quantity' => $nextQuantity,
            'image' => $variant->product->primaryImage?->image_url ?? asset('images/product-placeholder.svg'),
        ];

        session(['cart' => $cart]);

        return response()->json([
            'success' => true,
            'message' => 'Produk ditambahkan ke daftar inquiry.',
            'cart_count' => cart_count(),
            'cart_total' => cart_total(),
        ]);
    }

    public function update(UpdateCartRequest $request, $id)
    {
        $cart = session('cart', []);

        if (! isset($cart[$id])) {
            return response()->json(['success' => false, 'message' => 'Produk tidak ditemukan di daftar inquiry.'], 404);
        }

        ProductVariant::active()
            ->whereHas('product', fn ($query) => $query->published())
            ->findOrFail($id);

        $cart[$id]['quantity'] = (int) $request->quantity;
        session(['cart' => $cart]);

        return response()->json([
            'success' => true,
            'cart_count' => cart_count(),
            'cart_total' => cart_total(),
        ]);
    }

    public function remove($id)
    {
        $cart = session('cart', []);

        if (! isset($cart[$id])) {
            return response()->json(['success' => false, 'message' => 'Produk tidak ditemukan di daftar inquiry.'], 404);
        }

        unset($cart[$id]);
        session(['cart' => $cart]);

        return response()->json([
            'success' => true,
            'cart_count' => cart_count(),
            'cart_total' => cart_total(),
        ]);
    }

    public function clear()
    {
        session()->forget('cart');

        return redirect()->back()->with('success', 'Daftar inquiry berhasil dikosongkan.');
    }

    public function checkout(CheckoutRequest $request)
    {
        $cart = session('cart', []);

        if ($cart === []) {
            return redirect()->route('products.index')->with('error', 'Daftar inquiry masih kosong.');
        }

        $items = $this->resolveInquiryItems($cart);
        if ($items === null) {
            return back()->with(
                'error',
                'Satu atau lebih produk di daftar inquiry sudah tidak tersedia atau berubah. Tinjau kembali daftar Anda.'
            );
        }

        $url = $this->inquiryWhatsApp->listUrl(
            $this->whatsappNumber(),
            $items,
            $request->validated('customer_note'),
        );

        if ($url === null) {
            return back()->with('error', 'Kontak WhatsApp belum tersedia. Silakan coba lagi nanti.');
        }

        return redirect()->away($url);
    }

    public function getCartData()
    {
        $cart = session('cart', []);

        if ($cart === []) {
            return response()->json([
                'items' => [],
                'formatted_total' => 'Rp 0',
                'count' => 0,
            ]);
        }

        $items = $this->resolveInquiryItems($cart);
        if ($items === null) {
            return response()->json([
                'items' => [],
                'message' => 'Daftar inquiry berubah. Buka daftar untuk meninjau produk yang tidak lagi tersedia.',
                'cart_url' => route('cart.index'),
            ], 409);
        }

        $total = array_sum(array_column($items, 'line_total'));

        return response()->json([
            'items' => $items,
            'formatted_total' => $this->formatRupiah($total),
            'count' => array_sum(array_column($items, 'quantity')),
        ]);
    }

    /**
     * Resolve every session entry against current catalog data. A partial list is never presented or sent.
     *
     * @return array<int, array<string, mixed>>|null
     */
    private function resolveInquiryItems(array $cart): ?array
    {
        $variantIds = collect($cart)
            ->map(fn ($item, $key) => is_array($item) ? ($item['variant_id'] ?? $key) : null)
            ->filter(fn ($id) => filter_var($id, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) !== false)
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();

        if ($variantIds->count() !== count($cart)) {
            return null;
        }

        $variants = ProductVariant::with(['product.brand', 'product.primaryImage'])
            ->active()
            ->whereHas('product', fn ($query) => $query->published())
            ->whereIn('id', $variantIds)
            ->get()
            ->keyBy('id');

        if ($variants->count() !== $variantIds->count()) {
            return null;
        }

        $items = [];

        foreach ($cart as $key => $sessionItem) {
            if (! is_array($sessionItem)) {
                return null;
            }

            $variantId = (int) ($sessionItem['variant_id'] ?? $key);
            $quantity = filter_var(
                $sessionItem['quantity'] ?? null,
                FILTER_VALIDATE_INT,
                ['options' => ['min_range' => 1, 'max_range' => 99]],
            );
            $variant = $variants->get($variantId);

            if (! $variant || $quantity === false) {
                return null;
            }

            $product = $variant->product;
            $lineTotal = (int) $variant->price * $quantity;

            $items[] = [
                'id' => $variant->id,
                'product_id' => $product->id,
                'product_name' => $product->name,
                'brand_name' => $product->brand?->name ?? 'Brand belum diisi',
                'image' => $product->primaryImage?->image_url ?? asset('images/product-placeholder.svg'),
                'volume' => $variant->volume,
                'quantity' => $quantity,
                'price' => (int) $variant->price,
                'line_total' => $lineTotal,
                'formatted_price' => $this->formatRupiah($lineTotal),
                'formatted_unit_price' => $this->formatRupiah($variant->price),
                'slug' => $product->slug,
                'product_url' => route('products.show', $product),
                'effective_availability' => $product->effective_availability,
                'availability_label' => $this->inquiryWhatsApp->availabilityLabel($product->effective_availability),
            ];
        }

        return $items;
    }

    private function whatsappNumber(): ?string
    {
        return (StoreInfo::query()->first() ?? new StoreInfo)->whatsapp_number;
    }

    private function formatRupiah(int|float|string $amount): string
    {
        return 'Rp '.number_format((float) $amount, 0, ',', '.');
    }
}
