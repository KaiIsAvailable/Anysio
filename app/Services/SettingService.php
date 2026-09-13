<?php
namespace App\Services;

use App\Models\Setting;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class SettingService
{
    public function getEffectiveSettings($user = null): array
    {
        $user = get_effective_user();
        
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
            return [
                $setting->key => [
                    'value' => $setting->value,
                    'is_active' => (bool) $setting->is_active,
                ]
            ];
        })->toArray();

        Log::channel('testing')->info('Formatted Invoices Payload:', [
            'normalizedDefaults' => $normalizedDefaults,
            'dbSettings' => $dbSettings,
        ]);

        // Merge database settings over the top of config defaults
        return array_replace_recursive($normalizedDefaults, $dbSettings);
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

        return filter_var(data_get($resolvedSettings, "fee_types_config.{$slug}.is_active", true), FILTER_VALIDATE_BOOLEAN);
    }

    /**
     * Filter a fee types collection or query based on active settings.
     */
    public function filterActiveFeeTypes($feeTypes, string $userId)
    {
        $settings = $this->getEffectiveSettings($userId);

        // Supports both query builder and collections
        $collection = $feeTypes instanceof \Illuminate\Database\Eloquent\Builder ? $feeTypes->get() : $feeTypes;

        return $collection->filter(function ($feeType) use ($settings) {
            $categoryValue = is_object($feeType->category) ? $feeType->category->value : $feeType->category;
            $slug = Str::slug(strtolower($categoryValue . '_' . $feeType->name), '_');

            return filter_var(data_get($settings, "fee_types_config.{$slug}.is_active", true), FILTER_VALIDATE_BOOLEAN);
        });
    }

    /**
     * Handle saving settings and fee types configurations during update requests.
     */
    public function saveSettings(string $userId, array $requestData, array $activeStates): void
    {
        // 1. Resolve late penalty active state
        $latePenaltyActive = filter_var($activeStates['late_penalty_config'] ?? false, FILTER_VALIDATE_BOOLEAN);

        $feeTypesSetting = Setting::firstOrNew(['user_id' => $userId, 'key' => 'fee_types_config']);
        $feeTypesValue = $feeTypesSetting->value ?? [];

        // Check if the specific penalty fee exists, then update only its 'is_active' value
        if (isset($feeTypesValue['service_late_payment_penalty'])) {
            $feeTypesValue['service_late_payment_penalty']['is_active'] = $latePenaltyActive;

            Setting::updateOrCreate(
                ['user_id' => $userId, 'key' => 'fee_types_config'],
                [
                    'value' => $feeTypesValue, 
                    'is_active' => $feeTypesSetting->is_active ?? true
                ]
            );
        }

        // 1. Process standard settings
        if (isset($requestData['settings'])) {
            foreach ($requestData['settings'] as $key => $configValues) {
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
                }

                $isActive = ($key === 'late_penalty_config') 
                    ? $latePenaltyActive 
                    : filter_var($activeStates[$key] ?? false, FILTER_VALIDATE_BOOLEAN);

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

            $feeTypesConfig = collect($requestData['fee_types_config'])->map(function ($feeType, $slug) use ($isLatePenaltyActive) {
                // Always force service_late_payment_penalty to match the master late penalty state
                if ($slug === 'service_late_payment_penalty') {
                    $feeType['is_active'] = $isLatePenaltyActive;
                } elseif (isset($feeType['is_active'])) {
                    $feeType['is_active'] = filter_var($feeType['is_active'], FILTER_VALIDATE_BOOLEAN);
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

        if (isset($requestData['due_date_config'])) {
            $isActive = filter_var($activeStates['due_date_config'] ?? true, FILTER_VALIDATE_BOOLEAN);
            
            $dueDateConfig = $requestData['due_date_config'];
            
            // Normalize data types safely
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
    }

    public function calculateLatePenalty(string $userId, $dueDate, $totalAmount, $paymentDate = null, $invoiceItems = [])
    {
        $settings = $this->getEffectiveSettings($userId);

        $penaltySetting = $settings['late_penalty_config'] ?? [];
        $penaltySettingArray = is_array($penaltySetting) ? $penaltySetting : (array) $penaltySetting;

        // Check if penalty configuration is active
        $isActive = filter_var($penaltySettingArray['is_active'] ?? false, FILTER_VALIDATE_BOOLEAN);

        if (!$isActive) {
            // Return a zero penalty response so it doesn't calculate anything further
            return [
                'amount' => 0,
                'title' => 'Late Payment Penalty',
            ];
        }

        $config = $penaltySettingArray['value'] ?? [];
        $config = is_array($config) ? $config : (array) $config;
        $config['is_active'] = $isActive;

        Log::channel('testing')->info('', [
            'settings' => $settings,
            'config' => $config,
        ]);


        // Check applicable categories restriction
        $applicableCategories = $config['applicable_categories'] ?? [];

        // If applicable_categories is empty, disallow/skip penalties altogether
        if (empty($applicableCategories)) {
            return null; 
        }

        $hasMatchingCategory = collect($invoiceItems)->contains(function ($item) use ($applicableCategories) {
            $category = is_array($item) ? ($item['category'] ?? null) : ($item->feeType?->category ?? null);
            
            if ($category instanceof \BackedEnum) {
                $category = $category->value;
            } elseif (is_object($category)) {
                $category = $category->value ?? (string) $category;
            }

            return in_array($category, $applicableCategories);
        });

        if (!$hasMatchingCategory) {
            return null; 
        }

        $due = Carbon::parse($dueDate)->startOfDay();
        $graceDays = (int) ($config['grace_period_days'] ?? 0);
        $penaltyStartDate = $due->copy()->addDays($graceDays); // Due date + 0 days grace = Due date

        $today = $paymentDate ? Carbon::parse($paymentDate)->startOfDay() : Carbon::today();

        Log::channel('testing')->info('', [
            'due' => $due,
            'graceDays' => $graceDays,
            'penaltyStartDate' => $penaltyStartDate,
            'today' => $today,
        ]);

        if ($today->lte($penaltyStartDate)) {
            return null; // <-- Exits here!
        }

        $overdueDays = $penaltyStartDate->diffInDays($today);
        $frequency = max(1, (int) ($config['frequency'] ?? 1));
        $periods = floor($overdueDays / $frequency);

        if ($periods <= 0) {
            return null;
        }

        $type = $config['calculation_type'] ?? ($config['type'] ?? 'fixed');
        $maxAmountCents = (float) ($config['maximum_amount'] ?? 0);
        $maxAmount = $maxAmountCents > 0 ? $maxAmountCents / 100 : INF;

        $penaltyAmount = 0;
        $calculationText = '';

        if ($type === 'fixed') {
            $unitAmount = (float) ($config['amount'] ?? 0) / 100;
            $penaltyAmount = $unitAmount * $periods;
            $calculationText = sprintf('RM %.2f per %s (%d interval(s))', $unitAmount, $frequency > 1 ? $frequency . ' days' : 'day', $periods);
        } elseif ($type === 'percentage') {
            $rate = (float) ($config['rate'] ?? 0);
            $unitPenalty = $totalAmount * ($rate / 100);
            $penaltyAmount = $unitPenalty * $periods;
            $calculationText = sprintf('%.2f%% per %s (%d interval(s))', $rate, $frequency > 1 ? $frequency . ' days' : 'day', $periods);
        }

        if ($maxAmountCents > 0 && $penaltyAmount > $maxAmount) {
            $penaltyAmount = $maxAmount;
            $calculationText .= sprintf(' (Capped at RM %.2f)', $maxAmount);
        }

        return [
            'amount' => round($penaltyAmount, 2),
            'title' => 'Late Penalty',
            'calculation' => $calculationText . ($graceDays > 0 ? " [{$graceDays}d grace]" : '')
        ];
    }
}