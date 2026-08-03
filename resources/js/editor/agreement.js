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
        label: 'Bullet List (项目列表)',
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
        label: 'Numbered List (有序列表)',
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
        label: 'Preamble (协议主体)',
        category: 'Legal Elements',
        content: `
            <p style="margin: 10px 0; line-height: 1.6; text-align: justify;">
                This Agreement is made and entered into on <strong>[Date]</strong>, by and between <strong>[Party A Name]</strong> (hereinafter referred to as the "Company"), and <strong>[Party B Name]</strong> (hereinafter referred to as the "Client").
            </p>
        `
    },
    {
        id: 'notice-box',
        label: 'Notice Box (注意事项)',
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
        label: 'Page Break (分页符)',
        category: 'Basic Elements',
        content: `
        <div style="page-break-before: always; border-top: 2px dashed #cbd5e1; margin: 40px 0; position: relative; text-align: center;" class="pdf-page-break">
            <span style="background: #fff; padding: 0 10px; color: #94a3b8; font-size: 12px; font-family: monospace;">--- Page Break (PDF) ---</span>
        </div>
    `
    },
    {
        id: 'thick-divider',
        label: 'Thick Divider (粗分割线)',
        category: 'Basic Elements',
        content: `<hr style="border: 0; border-top: 4px solid #0f172a; margin: 35px 0;">`
    },
    {
        id: 'double-divider',
        label: 'Double Divider (双实线)',
        category: 'Basic Elements',
        content: `<hr style="border: 0; border-top: 4px double #0f172a; margin: 35px 0;">`
    }


];

export default agreementBlocks;