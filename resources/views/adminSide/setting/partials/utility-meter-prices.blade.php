@php
    $tariffManager = $tariffManager ?? get_effective_user();
    $catalog = app(\App\Services\UtilityTariffManagementService::class)->catalog($tariffManager);
    $official = collect($catalog)->filter(fn ($p) => $p['kind'] === 'official');
    $custom = collect($catalog)->filter(fn ($p) => $p['kind'] === 'custom');
    $defaultProfile = collect($catalog)->first(fn ($p) => $p['is_default']);
    $canCustom = in_array($tariffManager->role, ['ownerAdmin', 'agentAdmin']) && \Illuminate\Support\Facades\Gate::any(['super-admin', 'utilities.edit tariffs', 'settings.edit utility meter prices']);
    $meterOptions = $canCustom ? \App\Models\UtilityService::forManagement($tariffManager->id)->where('type', 'water')->with('location')->get()
        ->filter(fn ($s) => $s->location && app(\App\Services\UtilitySetupService::class)->managerFor($s->location)?->id === $tariffManager->id)
        ->mapWithKeys(fn ($s) => [$s->id => $s->location_label.' - '.$s->label])->all() : [];
    $propertyOptions = $canCustom ? app(\App\Services\UtilitySetupService::class)->managedLocations(\App\Models\Property::class, $tariffManager)
        ->mapWithKeys(fn ($p) => [$p->id => $p->name])->all() : [];
    $tariffOptions = collect($catalog)->filter(fn ($p) => $p['is_active'])->mapWithKeys(fn ($p, $id) => [$id => ($p['kind'] === 'official' ? 'Reference: ' : 'Landlord: ').$p['label']])->all();
    $oldAssignmentServices = json_decode(old('service_ids', '[]'), true);
    $oldAssignmentProperties = json_decode(old('property_ids', '[]'), true);
    $oldTariff = old('tariff');
    if (is_array($oldTariff)) $oldTariff['officialMode'] = (bool) old('official', false);
@endphp
<div x-data="utilityTariffManager(@js($catalog), @js(today()->toDateString()), @js($oldTariff))" x-init="assignmentServices = @js(is_array($oldAssignmentServices) ? $oldAssignmentServices : []); assignmentProperties = @js(is_array($oldAssignmentProperties) ? $oldAssignmentProperties : []); assignmentTariff = @js(old('assignment_tariff_id', ''))" class="space-y-6">
    @if($defaultProfile)
        <div class="rounded-xl border border-gray-200 bg-white p-5 space-y-2">
            <p class="font-semibold text-gray-900">Current default: {{ $defaultProfile['label'] }}</p>
            <p class="text-sm text-gray-600">{{ $defaultProfile['kind'] === 'official' ? 'Used automatically for meters without a separate tariff assignment. No setup is needed. Edit Rates saves your own version for this account.' : 'Your saved rates apply to meters without a separate tariff assignment. Editing saves a new version.' }} Saved charges and invoices keep their original rates.</p>
            @if(!empty($defaultProfile['billing_note']))<p class="text-sm text-gray-500">{{ $defaultProfile['billing_note'] }}</p>@endif
        </div>
    @endif
    <section class="p-5 sm:p-8 rounded-xl border border-gray-200 bg-white shadow-sm space-y-5">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div><h2 class="text-xl font-semibold text-gray-900">Official Provider Tariffs</h2><p class="mt-1 text-sm text-gray-500">Reference schedules are separate from landlord charges. Only Super Admin can publish a verified reference version.</p></div>
            @can('super-admin')<x-form.secondary-button @click="newTariff(true)">New Official Reference</x-form.secondary-button>@endcan
        </div>
        @forelse($official as $id => $profile)
            <article class="rounded-lg border border-gray-200 p-4 space-y-3">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <h3 class="font-semibold text-gray-900">{{ $profile['label'] }}</h3>
                    <span class="rounded-full px-3 py-1 text-xs {{ $profile['verification_status'] === 'verified' ? 'bg-emerald-100 text-emerald-800' : 'bg-amber-100 text-amber-800' }}">{{ ucfirst($profile['verification_status']) }} - Version {{ $profile['version'] }}</span>
                </div>
                <dl class="grid gap-3 text-sm sm:grid-cols-3">
                    <div><dt class="text-gray-500">State / Provider</dt><dd>{{ $profile['state'] }} / {{ $profile['provider'] }}</dd></div>
                    <div><dt class="text-gray-500">Customer category</dt><dd>{{ $profile['customer_category'] }}</dd></div>
                    <div><dt class="text-gray-500">Effective from</dt><dd>{{ $profile['effective_date'] ? \Carbon\Carbon::parse($profile['effective_date'])->format('d/m/Y') : 'Not recorded' }}</dd></div>
                    <div><dt class="text-gray-500">Verified on</dt><dd>{{ $profile['verified_at'] ? \Carbon\Carbon::parse($profile['verified_at'])->format('d/m/Y H:i') : 'Administrator verification required' }}</dd></div>
                    <div><dt class="text-gray-500">Source</dt><dd>@if($profile['source'])<a href="{{ $profile['source'] }}" target="_blank" rel="noopener noreferrer" class="text-indigo-600 underline">Reference document</a>@else Not recorded @endif</dd></div>
                </dl>
                @if($profile['verification_status'] !== 'verified')<p class="text-sm text-amber-800">Unverified reference data. These values are not a claim about current official LAP prices and cannot authorize automatic official billing.</p>@endif
                <div class="flex flex-wrap gap-3 text-sm text-gray-600">
                    @foreach($profile['tiers'] ?? [] as $tier)<span>Up to {{ $tier['limit'] ?? 'remaining usage' }} m3: {{ $tier['rate'] === null ? 'Rate not recorded' : 'RM '.$tier['rate'].'/m3' }}</span>@endforeach
                    <span>Minimum: {{ $profile['minimum_charge'] === null ? 'Not recorded' : 'RM '.$profile['minimum_charge'] }}</span>
                </div>
                <div class="flex flex-wrap gap-2">
                    @can('super-admin')<x-form.secondary-button @click="editTariff($el.dataset.tariff, true)" data-tariff="{{ $id }}">Publish New Reference Version</x-form.secondary-button>@endcan
                    @canany(['super-admin', 'utilities.edit tariffs', 'settings.edit utility meter prices'])
                        @if($canCustom)<x-form.secondary-button @click="copyReference($el.dataset.tariff)" data-tariff="{{ $id }}">{{ $profile['is_default'] ? 'Edit Rates' : 'Create Custom Rates' }}</x-form.secondary-button>@endif
                    @endcanany
                </div>
                <details class="text-sm text-gray-500"><summary class="cursor-pointer">Version history</summary><ul class="mt-2 space-y-2">@foreach($profile['history'] as $version)<li>Version {{ $version['version'] }} - {{ $version['effective_from'] ?? 'Unknown date' }} - {{ $version['verified_at'] ? 'Verified' : 'Unverified' }} - {{ $version['reason'] }}</li>@endforeach</ul></details>
            </article>
        @empty<p class="text-sm text-gray-500">No official reference has been recorded.</p>@endforelse
    </section>

    <section class="p-5 sm:p-8 rounded-xl border border-gray-200 bg-white shadow-sm space-y-5">
        <div class="flex flex-wrap items-center justify-between gap-3"><h2 class="text-xl font-semibold text-gray-900">Custom Tariffs</h2>@if($canCustom)<x-form.secondary-button @click="newTariff()">Create Custom Tariff</x-form.secondary-button>@endif</div>
        <p class="text-sm text-gray-600">Landlord-configured charges follow your contractual billing policy. They are not official provider prices.</p>
        @forelse($custom as $id => $profile)
            <article class="rounded-lg border border-gray-200 p-4 space-y-3">
                <div class="flex flex-wrap items-center justify-between gap-3"><div><h3 class="font-semibold">{{ $profile['label'] }}</h3><p class="text-xs text-gray-500">Landlord configured - Version {{ $profile['version'] }} - {{ $profile['is_active'] ? 'Active' : 'Disabled' }} - Effective {{ $profile['effective_date'] }}</p></div>
                    @if($canCustom)<x-form.secondary-button @click="editTariff($el.dataset.tariff)" data-tariff="{{ $id }}">Edit / New Version</x-form.secondary-button>@endif
                </div>
                <p class="text-sm text-amber-800">Custom charges may differ from the provider reference. Confirm the applicable tenancy policy.</p>
                @if($canCustom)
                    <x-form.form method="PATCH" action="{{ route('admin.utilities.tariffs.toggle', $id) }}" :autofocus="false" class="flex justify-end">
                        <x-form.text-input type="hidden" name="management_user_id" :value="$tariffManager->id" /><x-form.text-input type="hidden" name="is_active" :value="$profile['is_active'] ? 0 : 1" />
                        <x-form.primary-button loading="loading">{{ $profile['is_active'] ? 'Disable' : 'Enable' }} Tariff</x-form.primary-button>
                    </x-form.form>
                @endif
                <details class="text-sm text-gray-500"><summary class="cursor-pointer">Modification history</summary><ul class="mt-2 space-y-2">@foreach($profile['history'] as $version)<li>Version {{ $version['version'] }} - {{ $version['effective_from'] }} - {{ $version['created_at'] }} - Author {{ $version['created_by'] }} - {{ $version['reason'] }}</li>@endforeach</ul></details>
            </article>
        @empty<p class="text-sm text-gray-500">No custom water tariff has been created for this account.</p>@endforelse

        @if($canCustom || \Illuminate\Support\Facades\Gate::allows('super-admin'))
        <div x-show="editing" x-cloak class="border-t border-gray-200 pt-5">
            <h3 class="text-lg font-semibold mb-4" x-text="officialMode ? 'Publish an Official Reference Version' : 'Save a Landlord Tariff Version'"></h3>
            <x-form.form method="POST" action="{{ route('admin.utilities.tariffs.store') }}" :autofocus="false" class="space-y-5">
                <x-form.text-input type="hidden" name="management_user_id" :value="in_array($tariffManager->role, ['ownerAdmin', 'agentAdmin']) ? $tariffManager->id : ''" />
                <x-form.text-input type="hidden" name="official" x-bind:value="officialMode ? 1 : 0" />
                <x-form.text-input type="hidden" name="tariff[id]" x-model="fields.id" />
                <x-form.text-input type="hidden" name="tariff[reference_key]" x-model="fields.reference_key" />
                <div class="grid gap-4 sm:grid-cols-2">
                    <div><x-form.input-label for="tariff-name" value="Tariff Name" :required="true" /><x-form.text-input id="tariff-name" name="tariff[name]" x-model="fields.name" required maxlength="255" /></div>
                    <div><x-form.input-label for="tariff-provider" value="Provider (reference metadata)" /><x-form.text-input id="tariff-provider" name="tariff[provider]" x-model="fields.provider" x-bind:required="officialMode" maxlength="255" /></div>
                    <div><x-form.input-label for="tariff-state" value="State" /><x-form.text-input id="tariff-state" name="tariff[state]" x-model="fields.state" x-bind:required="officialMode" maxlength="100" /></div>
                    <div><x-form.input-label for="tariff-category" value="Customer Category" /><x-form.text-input id="tariff-category" name="tariff[customer_category]" x-model="fields.customer_category" x-bind:required="officialMode" maxlength="255" /></div>
                    <div><x-form.input-label for="tariff-effective" value="Rates Effective From" :required="true" :info="__('A new version applies from this date. Saved charges and issued invoices retain their existing snapshots.')" /><x-form.date-input id="tariff-effective" name="tariff[effective_from]" x-model="fields.effective_from" required /></div>
                    <div><x-form.input-label for="tariff-source" value="Source URL" /><x-form.text-input id="tariff-source" type="url" name="tariff[source_url]" x-model="fields.source_url" x-bind:required="officialMode" maxlength="2048" /></div>
                    <div><x-form.input-label for="tariff-calculation" value="Calculation Method" /><x-form.input-select id="tariff-calculation" name="tariff[definition][calculation]" :value="old('tariff.definition.calculation', 'blocks')" :options="['blocks' => 'Custom tiered / progressive blocks', 'flat' => 'Custom flat rate per m3', 'fixed' => 'Fixed monthly water charge', 'actual_bill' => 'Actual provider bill for allocation']" disabled-when="false" x-effect="if (selectedValues[0] !== fields.definition.calculation) selectedValues = [fields.definition.calculation]" @change="if ($event.detail?.value) setMethod($event.detail.value)" /></div>
                    <div><x-form.input-label for="tariff-min-policy" value="Minimum Charge Policy" /><x-form.input-select id="tariff-min-policy" name="tariff[definition][minimum_policy]" :options="['none' => 'No minimum', 'monthly' => 'Monthly minimum including zero usage', 'positive_usage' => 'Minimum only when consumption is positive']" :value="old('tariff.definition.minimum_policy', 'none')" disabled-when="false" x-effect="if (selectedValues[0] !== fields.definition.minimum_policy) selectedValues = [fields.definition.minimum_policy]" @change="if ($event.detail?.value) fields.definition.minimum_policy = $event.detail.value" /></div>
                    <div><x-form.input-label for="tariff-minimum" value="Minimum Monthly Charge (RM)" /><x-form.text-input id="tariff-minimum" name="tariff[definition][minimum_charge]" type="number" min="0" step="0.000001" x-model="fields.definition.minimum_charge" required /></div>
                </div>
                <template x-if="fields.definition.calculation === 'fixed'"><div class="max-w-sm"><x-form.input-label for="tariff-fixed" value="Fixed Monthly Amount (RM)" :required="true" /><x-form.text-input id="tariff-fixed" name="tariff[definition][fixed_amount]" type="number" min="0" step="0.01" x-model="fields.definition.fixed_amount" required /></div></template>
                <template x-if="['fixed', 'actual_bill'].includes(fields.definition.calculation)"><x-form.text-input type="hidden" name="tariff[definition][tiers]" value="" /></template>
                <template x-if="!['fixed', 'actual_bill'].includes(fields.definition.calculation)">
                    <div class="space-y-3"><div class="flex justify-between"><h4 class="font-semibold">Meter Rate Blocks (RM / m3)</h4><x-form.secondary-button @click="addTier()" x-show="fields.definition.calculation !== 'flat'">Add Block</x-form.secondary-button></div>
                        <template x-for="(tier, index) in fields.definition.tiers" :key="index"><div class="grid gap-3 rounded-lg border border-gray-200 p-3 sm:grid-cols-2">
                            <div><x-form.input-label value="Cumulative Upper Limit (m3)" /><template x-if="index < fields.definition.tiers.length - 1"><x-form.text-input type="number" min="0.001" step="0.001" x-model="tier.limit" ::name="'tariff[definition][tiers][' + index + '][limit]'" required /></template><template x-if="index === fields.definition.tiers.length - 1"><div><x-form.text-input type="hidden" ::name="'tariff[definition][tiers][' + index + '][limit]'" value="" /><p class="py-2 text-sm text-gray-500">No upper limit - remaining usage</p></div></template></div>
                            <div><x-form.input-label value="Rate per m3 (RM)" /><div class="flex gap-2"><x-form.text-input type="number" min="0" step="0.000001" x-model="tier.rate" ::name="'tariff[definition][tiers][' + index + '][rate]'" required /><x-form.secondary-button @click="removeTier(index)" x-show="fields.definition.tiers.length > 1">Remove</x-form.secondary-button></div></div>
                        </div></template>
                    </div>
                </template>
                <p x-show="!officialMode" class="text-sm text-amber-800" x-text="deviation"></p>
                <template x-if="!officialMode"><div class="space-y-3"><label class="flex gap-2 text-sm"><x-form.checkbox name="tariff[contract_confirmed]" value="1" required />I confirm these landlord charges follow the tenancy billing policy.</label><label class="flex gap-2 text-sm"><x-form.checkbox name="tariff[make_default]" value="1" x-model="fields.make_default" />Use this tariff as the account default for new charges.</label></div></template>
                @can('super-admin')<template x-if="officialMode"><label class="flex gap-2 text-sm"><x-form.checkbox name="tariff[verified]" value="1" />I have checked the source documentation and verify this official tariff version.</label></template>@endcan
                <div><x-form.input-label for="tariff-reason" value="Reason / Change Note" :required="true" /><x-form.text-input id="tariff-reason" name="tariff[reason]" x-model="fields.reason" required minlength="5" maxlength="2000" /></div>
                <div class="flex justify-end gap-2"><x-form.secondary-button @click="editing = false">Cancel</x-form.secondary-button><x-form.primary-button loading="loading">Save Tariff Version</x-form.primary-button></div>
            </x-form.form>
        </div>
        @endif
    </section>

    @if($canCustom)
    <section class="p-5 sm:p-8 rounded-xl border border-gray-200 bg-white shadow-sm space-y-4">
        <h3 class="text-lg font-semibold">Assign Tariff to Properties / Meters</h3>
        <x-form.form method="POST" :data-assignment-base="route('admin.utilities.tariffs')" x-bind:action="$el.dataset.assignmentBase + '/' + assignmentTariff + '/assign'" :autofocus="false" class="space-y-4">
            <x-form.text-input type="hidden" name="management_user_id" :value="$tariffManager->id" />
            <x-form.text-input type="hidden" name="assignment_tariff_id" x-bind:value="assignmentTariff" /><x-form.text-input type="hidden" name="service_ids" x-bind:value="JSON.stringify(assignmentServices)" /><x-form.text-input type="hidden" name="property_ids" x-bind:value="JSON.stringify(assignmentProperties)" />
            <div><x-form.input-label value="Tariff" /><x-form.input-select :options="$tariffOptions" x-effect="if (selectedValues[0] !== assignmentTariff) selectedValues = [assignmentTariff]" disabled-when="false" @change="if ($event.detail?.value) assignmentTariff = $event.detail.value" /></div>
            <div class="grid gap-4 sm:grid-cols-2"><div><x-form.input-label value="Properties (all current water meters)" /><x-form.input-select :options="$propertyOptions" :multiple="true" x-model="assignmentProperties" disabled-when="false" /></div><div><x-form.input-label value="Individual Meters" /><x-form.input-select :options="$meterOptions" :multiple="true" x-model="assignmentServices" disabled-when="false" /></div></div>
            <div class="grid gap-4 sm:grid-cols-2"><div><x-form.input-label for="assignment-from" value="Effective From" :required="true" /><x-form.date-input id="assignment-from" name="effective_from" :value="old('effective_from', today()->toDateString())" required /></div><div><x-form.input-label for="assignment-reason" value="Reason" :required="true" /><x-form.text-input id="assignment-reason" name="reason" :value="old('reason')" required minlength="5" /></div></div>
            <div class="flex justify-end"><x-form.primary-button loading="loading" disabled-when="!assignmentTariff">Save Assignments</x-form.primary-button></div>
        </x-form.form>
    </section>
    @endif

    <section class="p-5 sm:p-8 rounded-xl border border-gray-200 bg-white shadow-sm space-y-4">
        <h3 class="text-lg font-semibold">Price Calculation Preview</h3>
        <p class="text-sm text-gray-500" x-text="previewProfile?.kind === 'custom' ? 'Custom landlord tariff - not official LAP rates.' : 'Official reference preview. Unverified references cannot be used for automatic billing.'"></p>
        <div x-show="!editing"><x-form.input-label value="Selected Tariff" /><x-form.input-select :options="collect($catalog)->mapWithKeys(fn ($p, $id) => [$id => $p['label']])->all()" :value="array_key_first($catalog)" disabled-when="false" @change="if ($event.detail?.value) previewId = $event.detail.value" /></div>
        <div class="grid gap-4 sm:grid-cols-3"><div><x-form.input-label for="preview-previous" value="Previous Reading (m3)" /><x-form.text-input id="preview-previous" type="number" min="0" step="0.001" x-model="previous" /></div><div><x-form.input-label for="preview-current" value="Current Reading (m3)" /><x-form.text-input id="preview-current" type="number" min="0" step="0.001" x-model="current" /></div><div><x-form.input-label for="preview-bill" value="Actual Provider Bill (RM)" /><x-form.text-input id="preview-bill" type="number" min="0" step="0.01" x-model="billAmount" x-bind:disabled="previewProfile?.calculation !== 'actual_bill'" /></div></div>
        <p class="text-sm text-gray-600">Consumption: <strong x-text="usage ?? 'Enter valid readings'"></strong> m3</p>
        <template x-if="estimate"><div class="space-y-2"><template x-for="(block, index) in estimate.blocks" :key="index"><p class="text-sm text-gray-600" x-text="block.quantity + ' m3 x RM ' + block.rate + ' = ' + money(block.amount)"></p></template><p class="text-sm text-gray-600" x-text="estimate.minimumApplied ? 'Minimum charge applied according to this tariff policy.' : 'Minimum charge not applied.'"></p><p class="text-xl font-semibold text-indigo-700" x-text="'Final charge: ' + money(estimate.amount)"></p></div></template>
        <p class="text-xs text-gray-500">Preview only. Saving a tariff does not recalculate saved charges or issued invoices. Rebates and adjustments must be explicitly configured.</p>
    </section>
</div>
