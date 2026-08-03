import 'grapesjs/dist/css/grapes.min.css';
import grapesjs from 'grapesjs';
import gjsPresetWebpage from 'grapesjs-preset-webpage';

import agreementBlocks from './agreement';
import privacyBlocks from './privacy';
import invoiceBlocks from './invoice';
import receiptBlocks from './receipt';
import tosBlocks from './term_of_service';

const blockSets = {
  agreement: agreementBlocks,
  privacy: privacyBlocks,
  invoice: invoiceBlocks,
  receipt: receiptBlocks,
  tos: tosBlocks
};

export function createAgreementEditor(containerId) {
  const editor = grapesjs.init({
    container: `#${containerId}`,
    plugins: [gjsPresetWebpage],

    blockManager: {
      appendTo: '#blocks-container'
    },

    styleManager: {
      appendTo: '#styles-container'
    },

    storageManager: false
  });

  window.editor = editor;

  // ==========================================
  // 💡 进阶版：使用下拉菜单 (Dropdown) 统一管理海量变量
  // ==========================================
  const rte = editor.RichTextEditor;

  // ==========================================
  // 💡 修复版：完美处理光标丢失和插入定位
  // ==========================================
  window.insertVariableFromSelect = function (select) {
    const variableKey = select.value;
    if (!variableKey) return;

    // 带有不可编辑属性的变量 HTML
    const html = `<span contenteditable="false" style="background-color: #e0f2fe; color: #0284c7; padding: 2px 6px; border-radius: 4px; font-family: monospace; font-size: 14px; margin: 0 2px;" data-variable="${variableKey}">{{ ${variableKey} }}</span>&#8203;`;

    // 1. 获取 GrapesJS 画板的真实 Document (因为编辑器是跑在 iframe 里的)
    const canvasDoc = window.editor.Canvas.getDocument();

    // 2. 找到当前正在被编辑的文本组件
    const selectedComp = window.editor.getSelected();

    // 3. 将焦点强制还给文本框，唤醒它记忆中最后一次的光标位置
    if (selectedComp && selectedComp.view && selectedComp.view.el) {
      selectedComp.view.el.focus();
    }

    // 4. 使用浏览器原生的富文本底层命令，在光标处精确注入 HTML
    canvasDoc.execCommand('insertHTML', false, html);

    // 5. 重置下拉框回默认选项
    select.value = '';
  };

  // 2. 在工具栏只添加一个原生下拉框
  rte.add('variables-dropdown', {
    // 直接在 icon 里注入一段写好样式的 <select> HTML
    icon: `
            <select 
    onchange="window.insertVariableFromSelect(this)" 
    style="margin: 0 4px; padding: 2px 4px; font-size: 12px; font-weight: 600; border-radius: 4px; border: 1px solid #cbd5e1; background: #f8fafc; color: #0f172a; cursor: pointer; outline: none; max-width: 160px;"
>
    <option value="">+ Insert Variable...</option>
    
    <optgroup label="🧑‍💼 Tenant Info (租客)">
        <option value="tenant_name">Tenant Name</option>
        <option value="tenant_ic">Tenant IC / Passport</option>
    </optgroup>

    <optgroup label="🏠 Owner & Property (房产)">
        <option value="owner_name">Owner Name</option>
        <option value="owner_ic">Owner IC</option>
        <option value="property_name">Property Name</option>
        <option value="property_type">Property Type</option>
        <option value="property_address">Full Address</option>
    </optgroup>

    <optgroup label="📄 Lease Details (租约)">
        <option value="start_date">Start Date</option>
        <option value="end_date">End Date</option>
        <option value="check_out_date">Check Out Date</option>
        <option value="rent_price">Monthly Rent Price</option>
        <option value="security_deposit">Security Deposit</option>
        <option value="utilities_deposit">Utilities Deposit</option>
    </optgroup>
</select>
        `,
    // 这里不需要写 result 函数，因为点击动作交给了 select 的 onchange 处理
  });
  // ==========================================

  editor.on('load', () => {
    // 初始化加载 tos 的 blocks
    updateBlocks('tos');
  });

  return editor;
}

export function updateBlocks(category) {
    const bm = window.editor.BlockManager;

    // 1. 安全清除：只移除我们自定义动态添加的 blocks，保留 GrapesJS 默认自带的基础网页组件
    const allBlocks = bm.getAll();
    const blocksToRemove = [];

    allBlocks.forEach(block => {
        const id = block.id || block.get?.('id');
        // 假设你自定义的 block id 都是这些，或者你可以根据前缀来识别
        // 如果你的自定义 block 包含这些特定 ID，我们才移除它们
        const customIds = [
            'agreement-title', 'text-block', 'horizontal-line', 'basic-table', 
            'heading-2', 'signature-block', 'agreement-preamble', 'notice-box', 
            'numbered-clauses', 'thick-divider', 'double-divider'
            // 如果你还有 privacy/invoice/receipt 的自定义 id，也可以加进来
        ];

        if (customIds.includes(id) || id.startsWith('privacy-') || id.startsWith('invoice-') || id.startsWith('receipt-') || id.startsWith('tos-')) {
            blocksToRemove.push(id);
        }
    });

    // 批量安全移除旧的自定义 Block
    blocksToRemove.forEach(id => bm.remove(id));

    // 2. 获取当前分类对应的 Blocks 数组并添加
    const blocks = blockSets[category] || [];
    blocks.forEach(block => {
        // 防止重复添加
        if (!bm.get(block.id)) {
            bm.add(block.id, block);
        }
    });
}