<?php
namespace App\Services;

use App\Models\{Setting, User};
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;

class SettingService
{
    public function getSystemEarlyPaymentSettings(): array
    {
        $admin = User::where('role', User::ROLE_ADMIN)->oldest()->orderBy('id')->first();

        return $admin
            ? $this->getEffectiveSettings($admin)['early_payment_config']
            : ['value' => config('settings.early_payment_config'), 'is_active' => true];
    }

    public function saveSystemEarlyPaymentSettings(array $data): void
    {
        $admin = User::where('role', User::ROLE_ADMIN)->oldest()->orderBy('id')->firstOrFail();
        $number = (int) $data['number'];
        $days = $number * match ($data['mode']) {
            'months' => 30,
            'years' => 365,
            default => 1,
        };
        Setting::updateOrCreate(['user_id' => $admin->id, 'key' => 'early_payment_config'], [
            'value' => ['number' => $number, 'mode' => $data['mode'], 'days' => $days],
            'is_active' => (bool) $data['is_active'],
        ]);
    }

    public static function recurringInvoiceRules(): array
    {
        return [
            'due_date_config' => ['required', 'array:days,recurring_type,recurring_feq,recurring_feq_days'],
            'due_date_config.days' => ['sometimes', 'required', 'integer', 'between:0,365'],
            'due_date_config.recurring_type' => ['sometimes', 'required', 'in:auto,manual,both'],
            'due_date_config.recurring_feq' => ['sometimes', 'required', 'in:before,after'],
            'due_date_config.recurring_feq_days' => ['sometimes', 'required', 'integer', 'between:0,31'],
        ];
    }

    public function allowsManualRecurringInvoice($user): bool
    {
        return data_get($this->getEffectiveSettings($user), 'due_date_config.value.recurring_type') !== 'auto';
    }

    /** Check this before looking up wallets or outstanding invoices for auto-payment. */
    public function allowsAutomaticWalletPayment($user): bool
    {
        $settings = $this->getEffectiveSettings($user);

        return filter_var(data_get($settings, 'payment_config.is_active', false), FILTER_VALIDATE_BOOLEAN)
            && filter_var(data_get($settings, 'payment_config.value.auto_pay_from_wallet', false), FILTER_VALIDATE_BOOLEAN);
    }

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

        //Log::channel('testing')->info('Formatted Invoices Payload:', [
        //    'normalizedDefaults' => $normalizedDefaults,
        //    'dbSettings' => $dbSettings,
        //]);

        // Merge database settings over the top of config defaults
        $resolved = array_replace_recursive($normalizedDefaults, $dbSettings);
        // Each saved tariff schedule replaces its tiers as a whole, even if shorter.
        foreach (data_get($dbSettings, 'utility_tariff_config.value.profiles', []) as $id => $profile) {
            $defaults = data_get($normalizedDefaults, 'utility_tariff_config.value.profiles.'.$id, []);
            $resolved['utility_tariff_config']['value']['profiles'][$id] = array_replace($defaults, $profile);
        }
        // Category selections are a complete list, including an intentionally empty list.
        // Recursive array replacement otherwise silently restores default categories.
        $penaltyValue = data_get($dbSettings, 'late_penalty_config.value', []);
        if (is_array($penaltyValue) && array_key_exists('applicable_categories', $penaltyValue)) {
            $resolved['late_penalty_config']['value']['applicable_categories'] = $penaltyValue['applicable_categories'];
        }
        // A saved multi-select replaces the default list rather than merging its indexes.
        $processingValue = data_get($dbSettings, 'processing_fee_config.value', []);
        if (is_array($processingValue) && array_key_exists('applicable_payment_method', $processingValue)) {
            $resolved['processing_fee_config']['value']['applicable_payment_method'] = $processingValue['applicable_payment_method'];
        }
        return $resolved;
    }

    /**
     * Check if a specific fee type is active based on stored configurations.
     */
    public function isFeeTypeActive($feeType, array $resolvedSettings = null, ?string $userId = null): bool
    {
        if (!$feeType->is_active) return false;
        if ($resolvedSettings === null) $resolvedSettings = $this->getEffectiveSettings($userId);
        return filter_var(data_get($resolvedSettings, 'fee_types_config.value.'.$feeType->settingKey().'.is_active', true), FILTER_VALIDATE_BOOLEAN);
    }

    /**
     * Filter a fee types collection or query based on active settings.
     */
    public function filterActiveFeeTypes($feeTypes, string $userId)
    {
        $settings = $this->getEffectiveSettings($userId);

        $collection = $feeTypes instanceof \Illuminate\Database\Eloquent\Builder ? $feeTypes->get() : $feeTypes;

        return $collection->filter(fn ($feeType) => $this->isFeeTypeActive($feeType, $settings));
    }

    /**
     * Handle saving settings and fee types configurations during update requests.
     */
    public function saveSettings(string $userId, array $requestData, array $activeStates): void
    {
        // This independent form may only write this account's due-date settings.
        if (array_key_exists('due_date_config', $requestData)) {
            $validated = \Illuminate\Support\Facades\Validator::make(
                $requestData, self::recurringInvoiceRules()
            )->validate()['due_date_config'];
            \Illuminate\Support\Facades\DB::transaction(function () use ($userId, $validated) {
                // Lock the owner as well: there may not be a settings row yet.
                User::whereKey($userId)->lockForUpdate()->firstOrFail();
                $setting = Setting::firstOrNew(['user_id' => $userId, 'key' => 'due_date_config']);
                foreach (['days', 'recurring_feq_days'] as $key) {
                    if (array_key_exists($key, $validated)) $validated[$key] = (int) $validated[$key];
                }
                $setting->value = array_replace(config('settings.due_date_config'), $setting->value ?? [], $validated);
                $setting->is_active = $setting->exists ? $setting->is_active : true;
                $setting->save();
            });
            return;
        }

        // 1. Process standard settings
        if (isset($requestData['settings'])) {
            foreach ($requestData['settings'] as $key => $configValues) {
                $isActive = filter_var($activeStates[$key] ?? false, FILTER_VALIDATE_BOOLEAN);

                if ($key === 'processing_fee_config') {
                    $configValues['processing amount'] = round((float) $configValues['processing amount'], 2);
                    $configValues['applicable_payment_method'] = array_values($configValues['applicable_payment_method']);
                }

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

        if (isset($requestData['fee_types_config'])) {
            app(FeeTypeService::class)->saveActivationSettings($userId, $requestData['fee_types_config']);
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

        // 5. Process payment config
        if (isset($requestData['payment_config'])) {
            $isActive = filter_var($activeStates['payment_config'] ?? true, FILTER_VALIDATE_BOOLEAN);
            
            $storedConfig = Setting::where('user_id', $userId)->where('key', 'payment_config')->first();
            $paymentConfig = array_replace(
                config('settings.payment_config', []),
                $storedConfig?->value ?? [],
                $requestData['payment_config']
            );
            
            // Normalize and cast types safely
            foreach (['allow_unpaid_penalty', 'auto_pay_from_wallet'] as $key) {
                $paymentConfig[$key] = filter_var($paymentConfig[$key] ?? false, FILTER_VALIDATE_BOOLEAN);
            }

            Log::channel('testing')->info('Saving Payment Config', [
                'user_id' => $userId,
                'value' => $paymentConfig,
                'is_active' => $isActive,
            ]);

            Setting::updateOrCreate(
                ['user_id' => $userId, 'key' => 'payment_config'],
                ['value' => $paymentConfig, 'is_active' => $isActive]
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
