@props(['modules', 'editingState' => 'editing', 'roleId' => null])

@if($roleId !== null)
    <template x-for="permission in selected" :key="permission">
        <input type="hidden" :name="'permissions[{{ $roleId }}][' + permission + ']'" value="1">
    </template>
@endif

<div class="grid grid-cols-1 lg:grid-cols-2 gap-3">
    @foreach($modules as $module => $actions)
        @php
            $moduleNames = [];
            foreach ($actions as $key => $value) {
                foreach (is_array($value) ? array_merge([$key], $value) : [$value] as $action) {
                    $moduleNames[] = $module.'.'.$action;
                }
            }
        @endphp
        <div class="rounded-lg border border-gray-200 overflow-hidden"
             x-show="@js(strtolower($module)).includes(search.toLowerCase())"
             x-data="{ names: {{ \Illuminate\Support\Js::from($moduleNames) }} }">
            <div class="flex justify-between items-center gap-2 bg-gray-50 px-3 py-2">
                <h4 class="text-sm font-semibold text-gray-800">{{ ucfirst($module) }} <span class="text-xs font-normal text-gray-400" x-text="selected.filter(name => names.includes(name)).length + '/' + names.length"></span></h4>
                <label class="inline-flex items-center gap-2 text-xs text-gray-500 cursor-pointer">
                    {{ __('All') }}
                    <span class="relative inline-flex shrink-0">
                        <input type="checkbox" :disabled="!{{ $editingState }}" :checked="names.every(name => selected.includes(name))"
                           @change="selected = $event.target.checked ? [...new Set([...selected, ...names])] : selected.filter(name => !names.includes(name))"
                           class="sr-only peer" role="switch">
                        <span aria-hidden="true" class="block h-5 w-9 rounded-full bg-gray-200 transition-colors peer-checked:bg-indigo-600 peer-focus-visible:ring-2 peer-focus-visible:ring-indigo-500 peer-focus-visible:ring-offset-2 peer-disabled:cursor-not-allowed peer-disabled:opacity-50 after:content-[''] after:absolute after:top-0.5 after:left-0.5 after:h-4 after:w-4 after:rounded-full after:bg-white after:shadow-sm after:transition-transform peer-checked:after:translate-x-4"></span>
                    </span>
                </label>
            </div>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-x-4 px-3 py-1">
                @foreach($actions as $key => $value)
                    @php $action = is_array($value) ? $key : $value; @endphp
                    <div class="{{ is_array($value) ? 'sm:col-span-2' : '' }}">
                        <label class="flex items-center justify-between gap-2 py-2 text-sm text-gray-700 cursor-pointer">
                            <span>{{ ucfirst($action) }}</span>
                            <span class="relative inline-flex shrink-0">
                                <input type="checkbox" @if($roleId === null) name="permissions[]" @endif value="{{ $module.'.'.$action }}" x-model="selected" :disabled="!{{ $editingState }}"
                                @if(is_array($value))
                                    @change="if ($event.target.checked) { selected = [...new Set([...selected, ...{{ \Illuminate\Support\Js::from(array_map(fn ($child) => $module.'.'.$child, $value)) }}])]; } else { selected = selected.filter(name => !{{ \Illuminate\Support\Js::from(array_map(fn ($child) => $module.'.'.$child, $value)) }}.includes(name)); }"
                                @endif
                                class="sr-only peer" role="switch">
                                <span aria-hidden="true" class="block h-5 w-9 rounded-full bg-gray-200 transition-colors peer-checked:bg-indigo-600 peer-focus-visible:ring-2 peer-focus-visible:ring-indigo-500 peer-focus-visible:ring-offset-2 peer-disabled:cursor-not-allowed peer-disabled:opacity-50 after:content-[''] after:absolute after:top-0.5 after:left-0.5 after:h-4 after:w-4 after:rounded-full after:bg-white after:shadow-sm after:transition-transform peer-checked:after:translate-x-4"></span>
                            </span>
                        </label>
                        @if(is_array($value))
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-x-4 border-l-2 border-indigo-100 pl-3 ml-1 mb-1">
                                @foreach($value as $child)
                                    <label class="flex items-center justify-between gap-2 py-1.5 text-xs text-gray-600 cursor-pointer">
                                        <span>{{ ucfirst($child) }}</span>
                                        <span class="relative inline-flex shrink-0">
                                            <input type="checkbox" @if($roleId === null) name="permissions[]" @endif value="{{ $module.'.'.$child }}" x-model="selected" :disabled="!{{ $editingState }}" class="sr-only peer" role="switch">
                                            <span aria-hidden="true" class="block h-5 w-9 rounded-full bg-gray-200 transition-colors peer-checked:bg-indigo-600 peer-focus-visible:ring-2 peer-focus-visible:ring-indigo-500 peer-focus-visible:ring-offset-2 peer-disabled:cursor-not-allowed peer-disabled:opacity-50 after:content-[''] after:absolute after:top-0.5 after:left-0.5 after:h-4 after:w-4 after:rounded-full after:bg-white after:shadow-sm after:transition-transform peer-checked:after:translate-x-4"></span>
                                        </span>
                                    </label>
                                @endforeach
                            </div>
                        @endif
                    </div>
                @endforeach
            </div>
        </div>
    @endforeach
</div>

