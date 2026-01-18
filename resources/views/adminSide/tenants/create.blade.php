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
                <h1 class="text-2xl font-bold text-slate-900 font-sans whitespace-nowrap">Create Tenant</h1>

                <!-- Right: Spacer for centering -->
                <div class="flex-1"></div>
            </div>

            <div class="bg-white shadow-lg rounded-xl p-6">
                <form action="{{ route('admin.tenants.store') }}" method="POST" enctype="multipart/form-data">
                    @csrf

                    <!-- User Details -->
                    <div class="mb-6 border-b border-gray-100 pb-6">
                        <h2 class="text-xl font-semibold text-slate-800 mb-4">User Details</h2>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <!-- Name -->
                            <div>
                                <label for="name" class="block text-sm font-medium text-slate-700 mb-1">Full Name</label>
                                <input type="text" name="name" id="name" value="{{ old('name') }}" class="w-full rounded-lg border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 shadow-sm" required>
                                @error('name') <span class="text-red-600 text-xs">{{ $message }}</span> @enderror
                            </div>

                            <!-- Email (Owners Style) -->
                            <div class="bg-gray-50 p-4 rounded-lg border border-gray-200">
                                <div class="flex items-center justify-between mb-2">
                                    <label class="block text-sm font-semibold text-gray-700">Email Address</label>
                                    <label class="inline-flex items-center cursor-pointer">
                                        <input type="checkbox" name="random_email" id="random_email" class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500" value="1" {{ old('random_email') ? 'checked' : '' }}>
                                        <span class="ml-2 text-xs font-medium text-indigo-600 uppercase tracking-wider">Generate Random</span>
                                    </label>
                                </div>
                                <input type="email" name="email" id="email_input" value="{{ old('email') }}" placeholder="example@mail.com" class="block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 transition-all">
                                <p id="helper_text" class="mt-2 text-xs text-gray-500 italic">If random is selected, a temporary password will also be generated.</p>
                                @error('email') <span class="text-red-600 text-xs">{{ $message }}</span> @enderror
                            </div>
                        </div>
                    </div>

                    <h2 class="text-xl font-semibold text-slate-800 mb-4">Tenant Details</h2>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                        <!-- Phone -->
                        <div>
                            <label for="phone" class="block text-sm font-medium text-slate-700 mb-1">Phone</label>
                            <input type="text" name="phone" id="phone" value="{{ old('phone') }}" class="w-full rounded-lg border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 shadow-sm" required>
                            @error('phone') <span class="text-red-600 text-xs">{{ $message }}</span> @enderror
                        </div>

                        <!-- Identity Type Selection -->
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-3">Identity Document</label>
                            <div class="flex gap-4">
                                <label class="inline-flex items-center cursor-pointer">
                                    <input type="radio" name="identity_type" value="ic" class="form-radio text-indigo-600" checked onchange="toggleIdentityInputs()">
                                    <span class="ml-2 text-gray-700">IC (Malaysian)</span>
                                </label>
                                <label class="inline-flex items-center cursor-pointer">
                                    <input type="radio" name="identity_type" value="passport" class="form-radio text-indigo-600" onchange="toggleIdentityInputs()">
                                    <span class="ml-2 text-gray-700">Passport (Non-Malaysian)</span>
                                </label>
                            </div>
                        </div>

                        <!-- IC Number -->
                        <div id="ic_container">
                            <label for="ic_number" class="block text-sm font-medium text-slate-700 mb-1">IC Number</label>
                            <input type="text" name="ic_number" id="ic_number" value="{{ old('ic_number') }}" class="w-full rounded-lg border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 shadow-sm">
                            @error('ic_number') <span class="text-red-600 text-xs">{{ $message }}</span> @enderror
                        </div>

                        <!-- Passport -->
                        <div id="passport_container" class="hidden">
                            <label for="passport" class="block text-sm font-medium text-slate-700 mb-1">Passport Number</label>
                            <input type="text" name="passport" id="passport" value="{{ old('passport') }}" class="w-full rounded-lg border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 shadow-sm">
                            @error('passport') <span class="text-red-600 text-xs">{{ $message }}</span> @enderror
                        </div>

                        <!-- Nationality -->
                        <div>
                            <label for="nationality" class="block text-sm font-medium text-slate-700 mb-1">Nationality</label>
                            <!-- Note: JS will toggle readonly/value -->
                            <input type="text" name="nationality" id="nationality" value="{{ old('nationality', 'Malaysian') }}" class="w-full rounded-lg border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 shadow-sm bg-gray-100" readonly required>
                            @error('nationality') <span class="text-red-600 text-xs">{{ $message }}</span> @enderror
                        </div>

                        <!-- Gender -->
                        <div>
                            <label for="gender" class="block text-sm font-medium text-slate-700 mb-1">Gender</label>
                            <select name="gender" id="gender" class="w-full rounded-lg border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 shadow-sm" required>
                                <option value="">-- Select Gender --</option>
                                <option value="Male" {{ old('gender') == 'Male' ? 'selected' : '' }}>Male</option>
                                <option value="Female" {{ old('gender') == 'Female' ? 'selected' : '' }}>Female</option>
                            </select>
                            @error('gender') <span class="text-red-600 text-xs">{{ $message }}</span> @enderror
                        </div>

                        <div class="col-span-1 md:col-span-2">
                            <label for="occupation" class="block text-sm font-medium text-slate-700 mb-1">Occupation</label>
                            <input type="text" name="occupation" id="occupation" value="{{ old('occupation') }}" class="w-full rounded-lg border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 shadow-sm">
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
                        
                        <div id="contactList" class="space-y-4"></div>

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
                        <label for="ic_photo_path" id="photo_label" class="block text-sm font-medium text-slate-700 mb-1">IC Photo</label>
                        <input type="file" name="ic_photo_path" id="ic_photo_path" class="w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100 transition-colors">
                        @error('ic_photo_path') <span class="text-red-600 text-xs">{{ $message }}</span> @enderror
                    </div>

                    <div class="flex justify-end pt-4 border-t border-gray-100">
                        <button type="submit" class="bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-2.5 px-6 rounded-lg shadow-md transition duration-150 ease-in-out">
                            Save Tenant
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        // Email Generation Logic
        document.addEventListener('DOMContentLoaded', function() {
            const randomCheckbox = document.getElementById('random_email');
            const emailInput = document.getElementById('email_input');

            // Initial check
            if (randomCheckbox.checked) {
                toggleEmailField(true);
            }

            randomCheckbox.addEventListener('change', function() {
                toggleEmailField(this.checked);
            });

            function toggleEmailField(isChecked) {
                if (isChecked) {
                    emailInput.value = '';
                    emailInput.placeholder = 'System will auto-generate email...';
                    emailInput.readOnly = true;
                    emailInput.classList.add('bg-gray-100', 'cursor-not-allowed');
                } else {
                    emailInput.placeholder = 'example@mail.com';
                    emailInput.readOnly = false;
                    emailInput.classList.remove('bg-gray-100', 'cursor-not-allowed');
                }
            }
            
            // Initialize Identity Logic on load in case of validation errors/back navigation
            toggleIdentityInputs();
        });

        // Emergency Contact Logic
        (function() {
            const list = document.getElementById('contactList');
            const tpl = document.getElementById('contactRowTpl');
            const addBtn = document.getElementById('addContactBtn');
            let idx = 0;

            function addRow() {
                const html = tpl.innerHTML.replaceAll('__i__', String(idx));
                const wrap = document.createElement('div');
                wrap.innerHTML = html.trim();
                const row = wrap.firstElementChild;

                row.querySelector('.remove-contact').addEventListener('click', () => {
                    row.remove();
                });

                list.appendChild(row);
                idx++;
            }

            addBtn.addEventListener('click', addRow);
            
            // Optional: Start with 0 rows or 1 row? User req says "can have more than one", implies 0 is possible but usually 1 is good.
            // Let's NOT auto-add row to allow cleaner UI, or maybe 1. Let's start with 0.
        })();

        // Identity Document Toggle Logic
        function toggleIdentityInputs() {
            const icType = document.querySelector('input[name="identity_type"][value="ic"]');
            const isIc = icType.checked; // If IC is checked

            const icContainer = document.getElementById('ic_container');
            const passportContainer = document.getElementById('passport_container');
            const nationalityInput = document.getElementById('nationality');
            const photoLabel = document.getElementById('photo_label');

            if (isIc) {
                // Show IC, Hide Passport
                icContainer.classList.remove('hidden');
                passportContainer.classList.add('hidden');
                
                // Auto-fill Nationality
                nationalityInput.value = 'Malaysian';
                nationalityInput.readOnly = true;
                nationalityInput.classList.add('bg-gray-100');
                
                // Label change
                photoLabel.textContent = 'IC Photo';
            } else {
                // Show Passport, Hide IC
                icContainer.classList.add('hidden');
                passportContainer.classList.remove('hidden');
                
                // Manual Nationality
                if (nationalityInput.value === 'Malaysian') {
                    nationalityInput.value = ''; // Clear default if switching
                }
                nationalityInput.readOnly = false;
                nationalityInput.classList.remove('bg-gray-100');
                
                // Label change
                photoLabel.textContent = 'Passport Photo';
            }
        }
    </script>
</x-app-layout>
