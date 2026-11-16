<?php

namespace App\Services;

use App\Contracts\BulkSaveHandler;
use App\Models\BulkOperation;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\PermissionRegistrar;
use Throwable;

abstract class BulkOperationService
{
    protected string $module;

    protected string $modelClass;

    protected string $requestClass;

    public function __construct(protected BulkSaveHandler $records) {}

    abstract protected function managedRecords(User $manager): Builder;

    abstract protected function dispatchOperation(string $operationId): void;

    public function operationQuery(): Builder
    {
        $query = ($this->modelClass)::query();

        return $this->modelClass === BulkOperation::class ? $query->where('module', $this->module) : $query;
    }

    private function stagingDirectory(string $id): string
    {
        return $this->module.'-bulk-staging/'.$id;
    }

    private function progressKey(string $id): string
    {
        return $this->module.'-bulk-progress:'.$id;
    }

    public function start(array $rows, User $manager, array $uploads, string $requestKey, int $page, string $draftKey): BulkOperation
    {
        $this->records->authorizeRows($rows, $manager);
        $this->assertManagedRows($rows, $manager);
        $existing = $this->operationQuery()->where('actor_id', Auth::id())->where('request_key', $requestKey)->first();
        if ($existing) {
            if ($existing->manager_id !== $manager->id) {
                throw new AuthorizationException;
            }

            return $existing;
        }

        $rows = $this->validateDetails($rows, $uploads, $page);
        $this->records->validateRows($rows, $manager);

        $operation = new $this->modelClass;
        $operation->id = (string) Str::ulid();
        if ($this->modelClass === BulkOperation::class) {
            $operation->module = $this->module;
        }
        $staged = [];
        try {
            foreach ($uploads as $key => $files) {
                foreach ($files as $field => $file) {
                    if (! $file instanceof UploadedFile) {
                        continue;
                    }
                    $path = $file->store($this->stagingDirectory($operation->id), 'local');
                    if (! $path) {
                        throw new \RuntimeException('Unable to stage upload.');
                    }
                    $staged[$key][$field] = ['path' => $path, 'name' => $file->getClientOriginalName(), 'mime' => $file->getMimeType()];
                }
            }

            return DB::transaction(function () use ($operation, $rows, $manager, $requestKey, $page, $draftKey, $staged) {
                $operation->fill(['actor_id' => Auth::id(), 'manager_id' => $manager->id, 'request_key' => $requestKey,
                    'status' => 'queued', 'total' => count($rows), 'processed' => 0, 'page' => $page,
                    'draft_key' => $draftKey, 'payload' => ['rows' => $rows, 'uploads' => $staged]])->save();
                $this->dispatchOperation($operation->id);

                return $operation;
            });
        } catch (Throwable $exception) {
            Storage::disk('local')->deleteDirectory($this->stagingDirectory($operation->id));
            // A simultaneous retry may already have created this request.
            $existing = $this->operationQuery()->where('actor_id', Auth::id())->where('request_key', $requestKey)->first();
            if ($existing) {
                return $existing;
            }
            throw $exception;
        }
    }

    protected function assertManagedRows(array $rows, User $manager): void
    {
        $ids = collect($rows)->where('action', '!=', 'create')->pluck('id');
        if ($this->managedRecords($manager)->whereIn('id', $ids)->count() !== $ids->count()) {
            throw new AuthorizationException;
        }
    }

    private function validateDetails(array $rows, array $uploads, int $page): array
    {
        $validatedRows = [];
        $validationErrors = [];
        // Validate bounded chunks, then save the entire validated batch in one transaction.
        foreach (array_chunk($rows, 50, true) as $chunk) {
            $validation = ($this->requestClass)::create('/', 'POST', ['rows_json' => json_encode($chunk), 'page' => $page]);
            $validation->validateRowDetails = true;
            $validation->files->set('uploads', array_intersect_key($uploads, array_flip(array_column($chunk, 'uploadKey'))));
            $validation->setContainer(app())->setRedirector(app('redirect'));
            try {
                $validation->validateResolved();
                $validatedRows = array_merge($validatedRows, $validation->validated('rows'));
            } catch (ValidationException $exception) {
                $validationErrors = array_merge($validationErrors, $exception->errors());
            }
        }
        if ($validationErrors) {
            throw ValidationException::withMessages($validationErrors);
        }

        return $validatedRows;
    }

    public function process(string $id): void
    {
        if (! $this->operationQuery()->whereKey($id)->where('status', 'queued')->update(['status' => 'processing', 'updated_at' => now()])) {
            return;
        }
        $operation = $this->operationQuery()->findOrFail($id);
        $previousUser = Auth::user();
        $permissions = app(PermissionRegistrar::class);
        $previousTeam = $permissions->getPermissionsTeamId();
        try {
            $actor = User::find($operation->actor_id);
            if (! $actor) {
                throw new AuthorizationException;
            }
            Auth::setUser($actor);
            $manager = get_effective_user();
            if (! $manager || $manager->id !== $operation->manager_id) {
                throw new AuthorizationException;
            }
            $permissions->setPermissionsTeamId($manager->id);
            $payload = $operation->payload;
            $uploads = [];
            foreach ($payload['uploads'] as $key => $files) {
                foreach ($files as $field => $file) {
                    $uploads[$key][$field] = new UploadedFile(Storage::disk('local')->path($file['path']), $file['name'], $file['mime'], null, true);
                }
            }
            $validatedRows = $this->validateDetails($payload['rows'], $uploads, $operation->page);
            $counts = $this->records->save($validatedRows, $manager, $uploads, function (int $processed) use ($operation): void {
                if ($processed % 5 === 0 || $processed === $operation->total) {
                    // File cache remains visible while the record database transaction is uncommitted.
                    Cache::store('file')->put($this->progressKey($operation->id), $processed, 3600);
                }
            }, function (array $counts) use ($operation): void {
                // Fence the commit against a concurrent timeout cancellation (including hosts without PCNTL).
                $current = $operation->newQuery()->whereKey($operation->id)->lockForUpdate()->firstOrFail();
                if ($current->status !== 'processing') {
                    throw new \RuntimeException('Bulk operation is no longer processing.');
                }
                $current->update(['status' => 'succeeded', 'processed' => $operation->total, 'counts' => $counts, 'payload' => null]);
            });
            $this->cleanup($operation);
        } catch (ValidationException $exception) {
            $this->fail($operation, $exception->errors());
        } catch (AuthorizationException $exception) {
            $this->fail($operation, ['bulk' => ['You have no permission.']]);
        } catch (Throwable $exception) {
            Log::error('Background bulk save failed.', ['operation_id' => $operation->id, 'exception_type' => $exception::class]);
            $this->fail($operation, ['bulk' => ['Changes could not be saved. No changes were committed. Please try again.']]);
        } finally {
            if ($previousUser) {
                Auth::setUser($previousUser);
            } else {
                Auth::forgetGuards();
            }
            $permissions->setPermissionsTeamId($previousTeam);
        }
    }

    public function fail(BulkOperation $operation, array $errors, ?string $expectedStatus = null): void
    {
        $query = $operation->newQuery()->whereKey($operation->id)->whereIn('status', ['queued', 'processing']);
        if ($expectedStatus !== null) {
            $query->where('status', $expectedStatus);
        }
        $changed = $query->update(['status' => 'failed', 'errors' => json_encode($errors, JSON_THROW_ON_ERROR), 'payload' => null]);
        $operation->refresh();
        if ($changed) {
            $this->cleanup($operation);
        }
    }

    private function cleanup(BulkOperation $operation): void
    {
        Cache::store('file')->forget($this->progressKey($operation->id));
        Storage::disk('local')->deleteDirectory($this->stagingDirectory($operation->id));
    }

    public function progress(BulkOperation $operation): array
    {
        $operation->refresh();
        if ($operation->status === 'queued' && $operation->updated_at->lte(now()->subSeconds(config('queue.bulk_pending_timeout', 300)))) {
            $this->fail($operation, ['bulk' => ['Saving did not start in time. No changes were saved. Ask an administrator to start or restart the bulk queue worker, then retry your draft.']], 'queued');
        } elseif ($operation->status === 'processing' && $operation->updated_at->lte(now()->subSeconds(config('queue.bulk_processing_timeout', 660)))) {
            $this->fail($operation, ['bulk' => ['Saving exceeded its time limit and was cancelled. No changes were committed. Please retry your draft or contact an administrator.']], 'processing');
        }
        $processed = $operation->status === 'succeeded' ? $operation->total : (int) Cache::store('file')->get($this->progressKey($operation->id), $operation->processed);

        return ['status' => $operation->status, 'processed' => $processed, 'total' => $operation->total,
            'percent' => $operation->status === 'succeeded' ? 100 : min(99, (int) floor($processed * 100 / max(1, $operation->total)))];
    }
}
