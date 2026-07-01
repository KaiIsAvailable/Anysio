<x-app-layout>
    <div class="bg-gray-50 min-h-screen p-6">
        <div class="max-w-3xl mx-auto">
            <div class="mb-6">
                <a href="{{ route('admin.tenants.index') }}" class="text-indigo-600 hover:text-indigo-900 text-sm font-medium flex items-center transition-colors">
                    <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path>
                    </svg>
                    Back to List
                </a>
                <h1 class="text-2xl font-bold text-slate-900 mt-2">Create Tenant</h1>
            </div>

            <div class="bg-white shadow-lg rounded-xl p-6">
                <x-form.form action="{{ route('admin.tenants.store') }}" method="POST" enctype="multipart/form-data">
                    @csrf

                    <!-- Tenant Details -->
                    <div class="mb-6 border-b border-gray-100 pb-6">
                        <h2 class="text-xl font-semibold text-slate-800 mb-4">Tenant Details</h2>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <!-- Name -->
                            <div>
                                <x-form.input-label for="name" value="Full Name" class="mb-1" />
                                <x-form.text-input name="name" id="name" value="{{ old('name') }}" class="w-full" required />
                                <x-form.input-error :messages="$errors->get('name')" class="mt-1" />
                            </div>

                            <!-- Email (Owners Style) -->
                            <div class="bg-gray-50 p-4 rounded-lg border border-gray-200">
                                <div class="flex items-center justify-between mb-2">
                                    <x-form.input-label value="Email Address" class="mb-0" />
                                    <label class="inline-flex items-center cursor-pointer">
                                        <input type="checkbox" name="random_email" id="random_email" class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500" value="1" {{ old('random_email') ? 'checked' : '' }}>
                                        <span class="ml-2 text-xs font-medium text-indigo-600 tracking-wider">Generate Random</span>
                                    </label>
                                </div>
                                <x-form.text-input type="email" name="email" id="email_input" value="{{ old('email') }}" placeholder="example@mail.com" class="block w-full transition-all" />
                                <p id="helper_text" class="mt-2 text-xs text-gray-500 italic">Click generate random if you do not know user email.</p>
                                <x-form.input-error :messages="$errors->get('email')" class="mt-1" />
                            </div>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                        <!-- Phone -->
                        <div>
                            <x-form.input-label for="phone" value="Phone" class="mb-1" />
                            <x-form.text-input name="phone" id="phone" value="{{ old('phone') }}" oninput="this.value = this.value.replace(/[^0-9]/g, '')" inputmode="numeric" maxlength="20" class="w-full" required />
                            <p id="helper_text" class="mt-2 text-xs text-gray-500 italic">Example: 01xxxxxxxxx</p>
                            <x-form.input-error :messages="$errors->get('phone')" class="mt-1" />
                        </div>

                        <!-- Identity Type Selection -->
                        <div>
                            <x-form.input-label value="Identity Document" class="mb-3" />
                            <div class="flex gap-4">
                                <label class="inline-flex items-center cursor-pointer">
                                    <input type="radio" name="identity_type" value="ic" class="form-radio text-indigo-600" checked onchange="toggleIdentityInputs(false)">
                                    <span class="ml-2 text-gray-700">IC (Malaysian)</span>
                                </label>
                                <label class="inline-flex items-center cursor-pointer">
                                    <input type="radio" name="identity_type" value="passport" class="form-radio text-indigo-600" onchange="toggleIdentityInputs(false)">
                                    <span class="ml-2 text-gray-700">Passport (Non-Malaysian)</span>
                                </label>
                            </div>
                        </div>

                        <!-- IC Number -->
                        <div id="ic_container">
                            <x-form.input-label for="ic_number" value="IC Number" class="mb-1" />
                            <x-form.text-input name="ic_number" id="ic_number" value="{{ old('ic_number') }}" oninput="this.value = this.value.replace(/[^0-9]/g, '')" maxlength="12" inputmode="numeric" class="w-full" />
                            <p id="helper_text" class="mt-2 text-xs text-gray-500 italic">Example: 0109xxxxxxxx</p>
                            <x-form.input-error :messages="$errors->get('ic_number')" class="mt-1" />
                        </div>

                        <!-- Passport -->
                        <div id="passport_container" class="hidden">
                            <x-form.input-label for="passport" value="Passport Number" class="mb-1" />
                            <x-form.text-input name="passport" id="passport" value="{{ old('passport') }}" class="w-full" />
                            <p id="helper_text" class="mt-2 text-xs text-gray-500 italic">Example: A12345678</p>
                            <x-form.input-error :messages="$errors->get('passport')" class="mt-1" />
                        </div>

                        <!-- Nationality -->
                        <div>
                            <x-form.input-label for="nationality" value="Nationality" class="mb-1" />
                            <x-form.text-input name="nationality" id="nationality" value="{{ old('nationality', 'MALAYSIAN') }}" class="w-full bg-gray-100" readonly required />
                            <x-form.input-error :messages="$errors->get('nationality')" class="mt-1" />
                        </div>

                        <!-- Gender -->
                        <div>
                            <x-form.input-label for="gender" value="Gender" class="mb-1" />
                            <x-form.input-select name="gender" id="gender" :options="['Male' => 'Male', 'Female' => 'Female']" placeholder="-- Select Gender --" class="w-full shadow-sm" required />
                            <x-form.input-error :messages="$errors->get('gender')" class="mt-1" />
                        </div>

                        <div class="col-span-1 md:col-span-2">
                            <x-form.input-label for="occupation" value="Occupation" class="mb-1" />
                            <x-form.text-input name="occupation" id="occupation" value="{{ old('occupation') }}" class="w-full" />
                            <x-form.input-error :messages="$errors->get('occupation')" class="mt-1" />
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
                        
                        <div id="contactList" class="space-y-4"></div>
                    </div>

                    <!-- Photo -->
                    <div class="mb-6">
                        <x-form.input-label for="ic_photo_path" id="photo_label" value="IC Photo" class="mb-1" />
                        
                        <!-- Image Preview Container -->
                        <div id="ic_preview_container" class="mb-3 hidden">
                            <img id="ic_preview" src="#" alt="IC Preview" class="h-40 w-40 object-cover rounded-lg border border-gray-200">
                        </div>

                        <x-form.file-input name="ic_photo_path" id="ic_photo_path" onchange="previewImage(this, 'ic_preview', 'ic_preview_container')" class="w-full" />
                        <x-form.input-error :messages="$errors->get('ic_photo_path')" class="mt-1" />
                    </div>

                    <div class="flex justify-end pt-4 border-t border-gray-100">
                        <x-form.primary-button type="submit" loading="loading" class="bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-2.5 px-6 rounded-lg shadow-md transition duration-150 ease-in-out">
                            Save Tenant
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
                    photoLabel.innerText = 'IC Photo';
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
                    photoLabel.innerText = 'Passport Photo';
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
            const oldContacts = @json(old('emergency_contacts', []));
            let idx = 0;

            function addRow(data = null) {
                const html = `
                    <div class="contact-row rounded-lg border border-gray-200 bg-gray-50 p-4">
                        <div class="flex justify-between items-center mb-2">
                            <span class="text-xs font-semibold text-gray-500 tracking-wider">NEW CONTACT</span>
                            <button type="button" class="remove-contact text-sm text-red-600 hover:text-red-800" onclick="this.closest('.contact-row').remove()">
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

                if (data) {
                    const nameInput = row.querySelector(`input[name="emergency_contacts[${idx}][name]"]`);
                    const relationshipInput = row.querySelector(`input[name="emergency_contacts[${idx}][relationship]"]`);
                    const phoneInput = row.querySelector(`input[name="emergency_contacts[${idx}][phone]"]`);
                    
                    if (nameInput) nameInput.value = data.name || '';
                    if (relationshipInput) relationshipInput.value = data.relationship || '';
                    if (phoneInput) phoneInput.value = data.phone || '';
                }

                // Hide remove button for the first row to ensure at least one contact
                const removeBtn = row.querySelector('.remove-contact');
                if (list.children.length === 0) {
                    removeBtn.style.display = 'none';
                }

                list.appendChild(row);
                idx++;
            }

            addBtn.addEventListener('click', () => addRow());

            // Initialize
            list.innerHTML = '';
            
            // Check if oldContacts is a non-empty array or object
            const hasOldData = oldContacts && (
                (Array.isArray(oldContacts) && oldContacts.length > 0) ||
                (typeof oldContacts === 'object' && Object.keys(oldContacts).length > 0)
            );

            if (hasOldData) {
                Object.values(oldContacts).forEach(c => addRow(c));
            } else {
                addRow(); // Add exactly one row by default
            }
        })();

        document.addEventListener('DOMContentLoaded', () => toggleIdentityInputs(true));
    </script>
</x-app-layout>
