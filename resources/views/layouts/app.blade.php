<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ config('app.name', 'Anysio') }}</title>
    <link rel="icon" type="image/x-icon" href="{{ asset('image/anysio_logo.png') }}">

    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />
    <link href="https://cdn.quilljs.com/1.3.6/quill.snow.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script src="https://cdn.quilljs.com/1.3.6/quill.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>
    <script defer src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js"></script>

    <script>
        window.currentUser = {
            name: "{{ auth()->user()->name ?? '' }}"
        };
    </script>
    <!-- Date Picker -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>

    @vite([
        'resources/css/app.css',
        'resources/js/app.js',
        'resources/js/tenants.js',
        'resources/js/userManagement.js',
        'resources/js/room.js',
    ])
            @auth
                @php
                    $effectiveUser = get_effective_user();

                    $userMgmt = \App\Models\UserManagement::where('user_id', $effectiveUser->id)->first();

                    $latestSubscriptionInvoice = \App\Models\Invoice::where('user_id', $effectiveUser->id)
                        ->where('context', 'subscription')
                        ->whereIn('status', ['unpaid', 'partial', 'overdue'])
                        ->latest()
                        ->first();
                        
                    // 🔥 Remove 'staff' from the exclusion list so staff also trigger the payment block/modal 
                    // if their workspace/parent admin hasn't paid.
                    $mustPay = $latestSubscriptionInvoice !== null
                        && auth()->user()->role !== 'admin';
                @endphp
            @endauth


    @stack('scripts')
</head>

<body class="font-sans antialiased">

    <div class="min-h-screen bg-gray-100">

        @include('layouts.navigation')

        @auth
            @php
                // 1. 检查 User Management 状态
                $userMgmt = \App\Models\UserManagement::where(
                    'user_id',
                    auth()->id()
                )->first();

                // 2. 获取需要付款的 Subscription Invoice
                $latestSubscriptionInvoice = \App\Models\Invoice::where(
                    'user_id',
                    auth()->id()
                )
                ->where('context', 'subscription')
                ->whereIn('status', ['unpaid', 'partial', 'overdue'])
                ->latest()
                ->first();

                $mustPay = $latestSubscriptionInvoice !== null
                    && auth()->user()->role !== 'admin';
            @endphp
        @endauth


        {{-- ========================================================= --}}
        {{-- GLOBAL INVOICE PREVIEW MODAL                              --}}
        {{-- ========================================================= --}}
        {{-- 
            这个必须存在于页面中，
            make_payment.blade.php 的 View Invoice
            才能通过 open-invoice-preview 事件打开它。
        --}}
        @include('components.preview-invoice-modal')


        {{-- ========================================================= --}}
        {{-- PAYMENT MODAL                                             --}}
        {{-- ========================================================= --}}
        {{-- 
            这里只保留一次 make_payment。
            之前你的 app.blade.php 引入了两次，会造成重复 Modal。
        --}}
        @if($mustPay ?? false)

            <main x-data="{ openPayment: true }">

                @include(
                    'components.modals.make_payment',
                    ['invoice' => $latestSubscriptionInvoice]
                )

                <x-auth-session-status
                    class="mb-4"
                    :status="session('status')"
                />

                @isset($header)
                    <header class="bg-white shadow">
                        <div class="max-w-7xl mx-auto py-6 px-4 sm:px-6 lg:px-8">
                            {{ $header }}
                        </div>
                    </header>
                @endisset

                {{ $slot }}

            </main>

        @else

            <main x-data="{ openPayment: false }">

                <x-auth-session-status
                    class="mb-4"
                    :status="session('status')"
                />

                @isset($header)
                    <header class="bg-white shadow">
                        <div class="max-w-7xl mx-auto py-6 px-4 sm:px-6 lg:px-8">
                            {{ $header }}
                        </div>
                    </header>
                @endisset

                {{ $slot }}

            </main>

        @endif

    </div>


    <script>

        // =========================================================
        // Toast 自动消失
        // =========================================================

        window.addEventListener('DOMContentLoaded', function() {

            const toasts = document.querySelectorAll('[id^="toast-"]');

            toasts.forEach(toast => {

                setTimeout(() => {

                    toast.style.transition = 'all 0.5s ease';
                    toast.style.opacity = '0';
                    toast.style.transform = 'translateX(100%)';

                    setTimeout(() => toast.remove(), 500);

                }, 5000);

            });

        });


        // =========================================================
        // GLOBAL DOCUMENT PREVIEW ENGINE
        // Invoice & Receipt
        // =========================================================

        window.openDocumentPreview = function(
            type,
            content,
            baseVariables,
            items,
            title,
            extra = {}
        ) {

            if (!content) return;


            // -----------------------------------------------------
            // 1. 合并变量
            // -----------------------------------------------------

            let finalVariables = Object.assign(
                {},
                baseVariables
            );

            if (extra.receiptVariables) {

                Object.assign(
                    finalVariables,
                    extra.receiptVariables
                );

            }


            // -----------------------------------------------------
            // Receipt 专属变量
            // -----------------------------------------------------

            if (type === 'receipt') {

                let payDate =
                    extra.paymentDate || '—';

                let rNo =
                    extra.receiptNo || '—';

                let paidAmount =
                    extra.paidAmount || '0.00';


                Object.assign(finalVariables, {

                    'receipt_number': rNo,
                    'receipt_no': rNo,

                    'issue_date': payDate,
                    'payment_date': payDate,
                    'receipt_date': payDate,

                    'subtotal_amount': paidAmount,
                    'total_amount': paidAmount,
                    'total': paidAmount,

                    'amount_paid': paidAmount,
                    'total_paid': paidAmount,
                    'paid_amount': paidAmount

                });

            }


            // -----------------------------------------------------
            // 2. 创建临时 HTML
            // -----------------------------------------------------

            let tempDiv =
                document.createElement('div');

            tempDiv.innerHTML = content;


            // -----------------------------------------------------
            // 3. 清除 GrapesJS 特殊背景
            // -----------------------------------------------------

            tempDiv
                .querySelectorAll(
                    '.gjs-variable-tag, [data-variable]'
                )
                .forEach(el => {

                    let varName =
                        el.getAttribute('data-variable');

                    if (
                        varName === 'invoice_duedate' ||
                        varName === 'invoice_status'
                    ) {
                        return;
                    }

                    el.style.backgroundColor =
                        'transparent';

                    el.style.color =
                        'inherit';

                    el.style.padding =
                        '0';

                });


            // -----------------------------------------------------
            // 4. 动态生成 Invoice / Receipt Items
            // -----------------------------------------------------

            let dynamicRows = '';


            if (type === 'invoice') {

                if (items && items.length > 0) {

                    items.forEach(item => {

                        let itemDesc =
                            item.description ||
                            item.fee_type?.name ||
                            'Item';

                        let itemAmount =
                            item.amount || 0;


                        if (
                            typeof itemAmount === 'number' &&
                            itemAmount > 100
                        ) {

                            itemAmount =
                                (itemAmount / 100).toFixed(2);

                        } else if (
                            typeof itemAmount === 'number'
                        ) {

                            itemAmount =
                                parseFloat(itemAmount).toFixed(2);

                        }


                        dynamicRows += `
                            <tr style="border-bottom: 1px solid #e2e8f0;">
                                <td style="padding: 12px 15px; color: #0f172a;">
                                    ${itemDesc}
                                </td>

                                <td style="padding: 12px 15px; text-align: center; color: #475569;">
                                    1
                                </td>

                                <td style="padding: 12px 15px; text-align: right; color: #475569;">
                                    RM ${itemAmount}
                                </td>

                                <td style="padding: 12px 15px; text-align: right; color: #0f172a; font-weight: 500;">
                                    RM ${itemAmount}
                                </td>
                            </tr>
                        `;

                    });

                } else {

                    dynamicRows = `
                        <tr>
                            <td colspan="4"
                                style="padding: 12px 15px; text-align: center; color: #94a3b8; font-style: italic;">
                                No items recorded.
                            </td>
                        </tr>
                    `;

                }

            } else if (type === 'receipt') {

                let paidAmount =
                    extra.paidAmount || '0.00';

                let invNo =
                    extra.invoiceNo || '—';


                dynamicRows = `
                    <tr style="border-bottom: 1px solid #e2e8f0;">

                        <td style="padding: 12px 15px; color: #0f172a;">
                            Payment for Invoice: ${invNo}
                        </td>

                        <td style="padding: 12px 15px; text-align: center; color: #475569;">
                            1
                        </td>

                        <td style="padding: 12px 15px; text-align: right; color: #475569;">
                            RM ${paidAmount}
                        </td>

                        <td style="padding: 12px 15px; text-align: right; color: #0f172a; font-weight: 500;">
                            RM ${paidAmount}
                        </td>

                    </tr>
                `;

            }


            // -----------------------------------------------------
            // 5. 找到 Dynamic Items 的 tbody
            // -----------------------------------------------------

            if (
                type === 'invoice' ||
                type === 'receipt'
            ) {

                let tbody = null;


                let allNodes =
                    Array.from(
                        tempDiv.querySelectorAll(
                            'td, th, span, div, p'
                        )
                    );


                let targetNode =
                    allNodes.find(el =>
                        el.textContent.includes('⚙️') ||
                        el.textContent.includes('Dynamic Receipt Items') ||
                        el.textContent.includes('Dynamic Invoice Items')
                    );


                if (targetNode) {

                    tbody =
                        targetNode.closest('tbody');

                }


                if (!tbody) {

                    tbody =
                        tempDiv.querySelector(
                            'tbody[id^="dynamic-receipt-tbody"]'
                        ) ||
                        tempDiv.querySelector(
                            'tbody[id^="dynamic-invoice-tbody"]'
                        );

                }


                if (tbody) {

                    tbody.innerHTML =
                        dynamicRows;

                }

            }


            content =
                tempDiv.innerHTML;


            // -----------------------------------------------------
            // 6. 变量替换
            // -----------------------------------------------------

            Object.keys(finalVariables).forEach(key => {

                let val =
                    finalVariables[key];


                if (
                    val === null ||
                    val === undefined ||
                    val === ''
                ) {

                    val = 'N/A';

                }


                let replaceHtml =
                    `<span class="font-medium">${val}</span>`;


                let spanRegex =
                    new RegExp(
                        '<span[^>]*data-variable=.' +
                        key +
                        '.[^>]*>\\s*\\{\\{\\s*' +
                        key +
                        '\\s*\\}\\}\\s*<\\/span>',
                        'gi'
                    );


                if (content.match(spanRegex)) {

                    content =
                        content.replace(
                            spanRegex,
                            replaceHtml
                        );

                } else {

                    let doubleRegex =
                        new RegExp(
                            '\\{\\{\\s*' +
                            key +
                            '\\s*\\}\\}',
                            'g'
                        );

                    let singleRegex =
                        new RegExp(
                            '\\{\\s*' +
                            key +
                            '\\s*\\}',
                            'g'
                        );


                    content =
                        content
                            .replace(
                                doubleRegex,
                                replaceHtml
                            )
                            .replace(
                                singleRegex,
                                replaceHtml
                            );

                }

            });


            // =====================================================
            // 7. 根据类型打开对应 Modal
            // =====================================================

            if (type === 'invoice') {

                console.log(
                    '[Preview] Opening Invoice Preview'
                );

                window.dispatchEvent(
                    new CustomEvent(
                        'open-invoice-preview',
                        {
                            detail: {
                                title: title,
                                content: content
                            }
                        }
                    )
                );

            } else {

                console.log(
                    '[Preview] Opening Lease/Receipt Preview'
                );

                window.dispatchEvent(
                    new CustomEvent(
                        'open-lease-preview',
                        {
                            detail: {
                                title: title,
                                content: content
                            }
                        }
                    )
                );

            }


            document.body.style.overflow =
                'hidden';

        };

    </script>

</body>

</html>