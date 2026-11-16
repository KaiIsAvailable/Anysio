<?php

namespace App\Services;

use App\Http\Requests\BulkPropertyRequest;
use App\Models\BulkOperation;
use App\Models\Property;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

class PropertyBulkOperationService extends BulkOperationService
{
    protected string $module = 'property';

    protected string $modelClass = BulkOperation::class;

    protected string $requestClass = BulkPropertyRequest::class;

    public function __construct(PropertyBulkService $records)
    {
        parent::__construct($records);
    }

    protected function managedRecords(User $manager): Builder
    {
        return Property::forManagement($manager);
    }

    protected function dispatchOperation(string $operationId): void
    {
        \App\Jobs\SaveBulkOperation::dispatch('property', $operationId);
    }

    protected function assertManagedRows(array $rows, User $manager): void
    {
        $this->records->assertManagedRows($rows, $manager);
    }
}
