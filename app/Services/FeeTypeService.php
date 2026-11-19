<?php
namespace App\Services;

use App\Models\{FeeType, Setting, User};
use App\FeeTypeCategory;
use Illuminate\Support\Facades\{DB, Gate, Validator};
use Illuminate\Validation\{Rule, ValidationException};

class FeeTypeService
{
    public const REFERENCE_TABLES = ['lease_charges', 'invoice_items', 'refund_items', 'maintenance'];

    public static function categoryPermissions(): array
    {
        return array_map(fn ($category) => 'fee types.'.$category->value, FeeTypeCategory::cases());
    }

    public static function allowedCategories(): array
    {
        return array_values(array_filter(array_column(FeeTypeCategory::cases(), 'value'),
            fn ($category) => Gate::any(['super-admin', 'fee types.'.$category])));
    }

    public function settingsData(array $filters = []): array
    {
        Gate::authorize('fee types.view');
        $filters = Validator::make($filters, [
            'search' => ['nullable', 'string', 'max:200'],
            'category' => ['nullable', Rule::in(array_merge(['all'], array_column(FeeTypeCategory::cases(), 'value')))],
        ])->validate();
        $feeTypes = FeeType::forAccount(get_effective_user()->id)->visibleToActor()->search($filters['search'] ?? null)
            ->when(($filters['category'] ?? 'all') !== 'all', fn ($query) => $query->where('category', $filters['category']))
            ->withExists(['invoiceItems', 'leaseCharges', 'refundItems', 'maintenanceItems'])
            ->orderBy('category')->orderBy('name')->orderBy('id')->get();
        $categoryOptions = collect(self::allowedCategories())->mapWithKeys(fn ($category) => [$category => ucfirst($category)])->all();
        return compact('feeTypes', 'categoryOptions');
    }

    private function authorizeCategory(FeeTypeCategory|string $category): void
    {
        Gate::authorize('fee types.'.($category instanceof FeeTypeCategory ? $category->value : $category));
    }

    public static function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'category' => ['required', Rule::enum(FeeTypeCategory::class)],
            // Existing quick-add clients send item prices in cents.
            'original_price' => ['nullable', 'integer', 'between:0,4294967295'],
            'sell_price' => ['nullable', 'integer', 'between:0,4294967295'],
        ];
    }
    /**
     * Get active invoice fee types for the authenticated user.
     */
    public function getActiveInvoiceFeeTypes()
    {
        $user = get_effective_user();
        return app(SettingService::class)->filterActiveFeeTypes(
            FeeType::forAccount($user->id)->visibleToActor()->where('category', FeeTypeCategory::INVOICE->value)->active()->get(), $user->id
        );
    }

    /**
     * Create a new fee type.
     */
    public function createFeeType(array $data): FeeType
    {
        $user = get_effective_user();
        return DB::transaction(function () use ($user, $data) {
            User::whereKey($user->id)->lockForUpdate()->firstOrFail();
            $values = $this->validate($data, $user->id);
            return FeeType::create($values + ['user_id' => $user->id, 'is_system' => false, 'is_active' => true]);
        }, 3);
    }

    public function update(FeeType $fee, array $data): FeeType
    {
        return $this->locked($fee, function (FeeType $fee, User $user) use ($data) {
            if ($fee->is_system) $this->fail('System fee names, categories and prices cannot be changed here.');
            $values = $this->validate($data, $user->id, $fee);
            $oldKey = $fee->settingKey();
            if ($fee->category->value !== $values['category'] && $this->isReferenced($fee)) {
                $this->fail('This fee type is already used. Its category cannot be changed.');
            }
            $fee->fill($values)->save();
            if ($oldKey !== $fee->settingKey()) $this->moveSetting($user->id, $oldKey, $fee->settingKey());
            return $fee;
        });
    }

    public function changeStatus(FeeType $fee, bool $active): FeeType
    {
        return $this->locked($fee, function (FeeType $fee, User $user) use ($active) {
            if ($fee->is_system && $fee->settingKey() === 'service_late_payment_penalty') {
                $this->fail('Late Payment Penalty is controlled by the Late Payment Penalty setting.');
            }
            if ($fee->is_system && $active && !$fee->is_active) $this->fail('This system fee has been disabled globally.');
            if (!$fee->is_system) $fee->update(['is_active' => $active]);
            $setting = Setting::firstOrNew(['user_id' => $user->id, 'key' => 'fee_types_config']);
            $values = $setting->value ?? [];
            $values[$fee->settingKey()]['is_active'] = $active;
            $setting->fill(['value' => $values, 'is_active' => $setting->exists ? $setting->is_active : true])->save();
            return $fee;
        });
    }

    public function updateBatch(array $entries): array
    {
        $user = get_effective_user();
        return DB::transaction(function () use ($user, $entries) {
            User::whereKey($user->id)->lockForUpdate()->firstOrFail();
            $fees = FeeType::forAccount($user->id)->whereIn('id', array_column($entries, 'id'))
                ->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            abort_unless($fees->count() === count($entries), 404);
            foreach ($fees as $fee) $this->authorizeCategory($fee->category);
            $saved = [];
            foreach ($entries as $index => $entry) {
                $fee = $fees->get($entry['id']);
                try {
                    $edits = array_intersect_key($entry, array_flip(['name', 'original_price', 'sell_price']));
                    if ($edits) {
                        $fee = $this->update($fee, $edits + [
                            'name' => $fee->name, 'category' => $fee->category->value,
                            'original_price' => $fee->original_price, 'sell_price' => $fee->sell_price,
                        ]);
                    }
                    if (array_key_exists('is_active', $entry)) $fee = $this->changeStatus($fee, (bool) $entry['is_active']);
                } catch (ValidationException $exception) {
                    $errors = [];
                    foreach ($exception->errors() as $field => $messages) $errors["fees.$index.$field"] = $messages;
                    throw ValidationException::withMessages($errors);
                }
                $saved[] = $fee;
            }
            $settingService = app(SettingService::class);
            $settings = $settingService->getEffectiveSettings($user->id);
            return array_map(fn (FeeType $fee) => $fee->toArray() + [
                'effective_active' => $settingService->isFeeTypeActive($fee, $settings),
            ], $saved);
        }, 3);
    }

    public function delete(FeeType $fee): void
    {
        $this->locked($fee, function (FeeType $fee, User $user) {
            if ($fee->is_system) $this->fail('System fee types cannot be deleted.');
            if ($this->isReferenced($fee)) $this->fail('This fee type is already used and cannot be deleted. Deactivate it instead.');
            $key = $fee->settingKey();
            try {
                $fee->delete();
            } catch (\Illuminate\Database\QueryException $exception) {
                if (in_array($exception->getCode(), ['23000', '23503'], true)) {
                    $this->fail('This fee type is now referenced and cannot be deleted. Deactivate it instead.');
                }
                throw $exception;
            }
            $this->moveSetting($user->id, $key, null);
        });
    }

    public function saveActivationSettings(string $userId, array $entries): void
    {
        abort_unless(get_effective_user()?->id === $userId, 404);
        DB::transaction(function () use ($userId, $entries) {
            User::whereKey($userId)->lockForUpdate()->firstOrFail();
            $fees = FeeType::forAccount($userId)->get()->keyBy(fn (FeeType $fee) => $fee->settingKey());
            foreach ($entries as $key => $entry) {
                if (!is_array($entry) || !array_key_exists('is_active', $entry)) $this->fail('Enter a valid fee activation value.');
                $fee = $fees->get($key);
                if (!$fee) $this->fail('This fee type is not available in your account.');
                if ($fee->is_system && $key === 'service_late_payment_penalty') continue;
                if (!in_array($entry['is_active'], [true, false, 0, 1, '0', '1', 'true', 'false'], true)) $this->fail('Enter a valid fee activation value.');
                $value = filter_var($entry['is_active'], FILTER_VALIDATE_BOOLEAN);
                $this->changeStatus($fee, $value);
            }
        }, 3);
    }

    public function isReferenced(FeeType $fee): bool
    {
        // Include references belonging to soft-deleted financial/operational records.
        foreach (self::REFERENCE_TABLES as $table) {
            if (DB::table($table)->where('fee_type_id', $fee->id)->exists()) return true;
        }
        return false;
    }

    private function locked(FeeType $fee, callable $action)
    {
        $user = get_effective_user();
        return DB::transaction(function () use ($fee, $user, $action) {
            // Serialize duplicate checks and name-based settings updates for this account.
            User::whereKey($user->id)->lockForUpdate()->firstOrFail();
            $locked = FeeType::forAccount($user->id)->whereKey($fee->id)->lockForUpdate()->firstOrFail();
            $this->authorizeCategory($locked->category);
            $result = $action($locked, $user);
            $user->unsetRelation('settings');
            return $result;
        }, 3);
    }

    private function validate(array $data, string $userId, ?FeeType $fee = null): array
    {
        $values = Validator::make($data, self::rules())->validate();
        $this->authorizeCategory($values['category']);
        $values['name'] = trim(preg_replace('/\s+/u', ' ', $values['name']));
        if ($values['name'] === '') $this->fail('Enter a fee type name.', 'name');
        if ($values['category'] !== FeeTypeCategory::ITEM->value && (isset($values['original_price']) || isset($values['sell_price']))) {
            $this->fail('Default prices are supported only for item fee types.', 'category');
        }
        $candidate = new FeeType(['name' => $values['name'], 'category' => $values['category']]);
        if ($fee && $fee->name === $values['name'] && $fee->category->value === $values['category']) return $values;
        // Legacy settings use slugs: punctuation variants must not share an activation key.
        $duplicates = FeeType::forAccount($userId)->where('category', $values['category'])
            ->when($fee, fn ($query) => $query->whereKeyNot($fee->id))->get(['id', 'name', 'category']);
        if ($duplicates->contains(fn (FeeType $other) => $other->settingKey() === $candidate->settingKey())) {
            $this->fail('A fee type with this name already exists in this category.', 'name');
        }
        return $values;
    }

    private function moveSetting(string $userId, string $oldKey, ?string $newKey): void
    {
        $setting = Setting::where('user_id', $userId)->where('key', 'fee_types_config')->first();
        if (!$setting || !array_key_exists($oldKey, $setting->value ?? [])) return;
        $values = $setting->value;
        if ($newKey !== null) $values[$newKey] = $values[$oldKey];
        $keyStillUsed = FeeType::forAccount($userId)->get(['name', 'category'])
            ->contains(fn (FeeType $fee) => $fee->settingKey() === $oldKey);
        if (!$keyStillUsed) unset($values[$oldKey]);
        $setting->update(['value' => $values]);
    }

    private function fail(string $message, string $field = 'fee_type'): never
    {
        throw ValidationException::withMessages([$field => $message]);
    }
}
