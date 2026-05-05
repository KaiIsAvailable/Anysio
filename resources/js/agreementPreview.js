window.parseAgreementTemplate = function(template) {
    if (!template) return '';
    let content = template;

    // --- 1. 自动处理所有 data-placeholder 标记的元素 ---
    // 这样你就不用手动写 replacements 列表了
    const placeholderElements = document.querySelectorAll('[data-placeholder]');
    
    placeholderElements.forEach(el => {
        const placeholder = el.getAttribute('data-placeholder');
        let val = el.value || '__________';

        // 执行替换
        const regex = new RegExp(placeholder.replace(/[.*+?^${}()|[\]\\]/g, '\\$&'), 'g');
        content = content.replace(regex, `<span class="text-indigo-700 font-semibold">${val}</span>`);
    });

    // --- 2. 处理特殊的复合逻辑 (Tenant Name/IC) ---
    const tenantSelect = document.getElementById('tenant_id');
    if (tenantSelect && tenantSelect.value) {
        const fullText = tenantSelect.options[tenantSelect.selectedIndex].text;
        const match = fullText.match(/(.+?)\s*\((.+?)\)/);
        if (match) {
            content = content.replace(/{tenant_name}/g, `<span class="text-indigo-700 font-semibold">${match[1].trim()}</span>`);
            content = content.replace(/{tenant_ic}/g, `<span class="text-indigo-700 font-semibold">${match[2].trim()}</span>`);
        }
    }

    // --- 3. 处理系统变量 (Today, Owner) ---
    const today = new Date().toLocaleDateString();
    content = content.replace(/{today_date}/g, `<span class="text-indigo-700 font-semibold">${today}</span>`);
    
    const ownerName = window.currentUser?.name || '__________';
    content = content.replace(/{owner_name}/g, `<span class="text-indigo-700 font-semibold">${ownerName}</span>`);

    return content;
};