<?php

namespace App\Services;

use App\FeeTypeCategory;
use App\Models\{DocumentTemplate, FeeType, Invoice, Lease, User, Utility, UtilityAllocation, Unit, Room};
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Facades\{DB, Validator};
use Illuminate\Validation\ValidationException;

class UtilityInvoiceService
{
    private function invoiceNumber(User $manager): string
    {
        do {
            $number = app(DocumentSequenceService::class)->generateInvoiceNumber($manager);
        } while (Invoice::withTrashed()->where('invoice_no', $number)->exists());
        return $number;
    }

    public function preview(User $manager, string $month): array
    {
        Validator::make(['month' => $month], ['month' => ['required', 'date_format:Y-m']])->validate();
        return DB::transaction(function () use ($manager, $month) {
            User::whereKey($manager->id)->lockForUpdate()->firstOrFail();
            return $this->prepare($manager, $month);
        });
    }

    private function eligibleLease(?Lease $lease, User $manager, string $from, string $to): bool
    {
        return $lease && !$lease->cancelled_at && in_array($lease->status, ['New', 'Renew', 'Check Out', 'End'])
            && $lease->tenant?->user && $lease->start_date && $lease->start_date->toDateString() <= $from
            && !collect([$lease->end_date, $lease->checked_out_at, $lease->agreement_ended_at])->filter()->contains(fn ($d) => $d->toDateString() < $to)
            && app(LeaseManagementResolver::class)->resolve($lease)?->id === $manager->id;
    }

    private function rateSignature(array $tariff): array
    {
        $method = $tariff['calculation'] ?? null;
        if (!in_array($method, ['blocks', 'flat', 'whole_usage'], true) || !is_array($tariff['tiers'] ?? null)) {
            throw ValidationException::withMessages(['tariff' => 'The legacy rate schedule is incomplete.']);
        }
        $signature = [$method, $tariff['minimum_policy'] ?? ($method === 'flat' ? 'none' : 'monthly')];
        foreach (['minimum_charge', 'capacity_rate', 'network_rate', 'fixed_charge'] as $field) {
            $signature[] = UtilityDecimal::scaled($tariff[$field] ?? '0', 6);
        }
        $signature[] = isset($tariff['fixed_waiver_up_to']) ? UtilityDecimal::scaled($tariff['fixed_waiver_up_to'], 3) : null;
        foreach ($tariff['tiers'] as $tier) {
            if (!is_array($tier) || !array_key_exists('limit', $tier) || !isset($tier['rate'])) {
                throw ValidationException::withMessages(['tariff' => 'The legacy rate blocks are incomplete.']);
            }
            $signature[] = [$tier['limit'] === null || $tier['limit'] === '' ? null : UtilityDecimal::scaled($tier['limit'], 3),
                UtilityDecimal::scaled($tier['rate'], 6)];
        }
        return $signature;
    }

    private function legacySnapshot(Utility $record, array $plan, ?string $from, ?string $to): ?array
    {
        $original = $record->calculation_details ?? [];
        $savedTariff = $original['tariff'] ?? null;
        $service = $record->utilityService;
        if (array_key_exists('schema_version', $original) || !is_array($savedTariff) || $record->status !== 'confirmed'
            || $record->utility_tariff_version_id || isset($savedTariff['tariff_version_id']) || array_key_exists('final_amount', $original)
            || $service->billing_scope !== 'individual' || $service->billing_method !== 'metered'
            || $record->allocations->isNotEmpty() || $record->invoice_item_id || $record->utility_meter_event_id
            || $record->applied_rate !== null || $record->amount === null || !empty($original['adjustments'])
            || !empty($original['review_reason']) || !$from || !$to || $from > $to || $to > today()->toDateString()
            || $record->prev_reading === null || $record->curr_reading === null) return null;

        // Older entry screens stored date-only measurements, including same-day readings.
        // Validate their existing figures and charge; never invent dates or change later readings.
        if ($service->meterEvents->contains(fn ($event) => $event->event_date->toDateString() >= $from
            && $event->event_date->toDateString() <= $to)) return null;
        $tariff = app(UtilityTariffManagementService::class)->resolveTimeline($plan, $service->id, $from, $to);
        if (!($tariff['billable'] ?? false)) return null;
        try {
            if ($this->rateSignature($savedTariff) !== $this->rateSignature($tariff)) return null;
            $calculator = app(UtilityCalculationService::class);
            $usage = $calculator->consumption($record->prev_reading, $record->curr_reading);
            if ($record->consumption !== null && $record->consumption !== $usage) return null;
            $calculation = $calculator->calculate($tariff, $usage);
        } catch (ValidationException $exception) {
            return null;
        }
        if ($calculation['amount'] !== $record->amount) return null;
        return $calculation + ['schema_version' => 2, 'tariff' => $tariff, 'tariff_id' => $tariff['tariff_id'],
            'tariff_version_id' => $tariff['tariff_version_id'], 'tariff_version' => $tariff['version'],
            'final_amount' => $record->amount, 'billing_period' => $record->period->toDateString(),
            'previous' => $record->prev_reading, 'current' => $record->curr_reading,
            'previous_date' => $from, 'current_date' => $to, 'billing_from' => $from, 'billing_to' => $to,
            'unit' => 'm3', 'calculation_origin' => 'validated_legacy_charge', 'legacy_original_snapshot' => $original];
    }

    private function prepare(User $manager, string $month, bool $lock = false): array
    {
        $settings = app(SettingService::class)->getEffectiveSettings($manager->fresh());
        $issueDate = today()->toDateString();
        $dueDate = today()->addDays((int) data_get($settings, 'due_date_config.value.days', 7))->toDateString();
        $records = Utility::where('type', 'water')->whereDate('period', $month.'-01')
            ->whereHas('utilityService', fn ($q) => $q->forManagement($manager->id))
            ->with(['allocations' => fn ($q) => $q->orderBy('id'), 'utilityService.meterEvents', 'utilityService.location' => fn (MorphTo $morph) => $morph->morphWith([
                Unit::class => ['property'], Room::class => ['unit.property'],
            ])])->orderBy('id')->when($lock, fn ($q) => $q->lockForUpdate())->get();
        $locationIds = $records->map(fn ($r) => $r->utilityService->location_id)->unique();
        $allocationLeaseIds = $records->flatMap(fn ($r) => $r->allocations->pluck('lease_id'))->filter()->unique();
        $leases = Lease::where(fn ($q) => $q->whereIn('leasable_id', $locationIds)->orWhereIn('id', $allocationLeaseIds))
            ->whereNull('cancelled_at')->with(['tenant.user', 'leasable'])->orderBy('id')->when($lock, fn ($q) => $q->lockForUpdate())->get();
        $leaseMap = $leases->keyBy('id');
        $usedSubmeters = UtilityAllocation::where('management_user_id', $manager->id)->whereNull('superseded_at')->get()
            ->map(fn ($a) => data_get($a->snapshot, 'submeter_reading_id'))->filter()->all();
        $duplicates = $records->groupBy('utility_service_id')->filter(fn ($rows) => $rows->count() > 1)->keys();
        $entries = []; $skipped = []; $fingerprints = []; $legacyPlan = null;
        foreach ($records as $record) {
            $service = $record->utilityService;
            if (!$service->location || app(UtilitySetupService::class)->managerFor($service->location)?->id !== $manager->id) continue;
            [$from, $to] = UtilityAllocationService::interval($record);
            $matching = $leases->filter(fn ($lease) => $lease->leasable_type === $service->location_type
                && $lease->leasable_id === $service->location_id && $from && $to && $lease->start_date
                && $lease->start_date->toDateString() <= $to
                && !collect([$lease->end_date, $lease->checked_out_at, $lease->agreement_ended_at])->filter()->contains(fn ($d) => $d->toDateString() < $from));
            $reason = null; $candidates = []; $refundFingerprints = [];
            $snapshot = $record->calculation_details ?? []; $legacySnapshot = null;
            if (!array_key_exists('schema_version', $snapshot) && !$record->invoice_item_id && $record->amount !== null) {
                $legacyPlan ??= app(UtilityTariffManagementService::class)->timeline($manager);
                $legacySnapshot = $this->legacySnapshot($record, $legacyPlan, $from, $to);
                $snapshot = $legacySnapshot ?? $snapshot;
            }
            $consumption = $legacySnapshot['usage'] ?? $record->consumption;
            $method = $snapshot['method'] ?? data_get($snapshot, 'tariff.calculation');
            if ($record->invoice_item_id) $reason = 'Invoice already generated';
            elseif ($service->trashed() || $service->status !== 'active' || !in_array($service->billing_method, ['metered', 'fixed'])) $reason = 'Meter is not active';
            elseif ($duplicates->contains($service->id)) $reason = 'Multiple readings for this meter and month need review';
            elseif (!empty($snapshot['review_reason'])) $reason = $snapshot['review_reason'];
            elseif ($record->status !== 'confirmed' || !$from || !$to || $record->amount === null) $reason = 'Save complete readings, dates and calculation first';
            elseif (($snapshot['schema_version'] ?? null) !== 2 || ($snapshot['final_amount'] ?? null) !== $record->amount) $reason = 'Saved charge snapshot needs provenance review';
            elseif (!data_get($snapshot, 'tariff.billable', false)) $reason = 'The applied official tariff needs verification or a contractual custom tariff';
            elseif (!in_array($method, ['fixed', 'actual_bill']) && ($record->prev_reading === null || $record->curr_reading === null
                || $consumption === null || $record->curr_reading_date?->gt(today()))) $reason = 'Complete valid physical meter readings before billing';
            elseif ($record->amount <= 0) $reason = 'No positive amount to collect';
            elseif (in_array($record->id, $usedSubmeters, true)) $reason = 'This submeter is already used by a shared-source allocation';
            elseif ($service->billing_scope === 'shared') {
                $allocations = $record->allocations;
                if ($allocations->isEmpty()) $reason = 'Allocate this shared source charge to leases or vacancy shares first';
                elseif ($allocations->sum('amount') !== $record->amount || $allocations->contains(fn ($a) => $a->revision !== $record->allocation_revision
                    || !hash_equals(UtilityAllocationService::sourceHash($record), data_get($a->snapshot, 'source_hash', '')))) $reason = 'Allocation source changed; review and reallocate before billing';
                else foreach ($allocations as $allocation) {
                    if (!$allocation->lease_id || $allocation->amount <= 0 || $allocation->invoice_item_id) continue;
                    $lease = $leaseMap->get($allocation->lease_id);
                    if (!$this->eligibleLease($lease, $manager, $allocation->from_date->toDateString(), $allocation->to_date->toDateString())) {
                        $reason = 'An allocated lease does not cover its approved interval'; break;
                    }
                    $candidates[] = ['allocation_id' => $allocation->id, 'lease' => $lease, 'amount' => $allocation->amount,
                        'from' => $allocation->from_date->toDateString(), 'to' => $allocation->to_date->toDateString(), 'allocation_method' => $allocation->method];
                }
                if (!$reason && !$candidates) $reason = 'Shared charge is invoiced, zero, or retained as a landlord vacancy share';
            } elseif ($method === 'actual_bill') $reason = 'Actual provider bills require an explicit shared allocation policy';
            else {
                $lease = $matching->count() === 1 ? $matching->first() : null;
                if (!$lease) $reason = $matching->count() > 1 ? 'Tenant change or overlapping leases require allocation' : 'No direct lease; shared meters require allocation';
                elseif (!$this->eligibleLease($lease, $manager, $from, $to)) $reason = 'A valid eligible lease must cover the complete billing period';
                else $candidates[] = ['allocation_id' => null, 'lease' => $lease, 'amount' => $snapshot['final_amount'], 'from' => $from, 'to' => $to, 'allocation_method' => null];
            }
            if (!$reason) foreach ($candidates as $candidate) {
                try {
                    $refunds = app(RefundService::class)->assertInvoiceCreationAllowed($candidate['lease']);
                    $refundFingerprints[] = $refunds->map->getAttributes()->all();
                } catch (ValidationException $exception) { $reason = 'Checkout settlement prevents new invoices'; break; }
            }
            $fingerprints[] = [$record->getAttributes(), $service->getAttributes(), $legacySnapshot,
                $matching->map(fn ($l) => [$l->getAttributes(), $l->tenant?->user?->name])->values()->all(),
                $record->allocations->map->getAttributes()->all(),
                array_map(fn ($c) => [$c['lease']->getAttributes(), $c['lease']->tenant?->user?->name], $candidates), $refundFingerprints, $reason];
            if ($reason) { $skipped[] = ['location' => $service->location_label, 'reason' => $reason]; continue; }
            $allocationMap = $record->allocations->keyBy('id');
            $allocationWeight = $record->allocations->sum('weight');
            foreach ($candidates as $candidate) {
                $lease = $candidate['lease'];
                $allocation = $candidate['allocation_id'] ? $allocationMap->get($candidate['allocation_id']) : null;
                $entries[] = ['reading_id' => $record->id, 'allocation_id' => $candidate['allocation_id'], 'service_id' => $service->id,
                    'lease_id' => $lease->id, 'tenant_id' => $lease->tenant_id, 'tenant' => $lease->tenant->user->name, 'location' => $service->location_label,
                    'location_name' => match (true) {
                        $service->location instanceof \App\Models\Property => $service->location->name,
                        $service->location instanceof Unit => $service->location->unit_no,
                        $service->location instanceof Room => $service->location->room_no,
                    },
                    'location_type' => class_basename($service->location),
                    'property_name' => match (true) {
                        $service->location instanceof \App\Models\Property => $service->location->name,
                        $service->location instanceof Unit => $service->location->property?->name,
                        $service->location instanceof Room => $service->location->unit?->property?->name,
                    },
                    'meter' => $service->meter_number ?: $service->id, 'from' => $candidate['from'], 'to' => $candidate['to'],
                    'previous' => $record->prev_reading, 'current' => $record->curr_reading, 'consumption' => $consumption,
                    'legacy_snapshot' => $legacySnapshot,
                    'tariff_method' => $method, 'tariff_kind' => data_get($snapshot, 'tariff.kind'),
                    'tariff_version' => data_get($snapshot, 'tariff.version'), 'allocation_method' => $candidate['allocation_method'],
                    'calculation_details' => $snapshot, 'source_amount' => $record->amount,
                    'allocation_weight' => $allocation?->weight, 'allocation_total_weight' => $allocation ? $allocationWeight : null,
                    'amount' => $candidate['amount']];
            }
        }
        $groups = collect($entries)->groupBy('lease_id');
        $wallet = app(SettingService::class)->allowsAutomaticWalletPayment($manager->fresh());
        return ['month' => $month, 'issue_date' => $issueDate, 'due_date' => $dueDate, 'entries' => $entries,
            'skipped' => $skipped, 'lease_count' => $groups->count(), 'meter_count' => count(array_unique(array_column($entries, 'reading_id'))),
            'total' => array_sum(array_column($entries, 'amount')), 'wallet_enabled' => $wallet,
            'digest' => hash('sha256', json_encode([$manager->id, $month, $issueDate, $dueDate, $wallet, $fingerprints]))];
    }

    public function generate(User $manager, string $month, string $digest, ?array &$walletSummary = null): int
    {
        $walletSummary = null;
        $result = DB::transaction(function () use ($manager, $month, $digest) {
            User::whereKey($manager->id)->lockForUpdate()->firstOrFail();
            $preview = $this->prepare($manager, $month, true);
            if (!hash_equals($digest, $preview['digest']) || !$preview['entries']) {
                throw ValidationException::withMessages(['invoices' => 'The readings or leases changed, or invoices were already generated. Open a fresh preview.']);
            }
            $fee = FeeType::where('category', FeeTypeCategory::UTILITY->value)->whereRaw('LOWER(name) = ?', ['water'])
                ->where(fn ($q) => $q->where('user_id', $manager->id)->orWhere('is_system', true))
                ->orderByRaw('CASE WHEN user_id = ? THEN 0 ELSE 1 END', [$manager->id])->first();
            if (!$fee) $fee = FeeType::create(['user_id' => $manager->id, 'category' => FeeTypeCategory::UTILITY,
                'name' => 'Water', 'original_price' => 0, 'sell_price' => 0, 'is_system' => false, 'is_active' => true]);
            if (!$fee->is_active || !app(SettingService::class)->isFeeTypeActive($fee, app(SettingService::class)->getEffectiveSettings($manager->fresh()))) {
                throw ValidationException::withMessages(['invoices' => 'Activate the Water fee type in Settings before generating invoices.']);
            }
            $template = DocumentTemplate::where('category', 'invoice')->where('status', 'active')
                ->where(fn ($q) => $q->where('user_id', $manager->id)->orWhereNull('user_id'))
                ->orderByRaw('user_id IS NULL')->first();
            $summary = null;
            $invoiceService = app(InvoiceService::class);
            foreach (collect($preview['entries'])->groupBy('lease_id') as $leaseId => $entries) {
                $lease = Lease::whereKey($leaseId)->lockForUpdate()->firstOrFail();
                $refunds = app(RefundService::class)->assertInvoiceCreationAllowed($lease);
                $total = $entries->sum('amount');
                $invoice = Invoice::create([
                    'context' => Invoice::CONTEXT_TENANT, 'billable_type' => $lease->tenant->getMorphClass(),
                    'billable_id' => $lease->tenant_id, 'lease_id' => $lease->id, 'user_id' => $manager->id,
                    'document_template_id' => $template?->id,
                    'invoice_no' => $this->invoiceNumber($manager),
                    'type' => Invoice::TYPE_MANUAL, 'period' => $month.'-01', 'due_date' => $preview['due_date'],
                    'total_amount' => $total, 'amount_paid' => 0, 'amount_balance' => $total,
                    'status' => Invoice::STATUS_UNPAID, 'remarks' => 'Water charges for '.$month,
                ]);
                foreach ($entries as $entry) {
                    $item = $invoice->items()->create(['fee_type_id' => $fee->id, 'amount' => $entry['amount'],
                        'description' => 'Water charges; '.$entry['from'].' to '.$entry['to']
                            .'; '.($entry['consumption'] ?? 'N/A').' m3; '.$entry['tariff_kind'].' '.$entry['tariff_method'].' v'.$entry['tariff_version']
                            .($entry['allocation_method'] ? '; allocation '.$entry['allocation_method'] : '')]);
                    if ($entry['allocation_id']) UtilityAllocation::findOrFail($entry['allocation_id'])->update(['invoice_item_id' => $item->id]);
                    else {
                        $attributes = ['invoice_item_id' => $item->id, 'lease_id' => $lease->id];
                        if ($entry['legacy_snapshot']) $attributes += [
                            'consumption' => $entry['consumption'],
                            'utility_tariff_version_id' => $entry['legacy_snapshot']['tariff_version_id'],
                            'calculation_details' => $entry['legacy_snapshot'] + ['calculated_at' => now()->toIso8601String()],
                        ];
                        Utility::findOrFail($entry['reading_id'])->update($attributes);
                    }
                }
                $payment = $invoiceService->applyWalletToNewInvoice($invoice, $manager);
                if ($payment) $summary = $invoiceService->mergeWalletPaymentSummary($summary, $payment);
                foreach ($refunds as $refund) app(RefundService::class)->refreshSettlement($refund);
            }
            return ['count' => $preview['lease_count'], 'wallet_summary' => $summary];
        }, 3);
        $walletSummary = $result['wallet_summary'];
        return $result['count'];
    }
}
