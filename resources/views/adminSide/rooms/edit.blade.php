<x-app-layout>
    <div class="bg-gray-50 min-h-screen p-6">
        <div class="max-w-3xl mx-auto">

            {{-- Top Bar (same vibe as Owner create) --}}
            <div class="flex items-center justify-between mb-6">
                <div class="flex-1 flex justify-start">
                    <a href="{{ route('admin.rooms.show', $room->id) }}"
                       class="inline-flex items-center text-gray-500 hover:text-indigo-600 transition-colors">
                        <svg class="h-5 w-5 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                  d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
                        </svg>
                        Back
                    </a>
                </div>

                @php
                    $status = strtolower((string) $room->status);
                    $badge = match ($status) {
                        'available', 'vacant' => 'bg-green-100 text-green-800',
                        'occupied' => 'bg-amber-100 text-amber-800',
                        'maintenance' => 'bg-blue-100 text-blue-800',
                        'inactive', 'disabled' => 'bg-gray-100 text-gray-800',
                        default => 'bg-blue-100 text-blue-800',
                    };
                @endphp

                <div class="flex items-center gap-3 whitespace-nowrap">
                    <h1 class="text-2xl font-bold text-slate-900 font-sans">
                        Edit Room {{ $room->room_no ?? $room->id }}
                    </h1>
                    <span class="px-2.5 py-0.5 rounded-full text-xs font-medium {{ $badge }}">
                        {{ $room->status ?? '—' }}
                    </span>
                </div>

                <div class="flex-1"></div>
            </div>

            <p class="text-sm text-gray-500 mb-6">
                Update room details and manage room assets.
            </p>

            {{-- Error Box --}}
            @if ($errors->any())
                <div class="mb-6 rounded-xl border border-red-200 bg-red-50 p-4">
                    <div class="text-sm font-semibold text-red-800 mb-2">Please fix the following:</div>
                    <ul class="list-disc ml-5 text-sm text-red-700 space-y-1">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <form action="{{ route('admin.rooms.update', $room->id) }}" method="POST">
                @csrf
                @method('PUT')

                @php $today = now()->toDateString(); @endphp

                {{-- Card 1: Room Details --}}
                <div class="bg-white shadow-lg rounded-xl p-6 mb-6">
                    <h2 class="text-xl font-bold text-slate-900 mb-4">Room Details</h2>

                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-slate-900 mb-1">Select Owner</label>
                            <select name="owner_id"
                                    class="w-full rounded-lg border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 shadow-sm"
                                    required>
                                @foreach($owners as $owner)
                                    <option value="{{ $owner->id }}"
                                        {{ old('owner_id', $room->owner_id) == $owner->id ? 'selected' : '' }}>
                                        {{ $owner->user->name ?? '—' }} ({{ $owner->user->email ?? '—' }})
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-slate-900 mb-1">Room Number</label>
                            <input type="text" name="room_no"
                                   value="{{ old('room_no', $room->room_no) }}"
                                   placeholder="exp: 1-1-1A"
                                   class="w-full rounded-lg border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 shadow-sm"
                                   required>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-slate-900 mb-1">Room Type</label>
                            <input type="text" name="room_type"
                                   value="{{ old('room_type', $room->room_type) }}"
                                   placeholder="exp: Single room"
                                   class="w-full rounded-lg border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 shadow-sm"
                                   required>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-slate-900 mb-1">Status</label>
                            @php $statusVal = old('status', $room->status); @endphp
                            <select name="status"
                                    class="w-full rounded-lg border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 shadow-sm"
                                    required>
                                @foreach(['Occupied','Vacant','Maintenance'] as $s)
                                    <option value="{{ $s }}" {{ $statusVal == $s ? 'selected' : '' }}>{{ $s }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-slate-900 mb-1">Address</label>
                            <input type="text" name="address"
                                   value="{{ old('address', $room->address) }}"
                                   placeholder="exp: no100, jalan jaya jalan, taman ampang, 51900, kuala lumpur"
                                   class="w-full rounded-lg border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 shadow-sm"
                                   required>
                        </div>
                    </div>
                </div>

                {{-- Card 2: Room Assets --}}
                <div class="bg-white shadow-lg rounded-xl p-6 mb-6">
                    <div class="flex items-start justify-between gap-4 mb-4">
                        <div>
                            <h2 class="text-xl font-bold text-slate-900">Room Assets</h2>
                            <p class="text-sm text-gray-500 mt-1">Edit existing assets, tick delete, or add more assets below.</p>
                        </div>

                        <button type="button" id="add-asset-btn"
                                class="bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-2 px-4 rounded-lg shadow transition duration-150 ease-in-out">
                            + Add Asset
                        </button>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="w-full min-w-[980px]">
                            <thead>
                                <tr class="text-left text-xs font-semibold text-gray-500 uppercase tracking-wider border-b">
                                    <th class="py-3 pr-4">Name</th>
                                    <th class="py-3 pr-4">Condition</th>
                                    <th class="py-3 pr-4">Last Maintenance</th>
                                    <th class="py-3 pr-4">Remark</th>
                                    <th class="py-3">Delete</th>
                                </tr>
                            </thead>

                            <tbody id="assets-tbody" class="divide-y">
                                @forelse($room->assets as $i => $asset)
                                    <tr class="align-top">
                                        <td class="py-4 pr-4">
                                            <input type="hidden" name="assets[{{ $i }}][id]" value="{{ $asset->id }}">
                                            <input type="text"
                                                   name="assets[{{ $i }}][name]"
                                                   value="{{ old("assets.$i.name", $asset->name) }}"
                                                   class="w-full rounded-lg border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 shadow-sm"
                                                   placeholder="exp: Chair">
                                        </td>

                                        <td class="py-4 pr-4">
                                            @php $condVal = old("assets.$i.condition", $asset->condition); @endphp
                                            <select name="assets[{{ $i }}][condition]"
                                                    class="w-full rounded-lg border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 shadow-sm">
                                                @foreach(['Good','Broken','Maintaining'] as $c)
                                                    <option value="{{ $c }}" {{ $condVal == $c ? 'selected' : '' }}>{{ $c }}</option>
                                                @endforeach
                                            </select>
                                        </td>

                                        <td class="py-4 pr-4">
                                            <input type="date"
                                                   name="assets[{{ $i }}][last_maintenance]"
                                                   max="{{ $today }}"
                                                   value="{{ old("assets.$i.last_maintenance", optional($asset->last_maintenance)->format('Y-m-d')) }}"
                                                   class="w-full rounded-lg border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 shadow-sm">
                                            <div class="text-xs text-gray-400 mt-1">Cannot be a future date.</div>
                                        </td>

                                        <td class="py-4 pr-4">
                                            <input type="text"
                                                   name="assets[{{ $i }}][remark]"
                                                   value="{{ old("assets.$i.remark", $asset->remark) }}"
                                                   class="w-full rounded-lg border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 shadow-sm"
                                                   placeholder="(optional)">
                                        </td>

                                        <td class="py-4">
                                            <label class="inline-flex items-center gap-2 text-sm text-slate-900">
                                                <input type="checkbox"
                                                       name="assets[{{ $i }}][_delete]"
                                                       value="1"
                                                       class="rounded border-gray-300 text-red-600 focus:ring-red-500">
                                                Delete
                                            </label>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="5" class="py-6 text-sm text-gray-500">
                                            No assets yet. Click <span class="font-semibold text-slate-900">+ Add Asset</span> to create one.
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    <div class="pt-5 mt-4 border-t">
                        <div class="text-sm font-semibold text-slate-900">Notes</div>
                        <ul class="mt-2 text-sm text-gray-600 list-disc ml-5 space-y-1">
                            <li>Tick <span class="font-semibold">Delete</span> to remove an existing asset.</li>
                            <li>Add new assets using <span class="font-semibold">+ Add Asset</span>.</li>
                            <li><span class="font-semibold">Last Maintenance</span> cannot be in the future.</li>
                        </ul>
                    </div>
                </div>

                {{-- Card 3: Current Owner + Actions (same card, not separated) --}}
                <div class="bg-white shadow-lg rounded-xl p-6">
                    <h2 class="text-xl font-bold text-slate-900 mb-4">Current Owner</h2>

                    <div class="mb-5">
                        <div class="font-medium text-slate-900">{{ $room->owner->user->name ?? '—' }}</div>
                        <div class="text-sm text-gray-500">{{ $room->owner->user->email ?? '—' }}</div>
                    </div>

                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-slate-900 mb-1">Company</label>
                            <input type="text"
                                   value="{{ $room->owner->company_name ?? 'Individual' }}"
                                   class="w-full rounded-lg border-gray-300 shadow-sm bg-gray-50"
                                   readonly>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-slate-900 mb-1">Phone</label>
                            <input type="text"
                                   value="{{ $room->owner->phone ?? '—' }}"
                                   class="w-full rounded-lg border-gray-300 shadow-sm bg-gray-50"
                                   readonly>
                        </div>
                    </div>

                    {{-- Actions in same card --}}
                    <div class="flex justify-end gap-3 pt-6 mt-6 border-t">
                        <a href="{{ route('admin.rooms.show', $room->id) }}"
                           class="bg-white hover:bg-gray-50 text-slate-900 font-bold py-2 px-6 rounded-lg shadow transition duration-150 ease-in-out border">
                            Cancel
                        </a>

                        <button type="submit"
                                class="bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-2 px-6 rounded-lg shadow transition duration-150 ease-in-out">
                            Save Changes
                        </button>
                    </div>
                </div>

                {{-- Template for new asset row --}}
                @php $startIndex = $room->assets->count(); @endphp

                <template id="asset-row-template">
                    <tr class="align-top">
                        <td class="py-4 pr-4">
                            <input type="text"
                                   name="__NAME__"
                                   class="w-full rounded-lg border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 shadow-sm"
                                   placeholder="exp: Chair">
                        </td>

                        <td class="py-4 pr-4">
                            <select name="__COND__"
                                    class="w-full rounded-lg border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 shadow-sm">
                                <option value="Good">Good</option>
                                <option value="Broken">Broken</option>
                                <option value="Maintaining">Maintaining</option>
                            </select>
                        </td>

                        <td class="py-4 pr-4">
                            <input type="date"
                                   name="__DATE__"
                                   max="{{ now()->toDateString() }}"
                                   class="w-full rounded-lg border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 shadow-sm">
                            <div class="text-xs text-gray-400 mt-1">Cannot be a future date.</div>
                        </td>

                        <td class="py-4 pr-4">
                            <input type="text"
                                   name="__REMARK__"
                                   class="w-full rounded-lg border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 shadow-sm"
                                   placeholder="(optional)">
                        </td>

                        <td class="py-4">
                            <span class="text-xs text-gray-400">New</span>
                        </td>
                    </tr>
                </template>

                <div id="asset-config" data-start-index="{{ $startIndex }}"></div>
            </form>

            <script>
                (function () {
                    const tbody = document.getElementById('assets-tbody');
                    const btn = document.getElementById('add-asset-btn');
                    const tpl = document.getElementById('asset-row-template');
                    const cfg = document.getElementById('asset-config');

                    let idx = Number(cfg.dataset.startIndex || 0);

                    btn.addEventListener('click', function () {
                        const html = tpl.innerHTML
                            .replaceAll('__NAME__', `assets[${idx}][name]`)
                            .replaceAll('__COND__', `assets[${idx}][condition]`)
                            .replaceAll('__DATE__', `assets[${idx}][last_maintenance]`)
                            .replaceAll('__REMARK__', `assets[${idx}][remark]`);

                        const temp = document.createElement('tbody');
                        temp.innerHTML = html.trim();
                        tbody.appendChild(temp.firstElementChild);

                        idx++;
                    });
                })();
            </script>

        </div>
    </div>
</x-app-layout>
