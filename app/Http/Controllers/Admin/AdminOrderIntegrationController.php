<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\IntegrationOutbox;
use App\Support\OrderApi\OrderWebhookOutbox;
use Illuminate\Http\RedirectResponse;

/**
 * Super Admin view of the Qammaris App order integration (ORD-02e): flags, webhook health and failed events with
 * "Kirim ulang". Secret values are never shown; only whether they are configured.
 */
class AdminOrderIntegrationController extends Controller
{
    public function index()
    {
        $base = IntegrationOutbox::query()->with('order:id,code');

        return response()->view('admin.integrations.orders', [
            'flags' => [
                'API Website untuk App' => (bool) config('orders_api.enabled'),
                'Webhook ke App' => (bool) config('orders_api.webhook_enabled'),
                'Link tugas ke App' => (bool) config('orders_api.app_task_links'),
            ],
            'configured' => [
                'Client ID App' => config('orders_api.client_id') !== '',
                'Secret API (aktif)' => config('orders_api.secret') !== '',
                'Secret API (lama, rotasi)' => config('orders_api.secret_previous') !== '',
                'Secret webhook' => config('orders_api.webhook_secret') !== '',
            ],
            'webhookHost' => parse_url((string) config('orders_api.webhook_url'), PHP_URL_HOST),
            'counts' => [
                'pending' => (clone $base)->whereNull('delivered_at')->whereNull('failed_at')->count(),
                'failed' => (clone $base)->whereNotNull('failed_at')->count(),
                'delivered_24h' => (clone $base)->where('delivered_at', '>=', now()->subDay())->count(),
            ],
            'failed' => (clone $base)->whereNotNull('failed_at')->latest('failed_at')->limit(50)->get(),
            'pending' => (clone $base)->whereNull('delivered_at')->whereNull('failed_at')->orderBy('id')->limit(20)->get(),
        ])->header('Cache-Control', 'no-store, private');
    }

    public function resend(IntegrationOutbox $outbox): RedirectResponse
    {
        OrderWebhookOutbox::resend($outbox);

        return redirect()->route('admin.integrations.orders')->with('success', 'Event '.$outbox->event_id.' dikirim ulang (event dan isi sama, tanda tangan baru).');
    }
}
