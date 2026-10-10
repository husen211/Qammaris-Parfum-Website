<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\FragranceProfileStore;
use Illuminate\Console\Command;

class BuildFragranceProfiles extends Command
{
    protected $signature = 'fragrance-profiles:build {--apply : Write recommendation tables only} {--preview= : Current preview fingerprint} {--actor= : Existing admin ID for audit attribution}';

    protected $description = 'Preview source-derived fragrance profiles; apply only the exact current preview';

    public function handle(FragranceProfileStore $store): int
    {
        try {
            if (! $this->option('apply')) {
                $this->line(json_encode($store->preview(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));

                return self::SUCCESS;
            }
            $fingerprint = (string) $this->option('preview');
            $id = (string) $this->option('actor');
            if (! preg_match('/^[a-f0-9]{64}$/D', $fingerprint) || ! ctype_digit($id)) {
                $this->error('Apply needs a current preview fingerprint and existing admin actor.');

                return self::FAILURE;
            }
            $actor = User::findOrFail((int) $id);
            $result = $store->apply($fingerprint, $actor);
            $this->line(json_encode($result, JSON_THROW_ON_ERROR));

            return self::SUCCESS;
        } catch (\Throwable $error) {
            $this->error('Profile operation failed; no partial apply. Recheck preview/schema/admin access. ('.$error::class.')');

            return self::FAILURE;
        }
    }
}
