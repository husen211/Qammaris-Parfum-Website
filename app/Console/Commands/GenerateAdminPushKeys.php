<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Minishlink\WebPush\VAPID;

/** ORD-09: prints a new VAPID key pair for admin push. Nothing is written; the Owner puts it in the server .env once. */
class GenerateAdminPushKeys extends Command
{
    protected $signature = 'admin-push:vapid';

    protected $description = 'Generate VAPID keys for admin push notifications (printed, not stored)';

    public function handle(): int
    {
        $keys = VAPID::createVapidKeys();
        $this->line('ADMIN_PUSH_VAPID_PUBLIC_KEY='.$keys['publicKey']);
        $this->line('ADMIN_PUSH_VAPID_PRIVATE_KEY='.$keys['privateKey']);
        $this->warn('Keep the private key secret. Changing the keys later requires every device to turn notifications on again.');

        return self::SUCCESS;
    }
}
