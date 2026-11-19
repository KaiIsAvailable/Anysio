<?php
namespace App\Http\Controllers;

use App\FeeTypeCategory;
use App\Models\{Invoice, Role};
use App\Services\{SettingService, InvoiceService, FeeTypeService};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{Log, Auth, Gate};
use Spatie\Permission\Models\{Permission};

class SettingsController extends Controller
{
    public function index(SettingService $settingService)
    {
        $feeTypesRequest = request('tab') === 'fee-types';
        $canFeeTypes = Gate::any(['super-admin', 'fee types.view']);
        if ($feeTypesRequest && !$canFeeTypes) return view('errors.403');
        if ($feeTypesRequest && Gate::denies('super-admin') && Gate::denies('profile menu.setting')) {
            return view('adminSide.setting.index', app(FeeTypeService::class)->settingsData(request()->only('search', 'category')) + [
                'settings' => $settingService->getEffectiveSettings(), 'feeTypesOnly' => true, 'utilityOnly' => true,
            ]);
        }
        $utilityRequest = request('tab') === 'utility';
        $canUtilityTariffs = Gate::any(['super-admin', 'settings.utility tab', 'settings.edit utility meter prices'])
            || (Gate::allows('utilities.tab') && Gate::allows('utilities.tariff tab'));
        if ($utilityRequest && !$canUtilityTariffs) return view('errors.403');

        // Utilities-only staff and platform account selection use the same settings view,
        // without loading unrelated payment settings or staff-role data.
        if ($utilityRequest && (Gate::allows('super-admin') || Gate::denies('profile menu.setting'))) {
            $tariffManager = app(\App\Services\UtilityBillingAccess::class)->manager(request(), true);
            return view('adminSide.setting.index', ['utilityOnly' => true, 'tariffManager' => $tariffManager,
                'settings' => $settingService->getEffectiveSettings($tariffManager),
                'feeTypeSettings' => $canFeeTypes ? $settingService->getEffectiveSettings() : []] +
                ($canFeeTypes ? app(FeeTypeService::class)->settingsData(request()->only('search', 'category')) : []));
        }

        if (Gate::allows('super-admin')) {
            return app(AccountPermissionController::class)->index(request());
        }

        if (Gate::denies('super-admin') && Gate::denies('profile menu.setting')) {
            return view('errors.403');
        }

        /** @var User $user */
        $user = get_effective_user();
        
        // 1. Get resolved settings via service
        $settings = $settingService->getEffectiveSettings($user->id);

        Log::channel('testing')->info('Resolved settings and fee_types_config:', $settings);

        $feeTypeCategoryOptions = collect(FeeTypeCategory::cases())->mapWithKeys(fn ($category) => [
            $category->value => ucfirst($category->value)
        ])->toArray();

        $roles = Role::where('team_id', $user->id)->get();

        $modules = config('permissions.modules', []);
    
        foreach ($modules as $moduleName => $actions) {
            foreach ($actions as $key => $value) {
                if (is_array($value)) {
                    // 1. Create the parent action (e.g., 'invoice.record payment')
                    Permission::firstOrCreate([
                        'name' => "{$moduleName}.{$key}",
                        'guard_name' => 'web'
                    ]);

                    // 2. Loop and create each nested sub-action (e.g., 'invoice.edit penalty')
                    foreach ($value as $subAction) {
                        Permission::firstOrCreate([
                            'name' => "{$moduleName}.{$subAction}",
                            'guard_name' => 'web'
                        ]);
                    }
                } else {
                    // Standard flat action (e.g., 'invoice.tab', 'invoice.void')
                    Permission::firstOrCreate([
                        'name' => "{$moduleName}.{$value}",
                        'guard_name' => 'web'
                    ]);
                }
            }
        }

        return view('adminSide.setting.index', compact(
            'settings',
            'feeTypeCategoryOptions',
            'roles',
            'modules',
        ) + ($canFeeTypes ? app(FeeTypeService::class)->settingsData(request()->only('search', 'category')) : []));
    }

    public function update(Request $request, SettingService $settingService)
    {
        if ($request->has('utility_tariff_config')) {
            if (Gate::denies('super-admin') && Gate::denies('settings.edit utility meter prices')) {
                return redirect()->back()->with('error', 'You have no permission.');
            }
            $request->validate(['utility_tariff_config' => ['required', 'array']]);
            app(\App\Services\UtilityTariffService::class)->save(get_effective_user()->id, $request->input('utility_tariff_config'));
            return redirect()->route('admin.settings.index', ['tab' => 'utility', 'tariff' => $request->input('utility_tariff_config.profile_id')])
                ->with('success', 'Utility meter prices updated successfully.');
        }
        if ($request->has('due_date_config')) {
            if (Gate::denies('super-admin') && Gate::denies('settings.edit recurring invoice')) {
                return redirect()->back()->with('error', 'You have no permission.');
            }
            $request->validate(SettingService::recurringInvoiceRules());
        }
        $request->validate([
            'settings' => ['nullable', 'array:late_penalty_config,processing_fee_config'],
            'settings.processing_fee_config' => ['sometimes', 'required', 'array:processing amount,applicable_payment_method'],
            'settings.processing_fee_config.processing amount' => ['required_with:settings.processing_fee_config', 'numeric', 'min:0', 'decimal:0,2'],
            'settings.processing_fee_config.applicable_payment_method' => ['required_with:settings.processing_fee_config', 'array', 'min:1'],
            'settings.processing_fee_config.applicable_payment_method.*' => ['required', 'distinct', \Illuminate\Validation\Rule::enum(\App\Enums\PaymentMethod::class)],
            'is_active.processing_fee_config' => ['sometimes', 'required', 'boolean'],
            'settings.due_date_config' => ['prohibited'],
            'fee_types_config' => ['nullable', 'array'],
            'due_date_config' => ['nullable', 'array'],
            'pending_renewal_config' => ['nullable', 'array'],
            'payment_config' => ['nullable', 'array:allow_unpaid_penalty,auto_pay_from_wallet'],
            'payment_config.allow_unpaid_penalty' => ['sometimes', 'required', 'boolean'],
            'payment_config.auto_pay_from_wallet' => ['sometimes', 'required', 'boolean'],
            'is_active' => ['nullable', 'array'],
        ]);

        // Check every submitted section, including requests sent without the UI.
        foreach ([
            'settings.late_penalty_config' => 'settings.edit late payment',
            'settings.processing_fee_config' => 'settings.edit payment collection',
            'fee_types_config' => 'fee types.change status',
            'pending_renewal_config' => 'settings.edit pending renewal',
            'payment_config' => 'settings.edit payment collection',
        ] as $field => $permission) {
            if ($request->has($field) && Gate::denies('super-admin') && Gate::denies($permission)) {
                return redirect()->back()->with('error', 'You have no permission.');
            }
        }

        $userId = get_effective_user()->id;
        $activeStates = $request->input('is_active', []);

        // Delegate persistence and configuration rule logic to the service
        try {
            $settingService->saveSettings(
                $userId,
                $request->only(['settings', 'fee_types_config', 'due_date_config', 'pending_renewal_config', 'payment_config']),
                $activeStates
            );
        } catch (\Illuminate\Auth\Access\AuthorizationException $exception) {
            return back()->withInput()->with('error', 'You have no permission.');
        }

        $activeTab = $request->input('tab') ?? $request->query('tab');

        if ($activeTab) {
            $previousUrl = strtok(url()->previous(), '?');
            return redirect()->to($previousUrl . '?tab=' . $activeTab)->with('status', 'settings-updated');
        }

        return redirect()->back()->with('status', 'settings-updated');
    }

    public function calculatePenaltyPreview(Request $request, Invoice $invoice, \App\Services\InvoicePenaltyService $penalties)
    {
        $data = $request->validate(['payment_date' => ['required', 'date', 'before_or_equal:today']]);
        try {
            \Illuminate\Support\Facades\DB::transaction(fn () => app(\App\Services\RefundService::class)->assertInvoicePaymentAllowed($invoice));
            if ($invoice->amount_balance <= 0 || $invoice->isVoid()) {
                throw \Illuminate\Validation\ValidationException::withMessages(['invoice' => 'This invoice is already settled or void. No payment can be recorded.']);
            }
            return response()->json($penalties->quote($invoice, $data['payment_date']));
        } catch (\Illuminate\Validation\ValidationException $exception) {
            session()->flash('error', implode(' ', \Illuminate\Support\Arr::flatten($exception->errors())));
                if ($request->expectsJson()) {
                    return response()->json(['message' => session('error'), 'errors' => $exception->errors(),
                        'session_status' => ['type' => 'error', 'message' => session('error')]], 422);
                }
            throw $exception;
        }
    }

    public function userRolePermissions()
    {
        return redirect()->route('admin.settings.index', ['tab' => 'permissions']);
    }

    public function updateRolePermissions(Request $request)
    {
        if (Gate::denies('super-admin') && Gate::denies('settings.edit user role')) {
            return redirect()->back()->with('error', 'You have no permission.');
        }

        $request->validate([
            'permissions' => ['sometimes', 'array'],
            'permissions.*' => ['array'],
            'permissions.*.*' => ['in:0,1'],
        ]);
        $submittedData = $request->input('permissions', []);
        $manager = get_effective_user();
        $effectiveBossId = $manager?->id;
        $allowed = $manager ? app(\App\Services\AccountPermissionService::class)->delegatablePermissions($manager) : [];

        // 📝 Log incoming request data
        Log::channel('testing')->info('updateRolePermissions Request Received', [
            'effective_boss_id' => $effectiveBossId,
            'submitted_data' => $submittedData,
        ]);

        // 1. Fetch ALL roles for this team so we catch roles where everything was unchecked
        $roles = Role::where('team_id', $effectiveBossId)->get();

        // Validate every role before changing any, so a forged later entry cannot
        // leave earlier roles partially updated or cross workspace boundaries.
        if (array_diff(array_keys($submittedData), $roles->modelKeys())) {
            return redirect()->back()->withInput()->with('error', 'You have no permission.');
        }
        foreach ($submittedData as $rolePermissions) {
            $requested = array_keys(array_filter($rolePermissions, fn ($value) => $value == '1'));
            if (array_diff($requested, $allowed)) {
                return redirect()->back()->withInput()->with('error', 'You have no permission.');
            }
        }

        foreach ($roles as $role) {
            // Grab submitted permissions for this role, or default to an empty array if all were unchecked
            $rolePermissions = $submittedData[$role->id] ?? [];

            $activePermissions = array_keys(array_filter($rolePermissions, function ($value) {
                return $value == '1';
            }));

            // 2. This will now properly wipe out permissions when $activePermissions is empty []
            $role->syncPermissions($activePermissions);

            // 📝 Log each role's sync result
            Log::channel('testing')->info('Role Permissions Synced', [
                'role_id' => $role->id,
                'role_name' => $role->name,
                'synced_permissions' => $activePermissions,
            ]);
        }

        $activeTab = $request->input('active_tab', 'permissions');
        $roleTab = $request->input('role_tab');

        $previousUrl = strtok(url()->previous(), '?');
        
        return redirect()->to($previousUrl . '?tab=' . $activeTab . '&role_tab=' . $roleTab)
            ->with('success', 'Staff role permissions updated successfully.');
    }
}
