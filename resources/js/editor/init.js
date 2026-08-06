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

  const rte = editor.RichTextEditor;

  // ==========================================
  // 🌟 終極修復版：繞過瀏覽器過濾，強制 DOM 節點植入
  // ==========================================
  window.insertVariableFromSelect = function (select) {
    const variableKey = select.value;
    if (!variableKey) return;

    // 1. 獲取 GrapesJS 畫布的真實 Document
    const canvasDoc = window.editor.Canvas.getDocument();

    // 2. 找到當前正在被編輯的文本組件
    const selectedComp = window.editor.getSelected();

    // 3. 將焦點強制還給文本框
    if (selectedComp && selectedComp.view && selectedComp.view.el) {
      selectedComp.view.el.focus();
    }

    // 4. 使用原生 Range API 強制植入節點，免疫瀏覽器在 <li> 裡面的自動過濾
    const selection = canvasDoc.getSelection();
    if (selection && selection.rangeCount > 0) {
        const range = selection.getRangeAt(0);
        
        // 手動創建變數標籤 Span
        const span = canvasDoc.createElement('span');
        span.className = 'gjs-variable-tag';
        span.setAttribute('data-variable', variableKey);
        span.setAttribute('contenteditable', 'false');
        
        // 賦予最高權重的 Inline Style，確保在 <li> 裡面也絕對有顏色
        span.style.cssText = 'background-color: #e0f2fe !important; color: #0284c7 !important; padding: 2px 6px !important; border-radius: 4px !important; font-family: monospace !important; font-size: 14px !important; margin: 0 2px !important; display: inline-block !important; vertical-align: middle !important; line-height: normal !important;';
        span.innerHTML = `{{ ${variableKey} }}`;

        // 創建零寬空格 (\u200B)，確保插入變數後，游標可以順利移到變數後方繼續打字
        const zws = canvasDoc.createTextNode('\u200B');

        range.deleteContents();
        // 按照順序植入：先插 zws，再插 span，順序就會完美變成 [標籤][游標]
        range.insertNode(zws);
        range.insertNode(span);

        // 將游標精準定位到零寬空格之後
        range.setStartAfter(zws);
        range.collapse(true);
        selection.removeAllRanges();
        selection.addRange(range);
    }

    // 5. 重置下拉框回預設選項
    select.value = '';
  };

  // 增加工具列變數下拉選單
  rte.add('variables-dropdown', {
    icon: `
        <select 
            onchange="window.insertVariableFromSelect(this)" 
            style="margin: 0 4px; padding: 2px 4px; font-size: 12px; font-weight: 600; border-radius: 4px; border: 1px solid #cbd5e1; background: #f8fafc; color: #0f172a; cursor: pointer; outline: none; max-width: 160px;"
        >
            <option value="">+ Insert Variable...</option>
            
            <optgroup label="🧑‍💼 Tenant Info ">
                <option value="tenant_name">Tenant Name</option>
                <option value="tenant_ic">Tenant IC / Passport</option>
            </optgroup>

            <optgroup label="🏠 Owner & Property ">
                <option value="owner_name">Owner Name</option>
                <option value="owner_ic">Owner IC</option>
                <option value="property_name">Property Name</option>
                <option value="property_type">Property Type</option>
                <option value="property_address">Full Address</option>
            </optgroup>

            <optgroup label="📄 Lease Details ">
                <option value="start_date">Start Date</option>
                <option value="end_date">End Date</option>
                <option value="check_out_date">Check Out Date</option>
                <option value="rent_price">Monthly Rent Price</option>
            </optgroup>

            <optgroup label="💰 Deposits & Fees ">
                <option value="total_deposit">Total Deposit </option>
                <option value="security_deposit">Security Deposit </option>
                <option value="utilities_deposit">Utilities Deposit </option>
                <option value="management_fee">Management Fee </option>
            </optgroup>
        </select>
    `,
  });

  editor.on('load', () => {
    updateBlocks('tos');
  });

  return editor;
}

export function updateBlocks(category) {
    const bm = window.editor.BlockManager;

    const allBlocks = bm.getAll();
    const blocksToRemove = [];

    allBlocks.forEach(block => {
        const id = block.id || block.get?.('id');
        const customIds = [
            'agreement-title', 'text-block', 'horizontal-line', 'basic-table', 
            'heading-2', 'signature-block', 'agreement-preamble', 'notice-box', 
            'numbered-clauses', 'thick-divider', 'double-divider', 'page-break',
            'payment-clause', 'var-financials', 'var-property', 'var-dates',
            'layout-2-columns', 'layout-3-columns', 'layout-logo-right',
            'bullet-list', 'numbered-list'
        ];

        if (customIds.includes(id) || id.startsWith('privacy-') || id.startsWith('invoice-') || id.startsWith('receipt-') || id.startsWith('tos-')) {
            blocksToRemove.push(id);
        }
    });

    blocksToRemove.forEach(id => bm.remove(id));

    const blocks = blockSets[category] || [];
    blocks.forEach(block => {
        if (!bm.get(block.id)) {
            bm.add(block.id, block);
        }
    });
}