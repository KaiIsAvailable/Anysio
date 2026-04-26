@props(['payment'])

@if($payment)
<div x-data="{ showModal: false, isActioning: false }" class="mb-10">
    {{-- 主卡片容器 --}}
    <div class="relative overflow-hidden rounded-[2.5rem] bg-white border border-slate-100 shadow-[0_20px_50px_rgba(0,0,0,0.05)] transition-all hover:shadow-[0_30px_60px_rgba(0,0,0,0.08)]">
        
        {{-- 背景装饰 --}}
        <div class="absolute -top-24 -right-24 h-64 w-64 rounded-full bg-indigo-50 blur-3xl opacity-60"></div>

        <div class="relative flex flex-col md:flex-row items-stretch">
            
            {{-- 左侧：用户信息与收据 --}}
            <div class="flex-1 flex items-center gap-4 md:gap-6 p-6 md:p-8">
                {{-- 限制尺寸的收据缩略图 --}}
                <div class="relative group cursor-pointer shrink-0" @click="showModal = true">
                    {{-- 背景发光效果 --}}
                    <div class="absolute -inset-1.5 bg-gradient-to-tr from-indigo-500 to-purple-600 rounded-2xl blur opacity-20 group-hover:opacity-40 transition duration-500"></div>
                    
                    <div class="relative h-16 w-16 md:h-20 md:w-20 overflow-hidden rounded-xl border-[3px] border-white shadow-lg">
                        <img src="{{ route('admin.receipt.display', $payment->attachment) }}" 
                            alt="Receipt" 
                            class="h-full w-full object-cover transform group-hover:scale-110 transition duration-700">
                        
                        {{-- 新增：Hover 时的黑色遮罩和图标 --}}
                        <div class="absolute inset-0 bg-slate-900/40 opacity-0 group-hover:opacity-100 flex items-center justify-center transition-all duration-300">
                            {{-- 这里使用了放大镜图标 --}}
                            <svg class="w-6 h-6 text-white transform scale-50 group-hover:scale-100 transition-transform duration-300" 
                                fill="none" 
                                stroke="currentColor" 
                                viewBox="0 0 24 24">
                                <path stroke-linecap="round" 
                                    stroke-linejoin="round" 
                                    stroke-width="2.5" 
                                    d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0zM10 7v3m0 0v3m0-3h3m-3 0H7"/>
                            </svg>
                        </div>
                    </div>
                </div>

                {{-- 文字信息 --}}
                <div class="min-w-0 flex-1 space-y-1">
                    <div class="flex items-center gap-2 flex-wrap">
                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[9px] font-black bg-amber-50 text-amber-600 border border-amber-100 uppercase tracking-tighter animate-pulse">
                            Waiting Action
                        </span>
                        <span class="text-[9px] font-bold text-slate-400 uppercase tracking-widest truncate">INV #{{ $payment->invoice_no }}</span>
                    </div>
                    
                    <h3 class="text-base md:text-xl font-black text-slate-800 truncate leading-tight">
                        {{ $payment->user->name ?? 'User' }}
                    </h3>
                    
                    <div class="text-sm font-bold text-slate-500">
                        <span class="text-indigo-600 font-black">RM {{ number_format($payment->amount_due / 100, 2) }}</span>
                    </div>
                </div>
            </div>

            {{-- 右侧：操作面板 (在手机端会自动排成两列/紧凑布局) --}}
            <div class="md:w-64 bg-slate-50/80 border-t md:border-t-0 md:border-l border-slate-100 p-4 md:p-6 flex flex-row md:flex-col justify-center items-center gap-3">
                
                {{-- Reject Button (在左/上) --}}
                <form action="{{ route('admin.payments.reject', $payment->id) }}" method="POST" @submit="isActioning = true" class="flex-1 md:w-full">
                    @csrf
                    @method('PATCH')
                    <button type="submit" 
                            :disabled="isActioning"
                            onclick="return confirm('Reject this transaction?')"
                            class="w-full px-4 py-3 bg-white border border-slate-200 text-slate-400 font-black text-[10px] rounded-xl hover:text-rose-500 hover:border-rose-100 transition-all uppercase tracking-widest disabled:opacity-50">
                        Reject
                    </button>
                </form>

                {{-- Approve Button (在右/下) --}}
                <form action="{{ route('admin.payments.approve', $payment->id) }}" method="POST" @submit="isActioning = true" class="flex-[2] md:w-full">
                    @csrf
                    @method('PATCH')
                    <button type="submit" 
                            :disabled="isActioning"
                            class="group relative w-full px-4 py-3 bg-slate-900 text-white rounded-xl font-black text-[10px] transition-all hover:bg-indigo-600 hover:shadow-lg active:scale-[0.98] disabled:opacity-50">
                        <span class="flex items-center justify-center gap-2">
                            Approve
                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M14 5l7 7m0 0l-7 7m7-7H3"/></svg>
                        </span>
                    </button>
                </form>
            </div>
        </div>
    </div>

    {{-- 图片放大 Modal (与 Make Payment 风格统一) --}}
    <template x-teleport="body">
        <div x-show="showModal" 
             x-cloak
             class="fixed inset-0 z-[999] overflow-y-auto">
            
            {{-- 背景遮罩 (毛玻璃效果) --}}
            <div class="fixed inset-0 bg-slate-900/80 backdrop-blur-md transition-opacity" 
                 x-show="showModal"
                 x-transition:enter="ease-out duration-300"
                 x-transition:enter-start="opacity-0"
                 x-transition:enter-end="opacity-100"
                 @click="showModal = false">
            </div>

            <div class="flex items-center justify-center min-h-screen px-4 py-12">
                {{-- Modal 主体: 白色卡片 --}}
                <div class="relative bg-white rounded-[2.5rem] shadow-2xl max-w-2xl w-full overflow-hidden transition-all duration-300"
                     x-show="showModal"
                     x-transition:enter="ease-out duration-300"
                     x-transition:enter-start="opacity-0 translate-y-8 scale-95"
                     x-transition:enter-end="opacity-100 translate-y-0 scale-100"
                     @click.stop>
                    
                    {{-- Header (页眉) --}}
                    <div class="p-6 border-b border-gray-100 bg-gray-50/50 flex items-center justify-between">
                        <div>
                            <h3 class="text-xl font-black text-slate-800">Payment Proof</h3>
                            <p class="text-[10px] text-slate-500 mt-1 uppercase tracking-widest font-bold">
                                User: <span class="text-indigo-600">{{ $payment->user->name }}</span>
                            </p>
                        </div>
                        <button @click="showModal = false" class="p-2 hover:bg-gray-200/50 rounded-full transition-colors text-slate-400">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                            </svg>
                        </button>
                    </div>

                    {{-- 图片展示区域 (限制高度，带灰色背景) --}}
                    <div class="p-4 bg-slate-100 flex items-center justify-center">
                        <div class="relative group">
                            <img src="{{ route('admin.receipt.display', $payment->attachment) }}" 
                                 class="max-h-[60vh] w-auto object-contain rounded-2xl shadow-sm border-4 border-white"
                                 alt="Receipt Full View">
                        </div>
                    </div>

                    {{-- Footer (页脚) --}}
                    <div class="p-6 bg-white border-t border-gray-50 text-center">
                        <p class="text-xs text-slate-400 font-bold uppercase tracking-[0.2em]">
                            Uploaded on {{ $payment->created_at->format('M d, Y @ H:i') }}
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </template>
</div>
@endif