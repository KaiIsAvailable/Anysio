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
                            <!-- Tenant Type (Fixed / Read-only display) -->
                            <div class="mb-4">
                                <x-form.input-label value="Tenant Type" class="mb-1" />
                                <div class="flex items-center h-10 px-3 bg-gray-100 rounded-lg border border-gray-300 text-slate-700 font-medium capitalize">
                                    {{ $tenant->tenant_type }}
                                </div>
                                <!-- Keep the actual value submitted to the backend -->
                                <input type="hidden" name="tenant_type" value="{{ $tenant->tenant_type }}">
                            </div>

                            <!-- Full Name / Company Name -->
                            <div class="mb-4">
                                <x-form.input-label for="name" id="name_label" value="Full Name" class="mb-1" />
                                <x-form.text-input name="name" id="name" value="{{ old('name', $tenant->user->name) }}" class="w-full" required />
                                <x-form.input-error :messages="$errors->get('name')" class="mt-1" />
                            </div>

                            <!-- Email Address -->
                            <div class="mb-4 col-span-1 md:col-span-2">
                                <div class="flex items-center justify-between mb-1">
                                    <x-form.input-label for="email" value="Login Email Address" required />
                                    
                                    {{-- Email Verification Status Badge --}}
                                    @if($tenant->user->email_verified_at)
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-bold bg-emerald-100 text-emerald-800">
                                            Verified ({{ $tenant->user->email_verified_at->format('d/m/Y H:i') }})
                                        </span>
                                    @else
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-bold bg-amber-100 text-amber-800">
                                            Unverified
                                        </span>
                                    @endif
                                </div>

                                <x-form.text-input type="email" name="email" id="email" value="{{ old('email', $tenant->user->email) }}" class="w-full" required autocomplete="username" />
                                <x-form.input-error :messages="$errors->get('email')" class="mt-1" />

                                <!-- Manual Email Verification Checkbox -->
                                <div class="mt-3 flex items-center">
                                    <input type="hidden" name="is_email_verified" value="0">
                                    <input type="checkbox" name="is_email_verified" id="is_email_verified" value="1" 
                                        class="h-4 w-4 text-indigo-600 focus:ring-indigo-500 border-gray-300 rounded"
                                        {{ old('is_email_verified', $tenant->user->email_verified_at ? true : false) ? 'checked' : '' }}>
                                    <label for="is_email_verified" class="ml-2 block text-sm text-gray-700 font-medium">
                                        Mark email address as verified
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>

                    <h2 class="text-xl font-semibold text-slate-800 mb-4">Tenant Details</h2>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <!-- Phone -->
                        <div class="mb-4">
                            <x-form.input-label for="phone" value="Tenant Phone" class="mb-1" />
                            <x-form.text-input name="phone" id="phone" value="{{ old('phone', $tenant->phone) }}" oninput="this.value = this.value.replace(/[^0-9]/g, '')" maxlength="20" inputmode="numeric" class="w-full" required />
                            <x-form.input-error :messages="$errors->get('phone')" class="mt-1" />
                        </div>

                        <!-- CORPORATE FIELDS CONTAINER -->
                        <div id="corporate_fields_container" class="{{ $tenant->tenant_type === 'corporate' ? 'contents' : 'contents hidden' }}">
                            <h2 class="md:col-span-2 text-xl font-semibold text-slate-800">Company Details</h2>
                            <div class="md:col-span-2">
                                <x-form.input-label for="company_name" value="Company Name" class="mb-1" />
                                <x-form.text-input type="text" name="company_name" id="company_name" value="{{ old('company_name', $tenant->user->companies->first()?->name) }}" class="w-full" />
                                <x-form.input-error :messages="$errors->get('company_name')" class="mt-1" />
                            </div>
                            <div class="">
                                <x-form.input-label for="ssm_old_number" value="SSM Old Number" class="mb-1" />
                                <x-form.text-input type="text" name="ssm_old_number" id="ssm_old_number" value="{{ old('ssm_old_number', $tenant->user->companies->first()?->ssm_old_number) }}" class="w-full" />
                                <x-form.input-error :messages="$errors->get('ssm_old_number')" class="mt-1" />
                            </div>
                            <div class="">
                                <x-form.input-label for="ssm_new_number" value="SSM New Number" class="mb-1" />
                                <x-form.text-input type="text" name="ssm_new_number" id="ssm_new_number" value="{{ old('ssm_new_number', $tenant->user->companies->first()?->ssm_new_number) }}" class="w-full" />
                                <x-form.input-error :messages="$errors->get('ssm_new_number')" class="mt-1" />
                            </div>
                            <div class="md:col-span-2">
                                <x-form.input-label for="company_address" value="Company Address" class="mb-1" />
                                <x-form.text-input type="text" name="company_address" id="company_address" value="{{ old('company_address', $tenant->user->companies->first()?->address) }}" class="w-full" />
                                <x-form.input-error :messages="$errors->get('company_address')" class="mt-1" />
                            </div>
                            <div class="">
                                <x-form.input-label for="company_phone" value="Company Phone" class="mb-1" />
                                <x-form.text-input type="text" name="company_phone" id="company_phone" value="{{ old('company_phone', $tenant->user->companies->first()?->phone) }}" class="w-full" />
                                <x-form.input-error :messages="$errors->get('company_phone')" class="mt-1" />
                            </div>
                            <div class="">
                                <x-form.input-label for="company_email" value="Company Email" class="mb-1" />
                                <x-form.text-input type="email" name="company_email" id="company_email" value="{{ old('company_email', $tenant->user->companies->first()?->email) }}" class="w-full" />
                                <x-form.input-error :messages="$errors->get('company_email')" class="mt-1" />
                            </div>
                            <div class="md:col-span-2">
                                <x-form.input-label for="company_logo" value="Company Logo" class="mb-1" />
                                <x-form.file-input name="company_logo" id="company_logo" accept="image/jpeg,image/png,image/webp" class="w-full" />
                                <x-form.input-error :messages="$errors->get('company_logo')" class="mt-1" />
                                @if($tenant->user->companies->first()?->logo_path)
                                    <img src="{{ asset('storage/' . $tenant->user->companies->first()->logo_path) }}" alt="Current company logo" class="mt-2 h-20 w-20 object-contain" />
                                @endif
                            </div>
                            @if($tenant->tenant_type === 'corporate')
                                <div class="md:col-span-2">
                                    @include('adminSide.tenants.partials.document-fields')
                                </div>
                            @endif
                        </div>

                        <!-- INDIVIDUAL FIELDS CONTAINER -->
                        @php
                            $isIc = !empty($tenant->ic_number) || empty($tenant->passport);
                        @endphp
                        <div id="individual_fields_container" class="{{ $tenant->tenant_type === 'corporate' ? 'contents hidden' : 'contents' }}">
                            <!-- Identity Type Selection -->
                            <div class="mb-4 col-span-1 md:grid-cols-2">
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

                            <!-- Occupation -->
                            <div class="mb-4">
                                <x-form.input-label for="occupation" value="Occupation" class="mb-1" />
                                <x-form.text-input name="occupation" id="occupation" value="{{ old('occupation', $tenant->occupation) }}" class="w-full" />
                                <x-form.input-error :messages="$errors->get('occupation')" class="mt-1" />
                            </div>
                        </div>
                    </div>

                    @canany(['owner-admin', 'tenant.modify bank details'])
                    <!-- Bank Account -->
                    <div class="mb-6 border-b pb-4">
                        <h2 class="text-xl font-semibold text-slate-800 mb-4">Bank Account</h2>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <x-form.input-label for="bank_name" value="Bank Name" :required="true" class="mb-1" />
                                <x-form.text-input name="bank_name" id="bank_name" value="{{ old('bank_name', $bankDetail?->bank_name) }}" class="w-full" required />
                                <x-form.input-error :messages="$errors->get('bank_name')" class="mt-1" />
                            </div>
                            <div>
                                <x-form.input-label for="bank_code" value="Bank Code" class="mb-1" />
                                <x-form.text-input name="bank_code" id="bank_code" value="{{ old('bank_code', $bankDetail?->bank_code) }}" class="w-full" />
                                <x-form.input-error :messages="$errors->get('bank_code')" class="mt-1" />
                            </div>
                            <div>
                                <x-form.input-label for="account_holder_name" value="Account Holder Name" :required="true" class="mb-1" />
                                <x-form.text-input name="account_holder_name" id="account_holder_name" value="{{ old('account_holder_name', $bankDetail?->account_holder_name) }}" class="w-full" required />
                                <x-form.input-error :messages="$errors->get('account_holder_name')" class="mt-1" />
                            </div>
                            <div>
                                <x-form.input-label for="account_number" value="Account Number" :required="!$bankDetail" class="mb-1" />
                                <x-form.text-input name="account_number" id="account_number" value="{{ old('account_number', $bankDetail?->account_number) }}" class="w-full" autocomplete="off" inputmode="numeric" pattern="[0-9]*" oninput="this.value = this.value.replace(/[^0-9]/g, '')" required />
                                @if($bankDetail?->account_number)
                                    <p class="mt-1 text-xs text-gray-500">Current account: ******{{ substr($bankDetail->account_number, -4) }}</p>
                                @endif
                                <x-form.input-error :messages="$errors->get('account_number')" class="mt-1" />
                            </div>
                        </div>
                        <input type="hidden" name="is_default" value="1">
                    </div>
                    @endcanany

                    <!-- Emergency Contacts -->
                    <div class="mb-6 border-b border-gray-100 pb-6 mt-6">
                        <div class="flex items-center justify-between mb-4">
                            <h2 class="text-xl font-semibold text-slate-800">Emergency Contacts</h2>
                            <button type="button" id="addContactBtn" class="bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-2 px-4 rounded-lg shadow-sm transition duration-150 ease-in-out text-sm">
                                + Add Contact
                            </button>
                        </div>
                        
                        <div id="contactList" class="space-y-4">
                            @foreach(old('emergency_contacts', $tenant->emergencyContacts) as $index =>$contact)
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
                                                value="{{ is_array($contact) ? ($contact['name'] ?? '') :$contact->name }}" 
                                                class="w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" required>
                                        </div>
                                        <div>
                                            <label class="block text-sm font-medium text-slate-700 mb-1">Relationship</label>
                                            <input type="text" name="emergency_contacts[{{ $index }}][relationship]" 
                                                value="{{ is_array($contact) ? ($contact['relationship'] ?? '') :$contact->relationship }}" 
                                                class="w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                        </div>
                                        <div>
                                            <label class="block text-sm font-medium text-slate-700 mb-1">Phone</label>
                                            <input type="text" name="emergency_contacts[{{ $index }}][phone]" 
                                                value="{{ is_array($contact) ? ($contact['phone'] ?? '') :$contact->phone }}" 
                                                oninput="this.value = this.value.replace(/[^0-9]/g, '')" inputmode="numeric" maxlength="20"
                                                class="w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" required>
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>

                    @if($tenant->tenant_type !== 'corporate')
                        @include('adminSide.tenants.partials.document-fields')
                    @endif

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
        // Identity Type Toggle (IC vs Passport)
        function toggleIdentityInputs(isInit = false) {
            // Safety: If this is a corporate tenant, do not enforce individual required fields
            const tenantTypeInput = document.querySelector('input[name="tenant_type"]');
            if (tenantTypeInput && tenantTypeInput.value === 'corporate') {
                return;
            }

            const identityTypeEl = document.querySelector('input[name="identity_type"]:checked');
            if (!identityTypeEl) return;

            const identityType = identityTypeEl.value;
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
                if (icNumberInput) icNumberInput.required = true;
                if (passportInput) {
                    passportInput.required = false;
                    if (!isInit) passportInput.value = '';
                }
                if (photoLabel) photoLabel.innerText = 'Upload photocopy IC (for rental purpose)';
            } else {
                if (icContainer) icContainer.classList.add('hidden');
                if (passportContainer) passportContainer.classList.remove('hidden');
                if (nationalityInput) {
                    if (nationalityInput.value === 'MALAYSIAN') nationalityInput.value = '';
                    nationalityInput.classList.remove('bg-gray-100');
                    nationalityInput.readOnly = false;
                }
                if (icNumberInput) icNumberInput.required = false;
                if (isInit == false && icNumberInput) icNumberInput.value = '';
                if (passportInput) passportInput.required = true;
                if (photoLabel) photoLabel.innerText = 'Upload photocopy Passport (for rental purpose)';
            }
        }

        // Image / PDF Preview
        function previewImage(input, previewId, containerId) {
            const container = document.getElementById(containerId);
            const file = input.files?.[0];
            if (!file) return;

            // Fixed file size math check (2MB)
            if (!['image/jpeg', 'image/png', 'image/gif', 'application/pdf'].includes(file.type) || file.size > 2048 * 1024) {
                input.value = '';
                alert('Please select a JPEG, PNG, GIF or PDF file up to 2 MB.');
                return;
            }
            if (container.dataset.objectUrl) URL.revokeObjectURL(container.dataset.objectUrl);
            const url = URL.createObjectURL(file);
            container.dataset.objectUrl = url;
            
            container.innerHTML = '';
            const preview = document.createElement(file.type === 'application/pdf' ? 'a' : 'img');
            preview.id = previewId;
            if (file.type === 'application/pdf') {
                preview.href = url;
                preview.target = '_blank';
                preview.rel = 'noopener';
                preview.textContent = 'Open Selected PDF: ' + file.name;
                preview.className = 'text-indigo-600 underline text-sm';
            } else {
                preview.src = url;
                preview.alt = 'Document preview';
                preview.className = 'h-40 w-40 object-cover rounded-lg border border-gray-200';
            }
            container.appendChild(preview);
            container.classList.remove('hidden');
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

            if(addBtn) addBtn.addEventListener('click', addRow);
            
            if(list) {
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
            }
        })();

        document.addEventListener('DOMContentLoaded', () => {
            @if($tenant->tenant_type === 'corporate')
                // Force-remove required from all individual fields when it's a corporate tenant
                ['ic_number', 'passport', 'nationality', 'gender'].forEach(fieldId => {
                    const input = document.getElementById(fieldId);
                    if (input) {
                        input.removeAttribute('required');
                        input.required = false;
                    }
                });
            @else
                toggleIdentityInputs(true);
            @endif
        });
    </script>
</x-app-layout>
