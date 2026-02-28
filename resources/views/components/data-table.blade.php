@props(['collection', 'columns'])

<div class="bg-white rounded-xl shadow-lg overflow-hidden border border-gray-100">
    @if(isset($header))
        <div class="p-5 border-b border-gray-100 bg-white">
            {{ $header }}
        </div>
    @endif

    <div class="overflow-x-auto">
        @if($collection && $collection->count() > 0)
            <table class="w-full divide-y divide-gray-200 text-left">
                <thead class="bg-gray-50">
                    <tr>
                        @foreach($columns as $column)
                            <th class="px-6 py-4 text-xs font-semibold text-gray-500 uppercase tracking-wider">
                                {{ $column }}
                            </th>
                        @endforeach
                        <th class="px-6 py-4 text-center text-xs font-semibold text-gray-500 uppercase tracking-wider">Actions</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    {{ $slot }}
                </tbody>
            </table>
        @else
            <div class="text-center py-20 bg-white">
                <div class="inline-flex items-center justify-center w-16 h-16 bg-gray-100 rounded-full mb-4">
                    <svg class="w-8 h-8 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0a2 2 0 01-2 2H6a2 2 0 01-2-2m16 0l-8 4-8-4" />
                    </svg>
                </div>
                <h3 class="text-lg font-medium text-slate-900">No records found</h3>
                <p class="mt-1 text-gray-500">Try adjusting your search or filters.</p>
            </div>
        @endif
    </div>

    @if($collection && method_exists($collection, 'hasPages') && $collection->hasPages())
        <div class="bg-white px-6 py-4 border-t border-gray-100">
            {{ $collection->appends(request()->query())->links() }}
        </div>
    @endif
</div>