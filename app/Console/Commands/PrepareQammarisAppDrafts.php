<?php

namespace App\Console\Commands;

use App\Actions\Products\PrepareQammarisAppDrafts as PrepareDrafts;
use App\Actions\Products\QueueProductImportImages;
use App\Models\ProductImportBatch;
use App\Services\SpreadsheetSafeCell;
use Illuminate\Console\Command;
use Throwable;

class PrepareQammarisAppDrafts extends Command
{
    protected $signature = 'qammaris-app:prepare-drafts
        {--media-file= : Normalized private Shopee media JSON, cover and first two additional photos only}
        {--output= : New private CSV review report, required for preview}
        {--apply= : Exact persisted preview batch ID}
        {--images= : Queue images for an already applied batch}
        {--confirm : Apply or queue the exact reviewed batch}';

    protected $description = 'Owner-authorized launch drafts from synchronized UUID snapshots; never publishes';

    public function handle(PrepareDrafts $drafts, QueueProductImportImages $images, SpreadsheetSafeCell $safe): int
    {
        if (! app()->environment(['local', 'testing', 'staging'])) {
            $this->error('This preparation command is restricted to local/testing/staging. Production cutover is separate.');

            return self::FAILURE;
        }
        try {
            $apply = (string) $this->option('apply');
            $queue = (string) $this->option('images');
            if ($apply !== '' || $queue !== '') {
                if (! $this->option('confirm') || ($apply !== '' && $queue !== '') || ! ctype_digit($apply !== '' ? $apply : $queue)) {
                    throw new \DomainException('Select one batch operation and provide --confirm.');
                }
                if ($apply !== '') {
                    $batch = $drafts->apply((int) $apply);
                } else {
                    $batch = ProductImportBatch::where('contract_version', PrepareDrafts::VERSION)->findOrFail((int) $queue);
                    $result = $images->handle($batch, null);
                    $this->info('Queued rows: '.$result['queued_rows'].'; candidate images: '.$result['candidate_images'].'.');
                }
            } else {
                $output = (string) $this->option('output');
                $this->privatePath($output, false);
                $media = [];
                $file = (string) $this->option('media-file');
                if ($file !== '') {
                    $this->privatePath($file, true);
                    if (filesize($file) > 8 * 1024 * 1024) {
                        throw new \DomainException('Media input too large.');
                    }
                    $payload = json_decode(file_get_contents($file), true, 64, JSON_THROW_ON_ERROR);
                    if (($payload['schema'] ?? '') !== 'qammaris-shopee-media-v1' || ! is_array($payload['data'] ?? null)
                        || ! array_is_list($payload['data']) || count($payload['data']) > 5000) {
                        throw new \DomainException('Invalid media contract.');
                    }
                    $media = $payload['data'];
                }
                $batch = $drafts->preview($media);
                $this->report($batch, $output, $safe);
            }
            $this->info('Batch '.$batch->id.'; status '.$batch->status.'; source rows '.$batch->total_rows
                .'; created '.(int) $batch->applied_rows.'; retained/blocked '.(int) $batch->blocked_rows.'. No automatic publication.');

            return self::SUCCESS;
        } catch (Throwable $e) {
            // Never print exception context, HTTP payloads or configuration values.
            $this->error($e instanceof \DomainException ? $e->getMessage() : 'Draft preparation failed; inspect configuration and reviewed input.');

            return self::FAILURE;
        }
    }

    private function privatePath(string $path, bool $existing): void
    {
        $private = realpath(storage_path('app/private'));
        $parent = realpath(dirname($path));
        if ($path === '' || ! $private || ! $parent
            || ! str_starts_with(str_replace('\\', '/', $parent).'/', str_replace('\\', '/', $private).'/')
            || ($existing ? ! is_file($path) || is_link($path) : file_exists($path) || strtolower(pathinfo($path, PATHINFO_EXTENSION)) !== 'csv')) {
            throw new \DomainException('Use an existing input or new CSV output within storage/app/private.');
        }
    }

    private function report(ProductImportBatch $batch, string $path, SpreadsheetSafeCell $safe): void
    {
        $stream = fopen($path, 'x');
        if ($stream === false) {
            throw new \DomainException('Cannot create private review report.');
        }
        try {
            fwrite($stream, "\xEF\xBB\xBF");
            fputcsv($stream, ['batch_id', 'uuid', 'revision', 'name', 'source', 'hidden', 'action', 'existing_product_id',
                'price', 'size_ml', 'concentration', 'photo_match', 'shopee_id', 'photo_candidates', 'review_issues'], escape: '');
            foreach ($batch->rows as $row) {
                $d = $row->normalized_data;
                fputcsv($stream, array_map(fn ($v) => $safe->sanitize($v), [
                    $batch->id, $row->external_product_id, $d['source_snapshot']['revision'], $d['nama_produk'], $d['source'],
                    $d['source_snapshot']['hidden'] ? 'yes' : 'no', $row->candidate_action, $row->matched_product_id,
                    $d['harga'], $d['ukuran_ml'], $d['kategori'], $d['photo_match'], $d['shopee_id'],
                    implode('|', $d['photo_candidates']), implode('|', array_column($row->issues, 'message')),
                ]), escape: '');
            }
        } finally {
            fclose($stream);
        }
    }
}
