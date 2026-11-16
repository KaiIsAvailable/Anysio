<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\HandlesBulkOperations;
use App\Http\Requests\BulkOwnerRequest;
use App\Models\Owners;
use App\Models\User;
use App\Services\FileService;
use App\Services\OwnerBulkOperationService;
use App\Services\OwnerBulkService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class OwnerBulkController extends Controller
{
    use HandlesBulkOperations;

    public function index(Request $request, OwnerBulkService $owners, OwnerBulkOperationService $operations)
    {
        if (Gate::denies('super-admin') && (Gate::denies('owner.tab') || (Gate::denies('owner.create') && Gate::denies('owner.edit')))) {
            return view('errors.403');
        }
        $manager = get_effective_user();
        if (! $manager) {
            return view('errors.403');
        }
        $input = $request->validate([
            'after' => ['nullable', 'ulid'], 'ids' => ['sometimes', 'array', 'min:1', 'max:50'],
            'ids.*' => ['required', 'ulid', 'distinct'], 'operation' => ['nullable', 'uuid'],
        ]);
        $query = Owners::forManagement($manager)->whereHas('user', fn ($q) => $q->where('status', 'active'))
            ->with(['user.companies' => fn ($q) => $q->orderBy('companies.id'),
                'user.bankDetails' => fn ($q) => $q->wherePivot('is_default', true)->orderBy('bank_details.id')]);
        if ($request->expectsJson() && isset($input['ids'])) {
            $query->whereIn('id', $input['ids']);
        } elseif ($request->expectsJson() && ! empty($input['after'])) {
            $query->where('id', '>', $input['after']);
        }
        $records = $query->orderBy('id')->simplePaginate(50, ['*'], 'page', 1);
        $rows = $records->getCollection()->map(function (Owners $owner) use ($owners): array {
            $company = $owner->user->companies->first();
            $bank = $owner->user->bankDetails->first();
            $files = [];
            foreach (['companyLogo' => $company?->logo_path, 'ssmCertificate' => $company?->ssm_cert_path] as $field => $path) {
                if ($path) {
                    $files[$field] = ['name' => basename($path), 'url' => route('admin.owners.bulk-file', [$owner, $field]),
                        'isPdf' => strtolower(pathinfo($path, PATHINFO_EXTENSION)) === 'pdf'];
                }
            }

            return [
                'key' => $owner->id, 'version' => $owners->revision($owner), 'existingFiles' => $files,
                'name' => $owner->user->name, 'email' => $owner->user->email,
                'ic' => $owner->ic_number ?? '', 'phone' => $owner->phone ?? '', 'gender' => $owner->gender ?? '',
                'address' => $owner->address ?? '', 'postcode' => $owner->postcode ?? '', 'city' => $owner->city ?? '', 'state' => $owner->state ?? '',
                'companyName' => $company?->name ?? '', 'ssmOld' => $company?->ssm_old_number ?? '', 'ssmNew' => $company?->ssm_new_number ?? '',
                'companyPhone' => $company?->phone ?? '', 'companyEmail' => $company?->email ?? '', 'companyAddress' => $company?->address ?? '',
                'bankName' => $bank?->bank_name ?? '', 'bankCode' => $bank?->bank_code ?? '', 'accountHolder' => $bank?->account_holder_name ?? '',
                'accountNumber' => '', 'accountLastFour' => $bank?->account_number ? substr($bank->account_number, -4) : '',
            ];
        })->values();
        $gridConfig = ['rowsUrl' => route('admin.owners.bulk-edit'), 'modalPrefix' => 'owner-bulk',
            'recordSingular' => 'owner', 'nextCursor' => ! isset($input['ids']) && $records->hasMorePages() ? $rows->last()['key'] : null];
        if ($request->expectsJson()) {
            return response()->json(['rows' => $rows, 'nextCursor' => $gridConfig['nextCursor']])->header('Cache-Control', 'no-store');
        }
        $draftKey = $this->draftKey($manager);
        $saveRequestKey = (string) Str::uuid();
        $pending = $operations->operationQuery()->where('actor_id', Auth::id())->where('manager_id', $manager->id);
        $operation = ! empty($input['operation']) ? $pending->where('request_key', $input['operation'])->firstOrFail()
            : $pending->whereNull('acknowledged_at')->latest()->first();
        $operationKey = $operation && ! $operation->acknowledged_at ? $operation->request_key : null;
        $progressConfig = ['statusUrl' => route('admin.owners.bulk-progress', '__KEY__'),
            'completeUrl' => route('admin.owners.bulk-complete', '__KEY__'),
            'initialProgress' => $operationKey ? $operations->progress($operation) : null];

        return view('adminSide.owners.bulk-edit', compact('records', 'rows', 'draftKey', 'saveRequestKey', 'operationKey', 'progressConfig', 'gridConfig'));
    }

    public function store(BulkOwnerRequest $request, OwnerBulkOperationService $operations)
    {
        $manager = get_effective_user();

        return $this->startBulkSave($request, $operations, 'admin.owners', $manager ? $this->draftKey($manager) : '');
    }

    public function progress(string $requestKey, OwnerBulkOperationService $operations)
    {
        return $this->operationProgress($requestKey, $operations);
    }

    public function complete(string $requestKey, OwnerBulkOperationService $operations)
    {
        return $this->completeBulkSave($requestKey, $operations, 'admin.owners', 'owner');
    }

    public function file(Owners $owner, string $field, FileService $files)
    {
        $manager = get_effective_user();
        if (! $manager || (Gate::denies('super-admin') && (Gate::denies('owner.tab') || (Gate::denies('owner.create') && Gate::denies('owner.edit'))))) {
            return response()->view('errors.403', [], 403);
        }
        abort_unless(Owners::forManagement($manager)->whereKey($owner->id)->exists(), 404);
        $company = $owner->user?->companies()->orderBy('companies.id')->first();
        $path = match ($field) {
            'companyLogo' => $company?->logo_path, 'ssmCertificate' => $company?->ssm_cert_path, default => null,
        };
        abort_unless($path, 404);
        if ($field === 'companyLogo') {
            abort_unless(Storage::disk('public')->exists($path), 404);

            return Storage::disk('public')->response($path, basename($path), ['X-Content-Type-Options' => 'nosniff']);
        }
        $response = $files->getStreamResponse($path);
        $response->headers->set('X-Content-Type-Options', 'nosniff');

        return $response;
    }

    private function draftKey(User $manager): string
    {
        return 'owner-bulk:v1:'.Auth::id().':'.$manager->id.':'.implode('', array_map(
            fn ($ability) => (int) Gate::allows($ability), ['super-admin', 'owner.create', 'owner.edit', 'owner.delete']));
    }
}
