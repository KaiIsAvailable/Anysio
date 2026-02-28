<x-app-layout>
    <div class="bg-gray-50 min-h-screen p-6">
        <div class="max-w-3xl mx-auto">
            <!-- Header -->
            <div class="flex items-center justify-between mb-6">
                <!-- Left: Back Button -->
                <div class="flex-1 flex justify-start">
                    <a href="{{ route('admin.tenants.index') }}" class="text-indigo-600 hover:text-indigo-900 text-sm font-medium flex items-center">
                        <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path></svg>
                        Back to Tenants List
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

                    <!-- Tenant Details -->
                    <div class="mb-6 border-b border-gray-100 pb-6">
                        <h2 class="text-xl font-semibold text-slate-800 mb-4">Tenant Details</h2>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            @if(auth()->user()->role === 'agent' || auth()->user()->role === 'agentAdmin')
                                <div>
                                    <label for="owner_id" class="block text-sm font-medium text-slate-700 mb-1">Assign to Property Owner</label>
                                    <select name="owner_id" id="owner_id" 
                                        class="w-full rounded-lg border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 shadow-sm" 
                                        required tabindex="2" autofocus>
                                        <option value="" disabled selected>-- Select an Owner --</option>
                                        @foreach($managedOwners as $owner)
                                            <option value="{{ $owner->id }}" {{ old('owner_id') == $owner->id ? 'selected' : '' }}>
                                                {{ $owner->user->name }} ({{ $owner->ic_number ?? 'No IC' }})
                                            </option>
                                        @endforeach
                                    </select>
                                    @error('owner_id') <span class="text-red-600 text-xs">{{ $message }}</span> @enderror
                                </div>
                            @endif
                            <!-- Name -->
                            <div>
                                <label for="name" class="block text-sm font-medium text-slate-700 mb-1">Full Name</label>
                                <input type="text" name="name" id="name" value="{{ old('name') }}" class="w-full rounded-lg border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 shadow-sm" required tabindex="1">
                                @error('name') <span class="text-red-600 text-xs">{{ $message }}</span> @enderror
                            </div>

                            <!-- Email (Owners Style) -->
                            <div class="bg-gray-50 p-4 rounded-lg border border-gray-200">
                                <div class="flex items-center justify-between mb-2">
                                    <label class="block text-sm font-semibold text-gray-700">Email Address</label>
                                    <label class="inline-flex items-center cursor-pointer">
                                        <input type="checkbox" name="random_email" id="random_email" class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500" value="1" {{ old('random_email') ? 'checked' : '' }} tabindex="2">
                                        <span class="ml-2 text-xs font-medium text-indigo-600 uppercase tracking-wider">Generate Random</span>
                                    </label>
                                </div>
                                <input type="email" name="email" id="email_input" value="{{ old('email') }}" placeholder="example@mail.com" class="block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 transition-all" tabindex="3">
                                <p id="helper_text" class="mt-2 text-xs text-gray-500 italic">If random is selected, a temporary password will also be generated.</p>
                                @error('email') <span class="text-red-600 text-xs">{{ $message }}</span> @enderror
                            </div>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                        <!-- Phone -->
                        <div>
                            <label for="phone" class="block text-sm font-medium text-slate-700 mb-1">Phone</label>
                            <input type="text" name="phone" id="phone" value="{{ old('phone') }}" class="w-full rounded-lg border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 shadow-sm" required tabindex="4">
                            @error('phone') <span class="text-red-600 text-xs">{{ $message }}</span> @enderror
                        </div>

                        <!-- Identity Type Selection -->
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-3">Identity Document</label>
                            <div class="flex gap-4">
                                <label class="inline-flex items-center cursor-pointer">
                                    <input type="radio" name="identity_type" value="ic" class="form-radio text-indigo-600" checked onchange="toggleIdentityInputs()" tabindex="5">
                                    <span class="ml-2 text-gray-700">IC (Malaysian)</span>
                                </label>
                                <label class="inline-flex items-center cursor-pointer">
                                    <input type="radio" name="identity_type" value="passport" class="form-radio text-indigo-600" onchange="toggleIdentityInputs()" tabindex="6">
                                    <span class="ml-2 text-gray-700">Passport (Non-Malaysian)</span>
                                </label>
                            </div>
                        </div>

                        <!-- IC Number -->
                        <div id="ic_container">
                            <label for="ic_number" class="block text-sm font-medium text-slate-700 mb-1">IC Number</label>
                            <input type="text" name="ic_number" id="ic_number" value="{{ old('ic_number') }}" class="w-full rounded-lg border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 shadow-sm" tabindex="7">
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
                            <select name="gender" id="gender" class="w-full rounded-lg border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 shadow-sm" required tabindex="8">
                                <option value="">-- Select Gender --</option>
                                <option value="Male" {{ old('gender') == 'Male' ? 'selected' : '' }}>Male</option>
                                <option value="Female" {{ old('gender') == 'Female' ? 'selected' : '' }}>Female</option>
                            </select>
                            @error('gender') <span class="text-red-600 text-xs">{{ $message }}</span> @enderror
                        </div>

                        <div class="col-span-1 md:col-span-2">
                            <label for="occupation" class="block text-sm font-medium text-slate-700 mb-1">Occupation</label>
                            <input type="text" name="occupation" id="occupation" value="{{ old('occupation') }}" class="w-full rounded-lg border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 shadow-sm" tabindex="9">
                            @error('occupation') <span class="text-red-600 text-xs">{{ $message }}</span> @enderror
                        </div>
                    </div>

                    <!-- Emergency Contacts -->
                    <div class="mb-6 border-b border-gray-100 pb-6">
                        <div class="flex items-center justify-between mb-4">
                            <h2 class="text-xl font-semibold text-slate-800">Emergency Contacts</h2>
                            @error('emergency_contacts')
                                <div class="mb-4 p-2 bg-red-50 border border-red-200 text-red-600 rounded text-xs">
                                    Please provide at least one complete emergency contact.
                                </div>
                            @enderror
                            <button type="button" id="addContactBtn" class="bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-2 px-4 rounded-lg shadow-sm transition duration-150 ease-in-out text-sm">
                                + Add Contact
                            </button>
                        </div>
                        
                        <div id="contactList" class="space-y-4"></div>

                        <template id="contactRowTpl">
                            <div class="contact-row rounded-lg border border-gray-200 bg-gray-50 p-4">
                                <div class="flex justify-between items-center mb-2">
                                    <span class="text-xs font-semibold text-gray-500 uppercase tracking-wider">New Contact</span>
                                    <button type="button"
                                            class="remove-contact text-sm text-red-600 hover:text-red-800">
                                        Remove
                                    </button>
                                </div>
                                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                                    <div>
                                        <label class="block text-sm font-medium text-slate-700 mb-1">Name</label>
                                        <input type="text" name="emergency_contacts[__i__][name]" class="w-full rounded-lg border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 shadow-sm" placeholder="Contact Name" required tabindex="10">
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-slate-700 mb-1">Relationship</label>
                                        <input type="text" name="emergency_contacts[__i__][relationship]" class="w-full rounded-lg border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 shadow-sm" placeholder="e.g. Spouse, Parent" tabindex="11">
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-slate-700 mb-1">Phone</label>
                                        <input type="text" name="emergency_contacts[__i__][phone]" class="w-full rounded-lg border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 shadow-sm" placeholder="Phone Number" required tabindex="12">
                                    </div>
                                </div>
                            </div>
                        </template>
                    </div>

                    <!-- Photo -->
                    <div class="mb-6">
                        <label for="ic_photo_path" id="photo_label" class="block text-sm font-medium text-slate-700 mb-1">IC Photo</label>
                        <input type="file" name="ic_photo_path" id="ic_photo_path" class="w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100 transition-colors" tabindex="13">
                        @error('ic_photo_path') <span class="text-red-600 text-xs">{{ $message }}</span> @enderror
                    </div>

                    <div class="flex justify-end pt-4 border-t border-gray-100">
                        <button type="submit" class="bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-2.5 px-6 rounded-lg shadow-md transition duration-150 ease-in-out" tabindex="14">
                            Save Tenant
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
