<?php
namespace App\Http\Controllers;

use App\FeeTypeCategory;
use App\Models\{FeeType, Owners, Invoice, Role};
use App\Services\{SettingService, InvoiceService};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{Log, Auth, Gate};
use Spatie\Permission\Models\{Permission};

class SettingsController extends Controller
{
    public function index(SettingService $settingService)
    {
        if (Gate::denies('owner-admin') && Gate::denies('profile menu.setting')) {
            return view('errors.403');
        }

        /** @var User $user */
        $user = get_effective_user();
        
        // 1. Get resolved settings via service
        $settings = $settingService->getEffectiveSettings($user->id);

        Log::channel('testing')->info('Resolved settings and fee_types_config:', $settings);

        $feeTypesQuery = FeeType::query()
            ->where('is_active', true)
            ->where(function ($query) use ($user) {
                $query->where('is_system', true);
                if ($user->role === 'ownerAdmin') {
                    $query->orWhere('user_id', $user->id);
                } elseif ($user->role === 'agentAdmin') {
                    $managedOwnerIds = Owners::where('agent_id', $user->id)->select('user_id');
                    $query->orWhere('user_id', $user->id)
                        ->orWhereIn('user_id', $managedOwnerIds);
                }
            });

        $feeTypes = $feeTypesQuery
            ->orderBy('category')
            ->orderBy('name')
            ->get();

        $rentFeeTypes       = $feeTypes->where('category', 'rent')->values();
        $depositFeeTypes    = $feeTypes->where('category', 'deposit')->values();
        $utilityFeeTypes    = $feeTypes->where('category', 'utility')->values();
        $serviceFeeTypes    = $feeTypes->where('category', 'service')->values();
        $penaltyFeeTypes    = $feeTypes->where('category', 'penalty')->values();
        $managementFeeTypes = $feeTypes->where('category', 'management')->values();

        $feeTypeCategoryOptions = collect(FeeTypeCategory::cases())->mapWithKeys(fn ($category) => [
            $category->value => ucfirst($category->value)
        ])->toArray();

        $roles = Role::where('team_id', $user->id)->get();

        $modules = config('permissions.modules', []);
    
        foreach ($modules as $moduleName => $actions) {
            foreach ($actions as $action) {
                Permission::firstOrCreate([
                    'name' => "{$moduleName}.{$action}",
                    'guard_name' => 'web'
                ]);
            }
        }

        return view('adminSide.setting.index', compact(
            'settings',
            'rentFeeTypes',
            'depositFeeTypes',
            'utilityFeeTypes',
            'serviceFeeTypes',
            'penaltyFeeTypes',
            'managementFeeTypes',
            'feeTypeCategoryOptions',
            'roles',
            'modules',
        ));
    }

    public function update(Request $request, SettingService $settingService)
    {
        $request->validate([
            'settings' => ['nullable', 'array'],
            'fee_types_config' => ['nullable', 'array'],
            'due_date_config' => ['nullable', 'array'],
            'pending_renewal_config' => ['nullable', 'array'],
            'is_active' => ['nullable', 'array'],
        ]);

        $userId = get_effective_user()->id;
        $activeStates = $request->input('is_active', []);

        // Delegate persistence and configuration rule logic to the service
        $settingService->saveSettings(
            $userId, 
            $request->only(['settings', 'fee_types_config', 'due_date_config', 'pending_renewal_config']), 
            $activeStates
        );

        $activeTab = $request->input('tab') ?? $request->query('tab');

        if ($activeTab) {
            $previousUrl = strtok(url()->previous(), '?');
            return redirect()->to($previousUrl . '?tab=' . $activeTab)->with('status', 'settings-updated');
        }

        return redirect()->back()->with('status', 'settings-updated');
    }

    public function calculatePenaltyPreview(Request $request, Invoice $invoice, SettingService $settingService)
    {
        // Ensure you pass the correct user identifier linked to this setting or lease
        $userId = get_effective_user()->id;

        $penalty = $settingService->calculateLatePenalty(
            $userId,
            $request->input('due_date'),
            $request->input('total_amount'),
            $request->input('payment_date'),
            $invoice->items()->with('feeType')->get()
        );

        Log::channel('testing')->info('', [
            'userId' => $userId,
            'penalty' => $penalty,
            'request' => $request->all(),
        ]);

        return response()->json($penalty);
    }

    public function userRolePermissions()
    {
        $roles = Role::all();
        $modules = config('permissions.modules', []);

        return view('adminSide.settings.user-role-permissions', compact('roles', 'modules'));
    }

    public function updateRolePermissions(Request $request)
    {
        if (Gate::denies('owner-admin') && Gate::denies('settings.edit user role')) {
            return redirect()->back()->with('error', 'You have no permission.');
        }

        $submittedData = $request->input('permissions', []);
        $effectiveBossId = get_effective_user()?->id;

        // 📝 Log incoming request data
        Log::channel('testing')->info('updateRolePermissions Request Received', [
            'effective_boss_id' => $effectiveBossId,
            'submitted_data' => $submittedData,
        ]);

        // 1. Fetch ALL roles for this team so we catch roles where everything was unchecked
        $roles = Role::where('team_id', $effectiveBossId)->get();

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
            ->with('status', 'permissions-updated');
    }
}