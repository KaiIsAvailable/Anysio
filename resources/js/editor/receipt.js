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
            <table style="width: 100%; border-collapse: collapse; margin-bottom: 20px; font-size: 14px;">
                <thead>
                    <tr style="background-color: #f8fafc; border-bottom: 2px solid #cbd5e1;">
                        <th style="padding: 12px 15px; text-align: left; color: #334155;">Description</th>
                        <th style="padding: 12px 15px; text-align: right; color: #334155; width: 25%;">Amount Paid</th>
                    </tr>
                </thead>
                <tbody>
                    <tr style="border-bottom: 1px solid #e2e8f0;">
                        <td style="padding: 12px 15px; color: #0f172a;">Monthly Rent</td>
                        <td style="padding: 12px 15px; text-align: right; color: #0f172a; font-weight: 500;">RM {{ rent_price }}</td>
                    </tr>
                    <tr style="border-bottom: 1px solid #e2e8f0;">
                        <td style="padding: 12px 15px; color: #0f172a;">Utilities</td>
                        <td style="padding: 12px 15px; text-align: right; color: #0f172a; font-weight: 500;">RM 0.00</td>
                    </tr>
                </tbody>
            </table>
        `
    },
    {
        id: 'receipt-totals',
        label: 'Total Paid',
        category: 'Receipt Elements',
        content: `
            <table style="width: 40%; border-collapse: collapse; margin-left: auto; margin-bottom: 40px; font-size: 14px;">
                <tr>
                    <td style="padding: 15px; text-align: right; font-weight: bold; font-size: 16px; color: #0f172a;">Total Paid:</td>
                    <td style="padding: 15px; text-align: right; font-weight: bold; font-size: 18px; color: #059669; background-color: #ecfdf5; border: 1px solid #a7f3d0;">RM {{ total_amount }}</td>
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
    }
];

export default receiptBlocks;