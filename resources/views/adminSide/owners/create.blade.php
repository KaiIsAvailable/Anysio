<x-app-layout>
    <div class="bg-gray-50 min-h-screen p-6">
        <div class="max-w-3xl mx-auto">
            <div class="flex items-center justify-between mb-6">
                <div class="flex-1 flex justify-start">
                    <a href="{{ route('admin.owners.index') }}" class="inline-flex items-center text-gray-500 hover:text-indigo-600 transition-colors">
                        <svg class="h-5 w-5 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path></svg>
                        Back
                    </a>
                </div>
                
                <h1 class="text-2xl font-bold text-slate-900 font-sans whitespace-nowrap">Create Owner</h1>

                <div class="flex-1"></div>
            </div>

            <div class="bg-white shadow-lg rounded-xl p-6">
                <form action="{{ route('admin.owners.store') }}" method="POST">
                    @csrf

                    <div class="mb-4">
                        <label for="name" class="block text-sm font-medium text-slate-900 mb-1">Full Name</label>
                        <input type="text" name="name" id="name" value="{{ old('name') }}" class="w-full rounded-lg border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 shadow-sm" placeholder="Enter owner's full name" required>
                        @error('name') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                    </div>

                    <div class="mb-4">
                        <div class="flex items-center justify-between mb-1">
                            <label for="email" class="block text-sm font-medium text-slate-900">Email Address</label>
                            
                            <div class="flex items-center">
                                <input type="checkbox" name="random_email_password" id="random_email_password" onclick="Random_Email_Password();" 
                                      class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500 mr-2">
                                <label for="random_email_password" class="text-xs text-gray-600 cursor-pointer">Generate Random Credentials</label>
                            </div>
                        </div>

                        <input type="email" name="email" id="email" value="{{ old('email') }}" 
                              class="w-full rounded-lg border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 shadow-sm" 
                              placeholder="Enter owner's email address" required>
                              
                        @error('email') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                    </div>

                    <div class="mb-4">
                      <div class="flex items-center justify-between mb-1">
                            <label for="password" class="block text-sm font-medium text-slate-900 mb-1">Password</label>
                            
                            <div class="flex items-center">
                                <input type="checkbox" name="show_password" id="show_password" onclick="Show_Password();" 
                                      class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500 mr-2">
                                <label for="show_password" class="text-xs text-gray-600 cursor-pointer">Show Password</label>
                            </div>
                        </div>
                        
                        <input type="password" name="password" id="password" class="w-full rounded-lg border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 shadow-sm" placeholder="Enter a secure password" required>
                        @error('password') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div class="mb-4">
                            <label for="company_name" class="block text-sm font-medium text-slate-900 mb-1">Company Name</label>
                            <input type="text" name="company_name" id="company_name" value="{{ old('company_name') }}" class="w-full rounded-lg border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 shadow-sm" placeholder="Optional">
                            @error('company_name') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                        </div>

                        <div class="mb-4">
                            <label for="ic_number" class="block text-sm font-medium text-slate-900 mb-1">IC Number</label>
                            <input type="text" name="ic_number" id="ic_number" value="{{ old('ic_number') }}" class="w-full rounded-lg border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 shadow-sm" required>
                            @error('ic_number') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                        </div>

                        <div class="mb-4">
                            <label for="phone" class="block text-sm font-medium text-slate-900 mb-1">Phone Number</label>
                            <input type="text" name="phone" id="phone" value="{{ old('phone') }}" class="w-full rounded-lg border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 shadow-sm" placeholder="e.g. +60123456789" required>
                            @error('phone') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                        </div>

                        <div class="mb-4">
                            <label for="gender" class="block text-sm font-medium text-slate-900 mb-1">Gender</label>
                            <select name="gender" id="gender" class="w-full rounded-lg border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 shadow-sm" required>
                                <option value="">-- Select Gender --</option>
                                <option value="Male" {{ old('gender') == 'Male' ? 'selected' : '' }}>Male</option>
                                <option value="Female" {{ old('gender') == 'Female' ? 'selected' : '' }}>Female</option>
                            </select>
                            @error('gender') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                        </div>
                    </div>

                    <div class="flex justify-end pt-4 border-t border-gray-100 mt-4">
                        <button type="submit" class="bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-2 px-8 rounded-lg shadow-md transition duration-150 ease-in-out">
                            Save Owner
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

@push('scripts')
<script>
    function Random_Email_Password() {
        var random_email_password = document.getElementById("random_email_password");
        var emailField = document.getElementById("email");
        var passwordField = document.getElementById("password");

        if (random_email_password.checked == true){
            var random = Math.random().toString(36).substring(2, 10); 

            // Generate random email
            var randomEmail = random + "@example.com";
            emailField.value = randomEmail;
            emailField.readOnly = true;

            // Generate random password
            var randomPassword = random;
            passwordField.value = randomPassword;
            passwordField.readOnly = true;
        } else {
            emailField.value = "";
            emailField.readOnly = false;

            passwordField.value = "";
            passwordField.readOnly = false;
        }
    }

    function Show_Password() {
        var show_password = document.getElementById("show_password");
        var passwordField = document.getElementById("password");

        if (show_password.checked == true){
            passwordField.type = "text";
        } else {
            passwordField.type = "password";
        }
    }
</script>
@endpush
</x-app-layout>