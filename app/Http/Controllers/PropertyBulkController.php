<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\HandlesBulkOperations;
use App\Http\Requests\BulkPropertyRequest;
use App\Models\User;
use App\Services\PropertyBulkOperationService;
use App\Services\PropertyBulkService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

class PropertyBulkController extends Controller
{
    use HandlesBulkOperations;

    public function index(Request $request, PropertyBulkService $properties, PropertyBulkOperationService $operations)
    {
        if (Gate::denies('super-admin') && Gate::denies('property.bulk edit')) {
            return view('errors.403');
        }

        $permissions = $properties->permissions();
        if (! $permissions['property']['view'] || ! collect($permissions)->contains(fn ($actions) => $actions['create'] || $actions['edit'])) {
            return view('errors.403');
        }
        $manager = get_effective_user();
        if (! $manager) {
            return view('errors.403');
        }
        $input = $request->validate([
            'entity' => ['nullable', 'in:property,unit,room,all'], 'after' => ['nullable', 'ulid'],
            'ids' => ['sometimes', 'array', 'min:1', 'max:50'], 'ids.*' => ['required', 'ulid', 'distinct'], 'operation' => ['nullable', 'uuid'],
        ]);
        $entity = $input['entity'] ?? 'property';
        if ($request->expectsJson()) {
            if ($entity === 'all' && ! isset($input['ids'])) {
                abort(422);
            }
            $rows = collect();
            $parents = collect();
            $cursor = null;
            foreach ($entity === 'all' ? PropertyBulkService::ENTITIES : [$entity] as $type) {
                if (! $permissions[$type]['view']) {
                    continue;
                }
                [$page, $pageRows, $pageParents, $next] = $this->page($properties, $manager, $type, $input);
                $rows = $rows->concat($pageRows);
                $parents = $parents->concat($pageParents);
                $cursor = $next;
            }

            return response()->json(['rows' => $rows->values(), 'parents' => $parents->unique('key')->values(), 'nextCursor' => $cursor])->header('Cache-Control', 'no-store');
        }
        $rows = collect();
        $cursors = [];
        foreach (PropertyBulkService::ENTITIES as $type) {
            if (! $permissions[$type]['view']) {
                continue;
            }
            [$page, $pageRows, $parents, $cursor] = $this->page($properties, $manager, $type, []);
            if ($type === 'property') {
                $records = $page;
            }
            $rows = $rows->concat($pageRows)->concat($parents);
            $cursors[$type] = $cursor;
        }
        $rows = $rows->unique('key')->values();
        $gridConfig = ['rowsUrl' => route('admin.properties.bulk-edit'), 'modalPrefix' => 'property-bulk', 'recordSingular' => 'record',
            'nextCursor' => $cursors['property'] ?? null, 'cursors' => $cursors, 'permissions' => $permissions, 'hierarchy' => true, 'canManageWaterMeters' => \App\Services\UtilitySetupService::canManageWaterMeters()];
        $ownerOptions = ['' => 'No owner / use property owner'] + $properties->selectableOwners($manager)->orderBy('name')->get(['id', 'name', 'email'])
            ->mapWithKeys(fn (User $owner) => [$owner->id => $owner->name.' ('.$owner->email.')'])->all();
        $draftKey = $this->draftKey($manager);
        $saveRequestKey = (string) Str::uuid();
        $pending = $operations->operationQuery()->where('actor_id', Auth::id())->where('manager_id', $manager->id);
        $operation = ! empty($input['operation']) ? $pending->where('request_key', $input['operation'])->firstOrFail()
            : $pending->whereNull('acknowledged_at')->latest()->first();
        $operationKey = $operation && ! $operation->acknowledged_at ? $operation->request_key : null;
        $progressConfig = ['statusUrl' => route('admin.properties.bulk-progress', '__KEY__'), 'completeUrl' => route('admin.properties.bulk-complete', '__KEY__'),
            'initialProgress' => $operationKey ? $operations->progress($operation) : null];

        return view('adminSide.rooms.property.bulk-edit', compact('records', 'rows', 'ownerOptions', 'draftKey', 'saveRequestKey', 'operationKey', 'progressConfig', 'gridConfig'));
    }

    private function page(PropertyBulkService $service, User $manager, string $entity, array $input): array
    {
        $query = $service->managedRecords($manager, $entity);
        if (isset($input['ids'])) {
            $query->whereIn('id', $input['ids']);
        } elseif (! empty($input['after'])) {
            $query->where('id', '>', $input['after']);
        }
        if ($entity === 'unit') {
            $query->with('property');
        }
        if ($entity === 'room') {
            $query->with('unit.property');
        }
        $page = $query->orderBy('id')->simplePaginate(50, ['*'], 'page', 1);
        $rows = $page->getCollection()->map(fn ($record) => $service->row($record, $entity))->values();
        $parents = collect();
        foreach ($page as $record) {
            if ($entity === 'unit') {
                $parents->push($service->row($record->property, 'property'));
            }
            if ($entity === 'room') {
                $parents->push($service->row($record->unit, 'unit'));
                $parents->push($service->row($record->unit->property, 'property'));
            }
        }

        return [$page, $rows, $parents, ! isset($input['ids']) && $page->hasMorePages() ? $rows->last()['key'] : null];
    }

    public function store(BulkPropertyRequest $request, PropertyBulkOperationService $operations)
    {
        if (Gate::denies('super-admin') && Gate::denies('property.bulk edit')) {
            return redirect()->back()->with('error', 'You have no permission.');
        }

        $manager = get_effective_user();

        return $this->startBulkSave($request, $operations, 'admin.properties', $manager ? $this->draftKey($manager) : '');
    }

    public function progress(string $requestKey, PropertyBulkOperationService $operations)
    {
        if (Gate::denies('super-admin') && Gate::denies('property.bulk edit')) {
            return redirect()->back()->with('error', 'You have no permission.');
        }

        return $this->operationProgress($requestKey, $operations);
    }

    public function complete(string $requestKey, PropertyBulkOperationService $operations)
    {
        if (Gate::denies('super-admin') && Gate::denies('property.bulk edit')) {
            return redirect()->back()->with('error', 'You have no permission.');
        }

        return $this->completeBulkSave($requestKey, $operations, 'admin.properties', 'property', 'marked removed');
    }

    private function draftKey(User $manager): string
    {
        $abilities = ['super-admin', 'utilities.manage meters'];
        foreach (PropertyBulkService::ENTITIES as $entity) {
            foreach (['create', 'edit', 'delete'] as $action) {
                $abilities[] = $entity.'.'.$action;
            }
        }

        return 'property-bulk:v1:'.Auth::id().':'.$manager->id.':'.implode('', array_map(fn ($ability) => (int) Gate::allows($ability), $abilities));
    }
}
