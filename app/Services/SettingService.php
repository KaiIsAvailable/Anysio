<?php
namespace App\Services;

use App\Models\{Setting, User};
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;

class SettingService
{
    public function getEffectiveSettings($user = null): array
    {
        if ($user) {
            if (!$user instanceof User) {
                $user = User::find($user);
            }
        } else {
            $user = get_effective_user();
        }
        
        // Fallback to config defaults if no user exists
        $defaultConfig = config('settings', []);
        $normalizedDefaults = [];
        
        foreach ($defaultConfig as $key => $defaultVal) {
            if (is_array($defaultVal) && array_key_exists('value', $defaultVal)) {
                $normalizedDefaults[$key] = $defaultVal;
            } else {
                $normalizedDefaults[$key] = [
                    'value' => $defaultVal,
                    'is_active' => true,
                ];
            }
        }

        if (!$user) {
            return $normalizedDefaults;
        }

        // Automatically load user settings relation
        $user->loadMissing('settings');

        $dbSettings = $user->settings->mapWithKeys(function ($setting) {
            $val = $setting->value;
            if ($setting->key === 'fee_types_config' && is_array($val) && !array_key_exists('value', $val)) {
                $val = ['value' => $val, 'is_active' => (bool) $setting->is_active];
            } else {
                $val = [
                    'value' => $setting->value,
                    'is_active' => (bool) $setting->is_active,
                ];
            }

            return [$setting->key => $val];
        })->toArray();

        Log::channel('testing')->info('Formatted Invoices Payload:', [
            'normalizedDefaults' => $normalizedDefaults,
            'dbSettings' => $dbSettings,
        ]);

        // Merge database settings over the top of config defaults
        $resolved = array_replace_recursive($normalizedDefaults, $dbSettings);
        // Category selections are a complete list, including an intentionally empty list.
        // Recursive array replacement otherwise silently restores default categories.
        $penaltyValue = data_get($dbSettings, 'late_penalty_config.value', []);
        if (is_array($penaltyValue) && array_key_exists('applicable_categories', $penaltyValue)) {
            $resolved['late_penalty_config']['value']['applicable_categories'] = $penaltyValue['applicable_categories'];
        }
        return $resolved;
    }

    /**
     * Check if a specific fee type is active based on stored configurations.
     */
    public function isFeeTypeActive($feeType, array $resolvedSettings = null, ?string $userId = null): bool
    {
        if (!$resolvedSettings && $userId) {
            $resolvedSettings = $this->getEffectiveSettings($userId);
        }

        $categoryValue = is_object($feeType->category) ? $feeType->category->value : $feeType->category;
        $slug = Str::slug(strtolower($categoryValue . '_' . $feeType->name), '_');

        return filter_var(data_get($resolvedSettings, "fee_types_config.value.{$slug}.is_active", true), FILTER_VALIDATE_BOOLEAN);
    }

    /**
     * Filter a fee types collection or query based on active settings.
     */
    public function filterActiveFeeTypes($feeTypes, string $userId)
    {
        $settings = $this->getEffectiveSettings($userId);

        $collection = $feeTypes instanceof \Illuminate\Database\Eloquent\Builder ? $feeTypes->get() : $feeTypes;

        return $collection->filter(function ($feeType) use ($settings) {
            $categoryValue = is_object($feeType->category) ? $feeType->category->value : $feeType->category;
            $slug = Str::slug(strtolower($categoryValue . '_' . $feeType->name), '_');

            // Explicitly pass true as the default fallback if the slug path is missing
            $isActive = data_get($settings, "fee_types_config.value.{$slug}.is_active", true);

            return filter_var($isActive, FILTER_VALIDATE_BOOLEAN);
        });
    }

    /**
     * Handle saving settings and fee types configurations during update requests.
     */
    public function saveSettings(string $userId, array $requestData, array $activeStates): void
    {
        // 1. Process standard settings
        if (isset($requestData['settings'])) {
            foreach ($requestData['settings'] as $key => $configValues) {
                $isActive = filter_var($activeStates[$key] ?? false, FILTER_VALIDATE_BOOLEAN);

                if ($key === 'late_penalty_config') {
                    $configValues['amount'] = round((float) ($configValues['amount'] ?? 0) * 100);
                    $configValues['maximum_amount'] = !empty($configValues['maximum_amount']) 
                        ? round((float) $configValues['maximum_amount'] * 100) 
                        : 0;

                    if (isset($configValues['applicable_categories']) && is_array($configValues['applicable_categories'])) {
                        $configValues['applicable_categories'] = array_values(
                            array_filter($configValues['applicable_categories'], fn($val) => !is_null($val) && $val !== '')
                        );
                    }

                    // SYNC HERE: Only sync fee_types_config if late_penalty_config is explicitly being saved
                    $feeTypesSetting = Setting::firstOrNew(['user_id' => $userId, 'key' => 'fee_types_config']);
                    $feeTypesValue = $feeTypesSetting->value ?? [];

                    if (isset($feeTypesValue['service_late_payment_penalty'])) {
                        $feeTypesValue['service_late_payment_penalty']['is_active'] = $isActive;
                        
                        Setting::updateOrCreate(
                            ['user_id' => $userId, 'key' => 'fee_types_config'],
                            [
                                'value' => $feeTypesValue, 
                                'is_active' => $feeTypesSetting->is_active ?? true
                            ]
                        );
                    }
                }

                Log::channel('testing')->info("Saving Setting: {$key}", [
                    'user_id' => $userId,
                    'value' => $configValues,
                    'is_active' => $isActive,
                ]);

                Setting::updateOrCreate(
                    ['user_id' => $userId, 'key' => $key],
                    ['value' => $configValues, 'is_active' => $isActive]
                );
            }
        }

        // 2. Process fee types config
        if (isset($requestData['fee_types_config'])) {
            $isActive = filter_var($activeStates['fee_types_config'] ?? true, FILTER_VALIDATE_BOOLEAN);

            // Check what the current late penalty master setting is
            $latePenaltySetting = Setting::where('user_id', $userId)->where('key', 'late_penalty_config')->first();
            $isLatePenaltyActive = $latePenaltySetting ? filter_var(data_get($latePenaltySetting->value, 'is_active', $latePenaltySetting->is_active), FILTER_VALIDATE_BOOLEAN) : false;

            // Fetch existing fee types from DB so we don't lose previous states
            $existingSetting = Setting::where('user_id', $userId)->where('key', 'fee_types_config')->first();
            $existingFeeTypes = $existingSetting->value ?? [];

            $feeTypesConfig = collect($requestData['fee_types_config'])->map(function ($feeType, $slug) use ($isLatePenaltyActive, $existingFeeTypes) {
                if ($slug === 'service_late_payment_penalty') {
                    $feeType['is_active'] = $isLatePenaltyActive;
                } else {
                    // If 'is_active' is missing or not provided in the request, fallback to its existing database state
                    $isActiveValue = $feeType['is_active'] ?? $existingFeeTypes[$slug]['is_active'] ?? false;
                    $feeType['is_active'] = filter_var($isActiveValue, FILTER_VALIDATE_BOOLEAN);
                }
                return $feeType;
            })->toArray();

            Log::channel('testing')->info('Saving Fee Types Config', [
                'user_id' => $userId,
                'value' => $feeTypesConfig,
                'is_active' => $isActive,
            ]);

            Setting::updateOrCreate(
                ['user_id' => $userId, 'key' => 'fee_types_config'],
                ['value' => $feeTypesConfig, 'is_active' => $isActive]
            );
        }

        // 3. Process due date config
        if (isset($requestData['due_date_config'])) {
            $isActive = filter_var($activeStates['due_date_config'] ?? true, FILTER_VALIDATE_BOOLEAN);
            
            $dueDateConfig = $requestData['due_date_config'];
            
            if (isset($dueDateConfig['days'])) {
                $dueDateConfig['days'] = (int) $dueDateConfig['days'];
            }
            if (isset($dueDateConfig['auto_generate'])) {
                $dueDateConfig['auto_generate'] = filter_var($dueDateConfig['auto_generate'], FILTER_VALIDATE_BOOLEAN);
            }

            Log::channel('testing')->info('Saving Due Date Config', [
                'user_id' => $userId,
                'value' => $dueDateConfig,
                'is_active' => $isActive,
            ]);

            Setting::updateOrCreate(
                ['user_id' => $userId, 'key' => 'due_date_config'],
                ['value' => $dueDateConfig, 'is_active' => $isActive]
            );
        }

        // 4. Process pending renewal config
        if (isset($requestData['pending_renewal_config'])) {
            $isActive = filter_var($activeStates['pending_renewal_config'] ?? true, FILTER_VALIDATE_BOOLEAN);
            
            $pendingRenewalConfig = $requestData['pending_renewal_config'];
            
            // Normalize and cast types safely
            if (isset($pendingRenewalConfig['number'])) {
                $pendingRenewalConfig['number'] = (int) $pendingRenewalConfig['number'];
            }
            if (isset($pendingRenewalConfig['days'])) {
                $pendingRenewalConfig['days'] = (int) $pendingRenewalConfig['days'];
            }

            Log::channel('testing')->info('Saving Pending Renewal Config', [
                'user_id' => $userId,
                'value' => $pendingRenewalConfig,
                'is_active' => $isActive,
            ]);

            Setting::updateOrCreate(
                ['user_id' => $userId, 'key' => 'pending_renewal_config'],
                ['value' => $pendingRenewalConfig, 'is_active' => $isActive]
            );
        }
    }

    public function calculateLatePenalty(string $userId, $dueDate, $totalAmount, $paymentDate = null, $invoiceItems = [])
    {
        return app(LatePenaltyCalculator::class)->calculate(
            $this->getEffectiveSettings($userId), $dueDate, $totalAmount, $paymentDate, $invoiceItems
        );
    }
}
