<?php

namespace App\Http\Controllers;

use App\Http\Requests\Cart\AddToCartRequest;
use App\Http\Requests\Cart\CheckoutRequest;
use App\Http\Requests\Cart\UpdateCartRequest;
use App\Models\ProductVariant;

class CartController extends Controller
{
    public function index()
    {
        $cart = session('cart', []);
        return view('cart.index', compact('cart'));
    }
    
    public function add(AddToCartRequest $request)
    {
        $variant = ProductVariant::with(['product.primaryImage'])->findOrFail($request->variant_id);
        
        if ($variant->stock < $request->quantity) {
            return response()->json([
                'success' => false,
                'message' => 'Stok tidak mencukupi'
            ], 400);
        }
        
        $cart = session('cart', []);
        
        // Ambil URL gambar yang valid
        $imageUrl = $variant->product->primaryImage 
            ? $variant->product->primaryImage->image_url 
            : 'https://placehold.co/100x100/F5F5F5/333?text=No+Image';

        if (isset($cart[$variant->id])) {
            $cart[$variant->id]['quantity'] += $request->quantity;
        } else {
            $cart[$variant->id] = [
                'variant_id' => $variant->id,
                'product_id' => $variant->product_id,
                'product_name' => $variant->product->name,
                'slug' => $variant->product->slug, 
                'brand_name' => $variant->product->brand->name,
                'volume' => $variant->volume,
                'price' => $variant->price,
                'quantity' => $request->quantity,
                'image' => $imageUrl,
            ];
        }
        
        session(['cart' => $cart]);
        
        return response()->json([
            'success' => true,
            'message' => 'Produk ditambahkan ke keranjang',
            'cart_count' => cart_count(), 
            'cart_total' => cart_total(), 
        ]);
    }
    
    public function update(UpdateCartRequest $request, $id)
    {
        $cart = session('cart', []);
        
        if (isset($cart[$id])) {
            $variant = ProductVariant::findOrFail($id);
            
            if ($variant->stock < $request->quantity) {
                return response()->json(['success' => false, 'message' => 'Stok kurang'], 400);
            }
            
            $cart[$id]['quantity'] = $request->quantity;
            session(['cart' => $cart]);
            
            return response()->json([
                'success' => true,
                'cart_count' => cart_count(),
                'cart_total' => cart_total(),
            ]);
        }
        
        return response()->json(['success' => false], 404);
    }
    
    public function remove($id)
    {
        $cart = session('cart', []);
        
        if (isset($cart[$id])) {
            unset($cart[$id]);
            session(['cart' => $cart]);
            
            return response()->json([
                'success' => true,
                'cart_count' => cart_count(),
                'cart_total' => cart_total(),
            ]);
        }
        
        return response()->json(['success' => false], 404);
    }
    
    public function clear()
    {
        session()->forget('cart');
        return redirect()->back()->with('success', 'Keranjang berhasil dikosongkan');
    }

    // === INI FUNGSI BARU UNTUK PROSES CHECKOUT DATA DIRI ===
   public function checkout(CheckoutRequest $request)
    {
        $cart = session('cart', []);
        
        if (empty($cart)) {
            return redirect()->route('products.index')->with('error', 'Keranjang kosong');
        }
        
        // 2. Susun Pesan WhatsApp (Style: Clean Digital Receipt)
        $message = "*QAMMARIS PERFUMES*\n";
        $message .= "Order Request\n";
        $message .= "______________________________\n\n"; // Garis tipis minimalis
        
        $counter = 1;
        $total = 0;
        
        foreach ($cart as $item) {
            $subtotal = $item['price'] * $item['quantity'];
            $total += $subtotal;
            
            // Format Item: 
            // 1. Brand - Product (Bold)
            //    Size x Qty (Regular)
            //    Subtotal (Regular)
            $message .= "{$counter}. *{$item['brand_name']} - {$item['product_name']}*\n";
            $message .= "   {$item['volume']}ml  x  {$item['quantity']} pcs\n";
            $message .= "   Rp " . number_format($subtotal, 0, ',', '.') . "\n\n";
            $counter++;
        }
        
        $message .= "______________________________\n";
        $message .= "*TOTAL : Rp " . number_format($total, 0, ',', '.') . "*\n";
        $message .= "______________________________\n\n";
        
        // 3. Data Pengiriman (Format Rata Kiri)
        $message .= "*SHIPPING DETAILS*\n";
        $message .= "Name    : " . $request->customer_name . "\n";
        $message .= "Phone   : " . $request->customer_phone . "\n";
        $message .= "Address : " . $request->customer_address . "\n";
        
        if ($request->customer_note) {
            $message .= "Note    : " . $request->customer_note . "\n";
        }
        
        $message .= "\n_Waiting for invoice and payment info._";
        
        // 4. Redirect ke WhatsApp
        $whatsappNumber = function_exists('setting') ? setting('whatsapp_number') : '6285144924931'; 
        
        if (substr($whatsappNumber, 0, 1) == '0') {
            $whatsappNumber = '62' . substr($whatsappNumber, 1);
        }
        
        $encodedMessage = urlencode($message);
        
        return redirect()->away("https://wa.me/{$whatsappNumber}?text={$encodedMessage}");
    }

    public function getCartData()
    {
        $cart = session('cart', []);
        $total = 0;
        $items = [];

        foreach ($cart as $id => $details) {
            $subtotal = $details['price'] * $details['quantity'];
            $total += $subtotal;
            
            $items[] = [
                'id' => $id,
                'product_name' => $details['product_name'],
                'brand_name' => $details['brand_name'] ?? '',
                'image' => $details['image'],
                'volume' => $details['volume'],
                'quantity' => $details['quantity'],
                'price' => $details['price'],
                'formatted_price' => 'Rp ' . number_format($subtotal, 0, ',', '.'),
                'slug' => $details['slug'] ?? '#', 
            ];
        }

        return response()->json([
            'items' => $items,
            'formatted_total' => 'Rp ' . number_format($total, 0, ',', '.'),
            'count' => count($cart)
        ]);
    }
}
