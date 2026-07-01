import 'grapesjs/dist/css/grapes.min.css';
import grapesjs from 'grapesjs';
import gjsPresetWebpage from 'grapesjs-preset-webpage';

import agreementBlocks from './agreement';
import privacyBlocks from './privacy';
import invoiceBlocks from './invoice';
import receiptBlocks from './receipt';
import tosBlocks from './term_of_service';

const blockSets = {
    agreement: agreementBlocks,
    privacy: privacyBlocks,
    invoice: invoiceBlocks,
    receipt: receiptBlocks,
    tos: tosBlocks
};

export function createAgreementEditor(containerId) {
    const editor = grapesjs.init({
        container: `#${containerId}`,
        plugins: [gjsPresetWebpage],

        blockManager: {
            appendTo: '#blocks-container'
        },

        styleManager: {
            appendTo: '#styles-container'
        },

        storageManager: false
    });

    window.editor = editor;

    editor.on('load', () => {
      updateBlocks('tos');
    });

    return editor;
}

export function updateBlocks(category) {
    const bm = window.editor.BlockManager;

    bm.getAll().forEach(block => {
      if (block) {
        bm.remove(block.id || block.get?.('id'));
      }
    });

    const blocks = blockSets[category] || [];
    blocks.forEach(block => {
      bm.add(block.id, block);
    });
}