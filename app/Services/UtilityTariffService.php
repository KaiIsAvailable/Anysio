<?php

namespace App\Services;

use App\Models\{Setting, User};
use Illuminate\Support\Facades\{DB, Validator};
use Illuminate\Validation\Rule;

class UtilityTariffService
{
    public function defaultWaterProfile(User $manager): array
    {
        return app(UtilityTariffManagementService::class)->resolve($manager, null, null, today()->toDateString());
    }

    public function waterAmount(array $profile, string $usage): int
    {
        $definition = $profile + ['minimum_policy' => 'monthly'];
        return app(UtilityCalculationService::class)->calculate($definition, $usage)['amount'];
    }

    public function profiles(array $settings): array
    {
        $profiles = config('utility_tariffs.profiles');
        foreach ($profiles as &$profile) {
            $profile['reference_available'] = false; $profile['verification_status'] = 'unverified';
            $profile['kind'] = 'official';
        }
        unset($profile);
        foreach (data_get($settings, 'utility_tariff_config.value.profiles', []) as $id => $saved) {
            if (isset($profiles[$id])) {
                // Tier lists replace entire schedules, including when their length changes.
                $reference = $profiles[$id];
                $pricing = array_intersect_key($saved, array_flip(['calculation', 'effective_date', 'tiers', 'capacity_rate', 'network_rate', 'minimum_charge', 'fixed_charge', 'fixed_waiver_up_to']));
                $profiles[$id] = array_replace($reference, $pricing);
                $profiles[$id]['customized'] = $profiles[$id] != $reference;
                if ($profiles[$id]['customized']) { $profiles[$id]['kind'] = 'custom'; $profiles[$id]['label'] = 'Landlord configured: '.$reference['label']; }
            }
        }
        return $profiles;
    }

    public function validate(array $input): array
    {
        $money = ['required', 'numeric', 'between:0,1000000', 'decimal:0,6'];
        $validator = Validator::make($input, [
            'profile_id' => ['required', Rule::in(array_keys(config('utility_tariffs.profiles')))],
            'calculation' => ['required', 'in:blocks,whole_usage,flat'],
            'effective_date' => ['nullable', 'date_format:Y-m-d'],
            'tiers' => ['required', 'array', 'min:1', 'max:12'],
            'tiers.*' => ['required', 'array:limit,rate'],
            'tiers.*.limit' => ['present', 'nullable', 'numeric', 'gt:0', 'max:1000000000', 'decimal:0,3'],
            'tiers.*.rate' => $money,
            'capacity_rate' => $money, 'network_rate' => $money,
            'minimum_charge' => $money, 'fixed_charge' => $money,
            'fixed_waiver_up_to' => ['present', 'nullable', 'numeric', 'between:0,1000000000', 'decimal:0,3'],
        ]);
        $validator->after(function ($validator) use ($input) {
            $tiers = $input['tiers'] ?? null;
            if (!is_array($tiers)) return;
            if (!array_is_list($tiers)) {
                $validator->errors()->add('tiers', 'Rate blocks must be an ordered list.');
                return;
            }
            if (($input['calculation'] ?? '') === 'flat' && count($tiers) !== 1) {
                $validator->errors()->add('tiers', 'A flat tariff must have exactly one unlimited block.');
            }
            $previous = 0;
            foreach ($tiers as $index => $tier) {
                if (!is_array($tier)) continue;
                $limit = $tier['limit'] ?? null;
                if ($index === count($tiers) - 1) {
                    if ($limit !== null && $limit !== '') $validator->errors()->add('tiers', 'The final block must have no upper limit.');
                } elseif (!is_numeric($limit) || UtilityDecimal::scaled($limit, 3) <= $previous) {
                    $validator->errors()->add('tiers', 'Block limits must increase and only the final block may be unlimited.');
                }
                $previous = $limit === null || $limit === '' ? $previous : UtilityDecimal::scaled($limit, 3);
            }
        });
        $data = $validator->validate();
        foreach (['capacity_rate', 'network_rate', 'minimum_charge', 'fixed_charge', 'fixed_waiver_up_to'] as $field) {
            $data[$field] = $data[$field] === null || $data[$field] === '' ? null : UtilityDecimal::format(UtilityDecimal::scaled($data[$field], $field === 'fixed_waiver_up_to' ? 3 : 6), $field === 'fixed_waiver_up_to' ? 3 : 6);
        }
        $data['tiers'] = array_map(fn ($tier) => [
            'limit' => ($tier['limit'] === null || $tier['limit'] === '') ? null : UtilityDecimal::format(UtilityDecimal::scaled($tier['limit'], 3), 3),
            'rate' => UtilityDecimal::format(UtilityDecimal::scaled($tier['rate'], 6), 6),
        ], $data['tiers']);
        return $data;
    }

    public function save(string $userId, array $input): void
    {
        $manager = User::findOrFail($userId);
        app(UtilityTariffManagementService::class)->authorizeCustom($manager);
        $data = $this->validate($input);
        $id = $data['profile_id'];
        unset($data['profile_id']);
        DB::transaction(function () use ($userId, $id, $data) {
            User::whereKey($userId)->lockForUpdate()->firstOrFail();
            $setting = Setting::firstOrNew(['user_id' => $userId, 'key' => 'utility_tariff_config']);
            $value = $setting->value ?? ['profiles' => []];
            $value['profiles'][$id] = $data;
            $setting->fill(['value' => $value, 'is_active' => true])->save();
            $tariff = \App\Models\UtilityTariff::where('management_user_id', $userId)->where('kind', 'custom')->where('reference_key', $id)->first();
            $reference = config('utility_tariffs.profiles.'.$id);
            app(UtilityTariffManagementService::class)->save(User::findOrFail($userId), [
                'id' => $tariff?->id, 'reference_key' => $id, 'name' => 'Landlord configured: '.$reference['label'],
                'effective_from' => $data['effective_date'] ?? today()->toDateString(),
                'reason' => 'Updated account-configured prices from legacy utility settings.',
                'contract_confirmed' => true,
                'definition' => $data + ['minimum_policy' => 'monthly'],
            ]);

        });
    }
}
