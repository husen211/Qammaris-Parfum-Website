<?php

namespace App\Jobs;

use App\Actions\Products\AcquireProductImportRowImages as AcquireProductImportRowImagesAction;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class AcquireProductImportRowImages implements ShouldQueue
{
    use Queueable;

    public int $tries = 2;

    public int $timeout = 90;

    public function __construct(public int $rowId)
    {
        $this->onQueue('product-import-images');
    }

    public function handle(AcquireProductImportRowImagesAction $action): void
    {
        $action->handle($this->rowId);
    }

    public function failed(Throwable $exception): void
    {
        app(AcquireProductImportRowImagesAction::class)->markUnexpectedFailure($this->rowId);
    }
}
