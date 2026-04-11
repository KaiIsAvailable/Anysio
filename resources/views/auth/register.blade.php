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

        <div class="mt-4" x-data="{ openTnc: false, activeTab: 'tos' }">
            <label for="terms" class="flex items-start">
                <div class="flex items-center h-5">
                    <input id="terms" name="terms" type="checkbox" required 
                        class="w-4 h-4 text-indigo-600 border-gray-300 rounded focus:ring-indigo-500">
                </div>
                <div class="ml-3 text-sm text-gray-600">
                    I agree to the 
                    <button type="button" @click="openTnc = true; activeTab = 'tos'" class="text-indigo-600 hover:underline font-medium">Terms of Service</button>
                    and 
                    <button type="button" @click="openTnc = true; activeTab = 'privacy'" class="text-indigo-600 hover:underline font-medium">Privacy Policy</button>.
                </div>
            </label>
            <x-input-error :messages="$errors->get('terms')" class="mt-2" />

            <div x-show="openTnc" class="fixed inset-0 z-50 overflow-y-auto" x-cloak>
                <div class="flex items-center justify-center min-h-screen px-4">
                    <div class="fixed inset-0 bg-gray-500 opacity-75" @click="openTnc = false"></div>

                    <div class="bg-white rounded-xl overflow-hidden shadow-xl transform transition-all sm:max-w-2xl sm:w-full z-50">
                        <div class="flex border-b border-gray-100">
                            <button type="button" 
                                @click="activeTab = 'tos'"
                                :class="activeTab === 'tos' ? 'border-indigo-600 text-indigo-600 border-b-2' : 'text-gray-500 hover:text-gray-700'"
                                class="flex-1 py-4 text-sm font-bold uppercase tracking-wider transition">
                                Terms of Service
                            </button>
                            <button type="button" 
                                @click="activeTab = 'privacy'"
                                :class="activeTab === 'privacy' ? 'border-indigo-600 text-indigo-600 border-b-2' : 'text-gray-500 hover:text-gray-700'"
                                class="flex-1 py-4 text-sm font-bold uppercase tracking-wider transition">
                                Privacy Policy
                            </button>
                            <button @click="openTnc = false" class="px-4 text-gray-400 hover:text-gray-600">
                                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                            </button>
                        </div>
                        
                        <div class="px-6 py-8 max-h-[60vh] overflow-y-auto bg-white">
                            <div class="prose prose-sm max-w-none font-serif text-slate-800 leading-relaxed" style="white-space: pre-line;">
                                
                                <div x-show="activeTab === 'tos'">
                                    @php
                                        $tos = \App\Models\Agreements::where('type', 'tos')->where('is_active', true)->latest()->first();
                                    @endphp
                                    @if($tos)
                                        {!! preg_replace("/(\r\n|\r|\n){3,}/", "\n\n", e($tos->content)) !!}
                                    @else
                                        <p class="italic text-gray-400">Terms of Service content is being updated...</p>
                                    @endif
                                </div>

                                <div x-show="activeTab === 'privacy'">
                                    @php
                                        $privacy = \App\Models\Agreements::where('type', 'privacy')->where('is_active', true)->latest()->first();
                                    @endphp
                                    @if($privacy)
                                        {!! preg_replace("/(\r\n|\r|\n){3,}/", "\n\n", e($privacy->content)) !!}
                                    @else
                                        <p class="italic text-gray-400">Privacy Policy content is being updated...</p>
                                    @endif
                                </div>

                            </div>
                        </div>

                        <div class="bg-gray-50 px-6 py-4 flex justify-between items-center">
                            <span class="text-xs text-gray-400 italic">Effective Date: {{ date('Y-m-d') }}</span>
                            <button type="button" @click="openTnc = false" 
                                    class="px-6 py-2 bg-indigo-600 text-white rounded-lg text-sm font-bold hover:bg-indigo-700 shadow-md">
                                CLOSE
                            </button>
                        </div>
                    </div>
                </div>
            </div>
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
