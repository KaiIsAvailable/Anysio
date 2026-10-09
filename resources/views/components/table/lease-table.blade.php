@props([
    'leases',
    'showOwner' => true,
    'showTenant' => true,
    'showAction' => true,
])

<div>
    @if($leases && $leases->count() > 0)
        <table class="w-full min-w-[1100px] divide-y divide-gray-200 text-left">
            <thead class="bg-gray-50">
                <tr>
                    <x-table.th name="Property / Unit / Room" />
                    
                    @if($showOwner)
                        <x-table.th name="Owner" />
                    @endif

                    @if($showTenant)
                        <x-table.th name="Tenant" sortField="t" />
                    @endif
                    
                    <x-table.th name="Duration" sortField="d"/>
                    <x-table.th name="Charges"/>
                    <x-table.th name="Status" sortField="s"/>

                    @if($showAction)
                        @canany(['owner-admin', 'leases.upload stamping', 'leases.view agreement', 'leases.cancel lease'])
                        <x-table.th name="Action" />
                        @endcanany
                    @endif
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100 bg-white">
                @foreach($leases as $lease)
                    @php
                        $status = strtolower((string) ($lease->status ?? ''));
                        $badge = get_status_badge($lease->status ?? null);
                        $type = basename(str_replace('\\', '/', $lease->leasable_type));
                    @endphp
                    <tr class="hover:!bg-indigo-50 transition-colors cursor-pointer group duration-150"
                        @canany(['owner-admin', 'leases.show'])
                        onclick="window.location='{{ route('admin.leases.show', $lease->id) }}'"
                        @endcanany>

                        {{-- Property / Unit / Room --}}
                        <td class="px-6 py-4">
                            <div class="text-sm font-semibold text-slate-900">
                                @if($type === 'Unit')
                                    <a href="{{ route('admin.units.show', $lease->leasable->id) }}" class="font-semibold text-indigo-600 hover:text-indigo-900 hover:underline">
                                        {{ $lease->leasable->unit_no }}
                                    </a>
                                @elseif($type === 'Property')
                                    <a href="{{ route('admin.properties.show', $lease->leasable->id) }}" class="font-semibold text-indigo-600 hover:text-indigo-900 hover:underline">
                                        {{ $lease->leasable->name }}
                                    </a>
                                @elseif($type === 'Room')
                                    <a href="{{ route('admin.rooms.show', $lease->leasable->id) }}" class="font-semibold text-indigo-600 hover:text-indigo-900 hover:underline">
                                        {{ $lease->leasable->room_no }}
                                    </a>
                                @else
                                    {{ $type }}: {{ $lease->leasable_id }}
                                @endif
                            </div>
                            <div class="text-sm text-gray-400 italic">{{ $type }}</div>
                        </td>

                        {{-- Owner (Conditional) --}}
                        @if($showOwner)
                            <td class="px-6 py-4">
                                <div class="text-sm text-slate-900">
                                    @if($type === 'Unit' || $type === 'Property')
                                        <a href="{{ route('admin.owners.show', $lease->leasable?->owner->owner->id) }}" class="font-semibold text-indigo-600 hover:text-indigo-900 hover:underline">
                                            {{ $lease->leasable?->owner->name }}
                                        </a>
                                    @elseif($type === 'Room')
                                        <a href="{{ route('admin.owners.show', $lease->leasable?->unit->owner->owner->id) }}" class="font-semibold text-indigo-600 hover:text-indigo-900 hover:underline">
                                            {{ $lease->leasable?->unit->owner->name }}
                                        </a>
                                    @else
                                        No Owner
                                    @endif
                                </div>
                            </td>
                        @endif

                        @if($showTenant)
                            {{-- Tenant --}}
                            <td class="px-6 py-4">
                                <div class="text-sm text-slate-900">
                                    <a href="{{ route('admin.tenants.show', $lease->tenant?->id) }}" class="font-semibold text-indigo-600 hover:text-indigo-900 hover:underline">
                                        {{ $lease->tenant?->user?->name ?? 'N/A' }}
                                    </a>
                                </div>   
                            </td>
                        @endif

                        {{-- Duration --}}
                        <td class="px-6 py-4">
                            <div class="text-sm text-slate-900">
                                {{ $lease->start_date_formatted }} to {{ $lease->end_date_formatted }}
                                @if ($lease->agreement_ended_at)
                                    <span class="text-sm text-gray-500 block">End Date:</span>
                                    <div class="text-sm text-slate-900">{{ $lease->agreement_ended_at_formatted }}</div>
                                @elseif ($lease->checked_out_at)
                                    <span class="text-sm text-gray-500 block">Check Out Date:</span>
                                    <div class="text-sm text-slate-900">{{ $lease->checked_out_at_formatted }}</div>
                                @endif
                            </div>
                        </td>

                        {{-- Charges --}}
                        <td class="px-6 py-4">
                            <div class="text-sm font-semibold text-indigo-600">
                                RM {{ number_format($lease->rent_price + $lease->charges->sum('amount') / 100, 2) }}
                            </div>
                            @if($lease->charges && $lease->charges->count() > 0)
                                <div class="space-y-0.5 border-t border-gray-100 pt-1 mt-1">
                                    @foreach($lease->charges as $charge)
                                        <div class="text-sm text-slate-600 flex justify-between gap-2">
                                            <span class="truncate" title="{{ $charge->description }}">{{ $charge->description }}:</span>
                                            <span class="text-sm text-slate-900 shrink-0">RM {{ number_format($charge->amount / 100, 2 ) }}</span>
                                        </div>
                                    @endforeach
                                </div>
                            @endif
                        </td>

                        {{-- Status --}}
                        <td class="px-6 py-4">
                            <span class="whitespace-nowrap px-2.5 py-0.5 rounded-full text-sm font-medium {{ $badge }}">
                                {{ strtoupper($lease->status ?? 'N/A') }} 
                                @if($lease->is_pending_renewal) (PENDING RENEWAL) @endif
                            </span>
                        </td>

                        {{-- Actions (Conditional) --}}
                        @if($showAction)
                            {{-- Action Column --}}
                            @canany(['owner-admin', 'leases.upload stamping', 'leases.view agreement', 'leases.cancel lease', 'leases.renew lease', 'leases.check out lease'])
                            <td class="px-6 py-4" x-data="{ 
                                openUpload: {{ $errors->any() && !$errors->has('error') ? 'true' : 'false' }}, 
                                shake: {{ $errors->any() ? 'true' : 'false' }},
                                errorMessage: '{{ $errors->first('error') }}',
                                activeLease: JSON.parse(sessionStorage.getItem('lastActiveLease') || '{}')
                            }" @click.stop>
                                
                                <div class="flex flex-col gap-3">
                                    
                                    {{-- Stamping Status Badge --}}
                                    @canany(['owner-admin', 'leases.upload stamping'])
                                    <div class="min-h-[24px] flex items-center">
                                        @if($lease->stamping_status)
                                            <div class="flex items-center gap-2">
                                                <span class="p-1 bg-emerald-100 text-emerald-600 rounded-full">
                                                    <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"></path></svg>
                                                </span>
                                                <a href="{{ route('admin.leases.view-cert', $lease->id) }}" class="text-sm font-bold text-emerald-600 hover:underline">
                                                    View Cert
                                                </a>
                                            </div>
                                        @elseif(!in_array(strtolower($lease->status), ['check out', 'end']))
                                            <span class="text-sm font-semibold text-amber-600 flex items-center gap-1 whitespace-nowrap uppercase">
                                                Pending Stamping
                                            </span>
                                        @else
                                            <span class="text-sm text-gray-400 font-medium uppercase tracking-tighter">
                                                NO STAMPING NEEDED
                                            </span>
                                        @endif
                                    </div>
                                    @endcanany

                                    {{-- Actions Dropdown Menu --}}
                                    <div class="relative" x-data="{ 
                                        openDropdown: false, 
                                        openUpwards: false,
                                        toggleDropdown() {
                                            // If it's about to open, close all other dropdowns first
                                            if (!this.openDropdown) {
                                                window.dispatchEvent(new CustomEvent('close-all-dropdowns'));
                                            }
                                            this.openDropdown = !this.openDropdown;
                                            if (this.openDropdown) {
                                                this.$nextTick(() => {
                                                    const buttonRect = this.$refs.toggleBtn.getBoundingClientRect();
                                                    const estimatedDropdownHeight = 220; 
                                                    const spaceBelow = window.innerHeight - buttonRect.bottom;
                                                    this.openUpwards = spaceBelow < estimatedDropdownHeight;
                                                });
                                            }
                                        }
                                    }" @close-all-dropdowns.window="openDropdown = false">

                                        {{-- Dropdown Toggle Button --}}
                                        <button x-ref="toggleBtn" 
                                                @click="toggleDropdown()" 
                                                type="button"
                                                class="w-full px-3 py-1.5 bg-gray-50 text-gray-700 text-xs font-black rounded-lg border border-gray-200 hover:bg-gray-100 transition-all shadow-sm flex items-center justify-between whitespace-nowrap">
                                            <span>ACTIONS</span>
                                            <svg class="w-4 h-4 transition-transform" :class="openDropdown ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                                            </svg>
                                        </button>

                                        {{-- Dropdown Panel --}}
                                        <div x-show="openDropdown" 
                                            @click.away="openDropdown = false"
                                            x-transition:enter="transition ease-out duration-100"
                                            x-transition:enter-start="transform opacity-0 scale-95"
                                            x-transition:enter-end="transform opacity-100 scale-100"
                                            x-transition:leave="transition ease-in duration-75"
                                            x-transition:leave-start="transform opacity-100 scale-100"
                                            x-transition:leave-end="transform opacity-0 scale-95"
                                            :class="openUpwards ? 'bottom-full mb-2' : 'mt-1'"
                                            class="absolute right-0 z-50 bg-white border border-gray-200 rounded-lg shadow-lg py-1 space-y-1 min-w-[160px]"
                                            style="display: none;">

                                            {{-- 1. Upload Stamping --}}
                                            @canany(['owner-admin', 'leases.upload stamping'])
                                                @if(!$lease->stamping_status && !in_array(strtolower($lease->status), ['check out']))
                                                    <button type="button" 
                                                        @click="openUpload = true; openDropdown = false;"
                                                        class="w-full text-left px-3 py-2 text-sm font-semibold text-indigo-600 hover:bg-indigo-50 transition-colors flex items-center whitespace-nowrap">
                                                        Upload Stamping
                                                    </button>
                                                @endif
                                            @endcanany

                                            {{-- 2. View Agreement --}}
                                            @canany(['owner-admin', 'leases.view agreement'])
                                                @if (!empty($lease->document_id))
                                                    <button type="button"
                                                        data-base-content="{{ $lease->documentTemplate?->html_template }}"
                                                        data-title="{{ $lease->documentTemplate?->title }}"
                                                        data-replacements="{{ json_encode([
                                                            'status' => $lease->status ?? 'N/A',
                                                            'tenant_name' => $lease->tenant?->user->name ?? 'N/A',
                                                            'tenant_ic' => $lease->tenant?->ic_number ?? 'N/A',
                                                            'owner_name' => ($lease->leasable instanceof Room) ? ($lease->leasable->unit?->owner?->name ?? 'N/A') : ($lease->leasable->owner?->name ?? 'N/A'),
                                                            'owner_ic' => ($lease->leasable instanceof Room) ? ($lease->leasable->unit?->owner?->owner?->ic_number ?? 'N/A') : ($lease->leasable->owner?->owner?->ic_number ?? 'N/A'),
                                                            'property_address' => $lease->leasable?->full_address ?? 'N/A',
                                                            'property_type' => $lease->leasableTypeLabel ?? 'N/A',
                                                            'property_name' => $lease->leasableName ?? 'N/A',
                                                            'rent_mode' => $lease->term_type ?? 'N/A',
                                                            'rent_price' => number_format($lease->rent_price, 2),
                                                            'deposit_mode' => $lease->deposit_mode ?? 'N/A',
                                                            'security_deposit' => number_format($lease->security_deposit, 2),
                                                            'utilities_deposit' => number_format($lease->utilities_deposit, 2),
                                                            'start_date' => $lease->start_date?->format('d/m/Y') ?? 'N/A',
                                                            'end_date' => $lease->end_date?->format('d/m/Y') ?? 'N/A',
                                                            'check_out_date' => $lease->checked_out_at?->format('d/m/Y') ?? 'N/A',
                                                            'end_agreement_date' => $lease->agreement_ended_at?->format('d/m/Y') ?? 'N/A',
                                                        ], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) }}"
                                                        @click="viewAgreement($el); openDropdown = false;"
                                                        class="w-full text-left px-3 py-2 text-sm font-semibold text-gray-700 hover:bg-indigo-50 hover:text-indigo-600 transition-colors flex items-center">
                                                        View Agreement
                                                    </button>
                                                @endif
                                            @endcanany

                                            {{-- 3. Renew Lease --}}
                                            @canany(['owner-admin', 'leases.renew lease'])
                                                @if(!in_array(strtolower($lease->status), ['cancelled', 'check out']))
                                                    <button type="button" 
                                                        @click.stop="window.dispatchEvent(new CustomEvent('open-lease-modal', { detail: { status: 'Renew', leaseId: '{{ $lease->id }}' } })); openDropdown = false;"
                                                        class="w-full text-left px-3 py-2 text-sm font-semibold text-emerald-600 hover:bg-emerald-50 transition-colors flex items-center">
                                                        Renew Lease
                                                    </button>
                                                @endif
                                            @endcanany

                                            {{-- 4. Check Out --}}
                                            @canany(['owner-admin', 'leases.check out lease'])
                                                @if(!in_array(strtolower($lease->status), ['cancelled', 'check out']))
                                                    <button type="button" 
                                                        @click.stop="window.dispatchEvent(new CustomEvent('open-lease-modal', { detail: { status: 'Check Out', leaseId: '{{ $lease->id }}' } })); openDropdown = false;"
                                                        class="w-full text-left px-3 py-2 text-sm font-semibold text-amber-600 hover:bg-amber-50 transition-colors flex items-center">
                                                        Check Out Lease
                                                    </button>
                                                @endif
                                            @endcanany

                                            {{-- 5. Cancel Lease --}}
                                            @canany(['owner-admin', 'leases.cancel lease'])
                                                @if($lease->status != 'cancelled')
                                                    <button type="button"
                                                        @click="
                                                            openDropdown = false;
                                                            $dispatch('open-modal', 'lease-confirm-modal'); 
                                                            $dispatch('open-lease-confirm-modal', { actionUrl: '{{ route('admin.leases.cancel', $lease->id) }}' })
                                                        "
                                                        class="w-full text-left px-3 py-2 text-sm font-semibold text-red-600 hover:bg-red-50 transition-colors flex items-center">
                                                        Cancel Lease
                                                    </button>
                                                @endif
                                            @endcanany

                                        </div>
                                    </div>

                                    {{-- The Stamping Modal component --}}
                                    @canany(['owner-admin', 'leases.upload stamping'])
                                        <div x-show="openUpload" style="display: none;">
                                            <x-modals.lease-stamping-modal :lease="$lease" />
                                        </div>
                                    @endcanany

                                </div>
                            </td>
                            @endcanany
                        @endif
                    </tr>
                @endforeach
            </tbody>
        </table>
    @else
        <div class="text-center py-12 bg-white">
            <h3 class="text-lg font-medium text-slate-900">No leases found</h3>
        </div>
    @endif
</div>