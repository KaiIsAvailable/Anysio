document.addEventListener('DOMContentLoaded', function () {
    const roleTypeSelect = document.getElementById('role_type');
    const pmsRoleSelect = document.getElementById('pms_role');
    const subSelect = document.getElementById('subscription_status');
    const referredBySelect = document.querySelector('select[name="referred_by"]');
    const randomCheckbox = document.getElementById('random_email');
    const emailInput = document.getElementById('email_input');

    // --- 函数：处理角色同步和必填逻辑 ---
    function handleRoleLogic() {
        if (!roleTypeSelect || !pmsRoleSelect) return;
        const selectedType = roleTypeSelect.value;

        const mapping = {
            'admin': 'admin',
            'owner': 'ownerAdmin',
            'agent': 'agentAdmin'
        };
        
        // 同步到管理角色
        if (mapping[selectedType]) {
            pmsRoleSelect.value = mapping[selectedType];
        }

        // 处理 Referral 必填状态
        if (referredBySelect) {
            if (selectedType === 'admin') {
                referredBySelect.required = false;
                referredBySelect.classList.remove('border-red-500');
            } else {
                referredBySelect.required = true;
            }
        }
    }

    // --- 函数：处理订阅状态颜色 ---
    function handleSubscriptionStyle() {
        if (!subSelect) return;
        if (subSelect.value === 'inactive') {
            subSelect.classList.add('border-red-500', 'text-red-600', 'bg-red-50');
        } else {
            subSelect.classList.remove('border-red-500', 'text-red-600', 'bg-red-50');
        }
    }

    // --- 函数：处理 Email 只读逻辑 ---
    function handleEmailLogic() {
        if (!randomCheckbox || !emailInput) return;
        if (randomCheckbox.checked) {
            emailInput.readOnly = true;
            emailInput.classList.add('bg-gray-100', 'cursor-not-allowed');
        } else {
            emailInput.readOnly = false;
            emailInput.classList.remove('bg-gray-100', 'cursor-not-allowed');
        }
    }

    // --- 绑定事件监听 ---
    if (roleTypeSelect) roleTypeSelect.addEventListener('change', handleRoleLogic);
    if (subSelect) subSelect.addEventListener('change', handleSubscriptionStyle);
    if (randomCheckbox) randomCheckbox.addEventListener('change', handleEmailLogic);

    // --- 【重要】初始化：页面加载时立刻跑一遍 ---
    handleRoleLogic();
    handleSubscriptionStyle();
    handleEmailLogic();
});