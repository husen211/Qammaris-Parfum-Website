<?php

namespace App\Console\Commands;

use App\Services\ProductMediaCopyVerifier;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

class CopyVerifyProductMedia extends Command
{
    protected $signature = 'product-media:copy-verify
                            {--source= : Source filesystem disk; defaults to PRODUCT_MEDIA_DISK}
                            {--target= : Target filesystem disk; defaults to PRODUCT_MEDIA_TARGET_DISK}
                            {--apply : Copy missing target objects; without this flag the command is read-only}';

    protected $description = 'Inventory, copy, and verify referenced product media without deleting source files';

    public function handle(ProductMediaCopyVerifier $copyVerifier): int
    {
        try {
            $sourceDisk = $this->diskOption('source', config('media.product_disk', 'public'));
            $targetDisk = $this->diskOption('target', config('media.migration_target_disk', 'r2'));
            $apply = (bool) $this->option('apply');
            $manifest = $copyVerifier->run($sourceDisk, $targetDisk, $apply);
            $manifestPath = $this->writeManifest($manifest);
        } catch (Throwable $error) {
            report($error);
            $this->error('Media copy-verify gagal dimulai. Periksa konfigurasi disk dan log aplikasi.');

            return self::FAILURE;
        }

        $this->info('Mode: '.$manifest['mode']);
        $this->line("Source: {$sourceDisk}");
        $this->line("Target: {$targetDisk}");
        $this->line("Manifest: {$manifestPath}");

        $rows = collect($manifest['summary'])
            ->map(fn ($count, $status) => [$status, $count])
            ->values()
            ->all();

        $this->table(['Status', 'Jumlah'], $rows);

        if (! $manifest['success']) {
            $this->error('Batch selesai dengan konflik atau object yang belum dapat diverifikasi.');

            return self::FAILURE;
        }

        $this->info($apply
            ? 'Copy-verify selesai. Source tetap dipertahankan.'
            : 'Dry-run selesai. Tidak ada object target yang ditulis.');

        return self::SUCCESS;
    }

    private function writeManifest(array $manifest): string
    {
        $diskName = config('media.manifest_disk', 'local');
        $directory = trim((string) config('media.manifest_directory', 'media-migrations'), '/');

        if (! is_string($diskName) || trim($diskName) === '' || $directory === '') {
            throw new RuntimeException('Manifest disk atau directory tidak valid.');
        }

        $path = $directory.'/'.$manifest['batch_id'].'.json';
        $written = Storage::disk(trim($diskName))->put(
            $path,
            json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)
        );

        if (! $written) {
            throw new RuntimeException('Manifest copy-verify tidak dapat ditulis.');
        }

        return trim($diskName).':'.$path;
    }

    private function diskOption(string $name, mixed $fallback): string
    {
        $value = $this->option($name) ?: $fallback;

        if (! is_string($value) || trim($value) === '') {
            throw new RuntimeException("Disk {$name} belum dikonfigurasi.");
        }

        return trim($value);
    }
}
