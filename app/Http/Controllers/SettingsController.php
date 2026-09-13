<?php
namespace App\Http\Controllers;

use App\FeeTypeCategory;
use App\Models\{FeeType, Owners, Invoice};
use App\Services\{SettingService, InvoiceService};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class SettingsController extends Controller
{
    public function index(SettingService $settingService)
    {
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

        return view('adminSide.setting.index', compact(
            'settings',
            'rentFeeTypes',
            'depositFeeTypes',
            'utilityFeeTypes',
            'serviceFeeTypes',
            'penaltyFeeTypes',
            'managementFeeTypes',
            'feeTypeCategoryOptions',
        ));
    }

    public function update(Request $request, SettingService $settingService)
    {
        $request->validate([
            'settings' => ['nullable', 'array'],
            'fee_types_config' => ['nullable', 'array'],
            'due_date_config' => ['nullable', 'array'],
            'is_active' => ['nullable', 'array'],
        ]);

        $userId = get_effective_user()->id;
        $activeStates = $request->input('is_active', []);

        // Delegate persistence and configuration rule logic to the service
        $settingService->saveSettings(
            $userId, 
            $request->only(['settings', 'fee_types_config', 'due_date_config']), 
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
}