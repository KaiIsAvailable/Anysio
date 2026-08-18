// resources/js/editor/receipt.js

const receiptBlocks = [
    {
        id: 'receipt-header',
        label: 'Receipt Header',
        category: 'Receipt Elements',
        content: `
            <table style="width: 100%; border-collapse: collapse; margin-bottom: 30px;">
                <tr>
                    <td style="width: 50%; vertical-align: top;">
                        <div style="width: 150px; height: 60px; background-color: #f1f5f9; color: #94a3b8; text-align: center; line-height: 60px; font-size: 12px; border: 1px dashed #cbd5e1;">[ Logo Here ]</div>
                    </td>
                    <td style="width: 50%; vertical-align: top; text-align: right;">
                        <h1 style="margin: 0; color: #059669; font-size: 32px; text-transform: uppercase; letter-spacing: 2px;">OFFICIAL RECEIPT</h1>
                        <p style="margin: 5px 0 0 0; color: #64748b; font-size: 14px;">Receipt No: #{{ receipt_number }}</p>
                        <p style="margin: 5px 0 0 0; color: #64748b; font-size: 14px;">Date: {{ issue_date }}</p>
                    </td>
                </tr>
            </table>
        `
    },
    {
        id: 'receipt-received-from',
        label: 'Received From',
        category: 'Receipt Elements',
        content: `
            <div style="background-color: #ecfdf5; border: 1px solid #a7f3d0; border-left: 4px solid #10b981; padding: 20px; margin-bottom: 25px;">
                <p style="margin: 0 0 10px 0; color: #065f46; font-size: 13px; text-transform: uppercase; font-weight: bold;">Received From:</p>
                <p style="margin: 0 0 5px 0; color: #0f172a; font-size: 18px; font-weight: bold;">{{ tenant_name }}</p>
                <p style="margin: 0; color: #475569; font-size: 14px;">The sum of: <strong style="color: #059669;">RM {{ total_amount }}</strong></p>
            </div>
        `
    },
    {
        id: 'receipt-payment-details',
        label: 'Payment Details',
        category: 'Receipt Elements',
        content: `
            <table style="width: 100%; border-collapse: collapse; margin-bottom: 30px; font-size: 14px;">
                <tr>
                    <td style="padding: 10px; border: 1px solid #e2e8f0; background-color: #f8fafc; font-weight: bold; color: #334155; width: 25%;">Payment Method:</td>
                    <td style="padding: 10px; border: 1px solid #e2e8f0; color: #0f172a; width: 25%;">{{ payment_method }}</td>
                    <td style="padding: 10px; border: 1px solid #e2e8f0; background-color: #f8fafc; font-weight: bold; color: #334155; width: 25%;">Reference No:</td>
                    <td style="padding: 10px; border: 1px solid #e2e8f0; color: #0f172a; width: 25%;">{{ reference_no }}</td>
                </tr>
            </table>
        `
    },
    {
        id: 'receipt-items-table',
        label: 'Items Table',
        category: 'Receipt Elements',
        content: `
            <h4 style="margin: 0 0 10px 0; color: #334155; font-size: 14px;">Payment For:</h4>
            <table style="width: 100%; border-collapse: collapse; margin-top: 20px;">
    <thead>
        <tr style="background-color: #f8fafc; border-bottom: 2px solid #e2e8f0;">
            <th style="padding: 12px 15px; text-align: left; color: #475569;">Description</th>
            <th style="padding: 12px 15px; text-align: center; color: #475569;">Qty</th>
            <th style="padding: 12px 15px; text-align: right; color: #475569;">Unit Price</th>
            <th style="padding: 12px 15px; text-align: right; color: #475569;">Amount</th>
        </tr>
    </thead>
    <tbody id="dynamic-receipt-tbody">
        <!-- JavaScript 會自動把 Payment for Invoice: xxx 塞進這裡 -->
    </tbody>
</table>
        `
    },
    {
        id: 'receipt-totals',
        label: 'Totals',
        category: 'Receipt Elements',
        content: `
            <table style="width: 40%; border-collapse: collapse; margin-left: auto; margin-bottom: 40px; font-size: 14px;">
                <tr>
                    <td style="padding: 10px 15px; text-align: right; font-weight: 500; color: #475569;">Subtotal:</td>
                    <td style="padding: 10px 15px; text-align: right; font-weight: 500; color: #0f172a;">RM {{ subtotal_amount }}</td>
                </tr>
                <tr>
                    <td style="padding: 15px; text-align: right; font-weight: bold; font-size: 16px; color: #0f172a; border-top: 1px solid #e2e8f0;">Total Paid:</td>
                    <td style="padding: 15px; text-align: right; font-weight: bold; font-size: 18px; color: #059669; background-color: #ecfdf5; border: 1px solid #a7f3d0; border-top: 1px solid #e2e8f0;">RM {{ total_amount }}</td>
                </tr>
            </table>
        `
    },
    {
        id: 'receipt-signature',
        label: 'Signature & Footer',
        category: 'Receipt Elements',
        content: `
            <table style="width: 100%; border-collapse: collapse; margin-top: 50px;">
                <tr>
                    <td style="width: 60%; vertical-align: bottom;">
                        <p style="margin: 0; color: #059669; font-weight: bold; font-size: 16px;">Thank you for your payment!</p>
                        <p style="margin: 5px 0 0 0; color: #64748b; font-size: 12px;">This is a computer-generated receipt and requires no physical signature.</p>
                    </td>
                    <td style="width: 40%; text-align: center; vertical-align: bottom;">
                        <hr style="border: 0; border-top: 1px solid #0f172a; margin: 0 0 10px 0;">
                        <p style="margin: 0; color: #475569; font-size: 14px;">Authorized Signature</p>
                        <p style="margin: 5px 0 0 0; color: #0f172a; font-weight: bold; font-size: 14px;">{{ owner_name }}</p>
                    </td>
                </tr>
            </table>
        `
    },
    {
        id: 'receipt-header',
        label: 'Receipt Header',
        category: 'Receipt Elements',
        content: `
        <table style="width: 100%; border-collapse: collapse; margin-bottom: 30px; font-family: sans-serif;">
            <tr>
                <td style="width: 50%; vertical-align: top;">
                    <h1 style="margin: 0; color: #0f172a; font-size: 24px;">Anysio Technologies</h1>
                </td>
                <td style="width: 50%; vertical-align: top; text-align: right;">
                    <h1 style="margin: 0; color: #059669; font-size: 36px; text-transform: uppercase; letter-spacing: 2px;">RECEIPT</h1>
                    <p style="margin: 5px 0 0 0; color: #64748b; font-size: 14px;">
                        <strong>Receipt #:</strong> <span data-variable="receipt_no" class="gjs-variable-tag">{{ receipt_no }}</span><br>
                        <strong>Date:</strong> <span data-variable="receipt_date" class="gjs-variable-tag">{{ receipt_date }}</span>
                    </p>
                </td>
            </tr>
        </table>`
    },
    {
        id: 'receipt-parties',
        label: 'Received From / Issued By',
        category: 'Receipt Elements',
        content: `
        <table style="width: 100%; border-collapse: collapse; margin-bottom: 30px; font-family: sans-serif;">
            <tr>
                <td style="width: 50%; vertical-align: top; padding-right: 20px;">
                    <h4 style="margin: 0 0 10px 0; color: #64748b; font-size: 12px; text-transform: uppercase;">Received From (Customer):</h4>
                    <p style="margin: 0 0 5px 0; font-weight: bold; color: #0f172a; font-size: 16px;"><span data-variable="user_name" class="gjs-variable-tag">{{ user_name }}</span></p>
                    <p style="margin: 0; color: #475569; font-size: 14px; line-height: 1.5;">
                        <strong>Phone:</strong> <span data-variable="user_phone" class="gjs-variable-tag">{{ user_phone }}</span><br>
                        <strong>Email:</strong> <span data-variable="user_email" class="gjs-variable-tag">{{ user_email }}</span>
                    </p>
                </td>
                <td style="width: 50%; vertical-align: top; padding-left: 20px;">
                    <h4 style="margin: 0 0 10px 0; color: #64748b; font-size: 12px; text-transform: uppercase;">Issued By (Biller):</h4>
                    <p style="margin: 0 0 5px 0; font-weight: bold; color: #0f172a; font-size: 16px;">Anysio Technologies</p>
                    <p style="margin: 0; color: #475569; font-size: 14px; line-height: 1.5;">
                        <strong>Phone:</strong> <span data-variable="company_phone" class="gjs-variable-tag">{{ company_phone }}</span><br>
                        <strong>Email:</strong> <span data-variable="company_email" class="gjs-variable-tag">{{ company_email }}</span>
                    </p>
                </td>
            </tr>
        </table>`
    },
    {
        id: 'receipt-payment-details',
        label: 'Payment Details',
        category: 'Receipt Elements',
        content: `
        <div style="background-color: #ecfdf5; border: 1px solid #10b981; border-radius: 8px; padding: 20px; margin-bottom: 30px; font-family: sans-serif;">
            <h3 style="margin: 0 0 15px 0; color: #065f46; font-size: 18px; text-align: center; letter-spacing: 1px;">PAYMENT SUCCESSFUL</h3>
            <table style="width: 100%; border-collapse: collapse; font-size: 14px;">
                <tr>
                    <td style="padding: 10px 8px; border-bottom: 1px solid #a7f3d0; color: #065f46; font-weight: bold;">Amount Received:</td>
                    <td style="padding: 10px 8px; border-bottom: 1px solid #a7f3d0; text-align: right; font-size: 18px; font-weight: bold; color: #059669;">RM <span data-variable="amount_paid" class="gjs-variable-tag">{{ amount_paid }}</span></td>
                </tr>
                <tr>
                    <td style="padding: 10px 8px; border-bottom: 1px solid #a7f3d0; color: #065f46;">Payment Method:</td>
                    <td style="padding: 10px 8px; border-bottom: 1px solid #a7f3d0; text-align: right; color: #047857;"><span data-variable="payment_method" class="gjs-variable-tag">{{ payment_method }}</span></td>
                </tr>
                <tr>
                    <td style="padding: 10px 8px; border-bottom: 1px solid #a7f3d0; color: #065f46;">Reference No / Trans ID:</td>
                    <td style="padding: 10px 8px; border-bottom: 1px solid #a7f3d0; text-align: right; color: #047857;"><span data-variable="reference_no" class="gjs-variable-tag">{{ reference_no }}</span></td>
                </tr>
                <tr>
                    <td style="padding: 10px 8px; color: #065f46;">Payment For:</td>
                    <td style="padding: 10px 8px; text-align: right; color: #047857;"><span data-variable="package_name" class="gjs-variable-tag">{{ package_name }}</span></td>
                </tr>
            </table>
        </div>`
    },
    {
        id: 'receipt-footer',
        label: 'Receipt Footer',
        category: 'Receipt Elements',
        content: `
        <p style="font-size: 12px; color: #64748b; text-align: center; border-top: 1px solid #e2e8f0; padding-top: 20px; margin-top: 40px; font-family: sans-serif;">
            This is a computer-generated receipt. No signature is required.<br>
            Thank you for your business!
        </p>`
    }
];

export default receiptBlocks;