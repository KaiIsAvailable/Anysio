<x-app-layout>
    <div class="py-12 bg-gray-50 min-h-screen">
        <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
            
            <div class="mb-6">
                <a href="{{ route('admin.staff.index') }}" class="text-indigo-600 hover:text-indigo-900 text-sm font-medium flex items-center">
                    <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path></svg>
                    Back to Staff List
                </a>
                <h1 class="text-2xl font-bold text-slate-900 mt-2">Add Staff</h1>
            </div>

            <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
                @if ($errors->any())
                    <div class="mb-4 p-4 bg-red-50 border-l-4 border-red-400 text-red-700">
                        <ul>
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif
                <form action="{{ route('admin.staff.store') }}" method="POST" class="p-8">
                    @csrf

                    <div class="space-y-6">
                        <div>
                            <label class="block text-sm font-semibold text-gray-700">Full Name</label>
                            <input type="text" name="name" placeholder="Enter staff name" required
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
                            <p class="mt-2 text-xs text-gray-500 italic">If random is selected, a temporary password will also be generated.</p>
                        </div>

                        <div>
                            <label class="block text-sm font-semibold text-gray-700">Role</label>
                            <select name="role" disabled
                                    class="mt-1 block w-full rounded-lg border-gray-100 bg-gray-50 shadow-sm">
                                <option value="staff" selected>Staff</option>
                            </select>
                            <p class="mt-1 text-xs text-gray-400">Hard-coded to staff.</p>
                        </div>

                        <div class="pt-4 border-t border-gray-100 flex justify-end gap-3">
                            <a href="{{ route('admin.staff.index') }}" class="px-6 py-2.5 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50">Cancel</a>
                            <x-primary-button class="px-6 py-2.5 text-sm font-medium shadow-md transition-all">
                                Create Staff
                            </x-primary-button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        const randomCheckbox = document.getElementById('random_email');
        const emailInput = document.getElementById('email_input');

        randomCheckbox?.addEventListener('change', function() {
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
