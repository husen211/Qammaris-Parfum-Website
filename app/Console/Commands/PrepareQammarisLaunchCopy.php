<?php

namespace App\Console\Commands;

use App\Actions\Products\ApplyProductMaintenanceBatch;
use App\Models\ProductImportBatch;
use App\Services\ProductMaintenanceBatchRecorder;
use App\Services\ProductMaintenancePreviewer;
use App\Services\QammarisLaunchCopyScope;
use Illuminate\Console\Command;
use Illuminate\Http\UploadedFile;
use Throwable;

class PrepareQammarisLaunchCopy extends Command
{
    protected $signature = 'qammaris-app:launch-copy
        {--file= : Private curated maintenance CSV for description/audience only}
        {--apply= : Exact persisted launch-copy preview batch ID}
        {--confirm : Apply this exact Owner-authorized draft-copy batch; never publishes}';

    protected $description = 'Prepare/apply audited staging draft copy without human account or publication changes';

    public function handle(ProductMaintenancePreviewer $previewer, ProductMaintenanceBatchRecorder $recorder, ApplyProductMaintenanceBatch $apply): int
    {
        try {
            app(QammarisLaunchCopyScope::class)->assertEnvironment();
            $id = (string) $this->option('apply');
            $path = (string) $this->option('file');
            if ($id !== '') {
                if (! ctype_digit($id) || ! $this->option('confirm') || $path !== '') {
                    throw new \RuntimeException('Specify an exact batch and --confirm only.');
                }
                $batch = ProductImportBatch::where('contract_version', QammarisLaunchCopyScope::VERSION)->findOrFail((int) $id);
                $batch = $apply->handle($batch, null);
            } else {
                $root = realpath(storage_path('app/private'));
                $filePath = realpath($path);
                if ($this->option('confirm') || ! $root || ! $filePath || ! is_file($filePath) || is_link($path)
                    || ! str_starts_with(str_replace('\\', '/', $filePath), str_replace('\\', '/', $root).'/')
                    || filesize($filePath) > 8 * 1024 * 1024 || strlen(basename($filePath)) > 200
                    || strtolower(pathinfo($filePath, PATHINFO_EXTENSION)) !== 'csv') {
                    throw new \RuntimeException('Use a private curated CSV for preview.');
                }
                $file = new UploadedFile($filePath, basename($filePath), 'text/csv', null, true);
                $preview = $previewer->preview($file);
                $batch = $recorder->recordLaunchCopy($file, $preview);
            }
            $this->info('Batch '.$batch->id.'; '.$batch->status.'; rows '.$batch->total_rows
                .'; applied '.(int) $batch->applied_rows.'. Products remain drafts; no publication operation.');

            return in_array($batch->status, [ProductImportBatch::STATUS_PREVIEWED, ProductImportBatch::STATUS_APPLIED], true)
                ? self::SUCCESS : self::FAILURE;
        } catch (Throwable) {
            $this->error('Launch copy refused or failed; check scoped environment, private CSV and current preview.');

            return self::FAILURE;
        }
    }
}
