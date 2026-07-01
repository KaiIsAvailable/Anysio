import './bootstrap';

import Alpine from 'alpinejs';
import { initAjaxSearch } from './ajax-search';
import { createAgreementEditor, updateBlocks } from './editor/init';

window.Alpine = Alpine;
Alpine.start();

document.addEventListener('DOMContentLoaded', () => {
    initAjaxSearch('#table-search-input', '#lease-table-wrapper');
});

window.createAgreementEditor = createAgreementEditor;
window.updateBlocks = updateBlocks;