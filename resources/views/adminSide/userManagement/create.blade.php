<script src="{{ asset('js/filament/userManagement.js') }}"></script>
<x-app-layout>
    <div class="py-12 bg-gray-50 min-h-screen">
        <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
            
            <div class="mb-6">
                <a href="{{ route('admin.userManagement.index') }}" class="text-indigo-600 hover:text-indigo-900 text-sm font-medium flex items-center">
                    <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path></svg>
                    Back to List
                </a>
                <h1 class="text-2xl font-bold text-slate-900 mt-2">Create New Management User</h1>
            </div>

            <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
                <form action="{{ route('admin.userManagement.store') }}" method="POST" class="p-8">
                    @csrf
                    
                    <div class="space-y-6">
                        <div>
                            <label class="block text-sm font-semibold text-gray-700">Full Name</label>
                            <input type="text" name="name" placeholder="Enter user's full name" required 
                                   class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        </div>

                        <div class="bg-gray-50 p-4 rounded-lg border border-gray-200">
                            <div class="flex items-center justify-between mb-2">
                                <label class="block text-sm font-semibold text-gray-700">Email Address</label>
                                <label class="inline-flex items-center cursor-pointer">
                                    <input type="checkbox" name="random_email" id="random_email" class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500">
                                    <span class="ml-2 text-xs font-medium text-indigo-600 uppercase tracking-wider">Generate Random</span>
                                </label>
                            </div>
                            <input type="email" name="email" id="email_input" placeholder="example@mail.com"
                                   class="block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 transition-all">
                            <p id="helper_text" class="mt-2 text-xs text-gray-500 italic">If random is selected, a temporary password will also be generated.</p>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label class="block text-sm font-semibold text-gray-700">Base User Type</label>
                                <select name="role_type" id="role_type" required class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                    <option value="">-- Select Type --</option>
                                    <option value="admin">Admin</option>
                                    <option value="owner">Owner</option>
                                    <option value="agent">Agent</option>
                                </select>
                                <p class="mt-1 text-xs text-gray-400">Determines the role in the 'users' table.</p>
                            </div>

                            <div>
                                <label class="block text-sm font-semibold text-gray-700">Management Role</label>
                                <select name="pms_role" id="pms_role" required 
                                        class="mt-1 block w-full rounded-lg border-gray-100 bg-gray-50 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" 
                                        style="pointer-events: none; touch-action: none;" 
                                        tabindex="-1">
                                    <option value="">-- Auto-selected --</option>
                                    <option value="admin">Admin</option>
                                    <option value="ownerAdmin">Owner Admin</option>
                                    <option value="agentAdmin">Agent Admin</option>
                                </select>
                                <p class="mt-1 text-xs text-gray-400">Determines access level in Management (Auto-synced).</p>
                            </div>
                        </div>

                        <div>
                            <label class="block text-sm font-semibold text-gray-700">Referred By (Package Code)</label>
                            <select name="referred_by" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                <option value="">-- No Referral --</option>
                                @foreach($refCodes as $code)
                                    <option value="{{ $code->ref_code }}">
                                        {{ $code->ref_code }} ({{ $code->package_name ?? 'No Package Name' }})
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div class="pt-4 border-t border-gray-100 flex justify-end gap-3">
                            <a href="{{ route('admin.userManagement.index') }}" class="px-6 py-2.5 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50">Cancel</a>
                            <button type="submit" class="px-6 py-2.5 text-sm font-medium text-white bg-indigo-600 rounded-lg hover:bg-indigo-700 shadow-md transition-all">
                                Create Account
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        const randomCheckbox = document.getElementById('random_email');
        const emailInput = document.getElementById('email_input');

        randomCheckbox.addEventListener('change', function() {
            if (this.checked) {
                emailInput.value = '';
                emailInput.placeholder = 'System will auto-generate email...';
                emailInput.readOnly = true;
                emailInput.classList.add('bg-gray-100', 'cursor-not-allowed');
            } else {
                emailInput.placeholder = 'example@mail.com';
                emailInput.readOnly = false;
                emailInput.classList.remove('bg-gray-100', 'cursor-not-allowed');
            }
        });
    </script>
</x-app-layout>