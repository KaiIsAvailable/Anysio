// resources/js/editor/refund.js

// 🌟 魔法函數加強版：自帶最高權重 Inline Style，保證 <li> 無法洗掉它的顏色
const varStyle = `background-color: #e0f2fe !important; color: #0284c7 !important; padding: 2px 6px !important; border-radius: 4px !important; font-family: monospace !important; font-size: 14px !important; margin: 0 2px !important; display: inline-block !important; vertical-align: middle !important; line-height: normal !important;`;
const makeVar = (name) => `<span class="gjs-variable-tag" contenteditable="false" data-variable="${name}" style="${varStyle}">{{ ${name} }}</span>`;

const refundAgreementBlocks = [
    // ==========================================
    // 1. Refund Basic Elements (基礎退款排版)
    // ==========================================
    {
        id: 'refund-title',
        label: 'Refund Settlement Title',
        category: 'Refund Basic Elements',
        content: `<h1 style="font-size: 24px; color: #0f172a; text-align: center; margin-bottom: 20px;">TENANCY DEPOSIT REFUND SETTLEMENT STATEMENT</h1>`
    },
    {
        id: 'refund-preamble',
        label: 'Refund Preamble',
        category: 'Refund Basic Elements',
        content: `<p style="margin: 10px 0; line-height: 1.6; text-align: justify;">
            This Refund Settlement Statement is issued on ${makeVar('checkout_date')} with respect to the tenancy agreement for ${makeVar('property_name')} between ${makeVar('owner_name')} ("Landlord") and ${makeVar('tenant_name')} ("Tenant").
        </p>`
    },

    // ==========================================
    // 2. Refund Calculation Elements (退款計算與扣除額表格)
    // ==========================================
    {
        id: 'refund-summary-table',
        label: 'Refund Calculation Table',
        category: 'Refund Financials',
        content: `
            <table style="width: 100%; border-collapse: collapse; margin: 20px 0; font-family: serif; font-size: 14px;">
                <thead>
                    <tr style="background-color: #f8fafc;">
                        <th style="border: 1px solid #cbd5e1; padding: 10px; text-align: left;" data-gjs-type="text">Description</th>
                        <th style="border: 1px solid #cbd5e1; padding: 10px; text-align: right; width: 30%;" data-gjs-type="text">Amount (RM)</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td style="border: 1px solid #cbd5e1; padding: 10px;">Total Deposit Collected (Security & Utilities)</td>
                        <td style="border: 1px solid #cbd5e1; padding: 10px; text-align: right; font-weight: bold;">RM ${makeVar('total_deposit_collected')}</td>
                    </tr>
                    <tr>
                        <td style="border: 1px solid #cbd5e1; padding: 10px;">Less: Auto-Detected Unpaid Invoices / Utilities</td>
                        <td style="border: 1px solid #cbd5e1; padding: 10px; text-align: right; color: #dc2626;">- RM ${makeVar('auto_deductions_total')}</td>
                    </tr>
                    <tr>
                        <td style="border: 1px solid #cbd5e1; padding: 10px;">Less: Custom Deductions & Damages Charges</td>
                        <td style="border: 1px solid #cbd5e1; padding: 10px; text-align: right; color: #dc2626;">- RM ${makeVar('custom_deductions_total')}</td>
                    </tr>
                    <tr style="background-color: #f1f5f9;">
                        <td style="border: 1px solid #cbd5e1; padding: 12px; font-weight: bold;">Nett Refund Payout Amount</td>
                        <td style="border: 1px solid #cbd5e1; padding: 12px; text-align: right; font-weight: bold; color: #2563eb; font-size: 16px;">RM ${makeVar('nett_refund_amount')}</td>
                    </tr>
                </tbody>
            </table>
        `
    },
    {
        id: 'refund-items-table',
        label: 'Refund Items Table',
        category: 'Refund Financials',
        content: `
            <table style="width: 100%; border-collapse: collapse; margin: 20px 0; font-family: serif; font-size: 14px;">
                <thead>
                    <tr style="background-color: #f8fafc; border-bottom: 2px solid #cbd5e1;">
                        <th style="border: 1px solid #cbd5e1; padding: 10px; text-align: left; width: 18%;" data-gjs-type="text">No.</th>
                        <th style="border: 1px solid #cbd5e1; padding: 10px; text-align: left;" data-gjs-type="text">Item</th>
                        <th style="border: 1px solid #cbd5e1; padding: 10px; text-align: center; width: 10%;" data-gjs-type="text">Qty</th>
                        <th style="border: 1px solid #cbd5e1; padding: 10px; text-align: right; width: 18%;" data-gjs-type="text">Total (RM)</th>
                    </tr>
                </thead>
                <tbody id="dynamic-refund-tbody">
                    <tr style="background-color: #f0fdf4;">
                        <td colspan="4" style="border: 1px dashed #bbf7d0; padding: 20px; text-align: center; color: #166534; font-weight: bold;" data-gjs-type="text">
                            Dynamic Refund Items Will Appear Here
                        </td>
                    </tr>
                </tbody>
            </table>
        `
    },
    {
        id: 'deductions-breakdown-list',
        label: 'Deductions Breakdown',
        category: 'Refund Financials',
        content: `
            <div style="margin: 15px 0; padding: 15px; border: 1px solid #e2e8f0; border-radius: 8px; background-color: #fff;">
                <h4 style="margin: 0 0 10px 0; font-size: 14px; color: #334155; text-transform: uppercase;">Deduction Items Breakdown:</h4>
                <ul style="margin: 0; padding-left: 20px; line-height: 1.6; color: #475569;">
                    <li>Unpaid Invoices: <strong>RM ${makeVar('unpaid_invoices_breakdown')}</strong></li>
                    <li>Damage/Repair Charges: <strong>RM ${makeVar('custom_items_breakdown')}</strong></li>
                </ul>
            </div>
        `
    },

    // ==========================================
    // 3. Refund Dynamic Variables (退款專用動態變數方塊)
    // ==========================================
    {
        id: 'var-refund-financials',
        label: '💰 Refund Variables',
        category: 'Refund Dynamic Variables',
        content: `
            <div style="padding: 10px; background-color: #f8fafc; border: 1px dashed #cbd5e1; font-family: monospace; font-size: 14px; margin: 10px 0;">
                Total Deposit Collected: RM ${makeVar('total_deposit_collected')}<br>
                Auto Deductions: RM ${makeVar('auto_deductions_total')}<br>
                Custom Deductions: RM ${makeVar('custom_deductions_total')}<br>
                Nett Refund Payout: RM ${makeVar('nett_refund_amount')}<br>
                Check Out Date: ${makeVar('checkout_date')}
            </div>
        `
    },

    // ==========================================
    // 4. Refund Signatures & Acknowledgement (退款確認與簽署區)
    // ==========================================
    {
        id: 'refund-acknowledgement',
        label: 'Refund Acknowledgment Clause',
        category: 'Refund Legal Elements',
        content: `
            <p style="margin: 15px 0; line-height: 1.6; text-align: justify; font-size: 13px;">
                By signing below, the Tenant acknowledges and agrees that the Nett Refund Payout Amount stated above represents the full and final settlement of all deposits and accounts pertaining to the tenancy of ${makeVar('property_address')}. No further claims shall be made by either party.
            </p>
        `
    },
    {
        id: 'refund-signature-block',
        label: 'Refund Signatures',
        category: 'Refund Legal Elements',
        content: `
            <table style="width: 100%; margin-top: 40px; border-collapse: collapse;">
                <tr>
                    <td style="width: 50%; padding-right: 20px; vertical-align: bottom;">
                        <p style="margin: 0 0 10px 0; font-weight: bold;">Authorized By (Landlord / Agent):</p>
                        <hr style="border: 0; border-top: 1px solid #000; margin: 30px 0 10px 0;">
                        <p style="margin: 5px 0; font-size: 14px;">Name: ${makeVar('owner_name')}</p>
                        <p style="margin: 5px 0; font-size: 14px;">Date: _________________</p>
                    </td>
                    <td style="width: 50%; padding-left: 20px; vertical-align: bottom;">
                        <p style="margin: 0 0 10px 0; font-weight: bold;">Acknowledged By (Tenant):</p>
                        <hr style="border: 0; border-top: 1px solid #000; margin: 30px 0 10px 0;">
                        <p style="margin: 5px 0; font-size: 14px;">Name: ${makeVar('tenant_name')}</p>
                        <p style="margin: 5px 0; font-size: 14px;">Date: _________________</p>
                    </td>
                </tr>
            </table>
        `
    }
];

export default refundAgreementBlocks;
