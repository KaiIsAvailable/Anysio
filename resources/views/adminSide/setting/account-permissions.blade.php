<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800">{{ __('System Settings') }}</h2>
    </x-slot>

    <div class="py-6">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <section class="bg-white shadow-sm sm:rounded-lg border border-gray-200 p-4 sm:p-5 space-y-4">
                <header>
                    <h3 class="text-lg font-medium text-gray-900">{{ __('Account Permissions') }}</h3>
                    <p class="text-sm text-gray-500">{{ __('Choose an account to control its access. These permissions apply only to that account. Staff access is controlled separately by staff roles.') }}</p>
                </header>

                @php
                    $accountOptions = $accounts->map(fn ($item) => [
                        'value' => $item->id,
                        'label' => $item->name.' - '.$item->email.' ('.($item->role === 'agentAdmin' ? __('Agent Admin') : __('Owner Admin')).')',
                    ])->all();
                @endphp
                <form method="GET" action="{{ route('admin.settings.index') }}" x-data="{ loadingAccount: false }" class="w-full sm:max-w-lg">
                    <label for="permission-account" class="block text-xs font-medium text-gray-600 mb-1">{{ __('Owner / Agent Admin') }}</label>
                    <x-form.input-select
                        id="permission-account"
                        name="account_id"
                        :options="$accountOptions"
                        :value="$account?->id"
                        :disabled="$accounts->isEmpty()"
                        :placeholder="$accounts->isEmpty() ? __('No owner or agent admin accounts found') : __('Search accounts...')"
                        @change="if ($event.detail?.value && $event.detail.value !== @js($account?->id) && !loadingAccount) { loadingAccount = true; $el.closest('form').requestSubmit(); }"
                    />
                </form>

                @if($errors->any())
                    <div role="alert" class="rounded-md bg-red-50 px-3 py-2 text-sm text-red-700">
                        @foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach
                    </div>
                @endif

                @if($account)
                    <form method="POST" action="{{ route('admin.settings.account-permissions.update', $account) }}"
                          x-data="{ editing: false, saving: false, search: '', selected: {{ \Illuminate\Support\Js::from(old('permissions', $account->account_permissions ?? [])) }}, original: {{ \Illuminate\Support\Js::from($account->account_permissions ?? []) }} }"
                          @submit="saving = true; $dispatch('form-loading', true)" @pageshow.window="saving = false" class="space-y-3">
                        @csrf
                        @method('PUT')
                        <div class="flex flex-wrap items-center justify-between gap-3 border-t border-gray-100 pt-3">
                            <div>
                                <p class="text-sm font-semibold text-gray-900">{{ $account->name }} <span class="font-normal text-gray-500">· {{ $account->role === 'agentAdmin' ? __('Agent Admin') : __('Owner Admin') }}</span></p>
                                <p class="text-xs text-gray-500"><span x-text="selected.length"></span> {{ __('permissions selected') }}</p>
                            </div>
                            <div class="flex items-center gap-3">
                                <input type="search" x-model="search" aria-label="{{ __('Find module') }}" placeholder="{{ __('Find module…') }}" class="w-36 sm:w-48 rounded-md border-gray-300 text-sm py-1.5 focus:ring-indigo-500 focus:border-indigo-500">
                                <button type="button" x-show="!editing" @click="editing = true" class="text-sm font-medium text-indigo-600 hover:text-indigo-800">{{ __('Edit Permissions') }}</button>
                            </div>
                        </div>

                        <x-permissions.module-grid :modules="$modules" />

                        <div x-show="editing" x-cloak class="sticky bottom-0 bg-white border-t border-gray-200 py-3 flex justify-end items-center gap-4">
                            <button type="button" @click="selected = [...original]; editing = false" :disabled="saving" class="text-sm text-gray-600 hover:text-gray-900">{{ __('Cancel') }}</button>
                            <x-form.primary-button loading="loading">{{ __('Save Permissions') }}</x-form.primary-button>
                        </div>
                    </form>
                @endif
            </section>
        </div>
    </div>
</x-app-layout>
