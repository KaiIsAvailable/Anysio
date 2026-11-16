<?php

namespace App\Services;

use App\Models\{Lease, Property, Room, Unit, User, Utility, UtilityService, UtilityMeterEvent, UtilityTariff};
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\{DB, Gate, Validator};
use Illuminate\Validation\ValidationException;

class UtilityReadingService
{
    private array $plans = [];

    public function pricingPlan(User $manager): array
    {
        return $this->plans[$manager->id] ??= app(UtilityTariffManagementService::class)->timeline($manager);
    }

    public function defaultReadingDate(string $month): string
    {
        $date = \Carbon\Carbon::parse($month.'-01');
        return $month === today()->format('Y-m') ? today()->toDateString()
            : ($month < today()->format('Y-m') ? $date->endOfMonth()->toDateString() : $date->toDateString());
    }

    public function updatePreviousDate(User $manager, UtilityService $service, string $month, string $date, string $revision): void
    {
        Validator::make(['date' => $date], ['date' => ['required', 'date_format:Y-m-d', 'before_or_equal:today']])->validate();
        DB::transaction(function () use ($manager, $service, $month, $date, $revision) {
            User::whereKey($manager->id)->lockForUpdate()->firstOrFail();
            $row = collect($this->rows($manager, $month))->firstWhere('id', $service->id);
            if (!$row || !hash_equals($row['revision'], $revision)) {
                throw ValidationException::withMessages(['previous_reading_date' => 'The reading has changed. Reload the edit page.']);
            }
            if ($row['previousDate'] === $date) return;
            if ($row['baselineDate']) {
                throw ValidationException::withMessages(['previous_reading_date' => 'The date belongs to a measured baseline. Preserve it and record a documented meter event or financial correction instead.']);
            }
            if ($row['locked']) {
                throw ValidationException::withMessages(['previous_reading_date' => 'An invoice or later reading depends on this record. Its date is locked.']);
            }
            if ($row['current'] !== '') {
                $this->save($manager, [
                    'month' => $month, 'reading_date' => $row['readingDate'],
                    'readings' => json_encode([[
                        'id' => $service->id, 'revision' => $revision, 'previous' => $row['previous'],
                        'previousDate' => $date, 'current' => $row['current'], 'rate' => $row['rate'],
                    ]]),
                ]);
                return;
            }
            // Retain a date for this month without inventing a current meter reading.
            $service->records()->updateOrCreate(['period' => $month.'-01'], [
                'type' => 'water', 'prev_reading' => $row['previous'] === '' ? null : $row['previous'],
                'prev_reading_date' => $date, 'status' => 'draft',
            ]);
        });
    }

    public function rows(User $manager, string $month, ?string $search = null): array
    {
        $period = $month.'-01';
        $services = UtilityService::forManagement($manager->id)->where('type', 'water')
            ->whereIn('billing_method', ['metered', 'fixed'])->whereIn('status', ['active', 'unconfirmed'])->search($search)
            ->with(['meterEvents' => fn ($q) => $q->whereNull('consumed_by_utility_id'), 'location' => fn (MorphTo $morph) => $morph->morphWith([Unit::class => ['property'], Room::class => ['unit.property']])])
            ->orderBy('location_type')->orderBy('location_id')->get();
        $records = $this->relevantRecords(Utility::whereIn('utility_service_id', $services->modelKeys()), $period)->groupBy('utility_service_id');
        $usedSubmeters = \App\Models\UtilityAllocation::where('management_user_id', $manager->id)->whereNull('superseded_at')->get()->map(fn ($a) => data_get($a->snapshot, 'submeter_reading_id'))->filter()->all();
        $laterIds = Utility::whereIn('utility_service_id', $services->modelKeys())->whereDate('period', '>', $period)
            ->whereNotNull('curr_reading')->pluck('utility_service_id')->flip();
        $plan = $this->pricingPlan($manager);
        $rows = [];
        foreach ($services as $service) {
            if (!$service->location || app(UtilitySetupService::class)->managerFor($service->location)?->id !== $manager->id) continue;
            $rows[] = $this->row($service, $period, $records->get($service->id, collect()), $laterIds->has($service->id) || in_array($records->get($service->id, collect())->first(fn ($r) => $r->period?->toDateString() === $period)?->id, $usedSubmeters, true), $plan, $this->defaultReadingDate($month));
        }
        return $rows;
    }

    public function hierarchy(User $manager, array $rows): array
    {
        $setup = app(UtilitySetupService::class);
        $properties = $setup->managedLocations(Property::class, $manager)->keyBy('id');
        $units = $setup->managedLocations(Unit::class, $manager);
        // A managed unit can sit inside a property managed by someone else. Its name
        // is location context; no other account's meter or units are exposed.
        foreach ($units as $unit) if ($unit->property) $properties->put($unit->property->id, $unit->property);
        $meters = collect($rows);
        $nodes = [];
        foreach ($properties->sort(fn ($a, $b) => strnatcasecmp($a->name, $b->name)) as $property) {
            $meter = $meters->first(fn ($row) => $row['locationType'] === 'property' && $row['propertyId'] === $property->id);
            $nodes[] = ['key' => 'property-'.$property->id, 'level' => 0, 'label' => $property->name, 'rowId' => $meter['id'] ?? null];
            foreach ($units->where('property_id', $property->id)->sort(fn ($a, $b) => strnatcasecmp($a->unit_no, $b->unit_no)) as $unit) {
                $meter = $meters->first(fn ($row) => $row['locationType'] === 'unit' && $row['unitId'] === $unit->id);
                $nodes[] = ['key' => 'unit-'.$unit->id, 'level' => 1, 'label' => $unit->unit_no, 'rowId' => $meter['id'] ?? null];
                foreach ($meters->where('locationType', 'room')->where('unitId', $unit->id)
                    ->sort(fn ($a, $b) => strnatcasecmp($a['roomNo'], $b['roomNo'])) as $room) {
                    $nodes[] = ['key' => 'room-'.$room['roomId'], 'level' => 2, 'label' => $room['roomNo'], 'rowId' => $room['id']];
                }
            }
        }
        return $nodes;
    }

    private function relevantRecords(Builder $query, string $period)
    {
        // Load only this month's rows and one baseline per meter, rather than its entire history.
        return $query->where(function ($records) use ($period) {
            $records->whereDate('utilities.period', $period)->orWhere('utilities.id', function ($previous) use ($period) {
                $previous->select('prior_readings.id')->from('utilities as prior_readings')
                    ->whereColumn('prior_readings.utility_service_id', 'utilities.utility_service_id')
                    ->where('prior_readings.status', 'confirmed')->whereNotNull('prior_readings.curr_reading')
                    ->where(fn ($dates) => $dates->whereNull('prior_readings.period')->orWhere('prior_readings.period', '<', $period))
                    ->orderByRaw('prior_readings.curr_reading_date IS NULL')->orderByDesc('prior_readings.curr_reading_date')
                    ->orderByDesc('prior_readings.id')->limit(1);
            });
        })->with(['invoiceItem.invoice.documentTemplate', 'meterEvent', 'allocations.invoiceItem', 'latestCorrection'])->get();
    }

    private function row(UtilityService $service, string $period, $records, bool $hasLaterReading = false, array $plan = [], ?string $readingDate = null): array
    {
        $current = $records->filter(fn ($record) => $record->period?->toDateString() === $period);
        $record = $current->first();
        $previous = $records->filter(fn ($entry) => $entry->status === 'confirmed' && $entry->curr_reading !== null
            && (!$entry->period || $entry->period->toDateString() < $period))
            ->sortByDesc(fn ($entry) => ($entry->curr_reading_date?->toDateString() ?? '0000-00-00').' '.$entry->id)->first();
        $value = data_get($previous?->latestCorrection?->replacement_snapshot, 'current') ?? $previous?->curr_reading ?? $service->opening_reading;
        $date = $previous ? (data_get($previous?->latestCorrection?->replacement_snapshot, 'current_date') ?? $previous->curr_reading_date?->toDateString()) : $service->opening_reading_date?->toDateString();
        $locked = $current->count() > 1 || $hasLaterReading || ($record && ($record->invoice_item_id || $record->allocations->isNotEmpty()));
        $savedTariff = data_get($record?->calculation_details, 'tariff');
        $tariff = $locked && $record ? $savedTariff : app(UtilityTariffManagementService::class)->resolveTimeline(
            $plan, $service->id, $record?->prev_reading_date?->toDateString() ?? $date,
            $record?->curr_reading_date?->toDateString() ?? $readingDate ?? today()->toDateString(), $savedTariff);
        $events = $service->relationLoaded('meterEvents') ? $service->meterEvents : $service->meterEvents()->whereNull('consumed_by_utility_id')->get();
        if ($record?->meterEvent) $events = $events->push($record->meterEvent)->unique('id');
        $location = $service->location;
        $unit = $location instanceof Room ? $location->unit : ($location instanceof Unit ? $location : null);
        $property = $location instanceof Property ? $location : $unit?->property;
        $invoice = $record?->invoiceItem?->invoice;
        return [
            'id' => $service->id, 'recordId' => $record?->id, 'chargeUrl' => $record ? route('admin.utilities.charges.show', ['utility' => $record, 'management_user_id' => $service->management_user_id]) : null, 'billingScope' => $service->billing_scope ?? 'individual',
            'savedTariff' => $savedTariff, 'eventId' => $record?->utility_meter_event_id ?? '', 'reprice' => false,
            'calculationNeedsRepair' => $record && (data_get($record->calculation_details, 'schema_version') !== 2
                || data_get($record->calculation_details, 'final_amount') !== $record->amount),
            'events' => $events->map(fn ($e) => array_replace($e->only(['id', 'type', 'old_closing', 'new_opening', 'capacity', 'reason']), ['event_date' => $e->event_date->toDateString()]))->all(),
            'billAmount' => data_get($record?->calculation_details, 'provider_bill_amount', ''),
            'billReference' => data_get($record?->calculation_details, 'bill_reference', ''),
            'location' => $service->location_label, 'label' => $service->label,
            'tariff' => $tariff, 'invoiced' => (bool) $record?->invoice_item_id || ($record && $record->allocations->whereNotNull('invoice_item_id')->isNotEmpty()), 'invoiceNo' => $invoice?->invoice_no,
            'invoiceDocumentUrl' => $invoice?->documentTemplate && Gate::any(['super-admin', 'invoice.view invoice'])
                ? route('admin.invoices.document-data', $invoice) : null,
            'meter' => $service->meter_number, 'account' => $service->account_number,
            'locationType' => strtolower(class_basename($location)), 'propertyId' => $property?->id,
            'unitId' => $unit?->id, 'roomId' => $location instanceof Room ? $location->id : null,
            'roomNo' => $location instanceof Room ? $location->room_no : null, 'setupStatus' => $service->status,
            'earlierPrevious' => $previous?->prev_reading ?? '',
            'earlierPreviousDate' => $previous?->prev_reading_date?->toDateString() ?? '',
            'earlierPreviousMonth' => $previous?->prev_reading_date?->format('m/Y'),
            'previousMonth' => $previous?->period?->format('m/Y') ?? $service->opening_reading_date?->format('m/Y'),
            'previous' => !$locked && $value !== null ? $value : ($record?->prev_reading ?? ''),
            'previousDate' => $record?->prev_reading_date?->toDateString() ?? $date ?? '',
            'current' => $record?->curr_reading ?? '', 'rate' => $record?->applied_rate ?? '',
            'readingDate' => $record?->curr_reading_date?->toDateString(),
            'previousLocked' => $value !== null, 'previousDateLocked' => $date !== null,
            'baseline' => $value, 'baselineDate' => $date,
            'needsSave' => $record?->status === 'draft',
            'locked' => (bool) $locked, 'status' => $locked ? ($current->count() > 1 ? 'Needs review' : 'Saved / locked') : ($record ? 'Saved' : 'Not entered'),
            'rebased' => !$locked && $record && $value !== null && $record->prev_reading !== $value,
            'revision' => hash('sha256', json_encode([
                $record?->getAttributes(), $previous?->getAttributes(), $service->opening_reading,
                $service->opening_reading_date?->toDateString(), $plan, $events->map->getAttributes()->all(), $previous?->latestCorrection?->getAttributes(),
            ])),
        ];
    }

    public function save(User $manager, array $input): array
    {
        $data = Validator::make($input, [
            'month' => ['required', 'date_format:Y-m'], 'reading_date' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
            'readings' => ['required', 'json', 'max:2000000'], 'flat_override_confirmed' => ['nullable', 'boolean'],
        ])->validate();
        $entries = json_decode($data['readings'], true);
        Validator::make(['rows' => $entries], [
            'rows' => ['required', 'array', 'min:1', 'max:2000'],
            'rows.*' => ['array:id,previous,previousDate,current,rate,revision,billAmount,billReference,eventId,reprice,adjustments'],
            'rows.*.id' => ['required', 'ulid', 'distinct'], 'rows.*.revision' => ['required', 'string', 'size:64'],
            'rows.*.previous' => ['present', 'nullable', 'numeric', 'min:0', 'max:999999999999.999', 'decimal:0,3'],
            'rows.*.current' => ['present', 'nullable', 'numeric', 'min:0', 'max:999999999999.999', 'decimal:0,3'],
            'rows.*.previousDate' => ['present', 'nullable', 'date_format:Y-m-d', 'before_or_equal:'.$data['reading_date']],
            'rows.*.rate' => ['present', 'nullable', 'numeric', 'between:0,1000000', 'decimal:0,6'],
            'rows.*.billAmount' => ['nullable', 'numeric', 'between:0,21474836.47', 'decimal:0,2'],
            'rows.*.billReference' => ['nullable', 'string', 'max:255'], 'rows.*.eventId' => ['nullable', 'ulid'],
            'rows.*.reprice' => ['nullable', 'boolean'], 'rows.*.adjustments' => ['sometimes', 'array', 'max:20'],
            'rows.*.adjustments.*.amount' => ['required', 'numeric', 'between:-21474836.47,21474836.47', 'decimal:0,2'],
            'rows.*.adjustments.*.reason' => ['required', 'string', 'min:5', 'max:2000'],
        ])->validate();
        return DB::transaction(function () use ($manager, $data, $entries) {
            User::whereKey($manager->id)->lockForUpdate()->firstOrFail();
            $this->plans = []; $plan = $this->pricingPlan($manager);
            $period = $data['month'].'-01'; $count = 0;
            foreach ($entries as $entry) {
                foreach (['previous', 'current', 'previousDate', 'rate'] as $field) if ($entry[$field] === '') $entry[$field] = null;
                $service = UtilityService::forManagement($manager->id)->where('type', 'water')->whereIn('billing_method', ['metered', 'fixed'])
                    ->whereIn('status', ['active', 'unconfirmed'])->whereKey($entry['id'])->lockForUpdate()->first();
                if (!$service || !$service->location || app(UtilitySetupService::class)->managerFor($service->location)?->id !== $manager->id) {
                    throw ValidationException::withMessages(['readings' => 'A selected meter is unavailable or outside this management account.']);
                }
                $records = $this->relevantRecords($service->records()->getQuery(), $period);
                $existing = $records->first(fn ($r) => $r->period?->toDateString() === $period);
                $later = $service->records()->whereDate('period', '>', $period)->whereNotNull('curr_reading')->exists()
                    || ($existing && \App\Models\UtilityAllocation::where('management_user_id', $manager->id)->whereNull('superseded_at')->where('snapshot->submeter_reading_id', $existing->id)->exists());
                $row = $this->row($service, $period, $records, $later, $plan, $this->defaultReadingDate($data['month']));
                if ($row['locked'] || !hash_equals($row['revision'], $entry['revision'])) {
                    throw ValidationException::withMessages(['readings' => $service->label.': readings changed, are invoiced, allocated or have a later reading. Reload before saving.']);
                }
                $savedTariff = ($entry['reprice'] ?? false) ? null : data_get($existing?->calculation_details, 'tariff');
                $tariff = app(UtilityTariffManagementService::class)->resolveTimeline($plan, $service->id, $entry['previousDate'], $data['reading_date'], $savedTariff);
                $method = $tariff['calculation'] ?? 'blocks';
                if ($entry['previous'] === null && $entry['current'] === null && !in_array($method, ['fixed', 'actual_bill'])) continue;
                if ($method === 'actual_bill' && empty($entry['billAmount']) && ($entry['billAmount'] ?? null) !== '0' && ($entry['billAmount'] ?? null) !== 0) continue;
                if ($row['baseline'] !== null && ($entry['previous'] === null || UtilityDecimal::scaled($entry['previous'], 3) !== UtilityDecimal::scaled($row['baseline'], 3))
                    && !in_array($method, ['fixed', 'actual_bill'])) {
                    throw ValidationException::withMessages(['readings' => $service->label.': previous reading must match saved history or the opening reading.']);
                }
                if ($row['baselineDate'] && $entry['previousDate'] !== $row['baselineDate'] && !in_array($method, ['fixed', 'actual_bill'])) {
                    throw ValidationException::withMessages(['readings' => $service->label.': previous date must match the recorded baseline. Use an authorized correction to change a measured date.']);
                }
                $from = $entry['previousDate']; $to = $data['reading_date'];
                if (in_array($method, ['fixed', 'actual_bill'])) $from ??= $period;
                if ($from && $entry['current'] !== null && $from >= $to) {
                    throw ValidationException::withMessages(['reading_date' => 'The current measurement must be later than the previous measurement.']);
                }
                if ($from && $service->records()->where('status', 'confirmed')->whereNotNull('curr_reading_date')
                    ->when($existing, fn ($q) => $q->where('id', '!=', $existing->id))
                    ->whereDate('curr_reading_date', '>', $from)
                    ->where(fn ($q) => $q->whereNull('prev_reading_date')->orWhereDate('prev_reading_date', '<', $to))->exists()) {
                    throw ValidationException::withMessages(['readings' => 'This reading interval overlaps another saved interval.']);
                }
                $event = null;
                if ($existing?->utility_meter_event_id && ($entry['eventId'] ?? null) !== $existing->utility_meter_event_id) {
                    throw ValidationException::withMessages(['meter_event' => 'Preserve the meter event already linked to this reading. Use a documented correction for additional changes.']);
                }
                if (empty($entry['eventId']) && $from && $service->meterEvents()->whereNull('consumed_by_utility_id')
                    ->whereDate('event_date', '>=', $from)->whereDate('event_date', '<=', $to)->exists()) {
                    throw ValidationException::withMessages(['meter_event' => 'Select the recorded meter event inside this interval before saving the affected reading.']);
                }
                if (!empty($entry['eventId'])) {
                    $event = UtilityMeterEvent::whereKey($entry['eventId'])->where('utility_service_id', $service->id)
                        ->where('management_user_id', $manager->id)->lockForUpdate()->firstOrFail();
                    if (($event->consumed_by_utility_id && $event->consumed_by_utility_id !== $existing?->id)
                        || !$from || $event->event_date->toDateString() < $from || $event->event_date->toDateString() > $to) {
                        throw ValidationException::withMessages(['meter_event' => 'The meter event was already used or is outside this reading interval.']);
                    }
                }
                $calculator = app(UtilityCalculationService::class);
                $needsReadingReview = !$event && $entry['previous'] !== null && $entry['current'] !== null
                    && UtilityDecimal::scaled($entry['current'], 3) < UtilityDecimal::scaled($entry['previous'], 3);
                $usage = $needsReadingReview ? null : $calculator->consumption($entry['previous'], $entry['current'], $event?->toArray());
                if ($event && $usage === null) throw ValidationException::withMessages(['meter_event' => 'Enter both readings to apply a meter event.']);
                if ($entry['rate'] !== null && in_array($method, ['fixed', 'actual_bill'])) {
                    throw ValidationException::withMessages(['rate' => 'Flat meter-rate overrides do not apply to fixed or actual provider bill tariffs.']);
                }
                if ($entry['rate'] !== null) {
                    $same = $existing?->applied_rate !== null && UtilityDecimal::scaled($entry['rate'], 6) === UtilityDecimal::scaled($existing->applied_rate, 6) && isset($savedTariff['tariff_version_id']);
                    if (!$same) {
                        app(UtilityTariffManagementService::class)->authorizeCustom($manager);
                        if (!($data['flat_override_confirmed'] ?? false)) throw ValidationException::withMessages(['rate' => 'Confirm the contractual flat override or assign a custom tariff in Settings.']);
                        $rate = UtilityDecimal::format(UtilityDecimal::scaled($entry['rate'], 6), 6);
                        $key = 'flat_override_'.substr(hash('sha256', $rate), 0, 32);
                        $flat = UtilityTariff::where('management_user_id', $manager->id)->where('kind', 'custom')->where('reference_key', $key)->first();
                        $version = $flat?->versions()->whereDate('effective_from', '<=', $from ?? $period)->orderByDesc('version')->first();
                        if (!$version) $version = app(UtilityTariffManagementService::class)->save($manager, [
                            'id' => $flat?->id, 'reference_key' => $key, 'name' => 'Landlord flat RM '.$rate.'/m3',
                            'effective_from' => $from ?? $period, 'contract_confirmed' => true,
                            'reason' => 'Contractual flat override explicitly entered with a meter reading.',
                            'definition' => ['calculation' => 'flat', 'minimum_policy' => 'none', 'minimum_charge' => '0', 'tiers' => [['limit' => null, 'rate' => $rate]]],
                        ]);
                        $tariff = app(UtilityTariffManagementService::class)->snapshot($version);
                    }
                }
                if ($method === 'actual_bill' && trim($entry['billReference'] ?? '') === '') throw ValidationException::withMessages(['billReference' => 'Record the provider bill reference for actual-bill allocation.']);
                $adjustments = $entry['adjustments'] ?? array_map(fn ($a) => ['reason' => $a['reason'], 'amount' => UtilityDecimal::format($a['amount'], 2)], data_get($existing?->calculation_details, 'adjustments', []));
                if (array_key_exists('adjustments', $entry)) app(UtilityTariffManagementService::class)->authorizeCustom($manager, 'utilities.manage charges');
                if ($adjustments && !($tariff['billable'] ?? false)) throw ValidationException::withMessages(['adjustments' => 'Select a billable verified or contractual custom tariff before applying adjustments.']);
                $calculation = !$needsReadingReview && ($tariff['billable'] ?? false) ? $calculator->calculate($tariff, $usage,
                    ['bill_amount' => $entry['billAmount'] ?? null, 'adjustments' => $adjustments])
                    : ['usage' => $usage, 'amount' => null, 'method' => $method, 'blocks' => [], 'adjustments' => [], 'review_reason' => $tariff['review_reason'] ?? 'Tariff needs verification.'];
                if ($needsReadingReview) $calculation['review_reason'] = 'Current reading is lower than previous reading. Review the readings or record a meter event before generating an invoice.';
                $leases = !$from ? collect() : Lease::where('leasable_type', $service->location_type)->where('leasable_id', $service->location_id)
                    ->whereNull('cancelled_at')->whereDate('start_date', '<=', $to)
                    ->where(fn ($q) => $q->whereNull('end_date')->orWhereDate('end_date', '>=', $from))
                    ->where(fn ($q) => $q->whereNull('checked_out_at')->orWhereDate('checked_out_at', '>=', $from))
                    ->where(fn ($q) => $q->whereNull('agreement_ended_at')->orWhereDate('agreement_ended_at', '>=', $from))->get();
                $lease = $leases->count() === 1 ? $leases->first() : null;
                if ($lease && ($lease->start_date->toDateString() > $from || collect([$lease->end_date, $lease->checked_out_at, $lease->agreement_ended_at])->filter()->contains(fn ($d) => $d->toDateString() < $to))) $lease = null;
                $snapshot = $calculation + ['schema_version' => 2, 'tariff' => $tariff, 'tariff_id' => $tariff['tariff_id'] ?? null,
                    'tariff_version_id' => $tariff['tariff_version_id'] ?? null, 'tariff_version' => $tariff['version'] ?? null,
                    'final_amount' => $calculation['amount'], 'billing_period' => $period, 'calculated_at' => now()->toIso8601String(),
                    'previous' => $entry['previous'], 'current' => $entry['current'], 'previous_date' => $from, 'current_date' => $to,
                    'billing_from' => $method === 'fixed' ? $period : $from,
                    'billing_to' => $method === 'fixed' ? \Carbon\Carbon::parse($period)->endOfMonth()->toDateString() : $to,
                    'meter_event' => $event?->toArray(), 'provider_bill_amount' => $entry['billAmount'] ?? null,
                    'bill_reference' => $entry['billReference'] ?? null, 'unit' => 'm3',
                    'lease_resolution' => $lease ? 'single_covering_lease' : 'allocation_required'];
                $attributes = ['utility_service_id' => $service->id, 'lease_id' => $lease?->id, 'type' => 'water', 'period' => $period,
                    'entry_key' => hash('sha256', $manager->id.':'.$service->id.':'.$period),
                    'prev_reading' => $entry['previous'], 'curr_reading' => $entry['current'], 'consumption' => $usage,
                    'prev_reading_date' => $from, 'curr_reading_date' => $entry['current'] !== null || in_array($method, ['fixed', 'actual_bill']) ? $to : null,
                    'applied_rate' => $entry['rate'], 'amount' => $calculation['amount'], 'status' => 'confirmed',
                    'utility_tariff_version_id' => $tariff['tariff_version_id'] ?? null, 'utility_meter_event_id' => $event?->id,
                    'calculation_details' => $snapshot];
                if ($existing) { $existing->update($attributes); $record = $existing; } else $record = Utility::create($attributes);
                if ($event) $event->update(['consumed_by_utility_id' => $record->id]);
                $setup = ['status' => 'active'];
                if ($service->opening_reading === null && $row['baseline'] === null && $entry['previous'] !== null) {
                    $setup += ['opening_reading' => $entry['previous'], 'opening_reading_date' => $entry['previousDate']];
                }
                $service->update($setup); $count++;
            }
            if (!$count) throw ValidationException::withMessages(['readings' => 'Enter at least one reading or a fixed/actual bill charge before saving.']);
            $this->plans = [];
            return ['count' => $count, 'status' => 'saved'];
        });
    }

    public function calculate($previous, $current, $rate, string $label = 'Meter'): array
    {
        $usage = app(UtilityCalculationService::class)->consumption($previous, $current);
        $amount = $rate === '' || $rate === null ? null : app(UtilityCalculationService::class)->calculate([
            'calculation' => 'flat', 'minimum_policy' => 'none', 'minimum_charge' => '0',
            'tiers' => [['limit' => null, 'rate' => $rate]],
        ], $usage)['amount'];
        return ['usage' => $usage, 'amount' => $amount];
    }
}
