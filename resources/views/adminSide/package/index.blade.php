<x-app-layout>
    <div class="py-12 bg-gray-50 min-h-screen font-sans">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            
            {{-- Header Section --}}
            <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4 mb-8">
                <div>
                    <h1 class="text-3xl font-bold text-slate-900 tracking-tight">System Packages</h1>
                    <p class="mt-2 text-sm text-gray-500">Define your P1-P4 billing plans and revenue models.</p>
                </div>
                {{-- 只有管理员能新增方案 --}}
                <div class="flex-shrink-0">
                    <a href="{{ route('admin.packages.create') }}" 
                       class="inline-flex items-center justify-center px-5 py-2.5 border border-transparent text-sm font-medium rounded-lg text-white bg-indigo-600 hover:bg-indigo-700 shadow-sm transition-all duration-200">
                        <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
                        </svg>
                        Create New Package
                    </a>
                </div>
            </div>

            {{-- Table Card --}}
            <div class="bg-white rounded-xl shadow-lg overflow-hidden border border-gray-100">
                
                {{-- Search/Filter (保留你的设计样式) --}}
                <div class="p-5 border-b border-gray-100 bg-white">
                    <div class="flex justify-end">
                        <form method="GET" action="{{ route('admin.packages.index') }}" class="flex items-stretch gap-2">
                            <div class="flex items-stretch">
                                <div class="relative flex-1">
                                    <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                                        <svg class="h-5 w-5 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                                        </svg>
                                    </div>
                                    <input type="text" name="search" value="{{ request('search') }}"
                                           style="padding-left: 45px;"
                                           class="block w-72 sm:w-80 pr-4 py-2.5 bg-gray-50 border border-gray-300 text-slate-900 text-sm rounded-l-lg focus:ring-indigo-500 focus:border-indigo-500 transition-colors"
                                           placeholder="Search by package name or code...">
                                </div>
                                <button type="submit" class="inline-flex items-center px-4 py-2 text-sm font-medium rounded-r-lg text-white bg-indigo-600 hover:bg-indigo-700 transition-colors">
                                    Search
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                {{-- Packages Table --}}
                <div class="overflow-x-auto">
                    @if($packages && $packages->count() > 0)
                        <table class="w-full divide-y divide-gray-200 text-left">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-4 text-xs font-semibold text-gray-500 uppercase">Plan Details</th>
                                    <th class="px-6 py-4 text-xs font-semibold text-gray-500 uppercase">Billing Mode</th>
                                    <th class="px-6 py-4 text-xs font-semibold text-gray-500 uppercase">Pricing</th>
                                    <th class="px-6 py-4 text-xs font-semibold text-gray-500 uppercase">Base Lease</th>
                                    <th class="px-6 py-4 text-xs font-semibold text-gray-500 uppercase">Usage Limits</th>
                                    <th class="px-6 py-4 text-xs font-semibold text-gray-500 uppercase">Status</th>
                                    <th class="px-6 py-4 text-center text-xs font-semibold text-gray-500 uppercase tracking-wider">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                @foreach($packages as $package)
                                    <tr class="hover:!bg-indigo-50 transition-colors cursor-pointer group duration-150">
                                        
                                        {{-- 1. Plan Details --}}
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="flex items-center">
                                                <div class="h-10 w-10 bg-indigo-100 rounded-full flex items-center justify-center text-indigo-700 font-bold shadow-sm">
                                                    {{ strtoupper(substr($package->ref_code, 0, 2)) }}
                                                </div>
                                                <div class="ml-4">
                                                    <div class="text-sm font-bold text-slate-900">{{ $package->name }}</div>
                                                    <div class="text-xs text-gray-500 font-mono uppercase">{{ $package->ref_code }}</div>
                                                </div>
                                            </div>
                                        </td>

                                        {{-- 2. Billing Mode (Monthly/Yearly) --}}
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-slate-600 uppercase font-medium">
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-slate-100 text-slate-800">
                                                {{ $package->price_mode }}
                                            </span>
                                        </td>

                                        {{-- 3. Pricing (Dynamic: % or RM) --}}
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            @if($package->commission_rate > 0)
                                                <div class="text-sm text-indigo-600 font-bold">
                                                    {{ number_format($package->commission_rate / 100, 2) }}%
                                                </div>
                                                <div class="text-[10px] text-gray-400 uppercase tracking-tighter">Of Rental</div>
                                            @elseif($package->price > 0)
                                                <div class="text-sm text-slate-900 font-bold">
                                                    RM {{ number_format($package->price / 100, 2) }}
                                                </div>
                                                <div class="text-[10px] text-gray-400 uppercase tracking-tighter">Subscription</div>
                                            @else
                                                <span class="text-sm text-gray-400 italic">Free</span>
                                            @endif
                                        </td>

                                        {{-- 4. Base Lease --}}
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="text-sm text-slate-900 font-semibold">{{ $package->base_lease }}</div>
                                            <div class="text-[10px] text-gray-400 uppercase tracking-tighter italic">Included Leases</div>
                                        </td>

                                        {{-- 5. Usage Limits --}}
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="text-sm text-slate-700">Add-on Limit: <strong>{{ $package->max_lease_limit }}</strong></div>
                                            <div class="text-xs text-gray-500">
                                                Rate: RM {{ number_format($package->extra_lease_price / 100, 2) }}/ea
                                            </div>
                                        </td>

                                        {{-- 6. Status --}}
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            @php
                                                $status = strtolower($package->status ?? 'unknown');
                                                $badge = match($status) {
                                                    'active' => 'bg-emerald-100 text-emerald-800 border-emerald-200',
                                                    'inactive' => 'bg-yellow-100 text-yellow-800 border-yellow-200',
                                                    default => 'bg-gray-100 text-gray-800 border-gray-200',
                                                };
                                            @endphp
                                            <span class="px-2.5 py-0.5 rounded-full text-xs font-medium {{ $badge }} uppercase">
                                                {{ $package->status ?? '-' }}
                                            </span>
                                        </td>
                                        
                                        {{-- 6. Actions --}}
                                        <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                            <div class="flex justify-end items-center gap-3">
                                                {{-- Edit 按钮 --}}
                                                <a href="{{ route('admin.packages.edit', $package->id) }}" class="p-2 text-indigo-600 hover:text-indigo-900 bg-indigo-50 hover:bg-indigo-100 rounded-lg transition-colors"> <!-- transition-transform hover:scale-110 -->
                                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path></svg>
                                                </a>

                                                @if($package->status === 'active')
                                                    {{-- 禁用按钮 (Destroy) --}}
                                                    <form action="{{ route('admin.packages.destroy', $package->id) }}" method="POST" onsubmit="return confirm('Disable this package? New users won\'t be able to subscribe to it.');" class="inline">
                                                        @csrf
                                                        @method('DELETE')
                                                        <button type="submit" class="p-2 text-red-600 hover:text-red-900 bg-red-50 hover:bg-red-100 rounded-lg transition-colors">
                                                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                                                            </svg>
                                                        </button>
                                                    </form>
                                                @else
                                                    {{-- 恢复按钮 (Restore) --}}
                                                    <form action="{{ route('admin.packages.restore', $package->id) }}" method="POST" class="inline">
                                                        @csrf
                                                        @method('PATCH')
                                                        <button type="submit" class="p-2 text-green-600 hover:text-green-900 bg-green-50 hover:bg-green-100 rounded-lg transition-colors" title="Restore Package">
                                                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h10a8 8 0 018 8v2M3 10l6 6m-6-6l6-6"></path>
                                                            </svg>
                                                        </button>
                                                    </form>
                                                @endif
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    @else
                        <div class="text-center py-20 bg-white">
                            <h3 class="text-lg font-medium text-slate-900">No packages defined</h3>
                            <p class="mt-1 text-gray-500">Start by creating your first billing plan.</p>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</x-app-layout>