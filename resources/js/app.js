import './bootstrap';

import Alpine from 'alpinejs';

window.Alpine = Alpine;

Alpine.start();

document.addEventListener('keydown', function(e) {
    if (e.key === 'Enter') {
        // 1. 如果是在选择 Gender 的下拉框
        if (e.target.tagName === 'SELECT') {
            // 如果下拉框是打开状态，回车应该是确认选择，不要跳走
            // 但如果已经选好了，按回车就跳到下一格
            e.preventDefault();
            focusNextInput(e.target);
        } 
        // 2. 普通输入框
        else if (e.target.tagName === 'INPUT') {
            e.preventDefault();
            focusNextInput(e.target);
        }
    }
});

function focusNextInput(currentElement) {
    // 1. 获取所有【潜在】可聚焦的元素（不分青红皂白先全拿出来）
    const allPossible = Array.from(document.querySelectorAll(
        'input:not([type="hidden"]):not([disabled]), select:not([disabled]), button[type="submit"]'
    ));
    
    // 2. 关键过滤：只保留真正看得见、能摸得着的元素
    const visibleInputs = allPossible.filter(el => {
        const style = window.getComputedStyle(el);
        return style.display !== 'none' && 
               style.visibility !== 'hidden' && 
               el.offsetWidth > 0 && 
               el.offsetHeight > 0;
    });

    const index = visibleInputs.indexOf(currentElement);

    if (index > -1 && index < visibleInputs.length - 1) {
        // 3. 强制延迟一点点聚焦，防止某些浏览器冲突
        setTimeout(() => {
            visibleInputs[index + 1].focus();
        }, 10);
    }
}
