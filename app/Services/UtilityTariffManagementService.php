<?php
namespace App\Services;

use App\Models\{Setting, User, UtilityService, UtilityTariff, UtilityTariffVersion, UtilityTariffAssignment};
use Illuminate\Support\Facades\{Auth, DB, Gate, Validator};
use Illuminate\Validation\{Rule, ValidationException};

class UtilityTariffManagementService
{
    public function authorizeCustom(User $manager, string $permission = 'utilities.edit tariffs'): void
    {
        abort_unless(in_array($manager->role, [User::ROLE_OWNER_ADMIN, User::ROLE_AGENT_ADMIN])
            && !in_array(Auth::user()?->role, [User::ROLE_TENANT, User::ROLE_OWNER])
            && Gate::any(array_merge(['super-admin', $permission], $permission === 'utilities.edit tariffs' ? ['settings.edit utility meter prices'] : [])), 403);
        abort_unless(Gate::allows('super-admin') || get_effective_user()?->id === $manager->id, 403);
    }

    public function snapshot(UtilityTariffVersion $version): array
    {
        $tariff = $version->tariff;
        $metadata = $version->definition['_metadata'] ?? [];
        if (!$metadata && isset($version->definition['label'])) {
            $parts = explode(' ? ', $version->definition['label']);
            $metadata = ['name' => $version->definition['label'], 'state' => $parts[0] ?? null,
                'provider' => $parts[1] ?? null, 'customer_category' => $parts[2] ?? null];
        }
        return array_replace($version->definition, [
            'profile_id' => $tariff->reference_key, 'tariff_id' => $tariff->id, 'tariff_version_id' => $version->id,
            'version' => $version->version, 'label' => $metadata['name'] ?? $tariff->name, 'kind' => $tariff->kind,
            'unit' => 'm³', 'utility_type' => $tariff->utility_type,
            'provider' => $metadata['provider'] ?? $tariff->provider, 'state' => $metadata['state'] ?? $tariff->state,
            'customer_category' => $metadata['customer_category'] ?? $tariff->customer_category,
            'source' => $version->source_url, 'effective_date' => $version->effective_from?->toDateString(),
            'verified_at' => $version->verified_at?->toIso8601String(), 'verified_by' => $version->verified_by,
            'verification_status' => $version->verified_at ? 'verified' : 'unverified',
            'billable' => $tariff->is_active && ($tariff->kind === 'custom' || $version->verified_at !== null),
            'customized' => $tariff->kind === 'custom', 'reference_available' => $version->verified_at !== null,
        ]);
    }

    public function catalog(User $manager): array
    {
        $default = $this->timeline($manager)['default'];
        $tariffs = UtilityTariff::visibleTo($manager->id)->where('utility_type', 'water')
            ->where(fn ($q) => $q->where('kind', 'custom')->orWhereNull('reference_key')
                ->orWhereIn('reference_key', config('utility_tariffs.visible_profiles', ['water_perak_domestic'])))
            ->with(['versions' => fn ($q) => $q->orderByDesc('version')])->orderBy('kind')->orderBy('name')->get();
        $result = [];
        foreach ($tariffs as $tariff) {
            $latest = $tariff->versions->first();
            if (!$latest) continue;
            $result[$tariff->id] = $this->snapshot($latest) + [
                'is_default' => $tariff->id === $default,
                'is_active' => $tariff->is_active,
                'history' => $tariff->versions->map(fn ($v) => ['version' => $v->version, 'effective_from' => $v->effective_from?->toDateString(),
                    'verified_at' => $v->verified_at?->toDateTimeString(), 'created_by' => $v->created_by,
                    'created_at' => $v->created_at?->toDateTimeString(), 'reason' => $v->reason,
                    'definition' => $v->definition, 'source_url' => $v->source_url])->all(),
            ];
        }
        return $result;
    }

    public function resolve(User $manager, ?UtilityService $service, ?string $from, string $to, ?array $saved = null): array
    {
        return $this->resolveTimeline($this->timeline($manager), $service?->id ?? '', $from, $to, $saved);
    }

    public function timeline(User $manager): array
    {
        $settings = app(SettingService::class)->getEffectiveSettings($manager);
        $key = data_get($settings, 'utility_tariff_config.value.default_profile', 'water_perak_domestic');
        $default = data_get($settings, 'utility_tariff_config.value.default_tariff_id');
        if (!$default) {
            $legacy = Setting::where('user_id', $manager->id)->where('key', 'utility_tariff_config')->first();
            $hasLegacyOverride = is_array(data_get($legacy?->value, 'profiles.'.$key));
            $default = UtilityTariff::visibleTo($manager->id)->where('reference_key', $key)
                ->when(!$hasLegacyOverride, fn ($q) => $q->where('kind', 'official'))
                ->orderByRaw('management_user_id IS NULL')->orderBy('id')->value('id');
        }
        $assignments = UtilityTariffAssignment::where('management_user_id', $manager->id)->orderBy('effective_from')->get();
        $ids = $assignments->pluck('utility_tariff_id')->push($default)->filter()->unique();
        $tariffs = UtilityTariff::visibleTo($manager->id)->whereIn('id', $ids)->with(['versions' => fn ($q) => $q->orderByDesc('effective_from')->orderByDesc('version')])->get();
        $catalog = [];
        foreach ($tariffs as $tariff) $catalog[$tariff->id] = $tariff->versions->map(fn ($v) => $this->snapshot($v))->all();
        return ['default' => $default, 'versions' => $catalog, 'assignments' => $assignments->map(fn ($a) => [
            'service_id' => $a->utility_service_id, 'tariff_id' => $a->utility_tariff_id, 'effective_from' => $a->effective_from->toDateString(),
        ])->all()];
    }

    public function resolveTimeline(array $plan, string $serviceId, ?string $from, string $to, ?array $saved = null): array
    {
        $assignments = array_values(array_filter($plan['assignments'], fn ($a) => $a['service_id'] === $serviceId));
        $id = $plan['default'];
        foreach ($assignments as $assignment) if ($assignment['effective_from'] <= $to) $id = $assignment['tariff_id'];
        $versions = $plan['versions'][$id] ?? [];
        $version = collect($versions)->first(fn ($v) => $v['effective_date'] && $v['effective_date'] <= $to);
        if (!$version) {
            $version = $versions[0] ?? ['label' => 'No tariff assigned', 'calculation' => 'blocks', 'kind' => 'official', 'tiers' => []];
            return array_replace($version, ['billable' => false, 'review_reason' => 'No tariff is effective for this reading date.']);
        }
        if ($saved && isset($saved['tariff_version_id'])) {
            // An unchanged saved charge keeps its version; an explicit Recalculate action selects a newer version.
            return $saved;
        }
        $changed = $from && ($version['effective_date'] > $from || collect($assignments)->contains(fn ($a) => $a['effective_from'] > $from && $a['effective_from'] <= $to));
        if ($changed) return array_replace($version, ['billable' => false, 'review_reason' => 'The tariff changes inside this interval. Intermediate readings or an actual provider bill are required.']);
        if (!($version['billable'] ?? false)) $version['review_reason'] = $version['kind'] === 'official'
            ? 'Unverified official reference: request administrator verification or select a contractual landlord tariff.' : 'The assigned tariff is disabled.';
        return $version;
    }

    public function validateDefinition(array $input): array
    {
        if (in_array($input['calculation'] ?? '', ['fixed', 'actual_bill'])) $input['tiers'] = [];
        $data = Validator::make($input, [
            'calculation' => ['required', 'in:blocks,flat,fixed,actual_bill,whole_usage'],
            'minimum_policy' => ['required', 'in:monthly,positive_usage,none'],
            'minimum_charge' => ['required', 'numeric', 'between:0,1000000', 'decimal:0,6'],
            'fixed_amount' => ['required_if:calculation,fixed', 'nullable', 'numeric', 'between:0,21474836.47', 'decimal:0,2'],
            'tiers' => ['present', 'array', 'max:12'],
            'tiers.*' => ['array:limit,rate'], 'tiers.*.limit' => ['present', 'nullable', 'numeric', 'gt:0', 'max:1000000000', 'decimal:0,3'],
            'tiers.*.rate' => ['required', 'numeric', 'between:0,1000000', 'decimal:0,6'],
        ])->validate();
        if ($data['calculation'] === 'actual_bill') {
            $data['minimum_policy'] = 'none'; $data['minimum_charge'] = '0';
        }
        $data['minimum_charge'] = UtilityDecimal::format(UtilityDecimal::scaled($data['minimum_charge'], 6), 6);
        $data['fixed_amount'] = isset($data['fixed_amount']) ? UtilityDecimal::format(UtilityDecimal::scaled($data['fixed_amount'], 2), 2) : null;
        if (!in_array($data['calculation'], ['fixed', 'actual_bill'])) {
            if (!$data['tiers'] || !array_is_list($data['tiers']) || ($data['calculation'] === 'flat' && count($data['tiers']) !== 1)) {
                throw ValidationException::withMessages(['tiers' => 'Supply an ordered rate schedule; flat rates have one unlimited block.']);
            }
            $lower = 0;
            foreach ($data['tiers'] as $index => &$tier) {
                $limit = $tier['limit'] === null || $tier['limit'] === '' ? null : UtilityDecimal::scaled($tier['limit'], 3);
                if (($index === count($data['tiers']) - 1 && $limit !== null)
                    || ($index < count($data['tiers']) - 1 && ($limit === null || $limit <= $lower))) {
                    throw ValidationException::withMessages(['tiers' => 'Use increasing cumulative limits and an unlimited final block.']);
                }
                $tier = ['limit' => $limit === null ? null : UtilityDecimal::format($limit, 3),
                    'rate' => UtilityDecimal::format(UtilityDecimal::scaled($tier['rate'], 6), 6)];
                $lower = $limit ?? $lower;
            }
            unset($tier);
        } else $data['tiers'] = [];
        return $data + ['capacity_rate' => '0', 'network_rate' => '0', 'fixed_charge' => '0', 'fixed_waiver_up_to' => null];
    }

    public function save(User $manager, array $input, bool $official = false): UtilityTariffVersion
    {
        if ($official) abort_unless(Gate::allows('super-admin'), 403);
        else $this->authorizeCustom($manager);
        $data = Validator::make($input, [
            'id' => ['nullable', 'ulid'], 'name' => ['required', 'string', 'max:255'],
            'provider' => [$official ? 'required' : 'nullable', 'string', 'max:255'], 'state' => [$official ? 'required' : 'nullable', 'string', 'max:100'],
            'customer_category' => [$official ? 'required' : 'nullable', 'string', 'max:255'], 'reference_key' => ['nullable', 'string', 'max:100'],
            'effective_from' => ['required', 'date_format:Y-m-d'], 'source_url' => [$official ? 'required' : 'nullable', 'url', 'max:2048'],
            'verified' => ['nullable', 'boolean'], 'reason' => ['required', 'string', 'min:5', 'max:2000'],
            'definition' => ['required', 'array'], 'make_default' => ['nullable', 'boolean'],
            'contract_confirmed' => $official ? ['nullable'] : ['accepted'],
        ])->validate();
        $definition = $this->validateDefinition($data['definition']);
        return DB::transaction(function () use ($manager, $data, $definition, $official) {
            User::whereKey($manager->id)->lockForUpdate()->firstOrFail();
            $tariff = !empty($data['id']) ? UtilityTariff::whereKey($data['id'])->lockForUpdate()->firstOrFail() : new UtilityTariff;
            if ($tariff->exists) abort_unless($official ? $tariff->kind === 'official' && $tariff->management_user_id === null
                : $tariff->kind === 'custom' && $tariff->management_user_id === $manager->id, 403);
            $tariff->fill(['management_user_id' => $official ? null : $manager->id, 'kind' => $official ? 'official' : 'custom',
                'name' => $data['name'], 'provider' => $data['provider'] ?? null, 'state' => $data['state'] ?? null,
                'customer_category' => $data['customer_category'] ?? null, 'utility_type' => 'water',
                'reference_key' => $data['reference_key'] ?? $tariff->reference_key])->save();
            $version = $tariff->versions()->create(['version' => ($tariff->versions()->max('version') ?? 0) + 1,
                'effective_from' => $data['effective_from'], 'definition' => $definition + ['_metadata' => $tariff->only(['name', 'provider', 'state', 'customer_category'])],
                'source_url' => $data['source_url'] ?? null, 'verified_at' => $official && ($data['verified'] ?? false) ? now() : null,
                'verified_by' => $official && ($data['verified'] ?? false) ? Auth::id() : null,
                'created_by' => Auth::id(), 'reason' => $data['reason']]);
            if (!$official && ($data['make_default'] ?? false)) {
                $setting = Setting::firstOrNew(['user_id' => $manager->id, 'key' => 'utility_tariff_config']);
                $value = $setting->value ?? []; $value['default_tariff_id'] = $tariff->id;
                $setting->fill(['value' => $value, 'is_active' => true])->save();
            }
            return $version;
        });
    }

    public function assign(User $manager, UtilityTariff $tariff, array $ids, string $effective, string $reason): void
    {
        $this->authorizeCustom($manager);
        abort_unless($tariff->management_user_id === $manager->id || ($tariff->kind === 'official' && $tariff->management_user_id === null), 403);
        if (!$tariff->is_active) throw ValidationException::withMessages(['tariff' => 'Enable this tariff before assigning it.']);
        Validator::make(compact('ids', 'effective', 'reason'), ['ids' => ['required', 'array', 'min:1', 'max:2000'],
            'ids.*' => ['required', 'ulid', 'distinct'], 'effective' => ['required', 'date_format:Y-m-d'],
            'reason' => ['required', 'string', 'min:5', 'max:2000']])->validate();
        DB::transaction(function () use ($manager, $tariff, $ids, $effective, $reason) {
            User::whereKey($manager->id)->lockForUpdate()->firstOrFail();
            $services = UtilityService::forManagement($manager->id)->where('type', 'water')->whereIn('id', $ids)->lockForUpdate()->get();
            if ($services->count() !== count($ids)) abort(403);
            foreach ($services as $service) {
                abort_unless($service->location && app(UtilitySetupService::class)->managerFor($service->location)?->id === $manager->id, 403);
                if ($service->tariffAssignments()->whereDate('effective_from', $effective)->exists()) {
                    throw ValidationException::withMessages(['assignments' => 'An assignment already exists on that date. Choose a new effective date to retain history.']);
                }
                $service->tariffAssignments()->create(['management_user_id' => $manager->id, 'utility_tariff_id' => $tariff->id,
                    'effective_from' => $effective, 'created_by' => Auth::id(), 'reason' => $reason]);
            }
        });
    }

    public function toggle(User $manager, UtilityTariff $tariff, bool $active): void
    {
        if ($tariff->kind === 'official') abort_unless(Gate::allows('super-admin'), 403);
        else { $this->authorizeCustom($manager); abort_unless($tariff->management_user_id === $manager->id, 403); }
        DB::transaction(function () use ($manager, $tariff, $active) {
            User::whereKey($manager->id)->lockForUpdate()->firstOrFail();
            $tariff->update(['is_active' => $active]);
        });
    }
}
