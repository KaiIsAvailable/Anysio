// resources/js/editor/invoice.js
const invoiceBlocks = [
    {
        id: 'text-block',
        label: 'Text Paragraph',
        category: 'Basic Elements',
        content: `<p style="margin: 10px 0; line-height: 1.5; color: #333;">Double-click to edit this text paragraph.</p>`
    },
    {
        id: 'invoice-number',
        label: 'Invoice Number',
        category: 'Dynamic Elements',
        content: `<p>{{invoice number}}</p>`
    },
];

export default invoiceBlocks;