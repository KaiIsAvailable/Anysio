
    <x-app-layout>

    <div class="py-6 px-4">
        <a href="{{ route('tenants.leases.index') }}"
            class="text-indigo-600 font-semibold">
            ← Back
        </a>

        <div class="mt-4 bg-white p-4 rounded-xl shadow-sm border">
            <h1 class="text-xl font-bold mb-4">
                Lease Details
            </h1>

            <p>
                Status:
                <strong>{{ $lease->status }}</strong>
            </p>

            <p class="mt-2">
                Start Date:
                {{ $lease->start_date_formatted ?? '-' }}
            </p>

            <p class="mt-2">
                End Date:
                {{ $lease->end_date_formatted ?? '-' }}
            </p>
        </div>
    </div>
</x-app-layout>