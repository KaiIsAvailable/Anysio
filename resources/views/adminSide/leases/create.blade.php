<x-app-layout>
    <div class="py-12 bg-gray-50 min-h-screen font-sans">
        <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">

            <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4 mb-8">
                <div>
                    <nav class="flex mb-2" aria-label="Breadcrumb">
                        <a href="{{ route('admin.leases.index') }}" class="text-sm font-medium text-indigo-600 hover:text-indigo-700 flex items-center transition-colors">
                            <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path>
                            </svg>
                            Back to Leases List
                        </a>
                    </nav>
                    <h1 class="text-3xl font-bold text-slate-900 tracking-tight">Create Lease</h1>
                    <p class="mt-2 text-sm text-gray-500">Set up a new tenant lease with utilities.</p>
                </div>
            </div>

            <div class="bg-white rounded-xl shadow-lg overflow-hidden border border-gray-100">
                <form method="POST" action="{{ route('admin.leases.store') }}">
                    @csrf
                    <div class="p-8 space-y-8">

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Room</label>
                                @if($selectedRoom)
                                    <input type="hidden" name="room_id" value="{{ $selectedRoom->id }}">
                                    <div class="mt-1 rounded-lg border border-gray-200 bg-gray-50 px-4 py-3 text-sm text-slate-900">
                                        {{ $selectedRoom->room_no ?? $selectedRoom->id }} - {{ $selectedRoom->owner?->user?->name ?? 'No owner' }}
                                    </div>
                                @else
                                    <select name="room_id" class="mt-1 block w-full rounded-lg border-gray-300 focus:border-indigo-500 focus:ring-indigo-500">
                                        <option value="">Select room</option>
                                        @foreach($rooms as $room)
                                            <option value="{{ $room->id }}" @selected(old('room_id') == $room->id)>
                                                {{ $room->room_no ?? $room->id }} - {{ $room->owner?->user?->name ?? 'No owner' }}
                                            </option>
                                        @endforeach
                                    </select>
                                @endif
                                @error('room_id')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>

                            <div class="relative">
                                <label class="block text-sm font-medium text-gray-700">Tenant IC</label>
                                @if($selectedTenant)
                                    <input type="hidden" name="tenant_id" value="{{ $selectedTenant->id }}">
                                    <div class="mt-1 rounded-lg border border-gray-200 bg-gray-50 px-4 py-3 text-sm text-slate-900">
                                        {{ $selectedTenant->ic_number ?? 'N/A' }} - {{ $selectedTenant->user?->name ?? 'N/A' }}
                                    </div>
                                    <p class="mt-2 text-sm text-gray-500">{{ $selectedTenant->user?->email ?? 'N/A' }}</p>
                                @else
                                    <input type="text" id="tenant-ic" autocomplete="off"
                                           class="mt-1 block w-full rounded-lg border-gray-300 focus:border-indigo-500 focus:ring-indigo-500"
                                           value="{{ old('tenant_ic') }}"
                                           placeholder="Type IC to search">
                                    <input type="hidden" name="tenant_id" id="tenant-id" value="{{ old('tenant_id') }}">
                                    <div id="tenant-results" class="absolute z-10 mt-2 w-full bg-white border border-gray-200 rounded-lg shadow-lg hidden"></div>
                                    @error('tenant_id')
                                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                    @enderror
                                    <p id="tenant-selected" class="mt-2 text-sm text-gray-500"></p>
                                @endif
                            </div>
                        </div>

                        @php
                            $latest = strtolower((string) ($latestStatus ?? ''));
                            $lockToEnd = $forceRenew && $latest === 'check out';
                        @endphp
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Start Date</label>
                                <input type="date" id="start-date" name="start_date" value="{{ old('start_date') }}"
                                       class="mt-1 block w-full rounded-lg border-gray-300 focus:border-indigo-500 focus:ring-indigo-500">
                                @error('start_date')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700">End Date</label>
                                <input type="date" id="end-date" name="end_date" value="{{ old('end_date') }}"
                                       class="mt-1 block w-full rounded-lg border-gray-300 focus:border-indigo-500 focus:ring-indigo-500">
                                @error('end_date')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700">Status</label>
                                @if($forceRenew)
                                    <input type="hidden" name="renew" value="1">
                                    <select name="status" id="lease-status" class="mt-1 block w-full rounded-lg border-gray-300 bg-white text-sm">
                                        @if($lockToEnd)
                                            <option value="End" @selected(old('status', 'End') === 'End')>End</option>
                                        @else
                                            @foreach($statuses as $status)
                                                @if($status !== 'New')
                                                    <option value="{{ $status }}" @selected(old('status', 'Renew') === $status)>
                                                        {{ $status }}
                                                    </option>
                                                @endif
                                            @endforeach
                                        @endif
                                    </select>
                                @else
                                    <select name="status" id="lease-status" class="mt-1 block w-full rounded-lg border-gray-300 focus:border-indigo-500 focus:ring-indigo-500">
                                        <option value="">Auto</option>
                                        @foreach($statuses as $status)
                                            <option value="{{ $status }}" @selected(old('status') === $status)>
                                                {{ $status }}
                                            </option>
                                        @endforeach
                                    </select>
                                @endif
                                @error('status')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Monthly Rent (RM)</label>
                                <input type="text" id="monthly-rent" name="monthly_rent" value="{{ old('monthly_rent') }}"
                                       class="mt-1 block w-full rounded-lg border-gray-300 focus:border-indigo-500 focus:ring-indigo-500"
                                       placeholder="500">
                                @error('monthly_rent')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700">Security Deposit (RM)</label>
                                <input type="text" id="security-deposit" name="security_deposit" value="{{ old('security_deposit') }}"
                                       class="mt-1 block w-full rounded-lg border-gray-300 focus:border-indigo-500 focus:ring-indigo-500"
                                       placeholder="500">
                                @error('security_deposit')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700">Utilities Deposit (RM)</label>
                                <input type="text" id="utilities-deposit" name="utilities_deposit" value="{{ old('utilities_deposit') }}"
                                       class="mt-1 block w-full rounded-lg border-gray-300 focus:border-indigo-500 focus:ring-indigo-500"
                                       placeholder="500">
                                @error('utilities_deposit')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>

                        <div class="pt-4 border-t border-gray-100">
                            <h2 class="text-lg font-semibold text-slate-900 mb-4">Utilities (RM)</h2>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div class="bg-gray-50 border border-gray-200 rounded-lg p-4">
                                    <h3 class="text-sm font-semibold text-slate-900 mb-3">Water</h3>
                                    <div class="grid grid-cols-2 gap-4">
                                        <div>
                                            <label class="block text-xs font-medium text-gray-500">Previous</label>
                                            <input type="text" id="water-prev" name="utilities[water][prev]" value="{{ old('utilities.water.prev') }}"
                                                   class="mt-1 block w-full rounded-lg border-gray-300 focus:border-indigo-500 focus:ring-indigo-500"
                                                   placeholder="0">
                                            @error('utilities.water.prev')
                                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                            @enderror
                                        </div>
                                        <div>
                                            <label class="block text-xs font-medium text-gray-500">Current</label>
                                            <input type="text" id="water-curr" name="utilities[water][curr]" value="{{ old('utilities.water.curr') }}"
                                                   class="mt-1 block w-full rounded-lg border-gray-300 focus:border-indigo-500 focus:ring-indigo-500"
                                                   placeholder="0">
                                            @error('utilities.water.curr')
                                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                            @enderror
                                        </div>
                                    </div>
                                </div>

                                <div class="bg-gray-50 border border-gray-200 rounded-lg p-4">
                                    <h3 class="text-sm font-semibold text-slate-900 mb-3">Electric</h3>
                                    <div class="grid grid-cols-2 gap-4">
                                        <div>
                                            <label class="block text-xs font-medium text-gray-500">Previous</label>
                                            <input type="text" id="electric-prev" name="utilities[electric][prev]" value="{{ old('utilities.electric.prev') }}"
                                                   class="mt-1 block w-full rounded-lg border-gray-300 focus:border-indigo-500 focus:ring-indigo-500"
                                                   placeholder="0">
                                            @error('utilities.electric.prev')
                                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                            @enderror
                                        </div>
                                        <div>
                                            <label class="block text-xs font-medium text-gray-500">Current</label>
                                            <input type="text" id="electric-curr" name="utilities[electric][curr]" value="{{ old('utilities.electric.curr') }}"
                                                   class="mt-1 block w-full rounded-lg border-gray-300 focus:border-indigo-500 focus:ring-indigo-500"
                                                   placeholder="0">
                                            @error('utilities.electric.curr')
                                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                            @enderror
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                    </div>

                    <div class="px-8 py-5 bg-white border-t border-gray-100 flex items-center justify-end">
                        <button type="submit"
                                class="inline-flex items-center justify-center px-5 py-2.5 border border-transparent text-sm font-medium rounded-lg text-white bg-indigo-600 hover:bg-indigo-700 shadow-sm transition-all duration-200">
                            Create Lease
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        (function () {
            const input = document.getElementById('tenant-ic');
            if (!input) {
                return;
            }
            const results = document.getElementById('tenant-results');
            const selected = document.getElementById('tenant-selected');
            const hiddenId = document.getElementById('tenant-id');
            let activeRequest = null;

            function clearSelection() {
                hiddenId.value = '';
                selected.textContent = '';
            }

            function hideResults() {
                results.classList.add('hidden');
                results.innerHTML = '';
            }

            function showResults(list) {
                results.innerHTML = '';
                if (!list.length) {
                    const empty = document.createElement('div');
                    empty.className = 'px-4 py-3 text-sm text-gray-500';
                    empty.textContent = 'No tenant found.';
                    results.appendChild(empty);
                    results.classList.remove('hidden');
                    return;
                }

                list.forEach(function (item) {
                    const row = document.createElement('button');
                    row.type = 'button';
                    row.className = 'w-full text-left px-4 py-3 hover:bg-gray-50';
                    row.innerHTML = '<div class="text-sm font-medium text-slate-900">' +
                        item.ic_number + ' - ' + (item.name || 'Unknown') +
                        '</div><div class="text-xs text-gray-500">' + (item.email || '') + '</div>';
                    row.addEventListener('click', function () {
                        hiddenId.value = item.id;
                        input.value = item.ic_number || '';
                        selected.textContent = (item.name || 'Unknown') + (item.email ? ' (' + item.email + ')' : '');
                        hideResults();
                    });
                    results.appendChild(row);
                });
                results.classList.remove('hidden');
            }

            input.addEventListener('input', function () {
                const value = input.value.trim();
                clearSelection();
                if (value.length < 2) {
                    hideResults();
                    return;
                }

                if (activeRequest) {
                    activeRequest.abort();
                }
                const controller = new AbortController();
                activeRequest = controller;

                fetch('{{ route('admin.leases.tenant-search') }}?ic=' + encodeURIComponent(value), {
                    signal: controller.signal
                })
                    .then(function (response) { return response.json(); })
                    .then(function (data) { showResults(data); })
                    .catch(function () {});
            });

            document.addEventListener('click', function (event) {
                if (!results.contains(event.target) && event.target !== input) {
                    hideResults();
                }
            });
        })();
    </script>

    <script>
        (function () {
            const status = document.getElementById('lease-status');
            if (!status) {
                return;
            }

            const today = new Date().toISOString().slice(0, 10);
            const fields = [
                document.getElementById('start-date'),
                document.getElementById('end-date'),
                document.getElementById('monthly-rent'),
                document.getElementById('security-deposit'),
                document.getElementById('utilities-deposit'),
                document.getElementById('water-prev'),
                document.getElementById('water-curr'),
                document.getElementById('electric-prev'),
                document.getElementById('electric-curr'),
            ];

            function toggleLocked(locked) {
                if (locked) {
                    fields.forEach(function (el) {
                        if (!el) {
                            return;
                        }
                        if (el.type === 'date') {
                            el.value = today;
                        } else {
                            el.value = '0';
                        }
                        el.setAttribute('readonly', 'readonly');
                        el.classList.add('bg-gray-50', 'pointer-events-none');
                    });
                } else {
                    fields.forEach(function (el) {
                        if (!el) {
                            return;
                        }
                        el.removeAttribute('readonly');
                        el.classList.remove('bg-gray-50', 'pointer-events-none');
                    });
                }
            }

            function onStatusChange() {
                const value = (status.value || '').toLowerCase();
                const locked = value === 'check out' || value === 'end';
                toggleLocked(locked);
            }

            status.addEventListener('change', onStatusChange);
            onStatusChange();
        })();
    </script>
</x-app-layout>
