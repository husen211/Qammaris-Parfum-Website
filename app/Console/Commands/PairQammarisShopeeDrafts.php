<?php

namespace App\Console\Commands;

use App\Actions\Products\PairQammarisShopeeDrafts as Pairing;
use App\Actions\Products\QueueProductImportImages;
use App\Models\ProductImportBatch;
use Illuminate\Console\Command;
use RuntimeException;
use Throwable;

class PairQammarisShopeeDrafts extends Command
{
    protected $signature = 'qammaris-app:shopee-pairs
        {--file= : Private curated mapping JSON for preview}
        {--apply= : Exact persisted pairing batch ID}
        {--images= : Exact applied pairing batch to enqueue image acquisition}
        {--confirm : Apply or enqueue the exact Owner-authorized batch}';

    protected $description = 'Pair Owner-approved Shopee sources to visible staging drafts by exact feed SKU/UUID';

    public function handle(Pairing $pairing, QueueProductImportImages $images): int
    {
        try {
            $pairing->assertEnvironment();
            $file = (string) $this->option('file');
            $apply = (string) $this->option('apply');
            $imageId = (string) $this->option('images');
            if (count(array_filter([$file, $apply, $imageId], fn ($v) => $v !== '')) !== 1) {
                throw new RuntimeException('Choose exactly one operation.');
            }
            if ($file !== '') {
                $root = realpath(storage_path('app/private'));
                $path = realpath($file);
                if ($this->option('confirm') || ! $root || ! $path || ! is_file($path) || is_link($file)
                    || ! str_starts_with(str_replace('\\', '/', $path), str_replace('\\', '/', $root).'/')
                    || filesize($path) > 8 * 1024 * 1024 || strtolower(pathinfo($path, PATHINFO_EXTENSION)) !== 'json') {
                    throw new RuntimeException('Use a private curated JSON for preview.');
                }
                $batch = $pairing->preview(json_decode(file_get_contents($path), true, flags: JSON_THROW_ON_ERROR));
            } else {
                $id = $apply !== '' ? $apply : $imageId;
                if (! ctype_digit($id) || ! $this->option('confirm')) {
                    throw new RuntimeException('Specify an exact batch and --confirm.');
                }
                $batch = ProductImportBatch::where('contract_version', Pairing::VERSION)->findOrFail((int) $id);
                if ($apply !== '') {
                    $batch = $pairing->apply($batch->id);
                } else {
                    $result = $images->handle($batch, null);
                    $this->info('Queued rows '.$result['queued_rows'].'; candidates '.$result['candidate_images'].'.');
                }
            }
            $this->info('Batch '.$batch->id.'; '.$batch->status.'; rows '.$batch->total_rows.'; valid '.$batch->valid_rows.'; review '.$batch->review_rows.'; applied '.(int) $batch->applied_rows.'. No publication operation.');

            return self::SUCCESS;
        } catch (Throwable) {
            $this->error('Shopee pairing refused or failed; check staging scope, private input and current preview.');

            return self::FAILURE;
        }
    }
}
