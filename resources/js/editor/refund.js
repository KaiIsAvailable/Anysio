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
    // 2. Refund Statement Table
    // ==========================================
    {
        id: 'refund-statement-table',
        label: 'Professional Refund Statement',
        category: 'Refund Financials',
        content: `
            <table style="width: 100%; border-collapse: collapse; margin: 24px 0; border: 1px solid #94a3b8; font-family: Arial, sans-serif; font-size: 13px; color: #1e293b;">
                <thead>
                    <tr style="background-color: #1e3a5f; color: #ffffff;">
                        <th colspan="3" style="padding: 13px 14px; text-align: left; font-size: 16px; letter-spacing: 0.3px;" data-gjs-type="text">REFUND SETTLEMENT SUMMARY</th>
                        <th style="padding: 13px 14px; text-align: right; font-size: 13px;" data-gjs-type="text">AMOUNT (RM)</th>
                    </tr>
                </thead>
                <tbody>
                    <tr style="background-color: #f8fafc;">
                        <th style="padding: 8px 10px; border: 1px solid #cbd5e1; text-align: center; width: 8%;" data-gjs-type="text">No.</th>
                        <th style="padding: 8px 10px; border: 1px solid #cbd5e1; text-align: left;" data-gjs-type="text">Description</th>
                        <th style="padding: 8px 10px; border: 1px solid #cbd5e1; text-align: center; width: 10%;" data-gjs-type="text">Qty</th>
                        <th style="padding: 8px 10px; border: 1px solid #cbd5e1; text-align: right; width: 20%;" data-gjs-type="text">Amount (RM)</th>
                    </tr>
                </tbody>
                <tbody id="dynamic-refund-tbody">
                    <tr>
                        <td colspan="4" style="padding: 18px; border: 1px dashed #cbd5e1; text-align: center; color: #64748b;" data-gjs-type="text">No deductions recorded</td>
                    </tr>
                </tbody>
                <tbody>
                    <tr>
                        <td colspan="3" style="padding: 10px 14px; border: 1px solid #cbd5e1; text-align: right; font-weight: bold;" data-gjs-type="text">Total deduction</td>
                        <td style="padding: 10px 14px; border: 1px solid #cbd5e1; text-align: right; font-weight: bold; color: #b91c1c;">RM ${makeVar('total_deductions')}</td>
                    </tr>
                    <tr>
                        <td colspan="3" style="padding: 13px 14px; border: 1px solid #cbd5e1; text-align: right; font-weight: bold; font-size: 15px;" data-gjs-type="text">Nett total refund</td>
                        <td style="padding: 13px 14px; border: 1px solid #cbd5e1; text-align: right; font-weight: bold; font-size: 16px;">RM ${makeVar('nett_refund_amount')}</td>
                    </tr>
                </tbody>
            </table>
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
                        <p style="margin: 5px 0; font-size: 14px;">Name: </p>
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
