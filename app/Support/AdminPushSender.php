<?php

namespace App\Support;

use App\Models\AdminPushSubscription;
use Illuminate\Support\Collection;
use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\WebPush;

/**
 * Sends one Web Push payload to many browsers (VAPID, aes128gcm). Returns the endpoint hashes the push service
 * says are gone (404/410) so they can be removed. Swapped for a fake in tests.
 */
class AdminPushSender
{
    /**
     * @param  Collection<int, AdminPushSubscription>  $subscriptions
     * @return array{sent: int, expired: list<string>}
     */
    public function send(Collection $subscriptions, array $payload): array
    {
        $webPush = new WebPush(['VAPID' => [
            'subject' => (string) config('admin_push.vapid.subject'),
            'publicKey' => (string) config('admin_push.vapid.public_key'),
            'privateKey' => (string) config('admin_push.vapid.private_key'),
        ]], ['TTL' => 3600, 'urgency' => 'high', 'topic' => substr(preg_replace('/[^A-Za-z0-9_-]/', '', (string) ($payload['tag'] ?? '')), 0, 32) ?: null]);
        $body = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        foreach ($subscriptions as $subscription) {
            $webPush->queueNotification(Subscription::create([
                'endpoint' => $subscription->endpoint,
                'publicKey' => $subscription->public_key,
                'authToken' => $subscription->auth_token,
                'contentEncoding' => 'aes128gcm',
            ]), $body);
        }
        $sent = 0;
        $expired = [];
        foreach ($webPush->flush() as $report) {
            if ($report->isSuccess()) {
                $sent++;
            } elseif ($report->isSubscriptionExpired()) {
                $expired[] = AdminPushSubscription::hashEndpoint($report->getEndpoint());
            }
        }

        return ['sent' => $sent, 'expired' => $expired];
    }
}
