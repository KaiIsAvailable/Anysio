<template x-teleport="body">
    <div x-show="openReceipt" x-cloak class="fixed inset-0 z-[999] overflow-y-auto">
        <x-ui.blur-overlay show="openReceipt" onClose="openReceipt = false" zIndex="z-[998]" />

        <div class="flex items-center justify-center min-h-screen px-4 py-12">
            <div @click.stop x-show="openReceipt" class="relative z-[1000] bg-white rounded-[2rem] shadow-2xl max-w-3xl w-full overflow-hidden">
                
                <!-- Modal Header -->
                <div class="flex items-center justify-between p-6 border-b">
                    <div>
                        <h3 class="text-xl font-bold text-slate-800">Payment Receipt</h3>
                        <p class="text-xs text-slate-500 mt-1"><span x-text="receiptUser"></span></p>
                    </div>
                    <button @click="openReceipt=false" class="p-2 rounded-full hover:bg-gray-100">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>

                <!-- Content Area (Switches based on isPdf boolean) -->
                <div class="bg-slate-100 p-5 flex flex-col justify-center items-center min-h-[400px]">
                    <template x-if="receiptImage">
                        <div class="w-full flex flex-col items-center justify-center">
                            
                            <!-- PDF Viewer -->
                            <template x-if="isPdf">
                                <div class="w-full flex flex-col items-center">
                                    <iframe :src="receiptImage" class="w-full h-[70vh] rounded-xl shadow-lg border border-gray-200 bg-white" type="application/pdf"></iframe>
                                    <p class="text-xs text-gray-500 mt-2">
                                        Having trouble viewing? <a :href="receiptImage" target="_blank" class="text-indigo-600 underline font-medium">Open in new tab</a>
                                    </p>
                                </div>
                            </template>

                            <!-- Image Viewer -->
                            <template x-if="!isPdf">
                                <img :src="receiptImage" class="max-h-[70vh] rounded-xl shadow-lg object-contain" alt="Payment Receipt Image">
                            </template>

                        </div>
                    </template>
                </div>

                <!-- Modal Footer -->
                <div class="border-t p-5 text-center flex justify-between items-center px-6">
                    <p class="text-xs uppercase tracking-widest text-slate-500" x-text="receiptDate"></p>
                    <a :href="receiptImage" target="_blank" class="text-xs font-semibold text-indigo-600 hover:text-indigo-800 flex items-center gap-1">
                        Open Full File
                    </a>
                </div>

            </div>
        </div>
    </div>
</template>