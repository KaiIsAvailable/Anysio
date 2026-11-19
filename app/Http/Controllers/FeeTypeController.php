<?php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\FeeTypeService;
use Illuminate\Support\Facades\Gate;
use App\Models\FeeType;
use Illuminate\Validation\ValidationException;

class FeeTypeController extends Controller
{
    protected FeeTypeService $feeTypeService;

    public function __construct(FeeTypeService $feeTypeService)
    {
        $this->feeTypeService = $feeTypeService;
    }

    public function index(Request $request)
    {
        if (Gate::denies('super-admin') && Gate::denies('fee types.view')) return view('errors.403');
        $request->query->set('tab', 'fee-types');
        return app(SettingsController::class)->index(app(\App\Services\SettingService::class));
    }

    /**
     * Store a newly created fee type in storage.
     */
    public function store(Request $request)
    {
        if (Gate::denies('super-admin') && Gate::denies('leases.add fee type') && Gate::denies('fee types.create')) {
            return redirect()->back()->with('error', 'You have no permission.');
        }
        try {
            $validated = $request->validate(FeeTypeService::rules());
            $feeType = $this->feeTypeService->createFeeType($validated);
        } catch (\Illuminate\Auth\Access\AuthorizationException $exception) {
            return $this->permissionError($request);
        } catch (ValidationException $exception) {
            return $this->validationError($request, $exception);
        }

        session()->flash('success', 'Fee type added successfully.');

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'feeType' => $feeType,
                'session_status' => ['type' => 'success', 'message' => session('success')],
            ]);
        }

        return back()->with('success', 'Fee type added successfully.')
            ->with('fee_types_tab', $request->input('tab') === 'fee-types');
    }

    public function update(Request $request, FeeType $fee_type)
    {
        if (Gate::denies('super-admin') && Gate::denies('fee types.edit')) return back()->with('error', 'You have no permission.');
        try {
            $this->feeTypeService->update($fee_type, $request->all());
        } catch (\Illuminate\Auth\Access\AuthorizationException $exception) {
            return $this->permissionError($request);
        } catch (ValidationException $exception) {
            return $this->validationError($request, $exception);
        }
        return back()->with('success', 'Fee type updated successfully.')->with('fee_types_editing', $request->boolean('editingFees'))
            ->with('fee_types_tab', $request->input('tab') === 'fee-types');
    }

    public function changeStatus(Request $request, FeeType $fee_type)
    {
        if (Gate::denies('super-admin') && Gate::denies('fee types.change status')) return back()->with('error', 'You have no permission.');
        try {
            $data = $request->validate(['is_active' => ['required', 'boolean']]);
            $this->feeTypeService->changeStatus($fee_type, (bool) $data['is_active']);
        } catch (\Illuminate\Auth\Access\AuthorizationException $exception) {
            return $this->permissionError($request);
        } catch (ValidationException $exception) {
            return $this->validationError($request, $exception);
        }
        return back()->with('success', 'Fee type status updated successfully.')->with('fee_types_editing', $request->boolean('editingFees'));
    }

    public function updateBatch(Request $request)
    {
        if (Gate::denies('super-admin') && Gate::denies('fee types.edit') && Gate::denies('fee types.change status')) {
            return back()->with('error', 'You have no permission.');
        }
        try {
            $data = $request->validate([
                'fees' => ['required', 'array', 'min:1'],
                'fees.*' => ['required', 'array:id,name,original_price,sell_price,is_active', 'min:2'],
                'fees.*.id' => ['required', 'string', 'distinct'],
                'fees.*.name' => ['sometimes', ...FeeTypeService::rules()['name']],
                'fees.*.original_price' => FeeTypeService::rules()['original_price'],
                'fees.*.sell_price' => FeeTypeService::rules()['sell_price'],
                'fees.*.is_active' => ['sometimes', 'required', 'boolean'],
            ]);
            foreach ($data['fees'] as $entry) {
                $edits = array_intersect_key($entry, array_flip(['name', 'original_price', 'sell_price']));
                if (($edits && Gate::denies('super-admin') && Gate::denies('fee types.edit')) ||
                    (array_key_exists('is_active', $entry) && Gate::denies('super-admin') && Gate::denies('fee types.change status'))) {
                    return back()->with('error', 'You have no permission.');
                }
            }
            $fees = $this->feeTypeService->updateBatch($data['fees']);
        } catch (\Illuminate\Auth\Access\AuthorizationException $exception) {
            return $this->permissionError($request);
        } catch (ValidationException $exception) {
            return $this->validationError($request, $exception);
        }
        session()->flash('success', 'Fee types saved successfully.');
        if ($request->expectsJson()) {
            return response()->json(['success' => true, 'fees' => $fees,
                'session_status' => ['type' => 'success', 'message' => session('success')]]);
        }
        return back()->with('success', 'Fee types saved successfully.');
    }

    public function destroy(Request $request, FeeType $fee_type)
    {
        if (Gate::denies('super-admin') && Gate::denies('fee types.delete')) return back()->with('error', 'You have no permission.');
        try {
            $this->feeTypeService->delete($fee_type);
        } catch (\Illuminate\Auth\Access\AuthorizationException $exception) {
            return $this->permissionError($request);
        } catch (ValidationException $exception) {
            return $this->validationError($request, $exception);
        }
        session()->flash('success', 'Fee type deleted successfully.');
        if ($request->expectsJson()) {
            return response()->json(['success' => true,
                'session_status' => ['type' => 'success', 'message' => session('success')]]);
        }
        return back()->with('success', 'Fee type deleted successfully.');
    }

    private function permissionError(Request $request)
    {
        session()->flash('error', 'You have no permission.');
        if ($request->expectsJson()) {
            return response()->json(['success' => false, 'message' => session('error'),
                'session_status' => ['type' => 'error', 'message' => session('error')]], 403);
        }
        return back()->withInput()->with('error', session('error'));
    }

    private function validationError(Request $request, ValidationException $exception)
    {
        $message = collect($exception->errors())->flatten()->implode(' ');
        session()->flash('error', $message);
        if ($request->expectsJson()) {
            return response()->json(['success' => false, 'errors' => $exception->errors(), 'message' => $message,
                'session_status' => ['type' => 'error', 'message' => $message]], 422);
        }
        return back()->withErrors($exception->errors())->withInput()->with('error', $message);
    }
}
