<?php

namespace App\Http\Controllers\Concerns;

use App\Http\Requests\BulkEditRequest;
use App\Services\BulkOperationService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

trait HandlesBulkOperations
{
    protected function startBulkSave(BulkEditRequest $request, BulkOperationService $operations, string $routePrefix, string $draftKey)
    {
        $manager = get_effective_user();
        if (! $manager) {
            return back()->with('error', 'You have no permission.');
        }
        try {
            $operation = $operations->start($request->input('rows'), $manager, $request->validated('uploads', []),
                $request->input('request_key') ?: (string) Str::uuid(), $request->integer('page'), $draftKey);
        } catch (AuthorizationException $exception) {
            return back()->with('error', 'You have no permission.');
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (\Throwable $exception) {
            Log::error('Unable to queue bulk save.', ['user_id' => Auth::id(), 'exception_type' => $exception::class]);

            return back()->with('error', 'Unable to start saving. Your draft has been retained. Please try again.');
        }

        return redirect()->route($routePrefix.'.bulk-edit', ['page' => $operation->page, 'operation' => $operation->request_key]);
    }

    protected function bulkOperationForActor(string $key, BulkOperationService $operations)
    {
        return $operations->operationQuery()->where('actor_id', Auth::id())
            ->where('manager_id', get_effective_user()?->id)->where('request_key', $key)->firstOrFail();
    }

    protected function operationProgress(string $key, BulkOperationService $operations)
    {
        return response()->json($operations->progress($this->bulkOperationForActor($key, $operations)))->header('Cache-Control', 'no-store');
    }

    protected function completeBulkSave(string $key, BulkOperationService $operations, string $routePrefix, string $module, string $removalLabel = 'marked inactive')
    {
        $operation = $this->bulkOperationForActor($key, $operations);
        $operations->progress($operation);
        if (! in_array($operation->status, ['succeeded', 'failed'], true)) {
            if (request()->boolean('progress_unavailable')) {
                return redirect()->route($routePrefix.'.index')->with('error', 'Unable to check saving progress. Your save may still be running. Do not submit it again; reopen Bulk Edit to check its status. Your draft has been retained.');
            }

            return redirect()->route($routePrefix.'.bulk-edit', ['page' => $operation->page, 'operation' => $key]);
        }
        if ($operation->acknowledged_at) {
            return redirect()->route($routePrefix.'.index');
        }
        $operation->update(['acknowledged_at' => now()]);
        if ($operation->status === 'failed') {
            return redirect()->route($routePrefix.'.bulk-edit', ['page' => $operation->page])
                ->withErrors($operation->errors ?: ['bulk' => 'Saving failed. Please try again.']);
        }
        $counts = $operation->counts;

        return redirect()->route($routePrefix.'.index')
            ->with('success', "Saved: {$counts['created']} new, {$counts['updated']} edited, {$counts['removed']} {$removalLabel}.")
            ->with($module.'_bulk_saved', true)->with($module.'_bulk_saved_draft_key', $operation->draft_key);
    }
}
