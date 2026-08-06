// resources/js/editor/invoice.js

// 🌟 魔法函數：讓發票的變數也統一穿上漂亮的藍色 UI 標籤外衣
const varStyle = `background-color: #e0f2fe !important; color: #0284c7 !important; padding: 2px 6px !important; border-radius: 4px !important; font-family: monospace !important; font-size: 14px !important; margin: 0 2px !important; display: inline-block !important; vertical-align: middle !important; line-height: normal !important;`;
const makeVar = (name) => `<span class="gjs-variable-tag" contenteditable="false" data-variable="${name}" style="${varStyle}">{{ ${name} }}</span>`;

const invoiceBlocks = [
    // 1. 发票顶部 (带 Logo 和大标题)
    {
        id: 'invoice-header',
        label: 'Invoice Header',
        category: 'Invoice Elements',
        content: `
            <table style="width: 100%; border-collapse: collapse; margin-bottom: 30px;">
                <tr>
                    <td style="width: 50%; vertical-align: top;">
                        <div style="width: 150px; height: 60px; background-color: #f1f5f9; color: #94a3b8; display: flex; align-items: center; justify-content: center; font-size: 12px; border: 1px dashed #cbd5e1;" data-gjs-type="text">[ Logo Here ]</div>
                    </td>
                    <td style="width: 50%; vertical-align: top; text-align: right;">
                        <h1 style="margin: 0; color: #0f172a; font-size: 36px; text-transform: uppercase; letter-spacing: 2px;" data-gjs-type="text">INVOICE</h1>
                        <p style="margin: 5px 0 0 0; color: #64748b; font-size: 14px;" data-gjs-type="text"># ${makeVar('invoice_number')}</p>
                    </td>
                </tr>
            </table>
        `
    },

    // 2. 收付款方信息 (左右两列)
    {
        id: 'invoice-bill-to',
        label: 'Bill To / From ',
        category: 'Invoice Elements',
        content: `
            <table style="width: 100%; border-collapse: collapse; margin-bottom: 30px;">
                <tr>
                    <td style="width: 50%; vertical-align: top; padding-right: 20px;">
                        <h4 style="margin: 0 0 10px 0; color: #64748b; font-size: 12px; text-transform: uppercase;" data-gjs-type="text">Billed To:</h4>
                        <p style="margin: 0 0 5px 0; font-weight: bold; color: #0f172a; font-size: 16px;" data-gjs-type="text">${makeVar('tenant_name')}</p>
                        <p style="margin: 0; color: #475569; font-size: 14px; line-height: 1.5;" data-gjs-type="text">${makeVar('property_address')}</p>
                    </td>
                    <td style="width: 50%; vertical-align: top; padding-left: 20px;">
                        <h4 style="margin: 0 0 10px 0; color: #64748b; font-size: 12px; text-transform: uppercase;" data-gjs-type="text">Pay To:</h4>
                        <p style="margin: 0 0 5px 0; font-weight: bold; color: #0f172a; font-size: 16px;" data-gjs-type="text">${makeVar('owner_name')}</p>
                        <table style="width: 100%; font-size: 14px; color: #475569;">
                            <tr><td style="padding: 3px 0;" data-gjs-type="text"><strong>Date:</strong></td><td style="text-align: right;" data-gjs-type="text">${makeVar('issue_date')}</td></tr>
                            <tr><td style="padding: 3px 0;" data-gjs-type="text"><strong>Due Date:</strong></td><td style="text-align: right; color: #ef4444; font-weight: bold;" data-gjs-type="text">${makeVar('due_date')}</td></tr>
                        </table>
                    </td>
                </tr>
            </table>
        `
    },

    // 3. 核心收费明细表 (動態版)
    {
        id: 'invoice-items-table',
        label: 'Dynamic Items Table ',
        category: 'Invoice Elements',
        content: `
            <table style="width: 100%; border-collapse: collapse; margin-bottom: 20px; font-size: 14px;">
                <thead>
                    <tr style="background-color: #f8fafc; border-bottom: 2px solid #cbd5e1;">
                        <th style="padding: 12px 15px; text-align: left; color: #334155;">Description</th>
                        <th style="padding: 12px 15px; text-align: center; color: #334155; width: 15%;">Qty</th>
                        <th style="padding: 12px 15px; text-align: right; color: #334155; width: 20%;">Unit Price</th>
                        <th style="padding: 12px 15px; text-align: right; color: #334155; width: 20%;">Amount</th>
                    </tr>
                </thead>
                <tbody data-gjs-type="text">
                    ${makeVar('dynamic_invoice_items')}
                </tbody>
            </table>
        `
    },

    // 4. 总计金额区
    {
        id: 'invoice-totals',
        label: 'Totals ',
        category: 'Invoice Elements',
        content: `
            <table style="width: 50%; border-collapse: collapse; margin-left: auto; margin-bottom: 40px; font-size: 14px;">
                <tr>
                    <td style="padding: 8px 15px; text-align: right; color: #475569;" data-gjs-type="text">Subtotal:</td>
                    <td style="padding: 8px 15px; text-align: right; color: #0f172a; width: 40%;" data-gjs-type="text">RM ${makeVar('total_amount')}</td>
                </tr>
                <tr>
                    <td style="padding: 8px 15px; text-align: right; color: #475569; border-bottom: 1px solid #e2e8f0;" data-gjs-type="text">Tax (0%):</td>
                    <td style="padding: 8px 15px; text-align: right; color: #0f172a; width: 40%; border-bottom: 1px solid #e2e8f0;" data-gjs-type="text">RM 0.00</td>
                </tr>
                <tr>
                    <td style="padding: 15px; text-align: right; font-weight: bold; font-size: 18px; color: #0f172a;" data-gjs-type="text">Total Due:</td>
                    <td style="padding: 15px; text-align: right; font-weight: bold; font-size: 18px; color: #0f172a; background-color: #f1f5f9;" data-gjs-type="text">RM ${makeVar('total_amount')}</td>
                </tr>
            </table>
        `
    },

    // 5. 支付方式 / 银行信息
    {
        id: 'invoice-payment-info',
        label: 'Payment Info ',
        category: 'Invoice Elements',
        content: `
            <div style="background-color: #f8fafc; border: 1px solid #e2e8f0; border-left: 4px solid #3b82f6; padding: 15px; margin-bottom: 20px;">
                <h4 style="margin: 0 0 10px 0; color: #0f172a; font-size: 14px;" data-gjs-type="text">Payment Instructions</h4>
                <p style="margin: 0 0 5px 0; font-size: 13px; color: #475569;" data-gjs-type="text">Please make the payment via Bank Transfer to:</p>
                <ul style="margin: 0; padding-left: 20px; font-size: 13px; color: #0f172a; font-weight: 500;">
                    <li data-gjs-type="text">Bank: <strong>Maybank</strong></li>
                    <li data-gjs-type="text">Account Name: <strong>[Company/Owner Name]</strong></li>
                    <li data-gjs-type="text">Account No: <strong>[1234567890]</strong></li>
                </ul>
            </div>
        `
    },

    // 6. 底部附言/条款
    {
        id: 'invoice-notes',
        label: 'Terms & Notes ',
        category: 'Invoice Elements',
        content: `
            <p style="font-size: 12px; color: #64748b; text-align: center; border-top: 1px solid #e2e8f0; padding-top: 20px; margin-top: 40px;" data-gjs-type="text">
                Payment is due within 7 days. Late payments may be subject to a 5% late fee penalty. <br>
                Thank you for your business!
            </p>
        `
    },
    
    // 7. 公司详情头部
    {
        id: 'invoice-company-details',
        label: 'Company & Invoice Info',
        category: 'Invoice Elements',
        content: `
            <table style="width: 100%; border-collapse: collapse; margin-bottom: 30px; font-size: 14px; color: #334155;">
                <tr>
                    <td style="width: 50%; vertical-align: top; line-height: 1.6;">
                        <div data-gjs-type="text">
                            <strong style="color: #0f172a; font-size: 16px;">[Your Company Name]</strong><br>
                            [Street Address]<br>
                            [City, State, Zip Code]<br>
                            Phone: [Phone Number]<br>
                            Email: [Email Address]<br>
                            Website: [Company Website]
                        </div>
                    </td>
                    
                    <td style="width: 50%; vertical-align: top;">
                        <table style="width: 100%; border-collapse: collapse; text-align: right;">
                            <tr>
                                <td style="padding-bottom: 8px; font-weight: bold; color: #64748b; width: 60%;"><div data-gjs-type="text">Date:</div></td>
                                <td style="padding-bottom: 8px; color: #0f172a; width: 40%;"><div data-gjs-type="text">${makeVar('issue_date')}</div></td>
                            </tr>
                            <tr>
                                <td style="padding-bottom: 8px; font-weight: bold; color: #64748b;"><div data-gjs-type="text">Invoice No:</div></td>
                                <td style="padding-bottom: 8px; color: #0f172a;"><div data-gjs-type="text">${makeVar('invoice_number')}</div></td>
                            </tr>
                            <tr>
                                <td style="padding-bottom: 8px; font-weight: bold; color: #64748b;"><div data-gjs-type="text">Due Date:</div></td>
                                <td style="padding-bottom: 8px; color: #0f172a; font-weight: bold;"><div data-gjs-type="text">${makeVar('due_date')}</div></td>
                            </tr>
                        </table>
                    </td>
                </tr>
            </table>
        `
    },
];

export default invoiceBlocks;