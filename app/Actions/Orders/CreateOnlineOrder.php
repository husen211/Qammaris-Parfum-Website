<?php

namespace App\Actions\Orders;

use App\Exceptions\CheckoutChanged;
use App\Exceptions\DuplicateOnlineOrderSubmission;
use App\Exceptions\OnlineOrderRejected;
use App\Exceptions\OrderValidationFailed;
use App\Models\Customer;
use App\Models\CustomerAddress;
use App\Models\OnlineOrder;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use App\Support\PhoneNumber;
use App\Support\Rupiah;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

class CreateOnlineOrder
{
    /**
     * @param  array<int, int>  $lines  variant ID => quantity
     * @param  array<string, mixed>|null  $customer  optional details typed by the admin from the chat
     * @param  string|null  $submissionToken  form UUID; a repeated submission returns the first order instead of a new one
     * @param  array{source?: string, customer_id?: ?int, new_customer?: bool, customer_address_id?: ?int}  $options
     *                                                                                                                where the order came from, and an explicitly chosen repeat customer / saved address (ORD-02d)
     * @return array{0: OnlineOrder, 1: string, 2: string} order, raw customer token, raw staff token
     *
     * @throws DuplicateOnlineOrderSubmission
     */
    public const FIXTURE_BRAND = 'E2E Sintetis';

    public const SOURCES = ['whatsapp' => 'WhatsApp', 'instagram' => 'Instagram', 'website' => 'Website', 'manual' => 'Langsung/lainnya'];

    public function handle(User $actor, array $lines, ?array $customer = null, ?string $submissionToken = null, array $options = []): array
    {
        abort_unless($actor->exists && Gate::forUser($actor)->allows('orders.manage'), 403);
        if ($lines === []) {
            throw new OnlineOrderRejected('Pilih minimal satu produk.');
        }
        $this->rejectRepeatedSubmission($actor, $submissionToken);

        try {
            return $this->create($actor, $lines, $customer, $submissionToken, $options);
        } catch (UniqueConstraintViolationException $error) {
            // Two requests with the same form token raced; the loser reports the winner's order.
            $this->rejectRepeatedSubmission($actor, $submissionToken);
            throw $error;
        }
    }

    private function rejectRepeatedSubmission(User $actor, ?string $submissionToken): void
    {
        $existing = $submissionToken === null ? null
            : OnlineOrder::where('created_by', $actor->id)->where('submission_token', $submissionToken)->first();
        if ($existing) {
            throw new DuplicateOnlineOrderSubmission($existing);
        }
    }

    private function create(User $actor, array $lines, ?array $customer, ?string $submissionToken, array $options): array
    {
        return DB::transaction(function () use ($actor, $lines, $customer, $submissionToken, $options): array {
            [$repeatCustomer, $address] = $this->chosenCustomer($options);
            if ($address !== null) {
                // A confirmed saved address is copied into the order; later edits never change the saved one.
                $customer = array_merge($customer ?? [], [
                    'fulfillment' => CustomerAddress::TYPES[$address->type], 'address' => $address->address,
                    'postcode' => $address->type === 'intercity' ? $address->postcode : null, 'location_url' => $address->location_url,
                ]);
            }
            // Admins confirm stock in the chat, so any published offer with a valid price may be ordered.
            // Staging fixtures (never production) use draft products of the synthetic brand only.
            $fixture = ($options['fixture'] ?? false) === true && ! app()->environment('production');
            $variants = ProductVariant::with('product.brand')->active()
                ->when(! $fixture, fn ($query) => $query->whereHas('product', fn ($product) => $product->published()))
                ->when($fixture, fn ($query) => $query->whereHas('product.brand', fn ($brand) => $brand->where('name', self::FIXTURE_BRAND)))
                ->whereIn('id', array_keys($lines))->get()->keyBy('id');
            if ($variants->count() !== count($lines)) {
                throw new OnlineOrderRejected('Ada produk yang tidak lagi tersedia di katalog. Pilih ulang produknya.');
            }

            $customerToken = Str::random(40);
            $staffToken = Str::random(40);
            $order = new OnlineOrder;
            $order->customer_token_hash = OnlineOrder::tokenHash($customerToken);
            $order->customer_token_encrypted = $customerToken;
            $order->staff_token_hash = OnlineOrder::tokenHash($staffToken);
            $order->staff_token_encrypted = $staffToken;
            $order->customer_link_expires_at = now()->addDays(OnlineOrder::CUSTOMER_LINK_DAYS);
            $order->stage = OnlineOrder::STAGE_AWAITING_CUSTOMER;
            $order->created_by = $actor->id;
            $order->submission_token = $submissionToken;
            $order->source = $options['source'] ?? 'whatsapp';
            // Cutover point (ADR-040): the state model is fixed when the order is created.
            $order->state_model = config('orders.v2_enabled') ? OnlineOrder::STATE_V2 : OnlineOrder::STATE_LEGACY;
            $order->save();
            $order->code = 'QAM-'.str_pad((string) $order->id, 4, '0', STR_PAD_LEFT);
            $order->save();

            foreach ($lines as $variantId => $quantity) {
                $this->addLine($order, $variants->get($variantId), $quantity);
            }
            $order->events()->create(['kind' => 'created', 'stage' => OnlineOrder::STAGE_AWAITING_CUSTOMER, 'actor_type' => 'admin', 'actor_user_id' => $actor->id]);

            if (($options['new_customer'] ?? false) && $repeatCustomer === null) {
                $phone = PhoneNumber::normalize($customer['customer_phone'] ?? null);
                if (blank($customer['customer_name'] ?? null) || $phone === null) {
                    throw new OrderValidationFailed('Isi nama dan nomor WhatsApp untuk menyimpan pelanggan baru.', ['customer_phone' => 'Wajib']);
                }
                $repeatCustomer = Customer::create(['name' => $customer['customer_name'], 'phone' => $phone, 'created_by' => $actor->id]);
            }
            if ($repeatCustomer !== null) {
                $order->customer_id = $repeatCustomer->id;
                $order->customer_address_id = $address?->id;
                $address?->forceFill(['last_used_at' => now()])->save();
                $order->save();
                $order->events()->create(['kind' => 'customer_linked', 'stage' => $order->stage, 'actor_type' => 'admin', 'actor_user_id' => $actor->id, 'note' => Str::limit($repeatCustomer->name, 197)]);
            }

            if ($customer !== null) {
                $order->fill($customer)->save();
                // Complete details from the admin mean the customer never has to open the form.
                if (filled($customer['customer_name'] ?? null)) {
                    $order->stage = OnlineOrder::STAGE_DETAILS_RECEIVED;
                    $order->save();
                    $order->events()->create(['kind' => 'advance', 'stage' => OnlineOrder::STAGE_DETAILS_RECEIVED, 'actor_type' => 'admin', 'actor_user_id' => $actor->id]);
                }
            }

            return [$order->fresh(), $customerToken, $staffToken];
        });
    }

    /**
     * ORD-04: guest order from the website cart. Nobody is signed in, so the order has no creator; the event names
     * the customer. It starts as `draft` ("Menunggu konfirmasi website"): no App queue and no payment until Staff
     * Order confirms it after the chat. The catalog is checked again here: every line must still be published,
     * available and at the unit price the customer reviewed. A repeated checkout key returns the first order.
     *
     * @param  array<int, array{quantity: int, price: string}>  $lines  variant ID => reviewed quantity and unit price
     * @param  array<string, ?string>  $details  recipient, delivery, packaging, payment preference
     * @return array{0: OnlineOrder, 1: string, 2: bool} order, raw customer token, created now (false: replayed key)
     *
     * @throws CheckoutChanged
     */
    public function fromWebsiteCheckout(array $lines, array $details, string $checkoutKey): array
    {
        if (! config('orders.website_checkout') || ! config('orders.v2_enabled')) {
            throw new OnlineOrderRejected('Checkout website belum aktif. Silakan pesan lewat WhatsApp.');
        }
        if ($lines === [] || ! Str::isUuid($checkoutKey)) {
            throw new OnlineOrderRejected('Keranjang kosong atau sesi checkout tidak valid.');
        }
        if ($existing = $this->checkoutReplay($checkoutKey)) {
            return $existing;
        }

        try {
            return DB::transaction(function () use ($lines, $details, $checkoutKey): array {
                $variants = ProductVariant::with('product.brand')->active()
                    ->whereHas('product', fn ($query) => $query->published())
                    ->whereIn('id', array_keys($lines))->get()->keyBy('id');
                foreach ($lines as $variantId => $line) {
                    $variant = $variants->get($variantId);
                    if (! $variant || $variant->product->effective_availability !== Product::AVAILABILITY_AVAILABLE) {
                        throw new CheckoutChanged('Ada produk yang sudah tidak tersedia. Periksa keranjang Anda.');
                    }
                    if (Rupiah::minorUnits($variant->price) <= 0 || Rupiah::minorUnits($variant->price) !== Rupiah::minorUnits($line['price'])) {
                        throw new CheckoutChanged('Harga produk berubah. Periksa ringkasan terbaru, lalu kirim lagi.');
                    }
                }

                $customerToken = Str::random(40);
                $staffToken = Str::random(40);
                $order = new OnlineOrder;
                $order->customer_token_hash = OnlineOrder::tokenHash($customerToken);
                $order->customer_token_encrypted = $customerToken;
                $order->staff_token_hash = OnlineOrder::tokenHash($staffToken);
                $order->staff_token_encrypted = $staffToken;
                $order->customer_link_expires_at = now()->addDays(OnlineOrder::CUSTOMER_LINK_DAYS);
                $order->created_by = null;
                $order->checkout_key = $checkoutKey;
                $order->source = 'website';
                $order->state_model = OnlineOrder::STATE_V2;
                $order->lifecycle = 'draft';
                $order->fill(array_intersect_key($details, array_flip(['customer_name', 'customer_phone', 'fulfillment', 'address', 'postcode', 'packaging', 'customer_note'])));
                $order->district = $details['district'] ?? null;
                $order->subdistrict = $details['subdistrict'] ?? null;
                $order->payment_preference = $details['payment_preference'] ?? null;
                $order->save();
                $order->code = 'QAM-'.str_pad((string) $order->id, 4, '0', STR_PAD_LEFT);
                $order->save();
                foreach ($lines as $variantId => $line) {
                    $this->addLine($order, $variants->get($variantId), $line['quantity']);
                }
                $order->events()->create(['kind' => 'created', 'stage' => $order->stage, 'actor_type' => 'customer', 'source' => 'customer', 'note' => 'Checkout website']);

                return [$order->fresh(), $customerToken, true];
            });
        } catch (UniqueConstraintViolationException $error) {
            // A double tap raced the first request; report the order that won.
            return $this->checkoutReplay($checkoutKey) ?? throw $error;
        }
    }

    /** @return array{0: OnlineOrder, 1: string, 2: bool}|null */
    private function checkoutReplay(string $checkoutKey): ?array
    {
        $order = OnlineOrder::where('checkout_key', $checkoutKey)->first();

        return $order ? [$order, $order->customer_token_encrypted, false] : null;
    }

    /** The agreed price is a snapshot; later catalog changes never rewrite this order. */
    private function addLine(OnlineOrder $order, ProductVariant $variant, int $quantity): void
    {
        if (Rupiah::minorUnits($variant->price) <= 0) {
            throw new OnlineOrderRejected('Produk '.$variant->product->name.' belum memiliki harga yang valid.');
        }
        $order->items()->create([
            'product_id' => $variant->product_id,
            'variant_id' => $variant->id,
            'brand_name' => $variant->product->brand?->name ?? 'Brand belum diisi',
            'product_name' => $variant->product->name,
            'volume' => $variant->volume,
            'unit_price' => $variant->price,
            'quantity' => $quantity,
            // Unknown weight stays null: phase 2 needs real weights, never an assumed 1 kg.
            'weight_grams' => $variant->weight_grams,
        ]);
    }

    /** @return array{0: ?Customer, 1: ?CustomerAddress} */
    private function chosenCustomer(array $options): array
    {
        if (isset($options['source']) && ! array_key_exists($options['source'], self::SOURCES)) {
            throw new OrderValidationFailed('Sumber pesanan tidak dikenal.', ['source' => 'Pilih WhatsApp, Instagram, Website, atau lainnya']);
        }
        $customer = isset($options['customer_id']) ? Customer::find($options['customer_id']) : null;
        if (isset($options['customer_id']) && ! $customer) {
            throw new OrderValidationFailed('Pelanggan tidak ditemukan.', ['customer_id' => 'Tidak ada']);
        }
        $address = null;
        if (isset($options['customer_address_id'])) {
            $address = $customer?->addresses()->whereKey($options['customer_address_id'])->first();
            if (! $address) {
                throw new OrderValidationFailed('Alamat ini bukan milik pelanggan yang dipilih.', ['customer_address_id' => 'Tidak valid']);
            }
        }

        return [$customer, $address];
    }
}
