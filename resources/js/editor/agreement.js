// resources/js/editor/agreement.js

// 🌟 魔法函數加強版：自帶最高權重 Inline Style，保證 <li> 無法洗掉它的顏色
const varStyle = `background-color: #e0f2fe !important; color: #0284c7 !important; padding: 2px 6px !important; border-radius: 4px !important; font-family: monospace !important; font-size: 14px !important; margin: 0 2px !important; display: inline-block !important; vertical-align: middle !important; line-height: normal !important;`;
const makeVar = (name) => `<span class="gjs-variable-tag" contenteditable="false" data-variable="${name}" style="${varStyle}">{{ ${name} }}</span>`;

const agreementBlocks = [
    // ==========================================
    // 1. Basic Elements (基礎排版元素)
    // ==========================================
    {
        id: 'agreement-title',
        label: 'Agreement Title',
        category: 'Basic Elements',
        content: `<h1>Agreement Title</h1>`
    },
    {
        id: 'heading-2',
        label: 'Section Heading',
        category: 'Basic Elements',
        content: `<h2 style="font-size: 20px; color: #2c3e50; border-bottom: 2px solid #eee; padding-bottom: 5px;">New Section</h2>`
    },
    {
        id: 'text-block',
        label: 'Text Paragraph',
        category: 'Basic Elements',
        content: `<p style="margin: 10px 0; line-height: 1.5; color: #333;">Double-click to edit this text paragraph.</p>`
    },
    {
        id: 'bullet-list',
        label: 'Bullet List ',
        category: 'Basic Elements',
        content: `
            <ul style="margin: 15px 0; padding-left: 20px; line-height: 1.6; color: #333;">
                <li style="margin-bottom: 5px;">Double-click to edit list item 1</li>
                <li style="margin-bottom: 5px;">Double-click to edit list item 2</li>
                <li style="margin-bottom: 5px;">Double-click to edit list item 3</li>
            </ul>
        `
    },
    {
        id: 'numbered-list',
        label: 'Numbered List ',
        category: 'Basic Elements',
        content: `
            <ol style="margin: 15px 0; padding-left: 20px; line-height: 1.6; color: #333;">
                <li style="margin-bottom: 5px;">Double-click to edit numbered item 1</li>
                <li style="margin-bottom: 5px;">Double-click to edit numbered item 2</li>
                <li style="margin-bottom: 5px;">Double-click to edit numbered item 3</li>
            </ol>
        `
    },
    {
        id: 'alphabetical-list',
        label: 'Alphabetical List',
        category: 'Basic Elements',
        content: `
        <ol style="list-style-type: upper-alpha; margin: 15px 0; padding-left: 20px; line-height: 1.6; color: #333;">
            <li style="margin-bottom: 5px;">Double-click to edit item A</li>
            <li style="margin-bottom: 5px;">Double-click to edit item B</li>
            <li style="margin-bottom: 5px;">Double-click to edit item C</li>
        </ol>
    `
    },
    {
        id: 'lower-alphabetical-list',
        label: 'Lower Alphabetical List',
        category: 'Basic Elements',
        content: `
        <ol style="list-style-type: lower-alpha; margin: 15px 0; padding-left: 20px; line-height: 1.6; color: #333;">
            <li style="margin-bottom: 5px;">Double-click to edit item a</li>
            <li style="margin-bottom: 5px;">Double-click to edit item b</li>
            <li style="margin-bottom: 5px;">Double-click to edit item c</li>
        </ol>
    `
    },
    {
        id: 'basic-table',
        label: 'Simple Table',
        category: 'Basic Elements',
        content: `
            <table style="width: 100%; border-collapse: collapse; margin: 20px 0;">
                <thead>
                    <tr style="background-color: #f2f2f2;">
                        <th style="border: 1px solid #ddd; padding: 8px;">Item</th>
                        <th style="border: 1px solid #ddd; padding: 8px;">Description</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td style="border: 1px solid #ddd; padding: 8px;">Sample</td>
                        <td style="border: 1px solid #ddd; padding: 8px;">Content goes here</td>
                    </tr>
                </tbody>
            </table>
        `
    },
    {
        id: 'thick-divider',
        label: 'Thick Divider ',
        category: 'Basic Elements',
        content: `<hr style="border: 0; border-top: 4px solid #0f172a; margin: 35px 0;">`
    },
    {
        id: 'double-divider',
        label: 'Double Divider ',
        category: 'Basic Elements',
        content: `<hr style="border: 0; border-top: 4px double #0f172a; margin: 35px 0;">`
    },
    {
        id: 'page-break',
        label: 'Page Break ',
        category: 'Basic Elements',
        content: `
        <div style="page-break-before: always; border-top: 2px dashed #cbd5e1; margin: 40px 0; position: relative; text-align: center;" class="pdf-page-break">
            <span style="background: #fff; padding: 0 10px; color: #94a3b8; font-size: 12px; font-family: monospace;">--- Page Break (PDF) ---</span>
        </div>
        `
    },

    // ==========================================
    // 2. Legal Elements (合約與法律條款)
    // ==========================================
    {
        id: 'agreement-preamble',
        label: 'Preamble',
        category: 'Legal Elements',
        content: `
            <p style="margin: 10px 0; line-height: 1.6; text-align: justify;">
                This Agreement is made and entered into on ${makeVar('start_date')}, by and between ${makeVar('owner_name')} (hereinafter referred to as the "Landlord/Owner"), and ${makeVar('tenant_name')} (hereinafter referred to as the "Tenant").
            </p>
        `
    },
    {
        id: 'payment-clause',
        label: 'Payment & Deposit Clause',
        category: 'Legal Elements',
        content: `
            <div style="margin: 15px 0; line-height: 1.8; text-align: justify;">
                <h3 style="color: #2c3e50; font-size: 16px; border-bottom: 1px solid #eee; padding-bottom: 5px;">Payment & Deposits</h3>
                <p>The Tenant agrees to pay a rental amount of <strong>RM ${makeVar('rent_price')}</strong>.</p>
                <p>Upon signing this agreement, the Tenant shall pay a Total Deposit of <strong>RM ${makeVar('total_deposit')}</strong>, which comprises of:</p>
                <ul style="padding-left: 20px;">
                    <li>Security Deposit: <strong>RM ${makeVar('security_deposit')}</strong></li>
                    <li>Utilities Deposit: <strong>RM ${makeVar('utilities_deposit')}</strong></li>
                </ul>
                <p><em>* Note: A Management Fee of <strong>RM ${makeVar('management_fee')}</strong> may apply.</em></p>
            </div>
        `
    },
    {
        id: 'numbered-clauses',
        label: 'Numbered Clauses',
        category: 'Legal Elements',
        content: `
            <ol style="margin: 15px 0; padding-left: 20px; line-height: 1.8; text-align: justify;">
                <li><strong>Obligations:</strong> The Tenant agrees to maintain the ${makeVar('property_type')} in good condition.</li>
                <li><strong>Payment Terms:</strong> All payments shall be made strictly on time.</li>
                <li><strong>Termination:</strong> This agreement will end on ${makeVar('end_date')}.</li>
            </ol>
        `
    },
    {
        id: 'notice-box',
        label: 'Notice Box',
        category: 'Legal Elements',
        content: `
            <div style="background-color: #fff8e1; border-left: 4px solid #ffc107; padding: 15px; margin: 20px 0;">
                <h4 style="margin: 0 0 10px 0; color: #b28900;">IMPORTANT NOTICE</h4>
                <p style="margin: 0; font-size: 14px; line-height: 1.5;">
                    Double-click to edit this important notice or disclaimer. Ensure all legal liabilities are clearly stated here.
                </p>
            </div>
        `
    },
    {
        id: 'signature-block',
        label: 'Signatures',
        category: 'Legal Elements',
        content: `
            <table style="width: 100%; margin-top: 40px; border-collapse: collapse;">
                <tr>
                    <td style="width: 50%; padding-right: 20px; vertical-align: bottom;">
                        <p style="margin: 0 0 10px 0; font-weight: bold;">Landlord / Owner :</p>
                        <hr style="border: 0; border-top: 1px solid #000; margin: 30px 0 10px 0;">
                        <p style="margin: 5px 0; font-size: 14px;">Name: ${makeVar('owner_name')}</p>
                        <p style="margin: 5px 0; font-size: 14px;">IC No: ${makeVar('owner_ic')}</p>
                        <p style="margin: 5px 0; font-size: 14px;">Date: _________________</p>
                    </td>
                    <td style="width: 50%; padding-left: 20px; vertical-align: bottom;">
                        <p style="margin: 0 0 10px 0; font-weight: bold;">Tenant :</p>
                        <hr style="border: 0; border-top: 1px solid #000; margin: 30px 0 10px 0;">
                        <p style="margin: 5px 0; font-size: 14px;">Name: ${makeVar('tenant_name')}</p>
                        <p style="margin: 5px 0; font-size: 14px;">IC No: ${makeVar('tenant_ic')}</p>
                        <p style="margin: 5px 0; font-size: 14px;">Date: _________________</p>
                    </td>
                </tr>
            </table>
        `
    },

    // ==========================================
    // 3. Dynamic Variables (讓使用者可以直接拉變數)
    // ==========================================
    {
        id: 'var-financials',
        label: '💰 Financial Variables',
        category: 'Dynamic Variables',
        content: `
            <div style="padding: 10px; background-color: #f8fafc; border: 1px dashed #cbd5e1; font-family: monospace; font-size: 14px; margin: 10px 0;">
                Rent: RM ${makeVar('rent_price')}<br>
                Total Deposit: RM ${makeVar('total_deposit')}<br>
                Security Deposit: RM ${makeVar('security_deposit')}<br>
                Utilities Deposit: RM ${makeVar('utilities_deposit')}<br>
                Management Fee: RM ${makeVar('management_fee')}
            </div>
        `
    },
    {
        id: 'var-property',
        label: '🏠 Property Variables',
        category: 'Dynamic Variables',
        content: `
            <div style="padding: 10px; background-color: #f8fafc; border: 1px dashed #cbd5e1; font-family: monospace; font-size: 14px; margin: 10px 0;">
                Property Name: ${makeVar('property_name')}<br>
                Property Type: ${makeVar('property_type')}<br>
                Address: ${makeVar('property_address')}
            </div>
        `
    },
    {
        id: 'var-dates',
        label: '📅 Date Variables',
        category: 'Dynamic Variables',
        content: `
            <div style="padding: 10px; background-color: #f8fafc; border: 1px dashed #cbd5e1; font-family: monospace; font-size: 14px; margin: 10px 0;">
                Start Date: ${makeVar('start_date')}<br>
                End Date: ${makeVar('end_date')}
            </div>
        `
    },

    // ==========================================
    // 4. Layout Elements (排版容器 - PDF 安全版)
    // ==========================================
    {
        id: 'layout-2-columns',
        label: '2 Columns ',
        category: 'Layout',
        content: `
            <table style="width: 100%; border-collapse: collapse; margin: 15px 0;">
                <tr>
                    <td style="width: 50%; padding: 10px; vertical-align: top; border: 1px dashed #cbd5e1; min-height: 50px;">
                        <div style="color: #94a3b8; font-size: 12px; text-align: center; padding: 20px 0;">[ Drop Content Here ]</div>
                    </td>
                    <td style="width: 50%; padding: 10px; vertical-align: top; border: 1px dashed #cbd5e1; min-height: 50px;">
                        <div style="color: #94a3b8; font-size: 12px; text-align: center; padding: 20px 0;">[ Drop Content Here ]</div>
                    </td>
                </tr>
            </table>
        `
    },
    {
        id: 'layout-3-columns',
        label: '3 Columns ',
        category: 'Layout',
        content: `
            <table style="width: 100%; border-collapse: collapse; margin: 15px 0;">
                <tr>
                    <td style="width: 33.33%; padding: 10px; vertical-align: top; border: 1px dashed #cbd5e1; min-height: 50px;">
                        <div style="color: #94a3b8; font-size: 12px; text-align: center; padding: 20px 0;">[ Column 1 ]</div>
                    </td>
                    <td style="width: 33.33%; padding: 10px; vertical-align: top; border: 1px dashed #cbd5e1; min-height: 50px;">
                        <div style="color: #94a3b8; font-size: 12px; text-align: center; padding: 20px 0;">[ Column 2 ]</div>
                    </td>
                    <td style="width: 33.33%; padding: 10px; vertical-align: top; border: 1px dashed #cbd5e1; min-height: 50px;">
                        <div style="color: #94a3b8; font-size: 12px; text-align: center; padding: 20px 0;">[ Column 3 ]</div>
                    </td>
                </tr>
            </table>
        `
    },
    {
        id: 'layout-logo-right',
        label: 'Header ',
        category: 'Layout',
        content: `
            <table style="width: 100%; border-collapse: collapse; margin: 15px 0;">
                <tr>
                    <td style="width: 70%; padding: 10px; vertical-align: middle; border: 1px dashed #cbd5e1;">
                        <h2 style="margin: 0; color: #1e293b;">${makeVar('property_name')}</h2>
                        <p style="margin: 5px 0 0 0; color: #64748b; font-size: 14px;">${makeVar('property_address')}</p>
                    </td>
                    <td style="width: 30%; padding: 10px; vertical-align: middle; text-align: right; border: 1px dashed #cbd5e1;">
                        <div style="background-color: #f1f5f9; padding: 20px; display: inline-block; color: #94a3b8; font-size: 12px;">
                            [ Drop Logo Here ]
                        </div>
                    </td>
                </tr>
            </table>
        `
    }
];

export default agreementBlocks;