<?php

namespace App\Http\Controllers;

use App\Actions\Orders\OnlineOrderWorkflow;
use App\Exceptions\OnlineOrderRejected;
use App\Models\OnlineOrder;
use App\Support\InquiryWhatsApp;
use App\Support\OnlineOrderTimeline;
use App\Support\Rupiah;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Staff task link shared in the store group. Holding the link is the only credential (Owner decision),
 * so it may only move shipping steps and record an advanced shipping fee; never payment or prices.
 */
class OnlineOrderStaffController extends Controller
{
    public function show(string $token, InquiryWhatsApp $whatsApp)
    {
        $order = $this->usableOrder($token);
        if (! $order) {
            return $this->private(response()->view('orders.unavailable', ['whatsappUrl' => null, 'staff' => true], 404));
        }
        $order->load(['items', 'events.actor']);

        return $this->private(response()->view('orders.staff', [
            'order' => $order,
            'token' => $token,
            'timeline' => OnlineOrderTimeline::items($order, 'staff'),
            'customerChatUrl' => $order->customer_phone ? $whatsApp->textUrl($order->customer_phone, 'Halo Kak '.$whatsApp->plainText((string) $order->customer_name).', kami dari Qammaris terkait pesanan '.$order->code.'.') : null,
            'staffStep' => in_array($order->nextStage(), [OnlineOrder::STAGE_COURIER_BOOKED, OnlineOrder::STAGE_SHIPPED, OnlineOrder::STAGE_COMPLETED], true)
                && $order->stepIndex() >= $order->stepIndex(OnlineOrder::STAGE_PAID),
        ]));
    }

    public function advance(Request $request, string $token, OnlineOrderWorkflow $workflow)
    {
        $order = $this->usableOrder($token);
        abort_unless($order, 404);
        $data = $request->validate([
            'from' => ['required', 'string', 'max:24'],
            'to' => ['required', 'string', 'max:24'],
            'staff_name' => ['required', 'string', 'min:2', 'max:40'],
            'courier' => ['nullable', Rule::in(array_keys(OnlineOrder::COURIERS))],
            'tracking_number' => ['nullable', 'string', 'max:40', 'regex:/^[A-Za-z0-9-]+$/'],
        ], ['staff_name.required' => 'Isi nama Anda.', 'tracking_number.regex' => 'Nomor resi hanya huruf, angka, atau tanda minus.']);

        try {
            $workflow->advance($order, $data['from'], $data['to'], 'staff', null, $this->name($data['staff_name']), [
                'courier' => $data['courier'] ?? null,
                'tracking_number' => $data['tracking_number'] ?? null,
            ]);
        } catch (OnlineOrderRejected $error) {
            return $this->private(back()->withInput()->with('error', $error->getMessage()));
        }

        return $this->private(redirect()->route('orders.staff.show', $token)->with('success', 'Status pesanan diperbarui. Terima kasih!'));
    }

    public function recordAdvance(Request $request, string $token, OnlineOrderWorkflow $workflow)
    {
        $order = $this->usableOrder($token);
        abort_unless($order, 404);
        $amount = trim((string) $request->input('amount'));
        $request->merge(['amount' => preg_match('/^\d{1,3}(\.\d{3})+$/', $amount) ? str_replace('.', '', $amount) : $amount]);
        $data = $request->validate([
            'staff_name' => ['required', 'string', 'min:2', 'max:40'],
            'amount' => ['required', 'numeric', 'min:1000', 'max:1000000', 'regex:'.Rupiah::WHOLE_PRICE_PATTERN],
        ], [
            'staff_name.required' => 'Isi nama Anda.',
            'amount.required' => 'Isi nominal ongkir yang ditalangi.',
            'amount.*' => 'Isi nominal rupiah bulat antara 1.000 dan 1.000.000, misalnya 11500.',
        ]);

        try {
            $workflow->recordStaffAdvance($order, (string) $data['amount'], $this->name($data['staff_name']));
        } catch (OnlineOrderRejected $error) {
            return $this->private(back()->with('error', $error->getMessage()));
        }

        return $this->private(redirect()->route('orders.staff.show', $token)->with('success', 'Talangan ongkir dicatat. Admin akan menggantinya.'));
    }

    private function usableOrder(string $token): ?OnlineOrder
    {
        $order = strlen($token) === 40 ? OnlineOrder::findByStaffToken($token) : null;

        return $order?->staffLinkUsable() ? $order : null;
    }

    private function name(string $value): string
    {
        return trim(preg_replace('/\s+/u', ' ', preg_replace('/[\x00-\x1F\x7F]/u', '', $value) ?? '') ?? '');
    }

    private function private($response)
    {
        return $response->header('Cache-Control', 'no-store, private')
            ->header('Referrer-Policy', 'no-referrer')
            ->header('X-Robots-Tag', 'noindex, nofollow');
    }
}
