<?php
namespace App\Http\Controllers;

use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class SettingsController extends Controller
{
    public function index()
    {
        $user = get_effective_user();
        $settings = Setting::getResolvedSettings($user->id);

        return view('adminSide.setting.index', compact('settings'));
    }

    public function update(Request $request)
    {
        $request->validate([
            'settings' => ['required', 'array'],
            'is_active' => ['nullable', 'array'],
        ]);

        $settingsData = $request->input('settings', []);
        $activeStates = $request->input('is_active', []);

        foreach ($settingsData as $key => $configValues) {
            // Apply custom transformations based on the setting key
            if ($key === 'late_penalty_config') {
                $configValues['amount'] = round((float) ($configValues['amount'] ?? 0) * 100);
                
                if (!empty($configValues['maximum_amount'])) {
                    $configValues['maximum_amount'] = round((float) $configValues['maximum_amount'] * 100);
                } else {
                    $configValues['maximum_amount'] = 0;
                }
            }

            // Determine active state from the toggle input
            $isActive = filter_var($activeStates[$key] ?? false, FILTER_VALIDATE_BOOLEAN);
            $userId = get_effective_user()->id;

            // Save per-user override dynamically
            Setting::updateOrCreate(
                [
                    'user_id' => $userId,
                    'key' => $key,
                ],
                [
                    'value' => $configValues,
                    'is_active' => $isActive,
                ]
            );
        }

        return redirect()->back()->with('status', 'settings-updated');
    }
}