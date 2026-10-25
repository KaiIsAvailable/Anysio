@props(['endpoint'])

<div x-data="{ destroy() { this.$el.querySelectorAll('input').forEach(input => input._flatpickr?.destroy()); } }" class="p-6 border-b">
    <x-form.form method="GET" :action="$endpoint" :async="true" :guard-composition="true" :autofocus="false"
        x-ref="filters" @submit.prevent="submit($event)" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3 items-end">
        <div>
            <x-form.input-label for="statement_from" value="From" />
            <x-form.date-input id="statement_from" name="statement_from" />
        </div>
        <div>
            <x-form.input-label for="statement_to" value="To" />
            <x-form.date-input id="statement_to" name="statement_to" />
        </div>
        <div>
            <x-form.input-label value="Record type" />
            <x-form.input-select name="statement_kind" x-model="recordKind" :ignore-checkout-or-renew="true"
                :options="['' => 'All records', 'invoice' => 'Invoices', 'payment' => 'Applied payments', 'wallet_credit' => 'Wallet credits', 'wallet_payment' => 'Wallet debits', 'wallet_other' => 'Other wallet entries']" />
        </div>
        <div>
            <x-form.input-label for="statement_invoice" value="Invoice No." />
            <x-form.text-input id="statement_invoice" name="statement_invoice" maxlength="100" />
        </div>
        <div class="flex flex-wrap gap-3 sm:col-span-2 lg:col-span-4">
            <x-form.primary-button type="submit">Search history</x-form.primary-button>
            <x-form.secondary-button @click="clearFilters($event.currentTarget.form)">Clear</x-form.secondary-button>
        </div>
    </x-form.form>
</div>
