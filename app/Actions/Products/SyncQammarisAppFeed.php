<?php

namespace App\Actions\Products;

use App\Models\ProductExternalIdentity;
use App\Services\QammarisAppClient;
use Illuminate\Support\Facades\DB;

class SyncQammarisAppFeed
{
    public function __construct(private QammarisAppClient $client, private ApplyQammarisAppAvailability $apply) {}

    public function handle(): bool
    {
        for ($page = 0; $page < 5; $page++) {
            $checkpoint = (int) DB::table('qammaris_app_sync_states')->where('id', 'products')->value('checkpoint');
            $feed = $this->client->changes($checkpoint);
            $committed = DB::transaction(function () use ($checkpoint, $feed): bool {
                $state = DB::table('qammaris_app_sync_states')->where('id', 'products')->lockForUpdate()->first();
                if ((int) $state->checkpoint !== $checkpoint) {
                    return false;
                }
                foreach ($feed['data'] as $snapshot) {
                    $current = DB::table('qammaris_app_products')->where('id', $snapshot['id'])->lockForUpdate()->first();
                    if ($current && (int) $current->revision >= $snapshot['revision']) {
                        continue;
                    }
                    DB::table('qammaris_app_products')->updateOrInsert(['id' => $snapshot['id']], [
                        'revision' => $snapshot['revision'],
                        'snapshot' => json_encode($snapshot, JSON_THROW_ON_ERROR),
                        'created_at' => $current?->created_at ?? now(), 'updated_at' => now(),
                    ]);
                    $identity = ProductExternalIdentity::query()
                        ->where('provider', ProductExternalIdentity::PROVIDER_QAMMARIS_APP)
                        ->where('external_product_id', $snapshot['id'])->first();
                    if ($identity) {
                        $this->apply->handle($identity->product, $snapshot);
                    }
                }
                DB::table('qammaris_app_sync_states')->where('id', 'products')->update([
                    'checkpoint' => $feed['next_seq'], 'last_synced_at' => now(), 'last_error' => null,
                ]);

                return true;
            }, 3);
            if ($committed && ! $feed['has_more']) {
                return false;
            }
        }

        return true;
    }
}
