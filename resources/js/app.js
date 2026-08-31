import './bootstrap';

import { initAjaxSearch } from './ajax-search';
import { createAgreementEditor, updateBlocks } from './editor/init';

document.addEventListener('DOMContentLoaded', () => {
    initAjaxSearch('#table-search-input', '#lease-table-wrapper');
});

window.createAgreementEditor = createAgreementEditor;
window.updateBlocks = updateBlocks;