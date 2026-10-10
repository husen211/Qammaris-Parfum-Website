<?php

namespace App\Http\Controllers;

use App\Actions\Orders\OnlineOrderChangeRequests;
use App\Actions\Orders\OnlineOrderDetails;
use App\Actions\Orders\OnlineOrderFulfillment;
use App\Actions\Orders\OnlineOrderWorkflow;
use App\Exceptions\OnlineOrderRejected;
use App\Http\Requests\Orders\CustomerOrderDetailsRequest;
use App\Models\OnlineOrder;
use App\Models\Product;
use App\Models\StoreInfo;
use App\Support\InquiryWhatsApp;
use App\Support\OnlineOrderMessages;
use App\Support\OnlineOrderTimeline;
use App\Support\OrderActor;
use Illuminate\Http\Request;

/** Customer link: complete or correct recipient details, then follow the order status. */
class OnlineOrderController extends Controller
{
    public function show(Request $request, string $token, InquiryWhatsApp $whatsApp, OnlineOrderMessages $messages)
    {
        $order = $this->usableOrder($token);
        if (! $order) {
            return $this->private(response()->view('orders.unavailable', ['whatsappUrl' => $this->storeChatUrl($whatsApp)], 404));
        }
        $order->load(['items', 'events']);
        // V2: after payment or once packing started, the same form sends a change request for review.
        $requestsChange = $order->isV2() && $order->lifecycle === 'active' && $order->handover_status === 'pending'
            && OnlineOrderChangeRequests::customerMustRequest($order);
        $canEdit = $order->customerCanEdit() || $requestsChange;
        $editing = $canEdit && ($order->stage === OnlineOrder::STAGE_AWAITING_CUSTOMER
            || $request->boolean('ubah') || session()->has('errors'));
        $locationUrl = $order->fulfillment === 'local_delivery'
            ? $whatsApp->textUrl($this->storeNumber(), $messages->customerLocation($order))
            : null;

        $images = Product::with('primaryImage')->whereIn('id', $order->items->pluck('product_id'))->get()
            ->mapWithKeys(fn (Product $product) => [$product->id => $product->primaryImage?->image_url]);

        return $this->private(response()->view('orders.customer', [
            'order' => $order,
            'images' => $images,
            'token' => $token,
            'editing' => $editing,
            'canEdit' => $canEdit,
            'requestsChange' => $requestsChange,
            'pendingChange' => $order->isV2() && $order->changeRequests()->where('status', 'pending')->exists(),
            'timeline' => OnlineOrderTimeline::items($order, 'customer'),
            'locationUrl' => $locationUrl,
            'whatsappUrl' => $this->storeChatUrl($whatsApp, $order),
            'checkoutWhatsappUrl' => $order->lifecycle === 'draft' ? $whatsApp->websiteOrderUrl($this->storeNumber(), $order) : null,
            'checkoutMessage' => $order->lifecycle === 'draft' ? $whatsApp->websiteOrderMessage($order) : null,
        ]));
    }

    public function submit(CustomerOrderDetailsRequest $request, string $token, OnlineOrderWorkflow $workflow, OnlineOrderDetails $details)
    {
        $order = $this->usableOrder($token);
        abort_unless($order, 404);
        $firstSubmit = $order->stage === OnlineOrder::STAGE_AWAITING_CUSTOMER;

        try {
            $review = false;
            if ($order->isV2()) {
                $review = $details->submitByCustomer($order, $request->validated());
            } else {
                $workflow->submitCustomerDetails($order, $request->validated());
            }
        } catch (OnlineOrderRejected $error) {
            return $this->private(redirect()->route('orders.customer.show', $token)->with('error', $error->getMessage()));
        }

        return $this->private(redirect()->route('orders.customer.show', $token)->with('success', match (true) {
            $review => 'Permintaan perubahan terkirim. Kami akan memeriksanya dan mengabari lewat WhatsApp.',
            $firstSubmit => 'Terima kasih, data pesanan sudah kami terima.',
            default => 'Perubahan data sudah disimpan.',
        }));
    }

    public function confirmReceived(string $token, OnlineOrderWorkflow $workflow, OnlineOrderFulfillment $fulfillment)
    {
        $order = $this->usableOrder($token);
        abort_unless($order, 404);

        try {
            if ($order->isV2()) {
                $fulfillment->confirmDelivery($order, $order->revision, OrderActor::customer());
            } else {
                $workflow->advance($order, OnlineOrder::STAGE_SHIPPED, OnlineOrder::STAGE_COMPLETED, 'customer');
            }
        } catch (OnlineOrderRejected $error) {
            return $this->private(redirect()->route('orders.customer.show', $token)->with('error', $error->getMessage()));
        }

        return $this->private(redirect()->route('orders.customer.show', $token)
            ->with('success', 'Terima kasih! Pesanan ditandai sudah diterima.'));
    }

    private function usableOrder(string $token): ?OnlineOrder
    {
        // Unknown, replaced and expired links share one response so a link reveals nothing about other orders.
        $order = strlen($token) === 40 ? OnlineOrder::findByCustomerToken($token) : null;

        return $order?->customerLinkUsable() ? $order : null;
    }

    private function storeNumber(): ?string
    {
        return (StoreInfo::query()->first() ?? new StoreInfo)->whatsapp_number;
    }

    private function storeChatUrl(InquiryWhatsApp $whatsApp, ?OnlineOrder $order = null): ?string
    {
        return $whatsApp->textUrl($this->storeNumber(), $order
            ? 'Halo Qammaris, saya ingin bertanya tentang pesanan '.$order->code.'.'
            : 'Halo Qammaris, link pesanan saya tidak bisa dibuka.');
    }

    private function private($response)
    {
        return $response->header('Cache-Control', 'no-store, private')
            ->header('Referrer-Policy', 'no-referrer')
            ->header('X-Robots-Tag', 'noindex, nofollow');
    }
}
