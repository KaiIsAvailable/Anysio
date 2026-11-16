<?php

namespace App\Jobs;

use App\Models\TenantBulkOperation;
use App\Services\TenantBulkOperationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class SaveTenantBulkOperation implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 600;

    public bool $failOnTimeout = true;

    public function __construct(public string $operationId)
    {
        $this->onConnection('tenant_bulk')->onQueue('tenant-bulk');
    }

    public function handle(TenantBulkOperationService $operations): void
    {
        $operations->process($this->operationId);
    }

    public function failed(?Throwable $exception): void
    {
        $operation = TenantBulkOperation::find($this->operationId);
        if ($operation && ! in_array($operation->status, ['succeeded', 'failed'], true)) {
            app(TenantBulkOperationService::class)->fail($operation, ['bulk' => ['Saving was interrupted. No tenant changes were committed. Please try again.']]);
        }
    }
}
