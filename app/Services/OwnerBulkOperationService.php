<?php

namespace App\Services;

use App\Http\Requests\BulkOwnerRequest;
use App\Models\BulkOperation;
use App\Models\Owners;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

class OwnerBulkOperationService extends BulkOperationService
{
    protected string $module = 'owner';

    protected string $modelClass = BulkOperation::class;

    protected string $requestClass = BulkOwnerRequest::class;

    public function __construct(OwnerBulkService $records)
    {
        parent::__construct($records);
    }

    protected function managedRecords(User $manager): Builder
    {
        return Owners::forManagement($manager);
    }

    protected function dispatchOperation(string $operationId): void
    {
        \App\Jobs\SaveBulkOperation::dispatch('owner', $operationId);
    }
}
