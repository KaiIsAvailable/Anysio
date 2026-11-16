<?php

namespace App\Services;

use App\Http\Requests\BulkTenantRequest;
use App\Models\TenantBulkOperation;
use App\Models\Tenants;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

class TenantBulkOperationService extends BulkOperationService
{
    protected string $module = 'tenant';

    protected string $modelClass = TenantBulkOperation::class;

    protected string $requestClass = BulkTenantRequest::class;

    public function __construct(TenantBulkService $records)
    {
        parent::__construct($records);
    }

    protected function managedRecords(User $manager): Builder
    {
        return Tenants::forManagement($manager);
    }

    protected function dispatchOperation(string $operationId): void
    {
        \App\Jobs\SaveTenantBulkOperation::dispatch($operationId);
    }
}
