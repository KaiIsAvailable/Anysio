<x-app-layout>
    <div class="py-12 bg-gray-50 min-h-screen font-sans">
        <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="mb-8">
                <h1 class="text-3xl font-bold text-slate-900 tracking-tight">Edit Manager</h1>
                <p class="mt-2 text-sm text-gray-500">Update account details and access permissions.</p>
            </div>

            <div class="bg-white rounded-xl shadow-lg border border-gray-100 overflow-hidden">
                <x-form.form action="{{ route('admin.userManagement.update', $userMgnt->id) }}" method="POST" class="p-8">
                    @method('PUT')

                    <div class="grid grid-cols-1 gap-6">
                        <div>
                            <x-form.input-label value="Full Name" class="mb-2" />
                            <x-form.text-input name="name" value="{{ old('name', $userMgnt->user->name) }}" 
                                    class="w-full" />
                        </div>

                        <div>
                            <div class="flex items-center justify-between mb-2">
                                <x-form.input-label value="Email Address" />
                                
                                {{-- Email Verification Status Badge --}}
                                @if($userMgnt->user->email_verified_at)
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-bold bg-emerald-100 text-emerald-800">
                                        Verified ({{ $userMgnt->user->email_verified_at->format('d/m/Y H:i') }})
                                    </span>
                                @else
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-bold bg-amber-100 text-amber-800">
                                        Unverified
                                    </span>
                                @endif
                            </div>

                            <x-form.text-input type="email" name="email" value="{{ old('email', $userMgnt->user->email) }}" class="w-full" />
                        </div>

                        <div>
                            <x-form.input-label value="Email Verification Status" class="mb-1" />
                            <x-form.input-select 
                                name="email_verification_status" 
                                id="email_verification_status" 
                                :value="old('email_verification_status', $userMgnt->user->email_verified_at ? 'verified' : 'unverified')" 
                                :options="['verified' => 'Verified', 'unverified' => 'Unverified']" 
                                required 
                                class="w-full" />
                            <p class="mt-1 text-xs text-gray-400">Manually verify or unverify this user's email address.</p>
                        </div>

                        <div>
                            <div>
                                <x-form.input-label value="Base User Type" class="mb-1" />
                                <x-form.input-select name="role_type" id="role_type" :value="old('role_type', $userMgnt->user->role)" :options="['admin' => 'Admin', 'ownerAdmin' => 'Owner Admin', 'agentAdmin' => 'Agent Admin']" placeholder="-- Select Type --" required class="w-full" />
                            </div>

                            {{-- Hidden Management Role (Auto-synced behind the scenes) --}}
                            <input type="hidden" name="pms_role" value="{{ old('pms_role', $userMgnt->role) }}">
                        </div>

                        {{-- Start Date & End Date fields side-by-side --}}
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <x-form.input-label value="Start Date" class="mb-1" />
                                <x-form.date-input id="start_date" name="start_date" value="{{ old('start_date', $userMgnt->start_date) }}" class="w-full" />
                            </div>

                            <div>
                                <x-form.input-label value="End Date" class="mb-1" />
                                <x-form.date-input id="end_date" name="end_date" value="{{ old('end_date', $userMgnt->end_date) }}" class="w-full" />
                            </div>
                        </div>

                        <div>
                            <x-form.input-label value="Subscription Status" class="mb-1" />
                            <x-form.input-select name="subscription_status" id="subscription_status" :value="old('subscription_status', $userMgnt->subscription_status)" :options="['active' => 'Active', 'inactive' => 'Inactive']" required 
                                    class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 {{ $userMgnt->subscription_status == 'inactive' ? 'bg-red-50 text-red-600' : '' }}" />
                            <p class="mt-1 text-xs text-gray-400">Set to 'Inactive' to suspend user access immediately.</p>
                        </div>
                    </div>

                    <div class="mt-10 flex items-center justify-end space-x-4 border-t border-gray-100 pt-6">
                        <a href="{{ route('admin.userManagement.index') }}" class="px-6 py-2.5 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50">Cancel</a>
                        <x-form.primary-button type="submit" loading="loading" class="px-6 py-2.5">
                            Save Changes
                        </x-form.primary-button>
                    </div>
                </x-form.form>
            </div>
        </div>
    </div>
</x-app-layout>