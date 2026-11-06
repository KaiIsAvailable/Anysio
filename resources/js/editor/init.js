import 'grapesjs/dist/css/grapes.min.css';
import grapesjs from 'grapesjs';
import gjsPresetWebpage from 'grapesjs-preset-webpage';

import agreementBlocks from './agreement';
import privacyBlocks from './privacy';
import invoiceBlocks from './invoice';
import receiptBlocks from './receipt';
import tosBlocks from './term_of_service';
import refundBlocks from './refund';
import { enableSessionDraft } from './session-draft';
import { documentBaseCss, documentHtml, printDocument, resizeDocumentPaper } from './document-layout';

window.documentLayout = { documentHtml, printDocument, resizeDocumentPaper };

window.enableDocumentTemplateSessionDraft = enableSessionDraft;

const blockSets = {
    agreement: agreementBlocks,
    privacy: privacyBlocks,
    invoice: invoiceBlocks,
    receipt: receiptBlocks,
    tos: tosBlocks,
    refund: refundBlocks
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

        // Typography changes should target the selected instance, not a shared
        // class such as gjs-variable-tag used by every variable in the document.
        selectorManager: {
            componentFirst: true
        },

        traitManager: {
            appendTo: '#traits-container'
        },

        canvas: {
            scrollableCanvas: true
        },

        // Inline iframe styles belong in canvasCss; canvas.styles expects stylesheet URLs.
        canvasCss: [
                'html { overflow: hidden !important; }',
                documentBaseCss,
                'body[data-gjs-type="wrapper"] { height: auto !important; min-height: 297mm !important; }',
            ].join('\n'),

        storageManager: false
    });

    window.editor = editor;

    // Variables are selectable/styleable tokens, never rich-text fields.
    // Recognize saved spans as well as newly inserted variables.
    editor.DomComponents.addType('template-variable', {
        isComponent: element => element.tagName === 'SPAN' && element.hasAttribute('data-variable')
            ? { type: 'template-variable' }
            : false,
        model: {
            defaults: {
                tagName: 'span',
                textable: true,
                editable: false,
                droppable: false,
                stylable: true,
                attributes: { contenteditable: 'false' }
            },
            init() {
                this.addAttributes({ contenteditable: 'false' });
                const style = { ...this.getStyle() };
                // Remove the old token badge without discarding user typography.
                const legacyBadge = /#e0f2fe|#0284c7/i.test(
                    `${style['background-color'] || ''} ${style.color || ''}`
                );
                delete style['background-color'];
                style.color = '#000000';
                if (legacyBadge) {
                    for (const property of ['padding', 'border-radius', 'margin', 'display', 'vertical-align', 'line-height']) {
                        delete style[property];
                    }
                    if (/^monospace\s*!important$/i.test(style['font-family'] || '')) {
                        delete style['font-family'];
                    }
                    if (/^14px\s*!important$/i.test(style['font-size'] || '')) {
                        delete style['font-size'];
                    }
                }
                this.setStyle(style);
            }
        }
    });

    // Show list traits when the user selects a list item or text inside a list,
    // while leaving Typography targeted at the component they actually clicked.
    const showListTraits = ({ component }) => {
            let list = component;
            while (list && !['ol', 'ul'].includes(list.get('tagName')?.toLowerCase())) {
                list = list.parent();
            }
            if (!list) return;
            if (!list.getTrait('listStyleType')) {
                // Session projects may restore an OL as a generic component.
                const model = editor.DomComponents.getType(
                    list.get('tagName').toLowerCase() === 'ul' ? 'custom-unordered-list' : 'custom-ordered-list'
                ).model;
                const defaults = typeof model.prototype.defaults === 'function'
                    ? model.prototype.defaults.call(list) : model.prototype.defaults;
                list.addTrait(defaults.traits);
                model.prototype.init.call(list);
            }
            if (editor.TraitManager.getComponent() !== list) {
                editor.TraitManager.select(list);
            }
    };
    editor.on('trait:select', showListTraits);

    // Let the outer canvas scroll through the complete document, including edits
    // and images that load after the template has been inserted.
    let paperUpdate;

    const resizePaper = () => {
        cancelAnimationFrame(paperUpdate);
        paperUpdate = requestAnimationFrame(() => {
            const frame = editor.Canvas.getFrame();
            if (!frame) return;
            const canvas = editor.Canvas.getElement();
            const width = 210 * 96 / 25.4;
            // Keep the frame model and its visible paper in the same coordinate
            // system so drag targets and insertion markers include canvas scroll.
            const layout = {
                width: `${width}px`,
                height: 'auto',
                minHeight: Math.ceil(297 * 96 / 25.4),
                x: Math.max(24, (canvas.clientWidth - width) / 2),
                y: 24
            };
            const changes = Object.fromEntries(
                Object.entries(layout).filter(([key, value]) => frame.get(key) !== value)
            );
            if (!Object.keys(changes).length) return;

            frame.set(changes, { noUndo: true });
            editor.Canvas.refresh({ spots: true });
        });
    };

    // GrapesJS's native auto-height observes content without repeatedly changing
    // the frame model (which stops/restarts its selection command).
    editor.on('canvas:frame:load:body', resizePaper);
    editor.on('project:load', resizePaper);

    // Editor overlays can extend the browser's scrollable overflow beyond the
    // paper. Bound navigation to the actual frame, not that overlay overflow.
    const scrollCanvas = editor.Canvas.getElement();
    let paperBoundsObserver;
    const limitPaperScroll = () => {
        const paper = editor.Canvas.getFrameEl()?.parentElement;
        if (!paper) return;
        const paperBottom = paper.getBoundingClientRect().bottom
            - scrollCanvas.getBoundingClientRect().top + scrollCanvas.scrollTop;
        const maxScroll = Math.max(0, Math.ceil(paperBottom) + 24 - scrollCanvas.clientHeight);
        if (scrollCanvas.scrollTop > maxScroll) scrollCanvas.scrollTop = maxScroll;
    };
    const observePaperBounds = () => {
        paperBoundsObserver?.disconnect();
        const paper = editor.Canvas.getFrameEl()?.parentElement;
        if (!paper) return;
        paperBoundsObserver = new ResizeObserver(limitPaperScroll);
        paperBoundsObserver.observe(paper);
        limitPaperScroll();
    };
    scrollCanvas.addEventListener('scroll', limitPaperScroll, { passive: true });
    editor.on('canvas:frame:load:body', observePaperBounds);

    const canvasObserver = new ResizeObserver(resizePaper);
    canvasObserver.observe(editor.Canvas.getElement());
    editor.on('destroy', () => {
        paperBoundsObserver?.disconnect();
        scrollCanvas.removeEventListener('scroll', limitPaperScroll);
        canvasObserver.disconnect();
        cancelAnimationFrame(paperUpdate);
    });

    const fontSizeProperty = editor.StyleManager.getProperty('typography', 'font-size');
    if (fontSizeProperty) {
        const units = fontSizeProperty.get('units') || [];
        fontSizeProperty.set({
            units: ['pt', ...units.filter(unit => unit !== 'pt')],
            unit: 'pt',
            defaults: '12pt'
        });
    }

    const fontFamilyProperty = editor.StyleManager.getProperty('typography', 'font-family');
    const fontFamilyOptions = fontFamilyProperty?.getOptions?.() || [];

    const additionalFonts = [
        { id: 'Calibri, sans-serif', label: 'Calibri' },
        { id: 'Century, serif', label: 'Century' }
    ];

    if (fontFamilyProperty) {
        fontFamilyProperty.set('options', [
            ...fontFamilyOptions,
            ...additionalFonts.filter(font => !fontFamilyOptions.some(option => option.id === font.id))
        ]);
    }


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

    editor.DomComponents.addType('custom-unordered-list', {
        isComponent: element => element.tagName === 'UL' ? { type: 'custom-unordered-list' } : false,
        model: {
            defaults: {
                tagName: 'ul',
                style: { 'padding-left': '20px', 'list-style-type': 'disc' },
                traits: [{
                    type: 'select',
                    name: 'listStyleType',
                    label: 'Bullet Style',
                    changeProp: true,
                    options: [
                        { id: 'disc', name: 'Solid circle' },
                        { id: 'circle', name: 'Hollow circle' },
                        { id: 'square', name: 'Square' },
                        { id: 'none', name: 'No bullet' }
                    ]
                }]
            },
            init() {
                this.set('listStyleType', this.getStyle()['list-style-type'] || 'disc');
                this.on('change:listStyleType', () => {
                    this.addStyle({ 'list-style-type': this.get('listStyleType') || 'disc' });
                });
                editor.Css.setRule(`#${this.getId()} > li::marker`, { 'font-weight': 'normal' });
            }
        }
    });

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
                    'font-weight': 'normal',
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
                                id: 'decimal-section',
                                name: '1.1, 1.2, 1.3'
                            },
                            {
                                id: 'lower-alpha',
                                name: 'a, b, c'
                            },
                            {
                                id: 'lower-alpha-parentheses',
                                name: '(a), (b), (c)'
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
                                id: 'lower-roman-parentheses',
                                name: '(i), (ii), (iii)'
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

                const savedMarker = this.getAttributes()['data-list-marker'];
                let currentListStyle = savedMarker === 'decimal-section'
                    ? 'decimal-section'
                    : savedMarker === 'parentheses'
                        ? `${currentStyle['list-style-type'] === 'lower-roman' ? 'lower-roman' : 'lower-alpha'}-parentheses`
                        : currentStyle['list-style-type'] || 'decimal';


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

                const applyListStyle = (listType) => {
                    const parentheses = ['lower-alpha-parentheses', 'lower-roman-parentheses'].includes(listType);
                    const section = listType === 'decimal-section';
                    const counterType = section ? 'decimal' : parentheses ? listType.replace('-parentheses', '') : listType;
                    this.addStyle({ 'list-style-type': counterType });
                    const markerSelector = `#${this.getId()} > li::marker`;
                    const markerStyle = { 'font-weight': 'normal' };
                    if (section) {
                        this.addAttributes({ 'data-list-marker': 'decimal-section' });
                        markerStyle.content = '"1." counter(list-item, decimal) " "';
                    } else if (parentheses) {
                        this.addAttributes({ 'data-list-marker': 'parentheses' });
                        // Store the marker rule in the template CSS so it also
                        // renders in preview/print and respects native OL start.
                        markerStyle.content = `"(" counter(list-item, ${counterType}) ") "`;
                    } else if (this.getAttributes()['data-list-marker']) {
                        this.removeAttributes('data-list-marker');
                        markerStyle.content = 'normal';
                    }
                    editor.Css.setRule(markerSelector, markerStyle);
                };
                applyListStyle(currentListStyle);


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
                    applyListStyle(listType);

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

            const logoVariables = [
                'user_company_logo',
                'tenant_company_logo',
                'owner_company_logo'
            ];

            if (logoVariables.includes(variableKey)) {
                const image = canvasDoc.createElement('img');

                image.className = 'gjs-variable-tag';
                image.setAttribute('data-variable', variableKey);
                image.setAttribute('contenteditable', 'false');
                image.setAttribute('alt', variableKey.replace(/_/g, ' '));
                image.setAttribute(
                    'src',
                    window.documentTemplateLogoPlaceholder || '/image/anysio_logo.png'
                );
                image.style.cssText =
                    'max-width: 180px;' +
                    'max-height: 80px;' +
                    'object-fit: contain;' +
                    'display: inline-block;' +
                    'vertical-align: middle;';

                const zws = canvasDoc.createTextNode('\u200B');

                range.deleteContents();
                range.insertNode(zws);
                range.insertNode(image);
                range.setStartAfter(zws);
                range.collapse(true);

                selection.removeAllRanges();
                selection.addRange(range);
                select.value = '';

                return;
            }

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
            span.setAttribute('data-gjs-type', 'template-variable');


            span.setAttribute(
                'contenteditable',
                'false'
            );


            // --------------------------------------
            // Inline Style
            // --------------------------------------

            // Inherit font family and size from the surrounding text. Typography
            // controls can still set an explicit size on the selected variable.
            span.style.cssText = 'color: #000000;';


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

        <optgroup label="User">
          <option value="user_name">User Name</option>
          <option value="user_email">User Email</option>
          <option value="user_phone">User Phone</option>
          <option value="user_company_logo">User Company Logo</option>
          <option value="user_company_name">User Company Name</option>
          <option value="user_old_ssm_number">User Old SSM Number</option>
          <option value="user_new_ssm_number">User New SSM Number</option>
          <option value="user_company_address">User Company Address</option>
          <option value="user_company_phone_number">User Company Phone Number</option>
          <option value="user_company_email">User Company Email</option>
          <option value="user_bank_name">User Bank Name</option>
          <option value="user_bank_code">User Bank Code</option>
          <option value="user_account_holder_name">User Account Holder Name</option>
          <option value="user_account_number">User Account Number</option>
        </optgroup>

        <optgroup label="Tenant Info">
          <option value="tenant_name">Tenant Name</option>
          <option value="tenant_ic">Tenant IC / Passport / SSM New Number</option>
          <option value="tenant_email">Tenant Email</option>
          <option value="tenant_phone">Tenant Phone</option>
          <option value="tenant_company_logo">Tenant Company Logo</option>
          <option value="tenant_company_name">Tenant Company Name</option>
          <option value="tenant_old_ssm_number">Tenant Old SSM Number</option>
          <option value="tenant_new_ssm_number">Tenant New SSM Number</option>
          <option value="tenant_company_address">Tenant Company Address</option>
          <option value="tenant_company_phone_number">Tenant Company Phone Number</option>
          <option value="tenant_company_email">Tenant Company Email</option>
          <option value="tenant_bank_name">Tenant Bank Name</option>
          <option value="tenant_bank_code">Tenant Bank Code</option>
          <option value="tenant_account_holder_name">Tenant Account Holder Name</option>
          <option value="tenant_account_number">Tenant Account Number</option>
        </optgroup>

        <optgroup label="Owner Info">
          <option value="owner_name">Owner Name</option>
          <option value="owner_ic">Owner IC / Passport / SSM New Number</option>
          <option value="owner_email">Owner Email</option>
          <option value="owner_phone">Owner Phone</option>
          <option value="owner_company_logo">Owner Company Logo</option>
          <option value="owner_company_name">Owner Company Name</option>
          <option value="owner_old_ssm_number">Owner Old SSM Number</option>
          <option value="owner_new_ssm_number">Owner New SSM Number</option>
          <option value="owner_company_address">Owner Company Address</option>
          <option value="owner_company_phone_number">Owner Company Phone Number</option>
          <option value="owner_company_email">Owner Company Email</option>
          <option value="owner_bank_name">Owner Bank Name</option>
          <option value="owner_bank_code">Owner Bank Code</option>
          <option value="owner_account_holder_name">Owner Account Holder Name</option>
          <option value="owner_account_number">Owner Account Number</option>
        </optgroup>


        <optgroup label="Lease Details">
          <option value="property_name">Property Name</option>
          <option value="property_type">Property Type</option>
          <option value="property_address">Full Address</option>
          <option value="start_date">Start Date</option>
          <option value="start_date_formatted">Start Date Formatted</option>
          <option value="end_date">End Date</option>
          <option value="lease_period">Period</option>
          <option value="check_out_status">Check Out Status</option>
          <option value="check_out_date">Check Out Date</option>
          <option value="rent_price">Monthly Rent Price</option>
        </optgroup>


        <optgroup label="Deposits & Fees">
          <option value="total_deposit">Total Deposit</option>
          <option value="deposit_receipt_no">Deposit Receipt Number</option>
          <option value="total_deductions">Total Deductions</option>
          <option value="security_deposit">Security Deposit</option>
          <option value="utilities_deposit">Utilities Deposit</option>
          <option value="management_fee">Management Fee</option>
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

    const categories = bm.getCategories();
    const categoryNav = document.getElementById('blocks-category-nav');
    const dropdownLayer = document.getElementById('blocks-dropdown-layer');

    bm.render();

    categories.forEach(blockCategory => {
        blockCategory.set('open', false);
    });

    const renderCategoryMenus = (attempt = 0) => {
        if (!categoryNav || !dropdownLayer) {
            return;
        }

        categoryNav.innerHTML = '';
        dropdownLayer.innerHTML = '';
        let categoryBlocksPending = false;

        categories.forEach(blockCategory => {
            const wrapper = document.createElement('div');
            const button = document.createElement('button');
            const menu = document.createElement('div');
            const categoryId = blockCategory.getId();
            wrapper.className = 'block-category-dropdown';
            wrapper.className = 'block-category-dropdown';
            button.type = 'button';
            button.textContent = blockCategory.getLabel();
            button.dataset.categoryId = categoryId;
            menu.className = 'block-category-menu';

            const categoryElement = blockCategory.view?.el;
            const categoryBlocks = categoryElement?.querySelector('.gjs-blocks-c');

            if (categoryBlocks) {
                Array.from(categoryBlocks.children).forEach(blockElement => {
                    menu.appendChild(blockElement);
                });
            } else {
                categoryBlocksPending = true;
            }

            button.addEventListener('click', () => {
                dropdownLayer.querySelectorAll('.block-category-menu').forEach(categoryMenu => {
                    categoryMenu.classList.toggle('is-open', categoryMenu === menu && !menu.classList.contains('is-open'));
                });
                categoryNav.querySelectorAll('button').forEach(categoryButton => {
                    categoryButton.classList.toggle('is-active', categoryButton === button && menu.classList.contains('is-open'));
                });
            });

            wrapper.appendChild(button);
            categoryNav.appendChild(wrapper);
            dropdownLayer.appendChild(menu);
        });

        if (categoryBlocksPending && attempt < 5) {
            requestAnimationFrame(() => renderCategoryMenus(attempt + 1));
        }
    };

    renderCategoryMenus();

}
