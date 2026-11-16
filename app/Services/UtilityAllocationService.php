<?php
namespace App\Services;

use App\Models\{User, Utility, UtilityAllocation, Lease, Property, Unit, Room};
use Illuminate\Support\Facades\{Auth, DB, Validator};
use Illuminate\Validation\ValidationException;

class UtilityAllocationService
{
    public static function interval(Utility $reading): array
    {
        return [data_get($reading->calculation_details, 'billing_from') ?? $reading->prev_reading_date?->toDateString(),
            data_get($reading->calculation_details, 'billing_to') ?? $reading->curr_reading_date?->toDateString()];
    }
    public static function sourceHash(Utility $reading): string
    {
        return hash('sha256', json_encode($reading->only(['id', 'amount', 'prev_reading', 'curr_reading', 'consumption',
            'prev_reading_date', 'curr_reading_date', 'utility_tariff_version_id', 'calculation_details'])));
    }

    public function coversLocation($source, $location): bool
    {
        if (!$source || !$location) return false;
        if ($source->getMorphClass() === $location->getMorphClass() && $source->id === $location->id) return true;
        $unit = $location instanceof Room ? $location->unit : ($location instanceof Unit ? $location : null);
        return ($source instanceof Unit && $unit?->id === $source->id)
            || ($source instanceof Property && $unit?->property_id === $source->id);
    }

    public function leases(User $manager, Utility $reading)
    {
        $service = $reading->utilityService;
        app(UtilityBillingAccess::class)->meter($manager, $service);
        [$from, $to] = self::interval($reading);
        $locations = [];
        foreach ([Property::class, Unit::class, Room::class] as $model) {
            $locations[$model] = app(UtilitySetupService::class)->managedLocations($model, $manager)
                ->filter(fn ($location) => $this->coversLocation($service->location, $location))->modelKeys();
        }
        return Lease::where(function ($query) use ($locations) {
                foreach ($locations as $model => $ids) $query->orWhere(fn ($q) => $q->where('leasable_type', (new $model)->getMorphClass())->whereIn('leasable_id', $ids));
            })->whereNull('cancelled_at')->whereNotNull('tenant_id')->whereDate('start_date', '<=', $to)
            ->where(fn ($q) => $q->whereNull('end_date')->orWhereDate('end_date', '>=', $from))
            ->with(['tenant.user', 'leasable'])->get()->filter(fn ($lease) => $lease->tenant?->user
                && $this->coversLocation($service->location, $lease->leasable)
                && app(LeaseManagementResolver::class)->resolve($lease)?->id === $manager->id)->values();
    }

    /** Exact floor(a*b/d) and remainder without an overflowing intermediate product. */
    public function ratio(int $a, int $b, int $d): array
    {
        if ($a < 0 || $b < 0 || $d <= 0 || $b > $d) throw new \InvalidArgumentException('Invalid allocation ratio.');
        $q = 0; $r = 0;
        foreach (str_split(decbin($a)) as $bit) {
            $q *= 2;
            if ($r >= $d - $r) { $r -= $d - $r; $q++; } else $r *= 2;
            if ($bit === '1') {
                if ($r >= $d - $b) { $r -= $d - $b; $q++; } else $r += $b;
            }
        }
        return [$q, $r];
    }

    public function distribute(int $total, array $weights): array
    {
        $denominator = 0;
        foreach ($weights as $weight) $denominator = UtilityDecimal::add($denominator, $weight);
        if (!$weights || $denominator <= 0) throw ValidationException::withMessages(['allocations' => 'Allocation weights must have a positive total.']);
        $amounts = []; $remainders = [];
        foreach ($weights as $key => $weight) {
            [$amounts[$key], $remainders[$key]] = $this->ratio($total, $weight, $denominator);
        }
        $keys = array_keys($weights);
        usort($keys, fn ($a, $b) => ($remainders[$b] <=> $remainders[$a]) ?: strcmp((string) $a, (string) $b));
        $remaining = $total - array_sum($amounts);
        foreach (array_slice($keys, 0, $remaining) as $key) $amounts[$key]++;
        return $amounts;
    }

    public function allocate(User $manager, Utility $reading, array $input): array
    {
        app(UtilityTariffManagementService::class)->authorizeCustom($manager, 'utilities.manage charges');
        app(UtilityBillingAccess::class)->meter($manager, $reading->utilityService);
        $data = Validator::make($input, [
            'method' => ['required', 'in:equal,occupants,percentage,submeter,manual'],
            'source_hash' => ['required', 'string', 'size:64'], 'reason' => ['required', 'string', 'min:5', 'max:2000'],
            'vacancy_reviewed' => ['accepted'],
            'rows' => ['required', 'array', 'min:1', 'max:2000'],
            'rows.*' => ['array:lease_id,weight,amount,submeter_reading_id,from_date,to_date'],
            'rows.*.lease_id' => ['present', 'nullable', 'ulid'], 'rows.*.weight' => ['nullable', 'numeric', 'gt:0', 'max:1000000000', 'decimal:0,6'],
            'rows.*.amount' => ['nullable', 'numeric', 'between:0,21474836.47', 'decimal:0,2'],
            'rows.*.submeter_reading_id' => ['nullable', 'ulid'], 'rows.*.from_date' => ['required', 'date_format:Y-m-d'],
            'rows.*.to_date' => ['required', 'date_format:Y-m-d'],
        ])->validate();
        return DB::transaction(function () use ($manager, $reading, $data) {
            User::whereKey($manager->id)->lockForUpdate()->firstOrFail();
            $reading = Utility::whereKey($reading->id)->lockForUpdate()->firstOrFail();
            [$sourceFrom, $sourceTo] = self::interval($reading);
            if (!hash_equals(self::sourceHash($reading), $data['source_hash']) || $reading->invoice_item_id
                || $reading->allocations()->whereNotNull('invoice_item_id')->exists()) {
                throw ValidationException::withMessages(['allocations' => 'The source charge changed or was already invoiced. Reload and use corrections for issued invoices.']);
            }
            if ($reading->utilityService->billing_scope !== 'shared' || !$reading->prev_reading_date || !$reading->curr_reading_date
                || $reading->amount === null || $reading->amount < 0 || !data_get($reading->calculation_details, 'tariff.billable', false)
                || data_get($reading->calculation_details, 'schema_version') !== 2 || data_get($reading->calculation_details, 'final_amount') !== $reading->amount) {
                throw ValidationException::withMessages(['allocations' => 'Save a valid shared source charge and its reading interval before allocating.']);
            }
            $eligible = $this->leases($manager, $reading)->keyBy('id');
            $weights = []; $rows = []; $manual = []; $submeters = [];
            foreach ($data['rows'] as $row) {
                $key = $row['lease_id'] ?: 'landlord';
                if (isset($rows[$key])) throw ValidationException::withMessages(['allocations' => 'A lease or landlord vacancy share may appear only once.']);
                if ($row['from_date'] < $sourceFrom || $row['to_date'] > $sourceTo || $row['from_date'] >= $row['to_date']) {
                    throw ValidationException::withMessages(['allocations' => 'Each allocation interval must be inside the source interval and in chronological order.']);
                }
                if ($key !== 'landlord') {
                    $lease = $eligible->get($key);
                    if (!$lease || $lease->start_date->toDateString() > $row['from_date']
                        || collect([$lease->end_date, $lease->checked_out_at, $lease->agreement_ended_at])->filter()->contains(fn ($d) => $d->toDateString() < $row['to_date'])) {
                        throw ValidationException::withMessages(['allocations' => 'A selected lease is unavailable or does not cover its allocated interval.']);
                    }
                    foreach ($rows as $otherKey => $other) {
                        if ($otherKey === 'landlord' || $row['from_date'] >= $other['to_date'] || $other['from_date'] >= $row['to_date']) continue;
                        $otherLease = $eligible->get($otherKey);
                        if ($this->coversLocation($lease->leasable, $otherLease->leasable) || $this->coversLocation($otherLease->leasable, $lease->leasable)) {
                            throw ValidationException::withMessages(['allocations' => 'Overlapping tenant allocations at the same leased location are ambiguous. Split their date intervals.']);
                        }
                    }
                }
                $weight = 1;
                if ($data['method'] === 'occupants') {
                    $people = UtilityDecimal::scaled($row['weight'] ?? '', 6);
                    if ($people % 1000000 !== 0) throw ValidationException::withMessages(['allocations' => 'Occupant counts must be positive whole numbers.']);
                    $days = \Carbon\Carbon::parse($row['from_date'])->diffInDays(\Carbon\Carbon::parse($row['to_date']));
                    $weight = UtilityDecimal::product(intdiv($people, 1000000), (int) $days);
                } elseif ($data['method'] === 'percentage') $weight = UtilityDecimal::scaled($row['weight'] ?? '', 6);
                elseif ($data['method'] === 'submeter') {
                    if ($key === 'landlord') $weight = UtilityDecimal::scaled($row['weight'] ?? '', 3);
                    else {
                        $submeter = Utility::whereKey($row['submeter_reading_id'] ?? null)->with('utilityService')->first();
                        if (!$submeter || $submeter->utilityService->management_user_id !== $manager->id
                            || $submeter->utilityService->parent_service_id !== $reading->utility_service_id
                            || $submeter->utilityService->location_type !== $eligible[$key]->leasable_type
                            || $submeter->utilityService->location_id !== $eligible[$key]->leasable_id
                            || $submeter->prev_reading_date?->toDateString() !== $row['from_date'] || $submeter->curr_reading_date?->toDateString() !== $row['to_date']
                            || $submeter->consumption === null || $submeter->invoice_item_id || $submeter->allocations()->exists()
                            || in_array($submeter->id, $submeters)
                            || UtilityAllocation::where('management_user_id', $manager->id)->whereNull('superseded_at')->where('utility_id', '!=', $reading->id)->where('snapshot->submeter_reading_id', $submeter->id)->exists()) {
                            throw ValidationException::withMessages(['allocations' => 'Choose unique, unbilled connected submeter readings matching each lease and date interval.']);
                        }
                        $submeters[] = $submeter->id; $weight = UtilityDecimal::scaled($submeter->consumption, 3);
                    }
                } elseif ($data['method'] === 'manual') $manual[$key] = UtilityDecimal::scaled($row['amount'] ?? '', 2);
                if ($data['method'] !== 'manual' && $weight <= 0) throw ValidationException::withMessages(['allocations' => 'Allocation weights must be positive.']);
                $weights[$key] = $weight; $rows[$key] = $row;
            }
            if ($data['method'] === 'percentage' && array_sum($weights) !== 100000000) throw ValidationException::withMessages(['allocations' => 'Configured percentages must total exactly 100%, including any vacancy share.']);
            $amounts = $data['method'] === 'manual' ? $manual : $this->distribute($reading->amount, $weights);
            if (array_sum($amounts) !== $reading->amount) throw ValidationException::withMessages(['allocations' => 'Allocations must total the source charge exactly.']);
            ksort($rows); $digest = hash('sha256', json_encode([$data['method'], $rows, $data['source_hash']]));
            $current = $reading->allocations()->get();
            if ($current->isNotEmpty() && data_get($current->first()->snapshot, 'allocation_digest') === $digest) return $current->all();
            foreach ($current as $allocation) $allocation->update(['superseded_at' => now(), 'superseded_reason' => $data['reason']]);
            $revision = $reading->allocation_revision + 1; $created = [];
            foreach ($rows as $key => $row) {
                $created[] = UtilityAllocation::create(['management_user_id' => $manager->id, 'utility_id' => $reading->id,
                    'revision' => $revision, 'beneficiary_key' => $key, 'lease_id' => $row['lease_id'] ?: null,
                    'method' => $data['method'], 'weight' => $weights[$key], 'amount' => $amounts[$key],
                    'from_date' => $row['from_date'], 'to_date' => $row['to_date'], 'created_by' => Auth::id(), 'reason' => $data['reason'],
                    'snapshot' => ['schema_version' => 2, 'source_hash' => $data['source_hash'], 'source_charge' => $reading->calculation_details,
                        'source_amount' => $reading->amount, 'final_amount' => $amounts[$key], 'basis' => $row,
                        'submeter_reading_id' => $row['submeter_reading_id'] ?? null, 'allocation_digest' => $digest,
                        'minimum_scope' => 'source_charge_once', 'rounding' => 'largest_remainder_then_beneficiary_key']]);
            }
            $reading->update(['allocation_revision' => $revision]);
            return $created;
        });
    }

    public function revoke(User $manager, Utility $reading, string $reason): void
    {
        app(UtilityTariffManagementService::class)->authorizeCustom($manager, 'utilities.manage charges');
        app(UtilityBillingAccess::class)->meter($manager, $reading->utilityService);
        Validator::make(['reason' => $reason], ['reason' => ['required', 'string', 'min:5', 'max:2000']])->validate();
        DB::transaction(function () use ($manager, $reading, $reason) {
            User::whereKey($manager->id)->lockForUpdate()->firstOrFail();
            $reading = Utility::whereKey($reading->id)->lockForUpdate()->firstOrFail();
            if ($reading->allocations()->whereNotNull('invoice_item_id')->exists()) {
                throw ValidationException::withMessages(['allocations' => 'Issued allocations require the correction workflow.']);
            }
            foreach ($reading->allocations()->get() as $allocation) $allocation->update(['superseded_at' => now(), 'superseded_reason' => $reason]);
            $reading->increment('allocation_revision');
        });
    }
}
