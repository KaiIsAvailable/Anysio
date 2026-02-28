// resources/js/tenants.js

document.addEventListener('DOMContentLoaded', function() {
    // --- 1. Email Generation Logic ---
    const randomCheckbox = document.getElementById('random_email');
    const emailInput = document.getElementById('email_input');

    if (randomCheckbox) {
        // Initial check
        if (randomCheckbox.checked) {
            toggleEmailField(true);
        }

        randomCheckbox.addEventListener('change', function() {
            toggleEmailField(this.checked);
        });
    }

    function toggleEmailField(isChecked) {
        if (!emailInput) return;
        if (isChecked) {
            emailInput.value = '';
            emailInput.placeholder = 'System will auto-generate email...';
            emailInput.readOnly = true;
            emailInput.classList.add('bg-gray-100', 'cursor-not-allowed');
        } else {
            emailInput.placeholder = 'example@mail.com';
            emailInput.readOnly = false;
            emailInput.classList.remove('bg-gray-100', 'cursor-not-allowed');
        }
    }

    // --- 2. Emergency Contact Logic ---
    const list = document.getElementById('contactList');
    const tpl = document.getElementById('contactRowTpl');
    const addBtn = document.getElementById('addContactBtn');

    if (list && tpl && addBtn) {
        // 关键修复：从当前已有的行数开始计数 (适用于 Edit 页面)
        let idx = list.querySelectorAll('.contact-row').length; 

        window.addContactRow = function(isInitial = false, data = null) {
            const html = tpl.innerHTML.replaceAll('__i__', String(idx));
            const wrap = document.createElement('div');
            wrap.innerHTML = html.trim();
            const row = wrap.firstElementChild;

            if (data) {
                if (data.name) row.querySelector('input[name*="[name]"]').value = data.name;
                if (data.relationship) row.querySelector('input[name*="[relationship]"]').value = data.relationship;
                if (data.phone) row.querySelector('input[name*="[phone]"]').value = data.phone;
            }

            if (isInitial) {
                const removeBtn = row.querySelector('.remove-contact');
                if (removeBtn) removeBtn.remove(); 
                const label = row.querySelector('.uppercase');
                if (label) label.textContent = "Primary Contact (Required)";
            } else {
                // 统一绑定删除逻辑
                const removeBtn = row.querySelector('.remove-contact');
                if (removeBtn) {
                    removeBtn.addEventListener('click', () => row.remove());
                }
            }

            list.appendChild(row);
            idx++;
        }

        addBtn.addEventListener('click', () => window.addContactRow(false));

        const oldContacts = JSON.parse('{!! json_encode(old("emergency_contacts", [])) !!}');

        if (oldContacts && Object.keys(oldContacts).length > 0) {
            // 关键修复：如果有 old 数据，说明是验证失败返回，先清空 PHP 渲染的旧行避免重复
            list.innerHTML = ''; 
            idx = 0; // 重置索引从 0 开始回填 old 数据
            Object.values(oldContacts).forEach((contact, index) => {
                window.addContactRow(index === 0, contact);
            });
        } else if (list.children.length === 0) {
            window.addContactRow(true);
        }
    }

    // --- 3. Identity Document Logic ---
    // 关键：为了让 HTML 里的 onchange 能加到，必须挂载到 window
    window.toggleIdentityInputs = function() {
        const icType = document.querySelector('input[name="identity_type"][value="ic"]');
        if (!icType) return;

        const isIc = icType.checked;
        const icContainer = document.getElementById('ic_container');
        const passportContainer = document.getElementById('passport_container');
        const nationalityInput = document.getElementById('nationality');
        const photoLabel = document.getElementById('photo_label');

        if (isIc) {
            icContainer?.classList.remove('hidden');
            passportContainer?.classList.add('hidden');
            if (nationalityInput) {
                nationalityInput.value = 'Malaysian';
                nationalityInput.readOnly = true;
                nationalityInput.classList.add('bg-gray-100');
            }
            if (photoLabel) photoLabel.textContent = 'IC Photo';
        } else {
            icContainer?.classList.add('hidden');
            passportContainer?.classList.remove('hidden');
            if (nationalityInput) {
                if (nationalityInput.value === 'Malaysian') nationalityInput.value = '';
                nationalityInput.readOnly = false;
                nationalityInput.classList.remove('bg-gray-100');
            }
            if (photoLabel) photoLabel.textContent = 'Passport Photo';
        }
    };

    // 初始化运行一次
    window.toggleIdentityInputs();

    //edit的remove emergency contact function
    if (list) {
        list.addEventListener('click', function(e) {
            // 处理 Edit 页面特有的删除 (带有标记的删除)
            if (e.target.classList.contains('remove-edit-contact')) {
                const row = e.target.closest('.contact-row');
                // 找到对应的隐藏删除标记
                const deleteInput = row.querySelector('.delete-flag');
                
                if (deleteInput) {
                    deleteInput.value = '1'; // 标记为需要删除
                    row.style.display = 'none'; // 视觉上隐藏，但 HTML 还在，为了提交数据
                } else {
                    // 如果是刚 add 出来还没存进 DB 的，直接 remove 掉就行
                    row.remove();
                }
            }
            
            // 处理 Create 页面或新加行的直接删除
            if (e.target.classList.contains('remove-contact')) {
                e.target.closest('.contact-row').remove();
            }
        });
    }
});

//Payment
window.openPaymentModal = function(button) {
    // 1. 获取 ID 对应的各个元素
    const form = document.getElementById('paymentForm');
    const invoiceEl = document.getElementById('modalInvoiceNo');
    const dueEl = document.getElementById('modalAmountDue');
    const paidInput = document.getElementById('modalAmountPaid');
    const modal = document.getElementById('paymentModal');

    if (!form || !modal) return;

    // 2. 从按钮的 data 属性中提取 Laravel 生成好的 URL 和数据
    const actionUrl = button.getAttribute('data-url');
    const invoiceNo = button.getAttribute('data-invoice');
    const amountDue = button.getAttribute('data-due');

    // 3. 填充数据
    form.action = actionUrl; // 这里直接就是正确的 /admin/payments/xxx/update
    if (invoiceEl) invoiceEl.innerText = invoiceNo;
    if (dueEl) dueEl.innerText = amountDue;
    if (paidInput) {
        // 将传入的字符串转为浮点数
        const amount = parseFloat(amountDue);
        // 使用 toFixed(2) 强制保留两位小数
        paidInput.value = isNaN(amount) ? '' : amount.toFixed(2);
    }

    // 4. 显示 Modal
    modal.classList.remove('hidden');
}

window.closePaymentModal = function() {
    const modal = document.getElementById('paymentModal');
    if (modal) modal.classList.add('hidden');
}


//Other Payment Form
window.openManualInvoiceModal = function(button) {
    const modal = document.getElementById('manualInvoiceModal');
    const form = document.getElementById('manualInvoiceForm');

    if (!modal || !form) return;

    // 1. 获取提交路由 (例如: /admin/tenants/01kgsk.../manual-invoice)
    const actionUrl = button.getAttribute('data-url');
    form.action = actionUrl;

    // 2. 重置表单 (确保上次填的数据不会留在里面)
    form.reset();

    // 3. 设置默认账期为本月 (YYYY-MM 格式)
    const periodInput = form.querySelector('input[name="period"]');
    if (periodInput) {
        const now = new Date();
        const yearMonth = now.getFullYear() + '-' + String(now.getMonth() + 1).padStart(2, '0');
        periodInput.value = yearMonth;
    }

    // 4. 显示 Modal
    modal.classList.remove('hidden');
}

window.closeManualInvoiceModal = function() {
    const modal = document.getElementById('manualInvoiceModal');
    if (modal) modal.classList.add('hidden');
}