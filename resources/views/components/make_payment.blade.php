{{-- Anysio Subscription Payment Modal --}}
<div x-data="{ 
    openPayment: true, 
    shakePayment: false,
    isUploading: false 
}" x-cloak>

    <template x-teleport="body">
        <div x-show="openPayment" class="fixed inset-0 z-[100] overflow-y-auto">
            
            <div class="flex items-center justify-center min-h-screen px-4 py-6">
                
                {{-- 背景遮罩 --}}
                <div class="fixed inset-0 bg-slate-900/80 backdrop-blur-md transition-opacity" 
                     @click="shakePayment = true; setTimeout(() => shakePayment = false, 400)">
                </div>

                {{-- Modal 主体 --}}
                <div class="relative bg-white rounded-3xl shadow-2xl max-w-xl w-full overflow-hidden transition-all duration-200"
                     x-show="openPayment"
                     :class="{ 'animate-shake': shakePayment }"
                     @click.stop>
                    
                    {{-- Header --}}
                    <div class="p-6 border-b border-gray-100 bg-gray-50/50 flex items-center justify-between">
                        <div>
                            <h3 class="text-xl font-black text-slate-800">Subscription Status</h3>
                            <p class="text-xs text-slate-500 mt-1 uppercase tracking-widest font-bold">Invoice: <span class="text-indigo-600">{{ $latestPayment->invoice_no }}</span></p>
                        </div>
                        <div class="text-right">
                            <span class="block text-[10px] font-bold text-gray-400 uppercase">Total Amount</span>
                            <span class="text-2xl font-black text-indigo-600">RM {{ number_format($latestPayment->amount_due / 100, 2) }}</span>
                        </div>
                    </div>

                    {{-- 情况 A：已经上传了收据，等待审核 --}}
                    @if($latestPayment->attachment && $latestPayment->status === 'pending')
                        <div class="p-12 flex flex-col items-center text-center space-y-6">
                            {{-- 这里的动画效果 --}}
                            <div class="relative">
                                <div class="absolute inset-0 bg-amber-400 rounded-full blur-2xl opacity-20 animate-pulse"></div>
                                <div class="relative bg-amber-50 text-amber-500 p-6 rounded-full inline-flex">
                                    <svg class="w-12 h-12 animate-spin-slow" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                    </svg>
                                </div>
                            </div>

                            <div class="space-y-2">
                                <h4 class="text-2xl font-black text-slate-800 tracking-tight">Waiting for Approval</h4>
                                <p class="text-slate-500 text-sm max-w-xs mx-auto">
                                    We've received your payment proof! Our team is currently verifying it.
                                </p>
                            </div>

                            <div class="bg-slate-50 border border-slate-100 rounded-2xl p-4 w-full text-left">
                                <div class="flex justify-between text-xs">
                                    <span class="text-slate-400 font-bold uppercase">Payment Mode:</span>
                                    <span class="text-slate-700 font-black">Manual Bank Transfer</span>
                                </div>
                                <div class="flex justify-between text-xs mt-2">
                                    <span class="text-slate-400 font-bold uppercase">Status:</span>
                                    <span class="inline-flex items-center text-amber-600 font-black">
                                        <span class="w-2 h-2 bg-amber-500 rounded-full mr-1.5 animate-pulse"></span>
                                        PENDING REVIEW
                                    </span>
                                </div>
                            </div>

                            <p class="text-[10px] text-slate-400 font-bold uppercase tracking-widest">
                                Usually approved within 1 - 2 hours
                            </p>
                        </div>

                    @elseif($latestPayment->status === 'rejected')
                        {{-- 1. 拒绝提示区域 --}}
                        <div class="p-10 flex flex-col items-center text-center space-y-6 bg-rose-50/30">
                            <div class="relative">
                                <div class="absolute inset-0 bg-rose-400 rounded-full blur-2xl opacity-20 animate-pulse"></div>
                                <div class="relative bg-rose-100 text-rose-600 p-6 rounded-full inline-flex">
                                    <svg class="w-12 h-12" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
                                    </svg>
                                </div>
                            </div>

                            <div class="space-y-2">
                                <h4 class="text-2xl font-black text-slate-800 tracking-tight">Verification Failed</h4>
                                <p class="text-slate-500 text-sm max-w-xs mx-auto">
                                    We couldn't verify your previous receipt. Please review the details below and <span class="text-rose-600 font-bold">re-upload</span> a valid proof.
                                </p>
                            </div>
                        </div>

                        {{-- 2. 重新上传表单 (与 Case B 结构完全一致) --}}
                        <form action="{{ route('admin.payments.upload-proof', $latestPayment->id) }}" 
                            method="POST" 
                            enctype="multipart/form-data"
                            @submit="isUploading = true">
                            @csrf
                            
                            <div class="px-8 pb-8 space-y-6">
                                <div class="bg-white border-2 border-dashed border-rose-200 rounded-3xl p-6">
                                    <div class="space-y-4">
                                        <div>
                                            <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-2">Re-upload Bank Receipt</label>
                                            <input type="file" name="attachment" required 
                                                class="block w-full text-sm text-slate-500 file:mr-4 file:py-3 file:px-6 file:rounded-xl file:border-0 file:text-sm file:font-bold file:bg-rose-50 file:text-rose-700 hover:file:bg-rose-100 border border-gray-100 rounded-2xl p-2 bg-gray-50/50 cursor-pointer transition-all"/>
                                        </div>

                                        <div>
                                            <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-2">Updated Transaction Ref (Optional)</label>
                                            <input type="text" name="transaction_ref" value="" 
                                                placeholder="e.g. 123456789" 
                                                class="block w-full px-5 py-4 bg-gray-50 border border-gray-100 rounded-2xl focus:ring-2 focus:ring-rose-500 text-sm font-medium">
                                        </div>
                                    </div>
                                </div>

                                <div class="text-center">
                                    <p class="text-[10px] text-slate-400 font-bold uppercase tracking-widest">
                                        Need help? <a href="mailto:support@anysio.com" class="text-indigo-500 hover:underline">Contact Support</a>
                                    </p>
                                </div>
                            </div>

                            {{-- Footer 按钮 --}}
                            <div class="p-6 bg-gray-50/80 border-t border-gray-100">
                                <button type="submit" 
                                        :disabled="isUploading"
                                        class="w-full px-6 py-5 bg-rose-600 text-white rounded-2xl font-black text-base hover:bg-rose-700 shadow-xl shadow-rose-200 transition-all active:scale-[0.98] disabled:opacity-70 flex items-center justify-center">
                                    <span x-show="!isUploading">Update & Resubmit Proof</span>
                                    <span x-show="isUploading" class="flex items-center">
                                        <svg class="animate-spin -ml-1 mr-3 h-5 w-5 text-white" fill="none" viewBox="0 0 24 24">
                                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                        </svg>
                                        Processing...
                                    </span>
                                </button>
                            </div>
                        </form>

                    {{-- 情况 B：还没上传，显示支付表单 --}}
                    @else
                        <form action="{{ route('admin.payments.upload-proof', $latestPayment->id) }}" 
                              method="POST" 
                              enctype="multipart/form-data"
                              @submit="isUploading = true">
                            @csrf
                            
                            <div class="p-8 space-y-8">
                                {{-- QR Section --}}
                                <div class="flex flex-col items-center justify-center space-y-4">
                                    <div class="relative group">
                                        <div class="absolute -inset-1 bg-gradient-to-r from-indigo-500 to-purple-600 rounded-2xl blur opacity-25"></div>
                                        <div class="relative bg-white p-3 rounded-2xl shadow-sm border border-gray-100">
                                            <img src="{{ asset('image/qr_example.jpeg') }}" class="w-48 h-48 object-contain" alt="Payment QR">
                                        </div>
                                    </div>
                                    <p class="text-sm text-gray-600 font-medium text-center">
                                        Scan with <span class="text-indigo-600 font-bold">DuitNow</span> or Bank App
                                    </p>
                                </div>

                                <hr class="border-gray-100">

                                {{-- Input Section --}}
                                <div class="space-y-4">
                                    <div>
                                        <label class="block text-xs font-bold text-gray-500 uppercase tracking-wider mb-2">1. Upload Bank Receipt</label>
                                        <input type="file" name="attachment" required class="block w-full text-sm text-slate-500 file:mr-4 file:py-3 file:px-6 file:rounded-xl file:border-0 file:text-sm file:font-bold file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100 border border-gray-200 rounded-2xl p-2 bg-gray-50/50 cursor-pointer transition-all"/>
                                    </div>

                                    <div>
                                        <label class="block text-xs font-bold text-gray-500 uppercase tracking-wider mb-2">2. Transaction Ref (Optional)</label>
                                        <input type="text" name="transaction_ref" placeholder="e.g. 123456789" class="block w-full px-5 py-4 bg-gray-50 border border-gray-200 rounded-2xl focus:ring-2 focus:ring-indigo-500 text-sm font-medium">
                                    </div>
                                </div>
                            </div>

                            {{-- Footer --}}
                            <div class="p-6 bg-gray-50/80 border-t border-gray-100">
                                <button type="submit" 
                                        :disabled="isUploading"
                                        class="w-full px-6 py-5 bg-indigo-600 text-white rounded-2xl font-black text-base hover:bg-indigo-700 shadow-xl shadow-indigo-200 transition-all active:scale-[0.98] disabled:opacity-70 flex items-center justify-center">
                                    <span x-show="!isUploading">Confirm & Submit Receipt</span>
                                    <span x-show="isUploading" class="flex items-center">
                                        <svg class="animate-spin -ml-1 mr-3 h-5 w-5 text-white" fill="none" viewBox="0 0 24 24">
                                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                        </svg>
                                        Processing...
                                    </span>
                                </button>
                            </div>
                        </form>
                    @endif
                </div>
            </div>
        </div>
    </template>
</div>

<style>
    @keyframes shake {
        0%, 100% { transform: translateX(0); }
        25% { transform: translateX(-8px); }
        50% { transform: translateX(8px); }
        75% { transform: translateX(-8px); }
    }
    .animate-shake { animation: shake 0.4s ease-in-out; }

    /* 慢速旋转动画，适合等待界面 */
    .animate-spin-slow { animation: spin 3s linear infinite; }
    @keyframes spin {
        from { transform: rotate(0deg); }
        to { transform: rotate(360deg); }
    }

    /* 锁定背景 */
    nav, aside, header { 
        pointer-events: none !important; 
        filter: blur(4px);
        user-select: none;
    }
    [x-cloak] { display: none !important; }
</style>