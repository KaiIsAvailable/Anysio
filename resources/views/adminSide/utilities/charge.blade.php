<x-app-layout>
    @php
        $snapshot = $reading->calculation_details ?? [];
        [$from, $to] = \App\Services\UtilityAllocationService::interval($reading);
        $money = fn ($cents) => 'RM '.\App\Services\UtilityDecimal::format((int) $cents, 2);
        $shared = $reading->utilityService->billing_scope === 'shared';
        $issued = $reading->invoice_item_id || $reading->allocations->contains(fn ($a) => $a->invoice_item_id);
        $oldShares = json_decode(old('allocations', '[]'), true);
        $oldShares = is_array($oldShares) && array_is_list($oldShares) ? $oldShares : [];
    @endphp
    <div class="py-10 bg-gray-50 min-h-screen"><div class="max-w-7xl mx-auto px-4 space-y-6">
        @unless($isTenant)<a class="text-indigo-600" href="{{ route('admin.utilities.history', ['management_user_id' => $manager->id]) }}">&lt; Back to Utility Reading History</a>@endunless
        <h1 class="text-2xl font-bold">Water Charge Details</h1>
        <section class="rounded-xl bg-white border p-6 space-y-3">
            <p class="font-semibold">{{ $reading->utilityService->location_label }} · Meter {{ $reading->utilityService->meter_number ?: 'Not recorded' }}</p>
            <p>{{ $from ? \Carbon\Carbon::parse($from)->format('d/m/Y') : 'Date not set' }} to {{ $to ? \Carbon\Carbon::parse($to)->format('d/m/Y') : 'Date not set' }}</p>
            <p>Previous: {{ $reading->prev_reading ?? 'Not recorded' }} · Current: {{ $reading->curr_reading ?? 'Not recorded' }} · Consumption: {{ $reading->consumption ?? 'Not recorded' }} m³</p>
            <p>{{ data_get($snapshot, 'tariff.label', data_get($snapshot, 'tariff.name', 'Legacy tariff')) }} · {{ match (data_get($snapshot, 'tariff.kind')) { 'official' => 'Official provider tariff', 'custom' => 'Landlord configured charge', default => 'Legacy / unverified charge' } }} · Version {{ data_get($snapshot, 'tariff.version', 'Not recorded') }} · {{ $snapshot['method'] ?? 'Legacy calculation' }}</p>
            @if(!$isTenant || !$shared)
                <x-utilities.calculation-details :snapshot="$snapshot" :previous="$reading->prev_reading" :current="$reading->curr_reading" :consumption="$reading->consumption" :amount="$reading->amount" amount-label="Saved source charge" />
            @endif
            @if($reading->invoiceItem)<p>Original invoice: {{ $reading->invoiceItem->invoice?->invoice_no }} ({{ $reading->invoiceItem->invoice?->status }})</p>@endif
            @if($shared)
                <p class="text-sm">The source minimum charge is applied once, then allocated. Tenant shares below are the amounts billed.</p>
                @foreach($allocations as $allocation)<p>{{ $allocation->lease?->tenant?->user?->name ?? 'Landlord / vacancy' }} · {{ $allocation->method }} · {{ $allocation->from_date->format('d/m/Y') }}–{{ $allocation->to_date->format('d/m/Y') }} · {{ $money($allocation->amount) }} · {{ $allocation->invoiceItem?->invoice?->invoice_no ?? 'Not invoiced' }}</p>@endforeach
            @endif
        </section>
        @canany(['super-admin', 'utilities.manage charges'])
        @if($canManage && $shared && !$issued && $reading->amount !== null && $from && $to)
            <section class="rounded-xl bg-white border p-6 space-y-4" x-data="utilityAllocationEditor(@js($reading->amount), @js($from), @js($to), @js($leaseData), @js($submeterUsage), @js($oldShares), @js(old('method', 'equal')))">
                <h2 class="text-xl font-semibold">Allocate Shared Charge</h2>
                <p class="text-sm text-gray-600">Review tenant changes and vacant spaces. Equal shares use one share per selected beneficiary; occupants use person-days. Percentages must total 100%. Manual amounts must total the source exactly.</p>
                <x-form.form :action="route('admin.utilities.allocations.store', $reading)" :autofocus="false" class="space-y-4">
                    <x-form.text-input type="hidden" name="management_user_id" :value="$manager->id" />
                    <x-form.text-input type="hidden" name="source_hash" :value="\App\Services\UtilityAllocationService::sourceHash($reading)" />
                    <x-form.text-input type="hidden" name="allocations" x-bind:value="JSON.stringify(rows)" />
                    <x-form.input-label value="Allocation method" />
                    <x-form.input-select name="method" value="equal" x-effect="if (selectedValues[0] !== method) selectedValues = [method]" @change="if ($event.detail?.value) method = $event.detail.value" :options="['equal'=>'Equal shares','occupants'=>'Occupant count × days','percentage'=>'Configured percentage','submeter'=>'Submeter consumption','manual'=>'Authorized manual amounts']" />
                    <div class="flex flex-wrap gap-3"><x-form.secondary-button @click="useAllLeases()">Use eligible leases</x-form.secondary-button><x-form.secondary-button @click="addLease()">Add lease</x-form.secondary-button><x-form.secondary-button @click="addVacancy()">Add vacancy share</x-form.secondary-button></div>
                    <template x-for="(row, index) in rows" :key="index"><div class="border rounded-lg p-4 grid gap-3 md:grid-cols-3">
                        <div><x-form.input-label value="Beneficiary" /><x-form.input-select x-effect="if (selectedValues[0] !== row.lease_id) selectedValues = [row.lease_id]" @change="if ($event.detail) row.lease_id = $event.detail.value || ''" :options="[''=>'Landlord / vacancy'] + $leaseOptions" /></div>
                        <div><x-form.input-label value="From" /><x-form.date-input id="allocation-from" name="" x-bind:id="'allocation-from-' + index" x-model="row.from_date" /></div>
                        <div><x-form.input-label value="To" /><x-form.date-input id="allocation-to" name="" x-bind:id="'allocation-to-' + index" x-model="row.to_date" /></div>
                        <div x-show="['occupants','percentage'].includes(method) || (method === 'submeter' && !row.lease_id)"><x-form.input-label value="Occupants / percentage / vacancy usage" /><x-form.text-input type="number" step="0.000001" min="0" x-model="row.weight" /></div>
                        <div x-show="method === 'submeter' && row.lease_id"><x-form.input-label value="Connected submeter reading" /><x-form.input-select x-effect="if (selectedValues[0] !== row.submeter_reading_id) selectedValues = [row.submeter_reading_id]" @change="if ($event.detail) row.submeter_reading_id = $event.detail.value || ''" :options="[''=>'Select a reading'] + $submeterOptions" /></div>
                        <div x-show="method === 'manual'"><x-form.input-label value="Amount (RM)" /><x-form.text-input type="number" step="0.01" min="0" x-model="row.amount" /></div>
                        <p x-text="result ? money(result[index]) : 'Complete allocation details'"></p><x-form.secondary-button class="text-red-600" @click="rows.splice(index, 1)">Remove share</x-form.secondary-button>
                    </div></template>
                    <x-form.input-label for="allocation-reason" value="Allocation reason / contractual basis" /><x-form.text-input id="allocation-reason" name="reason" :value="old('reason')" required minlength="5" />
                    <label class="flex gap-2 text-sm"><x-form.checkbox name="vacancy_reviewed" value="1" required />I reviewed all occupied and vacant spaces and tenant changes for this interval.</label>
                    <div class="flex justify-end"><x-form.primary-button loading="loading" disabled-when="!result">Save Allocation</x-form.primary-button></div>
                </x-form.form>
                @if($allocations->isNotEmpty())
                    <x-form.form method="DELETE" :action="route('admin.utilities.allocations.revoke', $reading)" :autofocus="false" class="space-y-3 border-t pt-4">
                        <x-form.text-input type="hidden" name="management_user_id" :value="$manager->id" /><x-form.input-label value="Reason to revoke unbilled allocation" /><x-form.text-input name="reason" required minlength="5" /><x-form.primary-button loading="loading">Revoke Unbilled Allocation</x-form.primary-button>
                    </x-form.form>
                @endif
            </section>
        @endif
        @endcanany
        @canany(['super-admin', 'utilities.manage charges'])
        @if($canManage && !$issued && $reading->period)
            <section class="rounded-xl bg-white border p-6"><h2 class="text-xl font-semibold mb-4">Authorized Adjustment</h2>
                <x-form.form :action="route('admin.utilities.adjustments.store', $reading)" :autofocus="false" class="space-y-3">
                    <x-form.text-input type="hidden" name="management_user_id" :value="$manager->id" /><x-form.input-label value="Signed amount (RM)" /><x-form.text-input name="amount" type="number" step="0.01" required /><x-form.input-label value="Reason" /><x-form.text-input name="reason" required minlength="5" /><div class="flex justify-end"><x-form.primary-button loading="loading">Save Adjustment</x-form.primary-button></div>
                </x-form.form>
            </section>
        @endif
        @endcanany
        @canany(['super-admin', 'utilities.correct invoices'])
        @if(!$isTenant && $issued)
            <section class="rounded-xl bg-white border p-6 space-y-4"><h2 class="text-xl font-semibold">Correct Issued Charge</h2>
                <p class="text-sm text-gray-600">Preview a corrected tenant charge. Confirmation voids the complete current invoice and replaces it, carrying other items unchanged. Original meter readings and allocated amounts remain in history. Correct a later physical reading using a documented meter event.</p>
                <x-form.form :action="route('admin.utilities.corrections.store', $reading)" :autofocus="false" class="space-y-3">
                    <x-form.text-input type="hidden" name="management_user_id" :value="$manager->id" />
                    @if($shared)<x-form.input-label value="Issued tenant share" /><x-form.input-select name="allocation_id" :value="old('allocation_id')" :options="$allocations->whereNotNull('invoice_item_id')->mapWithKeys(fn ($a) => [$a->id => ($a->lease?->tenant?->user?->name ?? 'Tenant').' - '.$money($a->amount)])->all()" />@endif
                    <x-form.input-label value="Corrected final amount (RM)" /><x-form.text-input type="number" name="amount" :value="old('amount')" min="0" step="0.01" required />
                    <x-form.input-label value="Correction reason and calculation evidence" /><x-form.text-input name="reason" :value="old('reason')" required minlength="5" />
                    <div class="flex justify-end"><x-form.primary-button loading="loading">Preview Correction</x-form.primary-button></div>
                </x-form.form>
                @if($proposal)
                    <div class="border rounded-lg p-4 space-y-3"><h3 class="font-semibold">Correction Preview</h3><p>{{ $proposal->originalItem->invoice->invoice_no }}: {{ $money($proposal->original_snapshot['amount']) }} → {{ $money($proposal->replacement_snapshot['final_amount']) }} (difference {{ $money($proposal->difference) }})</p><p>{{ $proposal->reason }}</p>
                        <p>The original invoice has {{ $money($proposal->originalItem->invoice->amount_paid) }} paid. Voiding credits that payment to the tenant wallet. The replacement follows your existing automatic wallet setting.</p>
                        <x-form.form :action="route('admin.utilities.corrections.confirm', [$reading, $proposal])" :autofocus="false" class="space-y-3">
                            <x-form.text-input type="hidden" name="management_user_id" :value="$manager->id" />
                            <label class="flex gap-2"><x-form.checkbox name="acknowledge_void" value="1" required />Void the current invoice and issue this replacement.</label>
                            @if($proposal->originalItem->invoice->amount_paid > 0)<label class="flex gap-2"><x-form.checkbox name="acknowledge_wallet_credit" value="1" required />Credit the paid amount to this tenant's wallet.</label>@endif
                            <div class="flex justify-end"><x-form.primary-button loading="loading">Confirm Correction</x-form.primary-button></div>
                        </x-form.form>
                    </div>
                @endif
            </section>
        @endif
        @endcanany
        @if($corrections->isNotEmpty())<section class="rounded-xl bg-white border p-6 space-y-2"><h2 class="text-xl font-semibold">Correction History</h2>@foreach($corrections as $correction)<p>{{ $correction->created_at->format('d/m/Y H:i') }} · {{ $correction->status }} · {{ $correction->originalItem?->invoice?->invoice_no }} → {{ $correction->replacementItem?->invoice?->invoice_no ?? 'Pending' }} · {{ $correction->reason }} · {{ $money($correction->difference) }}</p>@endforeach</section>@endif
        @unless($isTenant)
        @if($allocationHistory->isNotEmpty())<section class="rounded-xl bg-white border p-6 space-y-2"><h2 class="text-xl font-semibold">Allocation History</h2>@foreach($allocationHistory as $allocation)<p>Revision {{ $allocation->revision }} · {{ $allocation->lease?->tenant?->user?->name ?? 'Landlord / vacancy' }} · {{ $money($allocation->amount) }} · {{ $allocation->superseded_at ? 'Superseded: '.$allocation->superseded_reason : 'Current' }} · {{ $allocation->reason }}</p>@endforeach</section>@endif
        @endunless
    </div></div>
</x-app-layout>
