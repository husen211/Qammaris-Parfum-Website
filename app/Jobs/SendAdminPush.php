<?php

namespace App\Jobs;

use App\Models\AdminPushSubscription;
use App\Support\AdminPushSender;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;

/** ORD-09: delivers an admin push to every browser of an account that may handle orders; removes expired browsers. */
class SendAdminPush implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 3;

    /**
     * @param  array{title: string, body: string, url: string, tag: string}  $payload
     * @param  int|null  $onlyUserId  "Kirim tes": only this account's devices
     */
    public function __construct(public readonly array $payload, public readonly ?int $onlyUserId = null)
    {
        // The production worker only listens to the integration queue; the default queue is never processed there.
        $this->onQueue(config('qammaris_app.queue'));
    }

    public function handle(AdminPushSender $sender): void
    {
        $subscriptions = AdminPushSubscription::query()->with('user')->when($this->onlyUserId, fn ($query) => $query->where('user_id', $this->onlyUserId))->get()
            ->filter(fn (AdminPushSubscription $subscription) => $subscription->user && Gate::forUser($subscription->user)->allows('orders.manage'))
            ->values();
        if ($subscriptions->isEmpty()) {
            return;
        }
        $result = $sender->send($subscriptions, $this->payload);
        if ($result['expired'] !== []) {
            AdminPushSubscription::query()->whereIn('endpoint_hash', $result['expired'])->delete();
        }
        AdminPushSubscription::query()->whereIn('id', $subscriptions->pluck('id'))->whereNotIn('endpoint_hash', $result['expired'])->update(['last_sent_at' => now()]);
        Log::info('admin push sent', ['sent' => $result['sent'], 'expired' => count($result['expired'])]);
    }
}
