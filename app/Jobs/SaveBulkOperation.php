<?php

namespace App\Jobs;

use App\Services\BulkOperationService;
use App\Services\OwnerBulkOperationService;
use App\Services\PropertyBulkOperationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class SaveBulkOperation implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 600;

    public bool $failOnTimeout = true;

    public function __construct(public string $module, public string $operationId)
    {
        $this->onConnection('bulk')->onQueue('bulk');
    }

    private function operations(): BulkOperationService
    {
        return match ($this->module) {
            'owner' => app(OwnerBulkOperationService::class),
            'property' => app(PropertyBulkOperationService::class),
            default => throw new \LogicException('Unsupported bulk module.'),
        };
    }

    public function handle(): void
    {
        $this->operations()->process($this->operationId);
    }

    public function failed(?Throwable $exception): void
    {
        $operations = $this->operations();
        $operation = $operations->operationQuery()->find($this->operationId);
        if ($operation && ! in_array($operation->status, ['succeeded', 'failed'], true)) {
            $operations->fail($operation, ['bulk' => ['Saving was interrupted. No changes were committed. Please try again.']]);
        }
    }
}
