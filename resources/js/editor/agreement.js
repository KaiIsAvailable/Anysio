// resources/js/editor/agreement.js
const agreementBlocks = [
    {
        id: 'agreement-title',
        label: 'Agreement Title',
        category: 'Clauses',
        content: `<h1>Agreement Title</h1>`
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
        id: 'heading-2',
        label: 'Section Heading',
        category: 'Basic Elements',
        content: `<h2 style="font-size: 20px; color: #2c3e50; border-bottom: 2px solid #eee; padding-bottom: 5px;">New Section</h2>`
    },
    {
        id: 'signature-block',
        label: 'Signatures',
        category: 'Legal Elements',
        content: `
            <table style="width: 100%; margin-top: 40px; border-collapse: collapse;">
                <tr>
                    <td style="width: 50%; padding-right: 20px; vertical-align: bottom;">
                        <p style="margin: 0 0 10px 0; font-weight: bold;">Party A :</p>
                        <hr style="border: 0; border-top: 1px solid #000; margin: 30px 0 10px 0;">
                        <p style="margin: 5px 0; font-size: 14px;">Name: _________________</p>
                        <p style="margin: 5px 0; font-size: 14px;">Date: _________________</p>
                    </td>
                    <td style="width: 50%; padding-left: 20px; vertical-align: bottom;">
                        <p style="margin: 0 0 10px 0; font-weight: bold;">Party B :</p>
                        <hr style="border: 0; border-top: 1px solid #000; margin: 30px 0 10px 0;">
                        <p style="margin: 5px 0; font-size: 14px;">Name: _________________</p>
                        <p style="margin: 5px 0; font-size: 14px;">Date: _________________</p>
                    </td>
                </tr>
            </table>
        `
    },
    {
        id: 'agreement-preamble',
        label: 'Preamble ',
        category: 'Legal Elements',
        content: `
            <p style="margin: 10px 0; line-height: 1.6; text-align: justify;">
                This Agreement is made and entered into on <strong>[Date]</strong>, by and between <strong>[Party A Name]</strong> (hereinafter referred to as the "Company"), and <strong>[Party B Name]</strong> (hereinafter referred to as the "Client").
            </p>
        `
    },
    {
        id: 'notice-box',
        label: 'Notice Box ',
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
        id: 'numbered-clauses',
        label: 'Numbered Clauses',
        category: 'Clauses',
        content: `
            <ol style="margin: 15px 0; padding-left: 20px; line-height: 1.8; text-align: justify;">
                <li><strong>Obligations:</strong> The Client agrees to...</li>
                <li><strong>Payment Terms:</strong> All payments shall be made within...</li>
                <li><strong>Termination:</strong> This agreement may be terminated by either party with a 30-day notice.</li>
            </ol>
        `
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
    // ==========================================
    // 💡 Layout Elements (排版容器 - PDF 安全版)
    // ==========================================
    {
        id: 'layout-2-columns',
        label: '2 Columns ',
        category: 'Layout ',
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
        category: 'Layout ',
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
        category: 'Layout ',
        content: `
            <table style="width: 100%; border-collapse: collapse; margin: 15px 0;">
                <tr>
                    <td style="width: 70%; padding: 10px; vertical-align: middle; border: 1px dashed #cbd5e1;">
                        <h2 style="margin: 0; color: #1e293b;">Company Name</h2>
                        <p style="margin: 5px 0 0 0; color: #64748b; font-size: 14px;">123 Business Road, City, Country</p>
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