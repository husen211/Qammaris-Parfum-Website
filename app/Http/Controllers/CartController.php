<?php

namespace App\Http\Controllers;

use App\Http\Requests\Cart\AddToCartRequest;
use App\Http\Requests\Cart\CheckoutRequest;
use App\Http\Requests\Cart\UpdateCartRequest;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\StoreInfo;
use App\Support\CatalogAvailability;
use App\Support\InquiryWhatsApp;
use App\Support\Rupiah;

class CartController extends Controller
{
    public function __construct(private readonly InquiryWhatsApp $inquiryWhatsApp) {}

    public function index()
    {
        $cart = session('cart', []);
        $resolvedItems = $cart === [] ? [] : $this->resolveCartItems($cart);
        $hasUnavailableItems = $cart !== [] && $resolvedItems === null;
        $items = $resolvedItems ?? [];
        $estimateTotal = Rupiah::sum(array_column($items, 'line_total'));
        $whatsappAvailable = $this->inquiryWhatsApp->hasValidNumber($this->whatsappNumber());
        $canCheckout = $items !== [] && $this->canOrder($items) && $whatsappAvailable;

        return view('cart.index', compact(
            'items',
            'estimateTotal',
            'hasUnavailableItems',
            'whatsappAvailable',
            'canCheckout',
        ));
    }

    public function add(AddToCartRequest $request)
    {
        $variant = ProductVariant::with(['product.brand', 'product.primaryImage'])
            ->active()
            ->whereHas('product', fn ($query) => $query->published())
            ->findOrFail($request->variant_id);

        if ($variant->product->effective_availability !== Product::AVAILABILITY_AVAILABLE || $variant->price <= 0) {
            return response()->json([
                'success' => false,
                'message' => 'Produk ini belum bisa dipesan. Pilih produk berstatus Tersedia.',
            ], 422);
        }

        $cart = session('cart', []);
        $quantity = (int) $request->quantity;
        $nextQuantity = (int) ($cart[$variant->id]['quantity'] ?? 0) + $quantity;

        if ($nextQuantity > 99) {
            return response()->json([
                'success' => false,
                'message' => 'Jumlah maksimum dalam keranjang adalah 99.',
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
            'message' => 'Produk ditambahkan ke keranjang.',
            'cart_count' => cart_count(),
            'cart_total' => $this->currentCartTotal($cart),
        ]);
    }

    public function update(UpdateCartRequest $request, $id)
    {
        $cart = session('cart', []);

        if (! isset($cart[$id])) {
            return response()->json(['success' => false, 'message' => 'Produk tidak ditemukan di keranjang.'], 404);
        }

        ProductVariant::active()
            ->whereHas('product', fn ($query) => $query->published())
            ->findOrFail($id);

        $cart[$id]['quantity'] = (int) $request->quantity;
        session(['cart' => $cart]);

        return response()->json([
            'success' => true,
            'cart_count' => cart_count(),
            'cart_total' => $this->currentCartTotal($cart),
        ]);
    }

    public function remove($id)
    {
        $cart = session('cart', []);

        if (! isset($cart[$id])) {
            return response()->json(['success' => false, 'message' => 'Produk tidak ditemukan di keranjang.'], 404);
        }

        unset($cart[$id]);
        session(['cart' => $cart]);

        return response()->json([
            'success' => true,
            'cart_count' => cart_count(),
            'cart_total' => $this->currentCartTotal($cart),
        ]);
    }

    public function clear()
    {
        session()->forget('cart');

        return redirect()->back()->with('success', 'Keranjang berhasil dikosongkan.');
    }

    public function showCheckout()
    {
        $items = $this->resolveCartItems(session('cart', []));
        if (! $items || ! $this->canOrder($items)) {
            return redirect()->route('cart.index')->with('error', 'Tinjau keranjang: hanya produk Tersedia yang dapat dipesan.');
        }
        $checkoutQuote = $this->quote($items);
        session(['checkout_quote' => $checkoutQuote]);
        $subtotal = Rupiah::sum(array_column($items, 'line_total'));
        $whatsappAvailable = $this->inquiryWhatsApp->hasValidNumber($this->whatsappNumber());

        return response()->view('cart.checkout', compact('items', 'subtotal', 'checkoutQuote', 'whatsappAvailable'))
            ->header('Cache-Control', 'no-store, private')->header('Referrer-Policy', 'no-referrer');
    }

    public function checkout(CheckoutRequest $request)
    {
        $items = $this->resolveCartItems(session('cart', []));
        if (! $items || ! $this->canOrder($items)) {
            return redirect()->route('cart.index')->with('error', 'Tinjau keranjang: hanya produk Tersedia yang dapat dipesan.');
        }
        $quote = $request->validated('checkout_quote');
        if (! hash_equals((string) session('checkout_quote', ''), $quote) || ! hash_equals($this->quote($items), $quote)) {
            return redirect()->route('cart.checkout.show')->withInput($request->safe()->except('checkout_quote'))
                ->with('error', 'Harga atau isi keranjang berubah. Periksa ringkasan terbaru, lalu lanjutkan kembali.');
        }
        $url = $this->inquiryWhatsApp->orderUrl($this->whatsappNumber(), $items, $request->safe()->except('checkout_quote'));
        if ($url === null) {
            return redirect()->route('cart.checkout.show')->withInput($request->safe()->except('checkout_quote'))
                ->with('error', 'Kontak WhatsApp belum tersedia. Silakan coba lagi nanti.');
        }

        // Opening the composer is not proof of delivery: retain the cart for retries.
        return redirect()->away($url)->header('Cache-Control', 'no-store, private')->header('Referrer-Policy', 'no-referrer');
    }

    private function canOrder(array $items): bool
    {
        foreach ($items as $item) {
            if ($item['effective_availability'] !== Product::AVAILABILITY_AVAILABLE || $item['price'] <= 0) {
                return false;
            }
        }

        return true;
    }

    private function quote(array $items): string
    {
        // Bind the review to server-resolved identity, quantities, prices and status, never client totals.
        return hash('sha256', json_encode(array_map(fn ($item) => [
            $item['id'], $item['product_id'], $item['product_name'], $item['brand_name'], $item['volume'],
            $item['quantity'], $item['price'], $item['effective_availability'],
        ], $items), JSON_THROW_ON_ERROR));
    }

    public function getCartData()
    {
        $cart = session('cart', []);

        if ($cart === []) {
            return response()->json([
                'items' => [],
                'formatted_total' => Rupiah::format(0),
                'count' => 0,
            ]);
        }

        $items = $this->resolveCartItems($cart);
        if ($items === null) {
            return response()->json([
                'items' => [],
                'message' => 'Keranjang berubah. Buka daftar untuk meninjau produk yang tidak lagi tersedia.',
                'cart_url' => route('cart.index'),
            ], 409);
        }

        $total = Rupiah::sum(array_column($items, 'line_total'));

        return response()->json([
            // Preserve the existing numeric JSON fields; arithmetic uses exact decimal strings internally.
            'items' => array_map(fn ($item) => array_replace($item, [
                'price' => (float) $item['price'], 'line_total' => (float) $item['line_total'],
            ]), $items),
            'formatted_total' => Rupiah::format($total),
            'count' => array_sum(array_column($items, 'quantity')),
            'notice' => $this->inquiryWhatsApp->listNotice($items),
        ]);
    }

    /**
     * Resolve every session entry against current catalog data. A partial list is never presented or sent.
     *
     * @return array<int, array<string, mixed>>|null
     */
    private function resolveCartItems(array $cart): ?array
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
            $price = Rupiah::minorUnits($variant->price);
            $lineTotal = Rupiah::decimal($price * $quantity);

            $items[] = [
                'id' => $variant->id,
                'product_id' => $product->id,
                'product_name' => $product->name,
                'brand_name' => $product->brand?->name ?? 'Brand belum diisi',
                'image' => $product->primaryImage?->image_url ?? asset('images/product-placeholder.svg'),
                'volume' => $variant->volume,
                'quantity' => $quantity,
                'price' => Rupiah::decimal($price),
                'line_total' => $lineTotal,
                'formatted_price' => Rupiah::format($lineTotal),
                'formatted_unit_price' => Rupiah::format($variant->price),
                'slug' => $product->slug,
                'product_url' => route('products.show', $product),
                'effective_availability' => $product->effective_availability,
                'availability_label' => CatalogAvailability::label($product),
                'requires_stock_confirmation' => $product->availability_source !== 'qammaris_app',
            ];
        }

        return $items;
    }

    private function whatsappNumber(): ?string
    {
        return (StoreInfo::query()->first() ?? new StoreInfo)->whatsapp_number;
    }

    private function currentCartTotal(array $cart): ?float
    {
        $items = $this->resolveCartItems($cart);

        return $items === null ? null : (float) Rupiah::sum(array_column($items, 'line_total'));
    }
}
