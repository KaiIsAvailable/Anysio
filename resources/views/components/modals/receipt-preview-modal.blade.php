<template x-teleport="body">
    <div
        x-show="openReceipt"
        x-cloak
        class="fixed inset-0 z-[999] overflow-y-auto"
    >
        <x-ui.blur-overlay
            show="openReceipt"
            onClose="openReceipt = false"
            zIndex="z-[998]"
        />

        <div class="flex items-center justify-center min-h-screen px-4 py-12">

            <div
                @click.stop
                x-show="openReceipt"
                x-transition:enter="ease-out duration-300"
                x-transition:enter-start="opacity-0 scale-95 translate-y-6"
                x-transition:enter-end="opacity-100 scale-100 translate-y-0"
                class="relative z-[1000] bg-white rounded-[2rem] shadow-2xl max-w-3xl w-full overflow-hidden"
            >

                <div class="flex items-center justify-between p-6 border-b">

                    <div>
                        <h3 class="text-xl font-bold text-slate-800">
                            Payment Receipt
                        </h3>

                        <p class="text-xs text-slate-500 mt-1">
                            <span x-text="receiptUser"></span>
                        </p>
                    </div>

                    <button
                        @click="openReceipt=false"
                        class="p-2 rounded-full hover:bg-gray-100"
                    >
                        <svg class="w-6 h-6"
                             fill="none"
                             stroke="currentColor"
                             viewBox="0 0 24 24">
                            <path stroke-linecap="round"
                                  stroke-linejoin="round"
                                  stroke-width="2"
                                  d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    </button>

                </div>

                <div class="bg-slate-100 p-5 flex justify-center">

                    <img
                        :src="receiptImage"
                        class="max-h-[70vh] rounded-xl shadow-lg object-contain"
                    >

                </div>

                <div class="border-t p-5 text-center">

                    <p
                        class="text-xs uppercase tracking-widest text-slate-500"
                        x-text="receiptDate"
                    ></p>

                </div>

            </div>

        </div>

    </div>
</template>