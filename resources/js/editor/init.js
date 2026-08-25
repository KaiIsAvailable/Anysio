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

        traitManager: {
            appendTo: '#traits-container'
        },

        storageManager: false
    });

    window.editor = editor;


    // ==========================================
    // 🔢 Custom Ordered List
    // ==========================================
    //
    // 選中 <ol> 後，可以修改：
    //
    // List Type:
    // 1, 2, 3
    // a, b, c
    // A, B, C
    // i, ii, iii
    // I, II, III
    //
    // Start:
    // 從第幾個數字開始
    //
    // ==========================================

    editor.DomComponents.addType('custom-ordered-list', {

        // ------------------------------------------
        // 找到 <ol> 時，自動使用 custom-ordered-list
        // ------------------------------------------
        isComponent: el => {
            if (el.tagName === 'OL') {
                return {
                    type: 'custom-ordered-list'
                };
            }
        },


        // ------------------------------------------
        // Component Model
        // ------------------------------------------
        model: {

            defaults: {

                tagName: 'ol',

                // 預設樣式
                style: {
                    margin: '15px 0',
                    'padding-left': '20px',
                    'line-height': '1.6',
                    color: '#333'
                },


                // ========================================
                // Traits
                // ========================================
                traits: [

                    // --------------------------------------
                    // List Type
                    // --------------------------------------
                    {
                        type: 'select',

                        name: 'listStyleType',

                        label: 'List Type',

                        changeProp: true,

                        options: [
                            {
                                id: 'decimal',
                                name: '1, 2, 3'
                            },
                            {
                                id: 'lower-alpha',
                                name: 'a, b, c'
                            },
                            {
                                id: 'upper-alpha',
                                name: 'A, B, C'
                            },
                            {
                                id: 'lower-roman',
                                name: 'i, ii, iii'
                            },
                            {
                                id: 'upper-roman',
                                name: 'I, II, III'
                            }
                        ]
                    },


                    // --------------------------------------
                    // Start
                    // --------------------------------------
                    {
                        type: 'text',
                        name: 'listStart',
                        label: 'Start',
                        default: '1',
                        changeProp: true,

                        attributes: {
                            inputmode: 'numeric',
                            autocomplete: 'off'
                        }
                    }

                ]
            },


            // ========================================
            // Component 初始化
            // ========================================
            init() {

                // --------------------------------------
                // 讀取現有 OL 的 style
                // --------------------------------------

                const currentStyle = this.getStyle() || {};

                let currentListStyle =
                    currentStyle['list-style-type'] || 'decimal';


                // --------------------------------------
                // 讀取現有 OL 的 start
                // --------------------------------------

                let currentStart =
                    this.getAttributes()?.start || 1;


                // 確保 Start 是數字
                currentStart = parseInt(currentStart, 10);

                if (isNaN(currentStart)) {
                    currentStart = 1;
                }


                // --------------------------------------
                // 設定 Component Property
                // --------------------------------------

                this.set({
                    listStyleType: currentListStyle,
                    listStart: String(currentStart)
                });


                // --------------------------------------
                // 確保初始 OL 有正確 style
                // --------------------------------------

                this.addStyle({
                    'list-style-type': currentListStyle
                });


                // --------------------------------------
                // 如果原本有 start
                // 就保留
                // --------------------------------------

                if (currentStart !== 1) {
                    this.addAttributes({
                        start: currentStart
                    });
                }


                // ======================================
                // List Type 改變
                // ======================================

                this.on('change:listStyleType', () => {

                    const listType =
                        this.get('listStyleType') || 'decimal';


                    // 直接修改 OL 的 CSS
                    this.addStyle({
                        'list-style-type': listType
                    });

                });


                // ======================================
                // Start 改變
                // ======================================

                this.on('change:listStart', () => {

                    const value = this.get('listStart');

                    // 没有输入时不要处理
                    if (value === undefined || value === null || value === '') {
                        return;
                    }

                    const start = parseInt(value, 10);

                    // 不是数字就不要处理
                    if (isNaN(start)) {
                        return;
                    }

                    // 确保至少从 1 开始
                    const finalStart = Math.max(1, start);

                    // 直接修改 HTML 的 start attribute
                    this.addAttributes({
                        start: finalStart
                    });

                    // 同步 Component Property
                    this.set(
                        'listStart',
                        String(finalStart),
                        {
                            avoidStore: true
                        }
                    );
                });

            }

        }

    });


    // ==========================================
    // 🌟 Variable 插入功能
    // ==========================================

    window.insertVariableFromSelect = function (select) {

        const variableKey = select.value;

        if (!variableKey) return;


        // ----------------------------------------
        // GrapesJS Canvas 真實 Document
        // ----------------------------------------

        const canvasDoc =
            window.editor.Canvas.getDocument();


        // ----------------------------------------
        // 當前選中的 Component
        // ----------------------------------------

        const selectedComp =
            window.editor.getSelected();


        // ----------------------------------------
        // 將焦點還給文本 Component
        // ----------------------------------------

        if (
            selectedComp &&
            selectedComp.view &&
            selectedComp.view.el
        ) {

            selectedComp.view.el.focus();
        }


        // ----------------------------------------
        // 取得目前文字選取範圍
        // ----------------------------------------

        const selection =
            canvasDoc.getSelection();


        if (
            selection &&
            selection.rangeCount > 0
        ) {

            const range =
                selection.getRangeAt(0);


            // --------------------------------------
            // 建立 Variable Span
            // --------------------------------------

            const span =
                canvasDoc.createElement('span');


            span.className =
                'gjs-variable-tag';


            span.setAttribute(
                'data-variable',
                variableKey
            );


            span.setAttribute(
                'contenteditable',
                'false'
            );


            // --------------------------------------
            // Inline Style
            // --------------------------------------

            span.style.cssText =
                'background-color: #e0f2fe !important;' +
                'color: #0284c7 !important;' +
                'padding: 2px 6px !important;' +
                'border-radius: 4px !important;' +
                'font-family: monospace !important;' +
                'font-size: 14px !important;' +
                'margin: 0 2px !important;' +
                'display: inline-block !important;' +
                'vertical-align: middle !important;' +
                'line-height: normal !important;';


            span.innerHTML =
                `{{ ${variableKey} }}`;


            // --------------------------------------
            // 建立 Zero Width Space
            // --------------------------------------

            const zws =
                canvasDoc.createTextNode('\u200B');


            // --------------------------------------
            // 插入
            // --------------------------------------

            range.deleteContents();

            range.insertNode(zws);

            range.insertNode(span);


            // --------------------------------------
            // 游標放到 ZWS 後面
            // --------------------------------------

            range.setStartAfter(zws);

            range.collapse(true);


            selection.removeAllRanges();

            selection.addRange(range);
        }


        // ----------------------------------------
        // 重置下拉框
        // ----------------------------------------

        select.value = '';
    };


    // ==========================================
    // 🧩 Rich Text Editor Variable Dropdown
    // ==========================================

    const rte =
        editor.RichTextEditor;


    rte.add('variables-dropdown', {

        icon: `

      <select
        onchange="window.insertVariableFromSelect(this)"

        style="
          margin: 0 4px;
          padding: 2px 4px;
          font-size: 12px;
          font-weight: 600;
          border-radius: 4px;
          border: 1px solid #cbd5e1;
          background: #f8fafc;
          color: #0f172a;
          cursor: pointer;
          outline: none;
          max-width: 160px;
        "
      >

        <option value="">
          + Insert Variable...
        </option>


        <optgroup label="🧑‍💼 Tenant Info">

          <option value="tenant_name">
            Tenant Name
          </option>

          <option value="tenant_ic">
            Tenant IC / Passport
          </option>

        </optgroup>


        <optgroup label="🏠 Owner & Property">

          <option value="owner_name">
            Owner Name
          </option>

          <option value="owner_ic">
            Owner IC
          </option>

          <option value="property_name">
            Property Name
          </option>

          <option value="property_type">
            Property Type
          </option>

          <option value="property_address">
            Full Address
          </option>

        </optgroup>


        <optgroup label="📄 Lease Details">

          <option value="start_date">
            Start Date
          </option>

          <option value="end_date">
            End Date
          </option>

          <option value="check_out_date">
            Check Out Date
          </option>

          <option value="rent_price">
            Monthly Rent Price
          </option>

        </optgroup>


        <optgroup label="💰 Deposits & Fees">

          <option value="total_deposit">
            Total Deposit
          </option>

          <option value="security_deposit">
            Security Deposit
          </option>

          <option value="utilities_deposit">
            Utilities Deposit
          </option>

          <option value="management_fee">
            Management Fee
          </option>

        </optgroup>

      </select>

    `

    });


    // ==========================================
    // Block 初始化
    // ==========================================

    editor.on('load', () => {

        updateBlocks('tos');

    });


    return editor;
}


// ==========================================
// Block Category 更新
// ==========================================

export function updateBlocks(category) {

    const bm =
        window.editor.BlockManager;


    const allBlocks =
        bm.getAll();


    const blocksToRemove = [];


    allBlocks.forEach(block => {

        const id =
            block.id ||
            block.get?.('id');


        const customIds = [

            'agreement-title',

            'text-block',

            'horizontal-line',

            'basic-table',

            'heading-2',

            'signature-block',

            'agreement-preamble',

            'notice-box',

            'numbered-clauses',

            'thick-divider',

            'double-divider',

            'page-break',

            'payment-clause',

            'var-financials',

            'var-property',

            'var-dates',

            'layout-2-columns',

            'layout-3-columns',

            'layout-logo-right',

            'bullet-list',

            'numbered-list'

        ];


        if (

            customIds.includes(id) ||

            id.startsWith('privacy-') ||

            id.startsWith('invoice-') ||

            id.startsWith('receipt-') ||

            id.startsWith('tos-')

        ) {

            blocksToRemove.push(id);

        }

    });


    blocksToRemove.forEach(id => {

        bm.remove(id);

    });


    const blocks =
        blockSets[category] || [];


    blocks.forEach(block => {

        if (!bm.get(block.id)) {

            bm.add(
                block.id,
                block
            );

        }

    });

}