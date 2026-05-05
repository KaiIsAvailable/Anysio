@props(['lease']) {{-- 保留这个作为初始兼容，但主要逻辑将依赖父级的 activeLease --}}

<template x-teleport="body">
    <div x-show="openUpload" 
         class="fixed inset-0 z-[100] flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-sm"
         @click="shake = true; setTimeout(() => shake = false, 400)"
         x-transition:enter="transition ease-out duration-300"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-cloak>
        
        <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md overflow-hidden transition-all duration-200"
             :class="{ 'animate-shake border-2 border-indigo-500': shake }"
             @click.stop>
            
            {{-- 头部：显示选中的租约周期 --}}
            <div class="p-6 border-b border-gray-100 flex justify-between items-center bg-gray-50">
                <div>
                    <h3 class="text-lg font-bold text-slate-800">LHDN Stamping Upload</h3>
                    <p class="text-[10px] text-gray-400 uppercase tracking-widest font-bold">
                        Lease Duration: 
                        @if(isset($lease) && $lease)
                            <span>{{ $lease->start_date_formatted }} - {{ $lease->end_date_formatted }}</span>
                        @else
                            <span x-text="activeLease.start_date || 'N/A'"></span> - 
                            <span x-text="activeLease.end_date || 'N/A'"></span>
                        @endif
                    </p>
                </div>
                <button type="button" @click="openUpload = false" class="text-gray-400 hover:text-gray-600 text-2xl transition-transform hover:scale-110">
                    &times;
                </button>
            </div>

            {{-- 表单：关键在于 :action 绑定 --}}
            <form 
                {{-- Index 页面：如果存在 $lease，直接生成路由 --}}
                @if(isset($lease) && $lease)
                    action="{{ route('admin.leases.upload-stamping', $lease->id) }}"
                @else
                    {{-- Show 页面：action 留空，由 Alpine.js 的 :action 接管 --}}
                    action=""
                    :action="activeLease.upload_url"
                @endif
                method="POST" 
                enctype="multipart/form-data"
            >
                @csrf
                <div class="p-6 space-y-5">

                    {{-- 动态 Reference Number --}}
                    <div>
                        <label class="block text-xs font-black text-gray-500 uppercase tracking-wider mb-2">
                            Reference Number (Adjudication No)
                        </label>
                        <input type="text" name="stamping_reference_no" required 
                               :value="activeLease?.stamping_reference_no || ''"
                               placeholder="e.g. J1234567890"
                               class="w-full px-4 py-3 border border-gray-200 rounded-xl focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none transition-all placeholder:text-gray-300">
                    </div>

                    {{-- 文件上传部分 --}}
                    <div x-data="{ fileName: '' }">
                        <label class="block text-xs font-black text-gray-500 uppercase tracking-wider mb-2">
                            Stamping Certificate (PDF Only)
                        </label>

                        <div class="group relative flex flex-col items-center justify-center w-full h-32 border-2 border-dashed rounded-xl transition-all cursor-pointer"
                             :class="fileName ? 'border-emerald-400 bg-emerald-50' : 'border-gray-300 bg-gray-50 hover:bg-indigo-50 hover:border-indigo-400'">
                            
                            <div class="flex flex-col items-center justify-center pt-5 pb-6 text-center px-4">
                                <div x-show="!fileName">
                                    <p class="text-xs text-gray-500 group-hover:text-indigo-600 font-medium">Click to select PDF</p>
                                </div>
                                
                                <div x-show="fileName" x-cloak>
                                    <svg class="w-10 h-10 mb-3 text-emerald-500 mx-auto" fill="currentColor" viewBox="0 0 20 20">
                                        <path d="M9 2a2 2 0 00-2 2v8a2 2 0 002 2h6a2 2 0 002-2V6.414A2 2 0 0016.586 5L14 2.414A2 2 0 0012.586 2H9z" />
                                    </svg>
                                    <p class="text-xs font-bold text-emerald-700 flex flex-col items-center">
                                        <span x-text="fileName" class="mt-1 italic break-all font-normal"></span>
                                    </p>
                                </div>
                            </div>

                            <input type="file" 
                                   name="stamping_cert" 
                                   x-ref="fileInput"
                                   accept="application/pdf" 
                                   :required="!fileName" 
                                   class="absolute inset-0 opacity-0 cursor-pointer"
                                   @change="fileName = $event.target.files[0] ? $event.target.files[0].name : ''">
                        </div>

                        {{-- 清除选择 --}}
                        <div x-show="fileName" class="mt-2 text-right" x-cloak>
                            <button type="button" 
                                    @click="fileName = ''; $refs.fileInput.value = ''" 
                                    class="text-[10px] text-red-500 font-bold hover:text-red-700 uppercase tracking-tight transition-colors">
                                ✕ Remove & Reselect
                            </button>
                        </div>
                    </div>
                </div>

                <div class="p-6 bg-gray-50 border-t border-gray-100 flex gap-3">
                    <button type="button" @click="openUpload = false" 
                            class="flex-1 px-4 py-3 border border-gray-200 text-gray-600 rounded-xl font-bold text-sm hover:bg-white transition-all">
                        Cancel
                    </button>
                    <button type="submit" 
                            class="flex-1 px-4 py-3 bg-indigo-600 text-white rounded-xl font-bold text-sm hover:bg-indigo-700 shadow-lg shadow-indigo-200 transition-all">
                        Confirm Upload
                    </button>
                </div>
            </form>
        </div>
    </div>
</template>