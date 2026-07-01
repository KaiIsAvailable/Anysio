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
        id: 'horizontal-line',
        label: 'Divider Line',
        category: 'Basic Elements',
        content: `<hr style="border: 0; border-top: 1px solid #ccc; margin: 20px 0;">`
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
    }
];

export default agreementBlocks;