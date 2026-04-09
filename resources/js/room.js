/**
 * Room Assets Management - Unified Logic (Combined)
 */
(function () {
    // 内部状态
    let idx = 0;
    const fullLibrary = window.assetLibrary || [];

    // --- 核心工具函数 ---

    function getListContainer() {
        return document.getElementById('assetList') || document.getElementById('assets-tbody');
    }

    function getEmptyStateElement() {
        return document.getElementById('emptyState') || document.getElementById('no-assets-row');
    }

    function toggleEmptyState() {
        const list = getListContainer();
        const empty = getEmptyStateElement();
        if (!list || !empty) return;
        
        const rowCount = list.querySelectorAll('.asset-row:not(.hidden), tr:not(.hidden)').length;
        if (empty.tagName === 'TR') {
            empty.classList.toggle('hidden', rowCount > 0);
        } else {
            empty.style.display = rowCount === 0 ? 'block' : 'none';
        }
    }

    function getFilteredOptionsHtml() {
        const ownerSelect = document.getElementById('owner_select');
        if (!ownerSelect) return '<option value="">-- Select or Type --</option>';
        
        const selectedOption = ownerSelect.options[ownerSelect.selectedIndex];
        const userId = selectedOption ? selectedOption.getAttribute('data-user-id') : null;

        let html = '<option value="">-- Select or Type --</option>';
        if (userId && userId !== "") {
            const filtered = fullLibrary.filter(a => String(a.user_id) === String(userId));
            filtered.forEach(asset => {
                html += `<option value="${asset.name}">${asset.name}</option>`;
            });
        }
        return html;
    }

    /**
     * 添加新行 (核心方法)
     * 修复点：明确参数 initialCategory，并给予默认值 'General'
     */
    function addRow(isInitial = false, initialName = '', initialCategory = 'General') {
        const list = getListContainer();
        const tpl = document.getElementById('assetRowTpl') || document.getElementById('asset-row-template');
        if (!list || !tpl) return;

        // 替换模板占位符
        let templateHtml = tpl.innerHTML
            .replaceAll('__i__', String(idx))
            .replace(/__NAME__/g, `assets[${idx}][name]`)
            .replace(/__COND__/g, `assets[${idx}][condition]`)
            .replace(/__DATE__/g, `assets[${idx}][last_maintenance]`)
            .replace(/__REMARK__/g, `assets[${idx}][remark]`);

        const wrap = document.createElement(list.tagName === 'TBODY' ? 'tbody' : 'div');
        wrap.innerHTML = templateHtml.trim();
        const row = wrap.firstElementChild;
        row.classList.add('asset-row');

        // 注入下拉选项 (如果有该功能的话)
        const selector = row.querySelector('.asset-selector');
        if (selector) selector.innerHTML = getFilteredOptionsHtml();

        // 填入初始名字
        if (initialName) {
            const nameInput = row.querySelector('input[name*="[name]"]');
            if (nameInput) nameInput.value = initialName;
        }

        // 填入初始分类
        if (initialCategory) {
            const categorySelect = row.querySelector('select[name*="[category]"]');
            if (categorySelect) categorySelect.value = initialCategory;
        }

        // 处理删除逻辑 (内部绑定)
        const removeBtn = row.querySelector('.remove-asset') || row.querySelector('.delete-asset-btn');
        if (removeBtn) {
            removeBtn.addEventListener('click', function() {
                row.remove();
                reindexRows();
                toggleEmptyState();
            });
        }

        list.appendChild(row);
        idx++;
        toggleEmptyState();
        reindexRows(); // 保持 Entry # 编号正确
    }

    // --- 批量添加与 Modal 逻辑 ---

    function openAssetModal() {
        const modal = document.getElementById('assetModal');
        if (!modal) return;

        const list = getListContainer();
        const existingNames = Array.from(list.querySelectorAll('input[name*="[name]"]'))
                                .map(input => input.value.trim());

        modal.querySelectorAll('.asset-checkbox').forEach(cb => {
            if (existingNames.includes(cb.value)) {
                cb.disabled = true;
                cb.parentElement.classList.add('opacity-50', 'cursor-not-allowed');
            } else {
                cb.disabled = false;
                cb.parentElement.classList.remove('opacity-50', 'cursor-not-allowed');
            }
        });

        modal.classList.remove('hidden');
    }

    function closeAssetModal() {
        const modal = document.getElementById('assetModal');
        if (modal) {
            modal.classList.add('hidden');
            modal.querySelectorAll('.asset-checkbox').forEach(cb => cb.checked = false);
        }
    }

    function confirmBatchAdd() {
        const selected = document.querySelectorAll('.asset-checkbox:checked');
        const list = getListContainer();
        if (!list || !selected.length) return;

        // 获取当前页面上所有的资产名字，用于查重
        const allInputs = Array.from(list.querySelectorAll('input[name*="[name]"]'));
        const existingNames = new Set(allInputs.map(input => input.value.trim()).filter(v => v !== ""));

        selected.forEach(cb => {
            const assetName = cb.value;
            const assetCategory = cb.getAttribute('data-category'); 

            if (existingNames.has(assetName)) return;

            // 1. 尝试寻找现有的“空行”来填充
            const currentRows = Array.from(list.querySelectorAll('.asset-row'));
            const emptyRow = currentRows.find(row => {
                const input = row.querySelector('input[name*="[name]"]');
                return input && input.value.trim() === "";
            });

            if (emptyRow) {
                const nameInput = emptyRow.querySelector('input[name*="[name]"]');
                // 【核心修复】使用更精准的选择器找到 select
                const categorySelect = emptyRow.querySelector('select[name*="[category]"]');
                
                if (nameInput) nameInput.value = assetName;
                
                if (categorySelect && assetCategory) {
                    // 强制赋值并触发界面更新
                    categorySelect.value = assetCategory;
                    console.log(`成功将 ${assetName} 设为分类: ${assetCategory}`);
                } else {
                    console.warn(`找不到 ${assetName} 对应的分类下拉框`);
                }
                existingNames.add(assetName);
            } else {
                // 2. 如果没有空行，则创建新行
                // 确保 addRow 的第三个参数接收了 assetCategory
                addRow(false, assetName, assetCategory);
                existingNames.add(assetName);
            }
        });

        closeAssetModal();
        toggleEmptyState();
    }

    // --- 初始化 ---

    document.addEventListener('DOMContentLoaded', function () {
        const list = getListContainer();
        const addBtn = document.getElementById('addAssetBtn') || document.getElementById('add-asset-btn');

        // 1. 同步索引
        const rows = list ? list.querySelectorAll('.asset-row') : [];
        idx = rows.length;

        // 2. 绑定添加按钮
        if (addBtn) {
            addBtn.onclick = (e) => {
                e.preventDefault();
                addRow(false);
            };
        }

        // 3. 初始空状态处理
        if (rows.length === 0) {
            addRow(true);
        } else {
            reindexRows();
            toggleEmptyState();
        }
    });

    // 一个简单的重置编号函数
    function reindexRows() {
        const rows = document.querySelectorAll('#assetList .asset-row');
        rows.forEach((row, index) => {
            const title = row.querySelector('.text-indigo-600.uppercase');
            if (title) title.textContent = `Asset Entry #${index + 1}`;

            row.querySelectorAll('input, select').forEach(input => {
                const name = input.getAttribute('name');
                if (name) {
                    input.setAttribute('name', name.replace(/assets\[\d+\]/, `assets[${index}]`));
                }
            });
        });
        idx = rows.length; 
    }

    // 暴露全局
    window.openAssetModal = openAssetModal;
    window.closeAssetModal = closeAssetModal;
    window.confirmBatchAdd = confirmBatchAdd;
    window.addRow = addRow;
})();