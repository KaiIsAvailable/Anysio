<x-app-layout>
    <div class="bg-gray-50 min-h-screen p-6">
        <div class="max-w-3xl mx-auto">
            <!-- Header -->
            <div class="flex items-center justify-between mb-6">
                <div class="flex-1 flex justify-start">
                    <a href="{{ route('admin.tenants.index') }}" class="text-indigo-600 hover:text-indigo-900 text-sm font-medium flex items-center">
                        <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path></svg>
                        Back to Tenants List
                    </a>
                </div>
                <h1 class="text-2xl font-bold text-slate-900 font-sans whitespace-nowrap">Edit Tenant</h1>
                <div class="flex-1"></div>
            </div>

            <div class="bg-white shadow-lg rounded-xl p-6">
                <x-form.form action="{{ route('admin.tenants.update', $tenant->id) }}" method="POST" enctype="multipart/form-data">
                    @csrf
                    @method('PUT')

                    <!-- User Details -->
                    <div class="mb-6 border-b pb-4">
                        <h2 class="text-xl font-semibold text-slate-800 mb-4">User Details</h2>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div class="mb-4">
                                <x-form.input-label for="name" value="Full Name" class="mb-1" />
                                <x-form.text-input name="name" id="name" value="{{ old('name', $tenant->user->name) }}" class="w-full" required />
                                <x-form.input-error :messages="$errors->get('name')" class="mt-1" />
                            </div>

                            <div class="mb-4">
                                <x-form.input-label for="email" value="Email Address" class="mb-1" />
                                <x-form.text-input type="email" name="email" id="email" value="{{ old('email', $tenant->user->email) }}" class="w-full" required />
                                <x-form.input-error :messages="$errors->get('email')" class="mt-1" />
                            </div>
                        </div>
                    </div>

                    <h2 class="text-xl font-semibold text-slate-800 mb-4">Tenant Details</h2>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <!-- Phone -->
                        <div class="mb-4">
                            <x-form.input-label for="phone" value="Phone" class="mb-1" />
                            <x-form.text-input name="phone" id="phone" value="{{ old('phone', $tenant->phone) }}" oninput="this.value = this.value.replace(/[^0-9]/g, '')" maxlength="20" inputmode="numeric" class="w-full" required />
                            <x-form.input-error :messages="$errors->get('phone')" class="mt-1" />
                        </div>

                        @php
                            $isIc = !empty($tenant->ic_number) || empty($tenant->passport);
                        @endphp

                        <!-- Identity Type Selection -->
                        <div class="mb-4 col-span-1 md:col-span-2">
                            <x-form.input-label value="Identity Document" class="mb-2" />
                            <div class="flex gap-4">
                                <label class="inline-flex items-center cursor-pointer">
                                    <input type="radio" name="identity_type" value="ic" class="form-radio text-indigo-600" {{ $isIc ? 'checked' : '' }} onchange="toggleIdentityInputs(false)">
                                    <span class="ml-2">IC (Malaysian)</span>
                                </label>
                                <label class="inline-flex items-center cursor-pointer">
                                    <input type="radio" name="identity_type" value="passport" class="form-radio text-indigo-600" {{ !$isIc ? 'checked' : '' }} onchange="toggleIdentityInputs(false)">
                                    <span class="ml-2">Passport (Non-Malaysian)</span>
                                </label>
                            </div>
                        </div>

                        <!-- IC Number -->
                        <div class="mb-4" id="ic_container">
                            <x-form.input-label for="ic_number" value="IC Number" class="mb-1" />
                            <x-form.text-input name="ic_number" id="ic_number" value="{{ old('ic_number', $tenant->ic_number) }}" oninput="this.value = this.value.replace(/[^0-9]/g, '')" maxlength="12" inputmode="numeric" class="w-full" />
                            <x-form.input-error :messages="$errors->get('ic_number')" class="mt-1" />
                        </div>

                        <!-- Passport -->
                        <div class="mb-4 {{ $isIc ? 'hidden' : '' }}" id="passport_container">
                            <x-form.input-label for="passport" value="Passport Number" class="mb-1" />
                            <x-form.text-input name="passport" id="passport" value="{{ old('passport', $tenant->passport) }}" class="w-full" />
                            <x-form.input-error :messages="$errors->get('passport')" class="mt-1" />
                        </div>

                        <!-- Nationality -->
                        <div class="mb-4">
                            <x-form.input-label for="nationality" value="Nationality" class="mb-1" />
                            <x-form.text-input name="nationality" id="nationality" value="{{ old('nationality', $tenant->nationality) }}" class="w-full" required />
                            <x-form.input-error :messages="$errors->get('nationality')" class="mt-1" />
                        </div>

                        <!-- Gender -->
                        <div class="mb-4">
                            <x-form.input-label for="gender" value="Gender" class="mb-1" />
                            <x-form.input-select name="gender" id="gender" :value="old('gender', $tenant->gender)" :options="['Male' => 'Male', 'Female' => 'Female']" placeholder="-- Select Gender --" class="w-full shadow-sm" required />
                            <x-form.input-error :messages="$errors->get('gender')" class="mt-1" />
                        </div>

                        <div class="col-span-1 md:col-span-2">
                            <x-form.input-label for="occupation" value="Occupation" class="mb-1" />
                            <x-form.text-input name="occupation" id="occupation" value="{{ old('occupation', $tenant->occupation) }}" class="w-full" />
                            <x-form.input-error :messages="$errors->get('occupation')" class="mt-1" />
                        </div>
                    </div>

                    <!-- Emergency Contacts -->
                    <div class="mb-6 border-b border-gray-100 pb-6 mt-6">
                        <div class="flex items-center justify-between mb-4">
                            <h2 class="text-xl font-semibold text-slate-800">Emergency Contacts</h2>
                            <button type="button" id="addContactBtn" class="bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-2 px-4 rounded-lg shadow-sm transition duration-150 ease-in-out text-sm">
                                + Add Contact
                            </button>
                        </div>
                        
                        <div id="contactList" class="space-y-4">
                            @foreach(old('emergency_contacts', $tenant->emergencyContacts) as $index => $contact)
                                <div class="contact-row rounded-lg border border-gray-200 bg-gray-50 p-4">
                                    <div class="flex justify-between items-center mb-2">
                                    <span class="text-xs font-semibold text-gray-500 uppercase tracking-wider">
                                        NEW CONTACT
                                    </span>
                                    @if($index > 0)
                                            <input type="hidden" name="emergency_contacts[{{ $index }}][delete]" value="0" class="delete-flag">
                                            <button type="button" class="remove-edit-contact text-sm text-red-600 hover:text-red-800">
                                                Remove
                                            </button>
                                        @endif
                                    </div>
                                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                                        <div>
                                            <label class="block text-sm font-medium text-slate-700 mb-1">Name</label>
                                            <input type="text" name="emergency_contacts[{{ $index }}][name]" 
                                                value="{{ is_array($contact) ? ($contact['name'] ?? '') : $contact->name }}" 
                                                class="w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" required>
                                        </div>
                                        <div>
                                            <label class="block text-sm font-medium text-slate-700 mb-1">Relationship</label>
                                            <input type="text" name="emergency_contacts[{{ $index }}][relationship]" 
                                                value="{{ is_array($contact) ? ($contact['relationship'] ?? '') : $contact->relationship }}" 
                                                class="w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                        </div>
                                        <div>
                                            <label class="block text-sm font-medium text-slate-700 mb-1">Phone</label>
                                            <input type="text" name="emergency_contacts[{{ $index }}][phone]" 
                                                value="{{ is_array($contact) ? ($contact['phone'] ?? '') : $contact->phone }}" 
                                                oninput="this.value = this.value.replace(/[^0-9]/g, '')" inputmode="numeric" maxlength="20"
                                                class="w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" required>
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>

                    <!-- Photo -->
                    <div class="mb-6">
                        <x-form.input-label for="ic_photo_path" id="photo_label" value="Upload photocopy IC (for rental purpose)" class="mb-1" />
                        <p class="text-xs text-gray-500 mb-2">Please ask user to scratch IC before upload.</p>
                        
                        <div id="ic_preview_container" class="mb-2 {{ $tenant->ic_photo_path ? '' : 'hidden' }}">
                            @if($tenant->ic_photo_path)
                                <img id="ic_preview" src="{{ route('admin.tenants.ic_photo', basename($tenant->ic_photo_path)) }}" alt="Current Photo" class="h-40 w-40 object-cover rounded-lg border border-gray-200">
                            @else
                                <img id="ic_preview" src="#" alt="Preview" class="h-40 w-40 object-cover rounded-lg border border-gray-200">
                            @endif
                        </div>

                        <x-form.file-input name="ic_photo_path" id="ic_photo_path" onchange="previewImage(this, 'ic_preview', 'ic_preview_container')" class="w-full" />
                        <p class="text-xs text-gray-500 mt-1">Leave empty to keep current photo.</p>
                        <x-form.input-error :messages="$errors->get('ic_photo_path')" class="mt-1" />
                    </div>

                    <div class="flex justify-end pt-4 border-t">
                        <x-form.primary-button loading="loading">
                            Update Tenant
                        </x-form.primary-button>
                    </div>
                </x-form.form>
            </div>
        </div>
    </div>

    <script>
        // Identity Type Toggle
        function toggleIdentityInputs(isInit = false) {
            // If window.toggleIdentityInputs is defined in tenants.js, defer to it
            if (window.toggleIdentityInputs && window.toggleIdentityInputs !== toggleIdentityInputs) {
                window.toggleIdentityInputs(isInit);
                return;
            }

            const identityType = document.querySelector('input[name="identity_type"]:checked').value;
            const icContainer = document.getElementById('ic_container');
            const passportContainer = document.getElementById('passport_container');
            const nationalityInput = document.getElementById('nationality');
            const icNumberInput = document.getElementById('ic_number');
            const passportInput = document.getElementById('passport');
            const photoLabel = document.getElementById('photo_label');

            if (identityType === 'ic') {
                if (icContainer) icContainer.classList.remove('hidden');
                if (passportContainer) passportContainer.classList.add('hidden');
                if (nationalityInput) {
                    nationalityInput.value = 'MALAYSIAN';
                    nationalityInput.classList.add('bg-gray-100');
                    nationalityInput.readOnly = true;
                }
                if (icNumberInput) {
                    icNumberInput.required = true;
                }
                if (passportInput) {
                    passportInput.required = false;
                    if (!isInit) {
                        passportInput.value = '';
                    }
                }
                if (photoLabel) {
                    photoLabel.innerText = 'Upload photocopy IC (for rental purpose)';
                }
            } else {
                if (icContainer) icContainer.classList.add('hidden');
                if (passportContainer) passportContainer.classList.remove('hidden');
                if (nationalityInput) {
                    if (nationalityInput.value === 'MALAYSIAN') {
                        nationalityInput.value = '';
                    }
                    nationalityInput.classList.remove('bg-gray-100');
                    nationalityInput.readOnly = false;
                }
                if (icNumberInput) {
                    icNumberInput.required = false;
                    if (!isInit) {
                        icNumberInput.value = '';
                    }
                }
                if (passportInput) {
                    passportInput.required = true;
                }
                if (photoLabel) {
                    photoLabel.innerText = 'Upload photocopy Passport (for rental purpose)';
                }
            }
        }

        // Image Preview
        function previewImage(input, previewId, containerId) {
            const container = document.getElementById(containerId);
            const preview = document.getElementById(previewId);
            
            if (input.files && input.files[0]) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    preview.src = e.target.result;
                    container.classList.remove('hidden');
                }
                reader.readAsDataURL(input.files[0]);
            }
        }

        // Dynamic Emergency Contacts
        (function() {
            const list = document.getElementById('contactList');
            const addBtn = document.getElementById('addContactBtn');
            let idx = {{ count(old('emergency_contacts', $tenant->emergencyContacts)) }};

            function addRow() {
                const html = `
                    <div class="contact-row rounded-lg border border-gray-200 bg-gray-50 p-4 mt-4">
                        <div class="flex justify-between items-center mb-2">
                            <span class="text-xs font-semibold text-gray-500 uppercase tracking-wider">NEW CONTACT</span>
                            <button type="button" class="remove-contact text-sm text-red-600 hover:text-red-800">
                                Remove
                            </button>
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-slate-700 mb-1">Name</label>
                                <input type="text" name="emergency_contacts[${idx}][name]" class="w-full rounded-lg border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 shadow-sm" placeholder="Contact Name" required>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-slate-700 mb-1">Relationship</label>
                                <input type="text" name="emergency_contacts[${idx}][relationship]" class="w-full rounded-lg border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 shadow-sm" placeholder="e.g. Spouse, Parent">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-slate-700 mb-1">Phone</label>
                                <input type="text" name="emergency_contacts[${idx}][phone]" oninput="this.value = this.value.replace(/[^0-9]/g, '')" inputmode="numeric" maxlength="20" class="w-full rounded-lg border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 shadow-sm" placeholder="Phone Number" required>
                            </div>
                        </div>
                    </div>
                `;
                
                const wrap = document.createElement('div');
                wrap.innerHTML = html.trim();
                const row = wrap.firstElementChild;

                row.querySelector('.remove-contact').addEventListener('click', () => row.remove());
                list.appendChild(row);
                idx++;
            }

            addBtn.addEventListener('click', addRow);
            
            // Handle existing rows removal
            list.addEventListener('click', function(e) {
                if (e.target.classList.contains('remove-edit-contact')) {
                    const row = e.target.closest('.contact-row');
                    const deleteFlag = row.querySelector('.delete-flag');
                    if (deleteFlag) {
                        deleteFlag.value = '1';
                        row.classList.add('hidden');
                    } else {
                        row.remove();
                    }
                }
            });
        })();

        document.addEventListener('DOMContentLoaded', () => toggleIdentityInputs(true));
    </script>
</x-app-layout>
