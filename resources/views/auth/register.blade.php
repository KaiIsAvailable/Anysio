<x-guest-layout>
    <form method="POST" action="{{ route('register') }}">
        @csrf

        <!-- Name -->
        <div>
            <x-input-label for="name" :value="__('Name')" />
            <x-text-input id="name" class="block mt-1 w-full" type="text" name="name" :value="old('name')" required autofocus autocomplete="name" />
            <x-input-error :messages="$errors->get('name')" class="mt-2" />
        </div>

        <!-- Email Address -->
        <div class="mt-4">
            <x-input-label for="email" :value="__('Email')" />
            <x-text-input id="email" class="block mt-1 w-full" type="email" name="email" :value="old('email')" required autocomplete="username" />
            <x-input-error :messages="$errors->get('email')" class="mt-2" />
        </div>

        <!-- Role Selection -->
        <div class="mt-4" x-data="{ role: '', hasAgent: '' }">
            <x-input-label :value="__('I am a...')" />
            <div class="flex gap-4 mt-2">
                <label class="flex items-center">
                    <input type="radio" name="role" value="owner" x-model="role" class="text-indigo-600 focus:ring-indigo-500" required>
                    <span class="ml-2 text-sm text-gray-600">Owner</span>
                </label>
                <label class="flex items-center">
                    <input type="radio" name="role" value="tenant" x-model="role" class="text-indigo-600 focus:ring-indigo-500">
                    <span class="ml-2 text-sm text-gray-600">Tenant</span>
                </label>
                <label class="flex items-center">
                    <input type="radio" name="role" value="agent" x-model="role" class="text-indigo-600 focus:ring-indigo-500">
                    <span class="ml-2 text-sm text-gray-600">Agent</span>
                </label>
            </div>

            <div x-show="role === 'owner'" x-cloak class="mt-4 p-4 bg-gray-50 rounded-lg border border-gray-100">
                <x-input-label :value="__('Do you have an agent managing for you?')" />
                <div class="flex gap-4 mt-2">
                    <label class="flex items-center">
                        <input type="radio" name="has_agent" value="yes" x-model="hasAgent" :required="role === 'owner'">
                        <span class="ml-2 text-sm text-gray-600">Yes, I have an Agent</span>
                    </label>
                    <label class="flex items-center">
                        <input type="radio" name="has_agent" value="no" x-model="hasAgent" :required="role === 'owner'">
                        <span class="ml-2 text-sm text-gray-600">No, I manage by myself</span>
                    </label>
                </div>
            </div>
            
            <x-input-error :messages="$errors->get('role')" class="mt-2" />
            <x-input-error :messages="$errors->get('ic')" class="mt-2" />
            <x-input-error :messages="$errors->get('tenant_ic')" class="mt-2" />

            <div x-show="role === 'owner' && hasAgent === 'yes'" x-transition class="mt-4">
                <x-input-label for="ic" :value="__('IC Number')" />
                <x-text-input id="ic" class="block mt-1 w-full" type="text" name="ic" 
                            :value="old('ic')" 
                            ::required="role === 'owner' && hasAgent === 'yes'" />
                <p class="mt-1 text-xs text-gray-500 italic">We need this to link you with your registered agent.</p>
                <p class="mt-1 text-xs text-gray-500 italic">IC Without "-".</p>
            </div>

            <div x-show="role === 'tenant'" x-transition class="mt-4">
                <x-input-label for="tenant_ic" :value="__('IC Number')" />
                <x-text-input id="tenant_ic" class="block mt-1 w-full" type="text" name="tenant_ic" 
                            :value="old('tenant_ic')" 
                            ::required="role === 'tenant'" />
                <p class="mt-1 text-xs text-gray-500 italic">We need this to link you with your registered owner.</p>
                <p class="mt-1 text-xs text-gray-500 italic">IC Without "-".</p>
            </div>
        </div>

        <!-- Password -->
        <div class="mt-4">
            <x-input-label for="password" :value="__('Password')" />
            <x-password-input id="password" class="block mt-1 w-full" name="password" required autocomplete="new-password" />
            <x-input-error :messages="$errors->get('password')" class="mt-2" />
        </div>

        <!-- Confirm Password -->
        <div class="mt-4">
            <x-input-label for="password_confirmation" :value="__('Confirm Password')" />
            <x-password-input id="password_confirmation" class="block mt-1 w-full" name="password_confirmation" required autocomplete="new-password" />
            <x-input-error :messages="$errors->get('password_confirmation')" class="mt-2" />
        </div>
        
        <div class="flex items-center justify-end mt-4">
            <a class="underline text-sm text-gray-600 hover:text-gray-900 rounded-md focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500" href="{{ route('login') }}">
                {{ __('Already registered?') }}
            </a>

            <x-primary-button class="ms-4 bg-indigo-600 hover:bg-indigo-700 active:bg-indigo-800">
                {{ __('Register') }}
            </x-primary-button>
        </div>
    </form>
</x-guest-layout>
