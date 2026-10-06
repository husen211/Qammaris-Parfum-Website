<?php

namespace App\Console\Commands;

use App\Actions\Products\BootstrapLaunchCatalog as Bootstrap;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

class BootstrapLaunchCatalog extends Command
{
    protected $signature = 'catalog:bootstrap-launch {catalog} {media}
        {--catalog-sha= : Reviewed catalog file SHA-256}
        {--media-sha= : Reviewed media manifest SHA-256}
        {--expected-db= : Exact new, empty website database}
        {--apply : Apply the persisted reviewed preview}
        {--batch= : Persisted preview ID required for apply}
        {--rehearse : Apply within an outer transaction and roll it back}';

    protected $description = 'Preview or bootstrap the approved 350-public/95-draft catalog in a new production target';

    public function handle(Bootstrap $bootstrap): int
    {
        try {
            $this->guardTarget();
            $catalog = $this->readPacket('catalog', 'catalog-sha');
            $media = $this->readPacket('media', 'media-sha');
            $published = array_filter($catalog['products'] ?? [], fn ($p) => ($p['publication_status'] ?? null) === 'published');
            $drafts = array_filter($catalog['products'] ?? [], fn ($p) => ($p['publication_status'] ?? null) === 'draft');
            if (count($published) !== 350 || count($drafts) !== 95 || count($catalog['images'] ?? []) !== 1016) {
                throw new RuntimeException('This launch command accepts only the reviewed 350-public/95-draft/1016-photo cohort.');
            }
            $batch = $bootstrap->preview($catalog, $media);
            if ($this->option('apply') || $this->option('rehearse')) {
                if ((string) $this->option('batch') !== (string) $batch->id) {
                    throw new RuntimeException('Inspect the persisted preview first; apply requires its exact batch ID.');
                }
                if ($this->option('rehearse')) {
                    DB::beginTransaction();
                    try {
                        $result = $bootstrap->apply($batch->id);
                        $this->info('Rehearsal rows: '.$result->applied_rows.'; transaction will roll back.');
                    } finally {
                        DB::rollBack();
                    }
                    $batch->refresh();
                } else {
                    $batch = $bootstrap->apply($batch->id);
                }
            }
            $this->info('Batch '.$batch->id.' / '.$batch->status.' / '.$batch->total_rows.' rows.');

            return self::SUCCESS;
        } catch (Throwable) {
            // Never print exception traces/config or an imported payload on this privileged boundary.
            $this->error('Launch transfer refused or rolled back. Verify the reviewed files, target, media and preview.');

            return self::FAILURE;
        }
    }

    private function guardTarget(): void
    {
        $root = realpath(base_path());
        $prefix = '/home/u429527638/domains/qammarisparfum.id/releases/';
        $db = $this->option('expected-db');
        if (! app()->environment('production') || config('app.url') !== 'https://qammarisparfum.id' ||
            ! is_string($root) || ! str_starts_with($root, $prefix) ||
            ! preg_match('/^qammaris-[a-f0-9]{7,40}$/', substr($root, strlen($prefix))) ||
            ! is_string($db) || ! preg_match('/^u429527638_qam_(launch|live)$/', $db) ||
            DB::connection()->getDriverName() !== 'mysql' || DB::connection()->getDatabaseName() !== $db) {
            throw new RuntimeException('Launch target guard failed.');
        }
    }

    private function readPacket(string $argument, string $hashOption): array
    {
        $path = realpath((string) $this->argument($argument));
        $privateRoot = realpath(storage_path('app/private/launch'));
        $hash = $this->option($hashOption);
        if (! $path || ! $privateRoot || ! str_starts_with($path, $privateRoot.DIRECTORY_SEPARATOR) ||
            ! is_string($hash) || ! preg_match('/^[a-f0-9]{64}$/', $hash) ||
            ! hash_equals($hash, hash_file('sha256', $path)) || filesize($path) > 10_000_000) {
            throw new RuntimeException('Launch packet guard failed.');
        }

        return json_decode(file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);
    }
}
