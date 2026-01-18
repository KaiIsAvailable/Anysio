<x-app-layout>
    <div class="bg-gray-50 min-h-screen p-6">
        <div class="max-w-3xl mx-auto">
            <!-- Header -->
            <div class="flex items-center justify-between mb-6">
                <!-- Left: Back Button -->
                <div class="flex-1 flex justify-start">
                    <a href="{{ route('admin.tenants.index') }}" class="inline-flex items-center text-gray-500 hover:text-indigo-600 transition-colors">
                        <svg class="h-5 w-5 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path></svg>
                        Back
                    </a>
                </div>
                
                <!-- Center: Title -->
                <h1 class="text-2xl font-bold text-slate-900 font-sans whitespace-nowrap">Edit Tenant</h1>

                <!-- Right: Spacer for centering -->
                <div class="flex-1"></div>
            </div>

            <div class="bg-white shadow-lg rounded-xl p-6">
                <form action="{{ route('admin.tenants.update', $tenant->id) }}" method="POST" enctype="multipart/form-data">
                    @csrf
                    @method('PUT')

                    <!-- User Details -->
                    <div class="mb-6 border-b pb-4">
                        <h2 class="text-xl font-semibold text-slate-800 mb-4">User Details</h2>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <!-- Name -->
                            <div class="mb-4">
                                <label for="name" class="block text-sm font-medium text-slate-900 mb-1">Full Name</label>
                                <input type="text" name="name" id="name" value="{{ old('name', $tenant->user->name) }}" class="w-full rounded-lg border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 shadow-sm" required>
                                @error('name') <span class="text-red-600 text-xs">{{ $message }}</span> @enderror
                            </div>

                            <!-- Email -->
                            <div class="mb-4">
                                <label for="email" class="block text-sm font-medium text-slate-900 mb-1">Email Address</label>
                                <input type="email" name="email" id="email" value="{{ old('email', $tenant->user->email) }}" class="w-full rounded-lg border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 shadow-sm" required>
                                @error('email') <span class="text-red-600 text-xs">{{ $message }}</span> @enderror
                            </div>

                            <!-- Password fields removed - No edit password here -->
                        </div>
                    </div>

                    <h2 class="text-xl font-semibold text-slate-800 mb-4">Tenant Details</h2>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <!-- Phone -->
                        <div class="mb-4">
                            <label for="phone" class="block text-sm font-medium text-slate-900 mb-1">Phone</label>
                            <input type="text" name="phone" id="phone" value="{{ old('phone', $tenant->phone) }}" class="w-full rounded-lg border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 shadow-sm" required>
                            @error('phone') <span class="text-red-600 text-xs">{{ $message }}</span> @enderror
                        </div>

                        @php
                            $isIc = !empty($tenant->ic_number) || empty($tenant->passport); // Default to IC if both empty (new record edge case) or IC present
                        @endphp

                        <!-- Identity Type Selection -->
                        <div class="mb-4 col-span-1 md:col-span-2">
                            <label class="block text-sm font-medium text-slate-900 mb-2">Identity Document</label>
                            <div class="flex gap-4">
                                <label class="inline-flex items-center">
                                    <input type="radio" name="identity_type" value="ic" class="form-radio text-indigo-600" {{ $isIc ? 'checked' : '' }} onchange="toggleIdentityInputs()">
                                    <span class="ml-2">IC (Malaysian)</span>
                                </label>
                                <label class="inline-flex items-center">
                                    <input type="radio" name="identity_type" value="passport" class="form-radio text-indigo-600" {{ !$isIc ? 'checked' : '' }} onchange="toggleIdentityInputs()">
                                    <span class="ml-2">Passport (Non-Malaysian)</span>
                                </label>
                            </div>
                        </div>

                        <!-- IC Number -->
                        <div class="mb-4" id="ic_container">
                            <label for="ic_number" class="block text-sm font-medium text-slate-900 mb-1">IC Number</label>
                            <input type="text" name="ic_number" id="ic_number" value="{{ old('ic_number', $tenant->ic_number) }}" class="w-full rounded-lg border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 shadow-sm">
                            @error('ic_number') <span class="text-red-600 text-xs">{{ $message }}</span> @enderror
                        </div>

                        <!-- Passport -->
                        <div class="mb-4 {{ $isIc ? 'hidden' : '' }}" id="passport_container">
                            <label for="passport" class="block text-sm font-medium text-slate-900 mb-1">Passport Number</label>
                            <input type="text" name="passport" id="passport" value="{{ old('passport', $tenant->passport) }}" class="w-full rounded-lg border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 shadow-sm">
                            @error('passport') <span class="text-red-600 text-xs">{{ $message }}</span> @enderror
                        </div>

                        <!-- Nationality -->
                        <div class="mb-4">
                            <label for="nationality" class="block text-sm font-medium text-slate-900 mb-1">Nationality</label>
                            <input type="text" name="nationality" id="nationality" value="{{ old('nationality', $tenant->nationality) }}" class="w-full rounded-lg border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 shadow-sm" required>
                            @error('nationality') <span class="text-red-600 text-xs">{{ $message }}</span> @enderror
                        </div>

                        <!-- Gender -->
                        <div class="mb-4">
                            <label for="gender" class="block text-sm font-medium text-slate-900 mb-1">Gender</label>
                            <select name="gender" id="gender" class="w-full rounded-lg border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 shadow-sm" required>
                                <option value="">-- Select Gender --</option>
                                <option value="Male" {{ (old('gender') ?? $tenant->gender) == 'Male' ? 'selected' : '' }}>Male</option>
                                <option value="Female" {{ (old('gender') ?? $tenant->gender) == 'Female' ? 'selected' : '' }}>Female</option>
                            </select>
                            @error('gender') <span class="text-red-600 text-xs">{{ $message }}</span> @enderror
                        </div>

                        <!-- Occupation -->
                        <div class="mb-4">
                            <label for="occupation" class="block text-sm font-medium text-slate-900 mb-1">Occupation</label>
                            <input type="text" name="occupation" id="occupation" value="{{ old('occupation', $tenant->occupation) }}" class="w-full rounded-lg border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 shadow-sm">
                            @error('occupation') <span class="text-red-600 text-xs">{{ $message }}</span> @enderror
                        </div>
                    </div>

                    <!-- Emergency Contacts -->
                    <div class="mb-6 border-b border-gray-100 pb-6">
                        <div class="flex items-center justify-between mb-4">
                            <h2 class="text-xl font-semibold text-slate-800">Emergency Contacts</h2>
                            <button type="button" id="addContactBtn" class="bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-2 px-4 rounded-lg shadow-sm transition duration-150 ease-in-out text-sm">
                                + Add Contact
                            </button>
                        </div>
                        
                        <div id="contactList" class="space-y-4">
                            @foreach($tenant->emergencyContacts as $index => $contact)
                                <div class="contact-row rounded-lg border border-gray-200 bg-gray-50 p-4" data-index="{{ $index }}">
                                    <div class="flex justify-between items-center mb-2">
                                        <span class="text-xs font-semibold text-gray-500 uppercase tracking-wider">Contact Details</span>
                                        <div class="flex items-center gap-2">
                                            <input type="checkbox" name="emergency_contacts[{{ $index }}][_delete]" value="1" class="hidden delete-flag">
                                            <button type="button" class="remove-contact text-red-500 hover:text-red-700 p-1">
                                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                                            </button>
                                        </div>
                                    </div>
                                    <input type="hidden" name="emergency_contacts[{{ $index }}][id]" value="{{ $contact->id }}">
                                    
                                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                                        <div>
                                            <label class="block text-sm font-medium text-slate-700 mb-1">Name</label>
                                            <input type="text" name="emergency_contacts[{{ $index }}][name]" value="{{ old("emergency_contacts.$index.name", $contact->name) }}" class="w-full rounded-lg border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 shadow-sm" required>
                                        </div>
                                        <div>
                                            <label class="block text-sm font-medium text-slate-700 mb-1">Relationship</label>
                                            <input type="text" name="emergency_contacts[{{ $index }}][relationship]" value="{{ old("emergency_contacts.$index.relationship", $contact->relationship) }}" class="w-full rounded-lg border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 shadow-sm">
                                        </div>
                                        <div>
                                            <label class="block text-sm font-medium text-slate-700 mb-1">Phone</label>
                                            <input type="text" name="emergency_contacts[{{ $index }}][phone]" value="{{ old("emergency_contacts.$index.phone", $contact->phone) }}" class="w-full rounded-lg border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 shadow-sm" required>
                                        </div>
                                        <!-- Hidden delete flag for JS to toggle -->
                                        <input type="checkbox" name="emergency_contacts[{{ $index }}][_delete]" value="1" class="hidden delete-flag">
                                    </div>
                                </div>
                            @endforeach
                        </div>

                        <template id="contactRowTpl">
                            <div class="contact-row rounded-lg border border-gray-200 bg-gray-50 p-4">
                                <div class="flex justify-between items-center mb-2">
                                    <span class="text-xs font-semibold text-gray-500 uppercase tracking-wider">New Contact</span>
                                    <button type="button" class="remove-contact text-red-500 hover:text-red-700 p-1">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                                    </button>
                                </div>
                                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                                    <div>
                                        <label class="block text-sm font-medium text-slate-700 mb-1">Name</label>
                                        <input type="text" name="emergency_contacts[__i__][name]" class="w-full rounded-lg border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 shadow-sm" placeholder="Contact Name" required>
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-slate-700 mb-1">Relationship</label>
                                        <input type="text" name="emergency_contacts[__i__][relationship]" class="w-full rounded-lg border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 shadow-sm" placeholder="e.g. Spouse, Parent">
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-slate-700 mb-1">Phone</label>
                                        <input type="text" name="emergency_contacts[__i__][phone]" class="w-full rounded-lg border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 shadow-sm" placeholder="Phone Number" required>
                                    </div>
                                </div>
                            </div>
                        </template>
                    </div>

                    <!-- Photo -->
                    <div class="mb-6">
                        <label for="ic_photo_path" class="block text-sm font-medium text-slate-900 mb-1">Upload photocopy IC (for rental purpose)</label>
                        <p class="text-xs text-gray-500 mb-2">Please ask user to scratch IC before upload.</p>
                        
                        @if($tenant->ic_photo_path)
                            <div class="mb-2">
                                <img src="{{ route('admin.tenants.ic_photo', basename($tenant->ic_photo_path)) }}" alt="Current Photo" class="h-40 w-40 object-cover rounded-lg border border-gray-200" style="width: 150px; height: 150px;">
                            </div>
                        @endif

                        <input type="file" name="ic_photo_path" id="ic_photo_path" class="w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100">
                        <p class="text-xs text-gray-500 mt-1">Leave empty to keep current photo.</p>
                        @error('ic_photo_path') <span class="text-red-600 text-xs">{{ $message }}</span> @enderror
                    </div>

                    <div class="flex justify-end pt-4">
                        <button type="submit" class="bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-2 px-6 rounded-lg shadow transition duration-150 ease-in-out">
                            Update Tenant
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <script>
        // Identity Type Logic
        function toggleIdentityInputs() {
            const identityType = document.querySelector('input[name="identity_type"]:checked').value;
            const icContainer = document.getElementById('ic_container');
            const passportContainer = document.getElementById('passport_container');
            const nationalityInput = document.getElementById('nationality');

            if (identityType === 'ic') {
                icContainer.classList.remove('hidden');
                passportContainer.classList.add('hidden');
                
                // Only overwrite if switching to IC and it's not already Malaysian (or just enforce it)
                // For edit mode, we might want to respect if they manipulated it, BUT requirements say:
                // "If select ic, nationality = Malaysian"
                nationalityInput.value = 'Malaysian';
                nationalityInput.setAttribute('readonly', 'readonly');
                nationalityInput.classList.add('bg-gray-100');
            } else {
                icContainer.classList.add('hidden');
                passportContainer.classList.remove('hidden');
                
                // If switching to passport, allow edit. 
                // Don't clear immediately if it was already Malaysian, but let them change it.
                // Or clear it if switching from IC? Requirements say "else enter passport and it nationality"
                // Let's just remove readonly.
                nationalityInput.removeAttribute('readonly');
                nationalityInput.classList.remove('bg-gray-100');
            }
        }

        // Emergency Contact Logic
        (function() {
            const list = document.getElementById('contactList');
            const tpl = document.getElementById('contactRowTpl');
            const addBtn = document.getElementById('addContactBtn');
            // Start index from current count to avoid collision
            let idx = {{ $tenant->emergencyContacts->count() }}; 

            function addRow() {
                const html = tpl.innerHTML.replaceAll('__i__', String(idx));
                const wrap = document.createElement('div');
                wrap.innerHTML = html.trim();
                const row = wrap.firstElementChild;

                // New items just remove
                row.querySelector('.remove-contact').addEventListener('click', () => {
                    row.remove();
                });

                list.appendChild(row);
                idx++;
            }

            addBtn.addEventListener('click', addRow);
            
            // Handle existing rows
            list.querySelectorAll('.contact-row').forEach(row => {
                const removeBtn = row.querySelector('.remove-contact');
                const deleteFlag = row.querySelector('.delete-flag');
                
                if (removeBtn && deleteFlag) {
                    removeBtn.addEventListener('click', () => {
                        // Mark as deleted
                        deleteFlag.checked = true;
                        // Hide visually
                        row.style.display = 'none';
                    });
                }
            });
        })();

        document.addEventListener('DOMContentLoaded', function() {
            toggleIdentityInputs();
        });
    </script>
</x-app-layout>
