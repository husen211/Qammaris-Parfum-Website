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
        {--choices= : Private Owner choice JSON for candidate preview}
        {--review= : Original private candidate review JSON}
        {--source-batch= : Original applied pairing batch}
        {--apply= : Exact persisted pairing batch ID}
        {--images= : Exact applied pairing batch to enqueue image acquisition}
        {--confirm : Apply or enqueue the exact Owner-authorized batch}';

    protected $description = 'Pair Owner-approved Shopee sources to visible staging drafts by exact feed SKU/UUID';

    public function handle(Pairing $pairing, QueueProductImportImages $images): int
    {
        try {
            $pairing->assertEnvironment();
            $file = (string) $this->option('file');
            $choices = (string) $this->option('choices');
            $apply = (string) $this->option('apply');
            $imageId = (string) $this->option('images');
            if (count(array_filter([$file, $choices, $apply, $imageId], fn ($v) => $v !== '')) !== 1) {
                throw new RuntimeException('Choose exactly one operation.');
            }
            if ($choices !== '') {
                if ($this->option('confirm') || ! ctype_digit((string) $this->option('source-batch'))) {
                    throw new RuntimeException('Use a private Owner choice and original review/batch for preview.');
                }
                $batch = $pairing->previewOwnerChoices($this->readPrivateJson($choices),
                    $this->readPrivateJson((string) $this->option('review')), (int) $this->option('source-batch'));
            } elseif ($this->option('review') || $this->option('source-batch')) {
                throw new RuntimeException('Review/source batch require Owner choices.');
            } elseif ($file !== '') {
                if ($this->option('confirm')) {
                    throw new RuntimeException('Preview does not accept confirmation.');
                }
                $batch = $pairing->preview($this->readPrivateJson($file));
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

    private function readPrivateJson(string $file): array
    {
        $root = realpath(storage_path('app/private'));
        $path = realpath($file);
        if (! $root || ! $path || ! is_file($path) || is_link($file)
            || ! str_starts_with(str_replace('\\', '/', $path), str_replace('\\', '/', $root).'/')
            || filesize($path) > 8 * 1024 * 1024 || strtolower(pathinfo($path, PATHINFO_EXTENSION)) !== 'json') {
            throw new RuntimeException('Use a private JSON input.');
        }

        return json_decode(file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);
    }
}
