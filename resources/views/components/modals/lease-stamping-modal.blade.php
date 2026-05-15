{{-- resources/views/components/modals/upload-stamping.blade.php --}}
@props(['lease' => null])

<template x-teleport="body">
    <div x-show="openUpload" x-cloak class="fixed inset-0 z-[101] flex items-center justify-center p-4">
        
        {{-- A. 遮罩层：必须是绝对定位，撑满整个父级 fixed 容器 --}}
        <div class="absolute inset-0">
             <x-ui.blur-overlay 
                show="openUpload" 
                onClose="shake = true; setTimeout(() => shake = false, 400)" 
            />
        </div>

        {{-- B. Modal 主体：必须有 relative，确保它在 z-index 树中高于遮罩 --}}
        <div class="relative bg-white rounded-[2rem] shadow-2xl w-full max-w-md overflow-hidden transition-all duration-200 z-[102]"
             x-show="openUpload"
             x-transition:enter="transition ease-out duration-300"
             x-transition:enter-start="opacity-0 scale-95"
             x-transition:enter-end="opacity-100 scale-100"
             :class="{ 'animate-shake border-2 border-indigo-500': shake }"
             @click.stop>
            
            {{-- 头部 --}}
            <div class="p-6 border-b border-gray-100 flex justify-between items-center bg-gray-50/50">
                <div>
                    <h3 class="text-lg font-black text-slate-800">LHDN Stamping</h3>
                    <p class="text-[10px] text-indigo-600 uppercase tracking-widest font-bold mt-1">
                        <span x-text="activeLease.start_date || '{{ dateFormat($lease?->start_date) }}' || 'N/A'"></span> 
                        <span class="text-gray-300 mx-1">to</span>
                        <span x-text="activeLease.end_date || '{{ dateFormat($lease?->end_date) }}' || 'N/A'"></span>
                    </p>
                </div>
                <button type="button" @click="openUpload = false" class="text-gray-400 hover:text-rose-500 transition-colors text-2xl">
                    &times;
                </button>
            </div>

            {{-- 表单 --}}
            <form :action="activeLease.upload_url || '{{ $lease ? route('admin.leases.upload-stamping', $lease->id) : '' }}'"
                  method="POST" 
                  enctype="multipart/form-data">
                @csrf

                <div class="p-6 space-y-5">

                    {{-- Reference Number --}}
                    <div>
                        <label class="block text-[10px] font-black text-gray-400 uppercase tracking-[0.2em] mb-2">
                            Adjudication No (Reference)
                        </label>
                        <input type="text" name="stamping_reference_no" required 
                               :value="activeLease?.stamping_reference_no || ''"
                               placeholder="e.g. J1234567890"
                               class="w-full px-4 py-4 bg-slate-50 border border-gray-100 rounded-2xl focus:ring-2 focus:ring-indigo-500 outline-none transition-all font-bold text-slate-700">
                        @error('stamping_reference_no')
                            <p class="mt-1 text-xs text-rose-500 font-bold">{{ $message }}</p>
                        @enderror
                    </div>

                    {{-- 文件上传 --}}
                    <div x-data="{ fileName: '' }">
                        <label class="block text-[10px] font-black text-gray-400 uppercase tracking-[0.2em] mb-2">
                            Certificate (PDF)
                        </label>

                        <div class="group relative flex flex-col items-center justify-center w-full h-32 border-2 border-dashed rounded-2xl transition-all cursor-pointer"
                             :class="fileName ? 'border-emerald-400 bg-emerald-50' : 'border-gray-200 bg-slate-50 hover:border-indigo-400'">
                            
                            <div class="flex flex-col items-center justify-center text-center px-4">
                                <template x-if="!fileName">
                                    <p class="text-xs text-gray-400 group-hover:text-indigo-600 font-bold transition-colors">Click to select PDF</p>
                                </template>
                                
                                <template x-if="fileName">
                                    <div class="flex flex-col items-center">
                                        <div class="bg-emerald-500 text-white p-2 rounded-lg mb-2">
                                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" stroke-width="2.5"></path></svg>
                                        </div>
                                        <p class="text-[10px] font-black text-emerald-700 truncate max-w-[200px]" x-text="fileName"></p>
                                    </div>
                                </template>
                            </div>

                            <input type="file" name="stamping_cert" x-ref="fileInput"
                                   accept="application/pdf" :required="!fileName" 
                                   class="absolute inset-0 opacity-0 cursor-pointer"
                                   @change="fileName = $event.target.files[0]?.name || ''">
                            @error('stamping_cert')
                                <p class="mt-1 text-xs text-rose-500 font-bold text-center">{{ $message }}</p>
                            @enderror
                        </div>

                        <div x-show="fileName" class="mt-2 text-center" x-cloak>
                            <button type="button" @click="fileName = ''; $refs.fileInput.value = ''" 
                                    class="text-[9px] text-rose-500 font-black hover:underline uppercase tracking-tighter">
                                Remove File
                            </button>
                        </div>
                    </div>
                </div>

                {{-- Footer --}}
                <div class="p-6 bg-gray-50/50 border-t border-gray-100 flex gap-3">
                    <button type="button" @click="openUpload = false" 
                            class="flex-1 px-4 py-4 text-gray-400 rounded-2xl font-black text-xs uppercase tracking-widest hover:bg-white transition-all">
                        Cancel
                    </button>
                    <button type="submit" 
                        {{-- 提交时进入 loading 状态，防止重复点击 --}}
                        @click="loading = true"
                        :class="loading ? 'opacity-70 cursor-not-allowed' : ''"
                        class="flex-[2] px-4 py-4 bg-slate-900 text-white rounded-2xl font-black text-xs uppercase tracking-widest hover:bg-indigo-600 shadow-lg transition-all flex items-center justify-center">
                        
                        Upload Now
                    </button>
                </div>
            </form>
        </div>
    </div>
</template>