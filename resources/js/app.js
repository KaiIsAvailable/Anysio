import './bootstrap';
import Alpine from 'alpinejs';
import collapse from '@alpinejs/collapse';

Alpine.plugin(collapse);
window.Alpine = Alpine;
Alpine.start();

import { initAjaxSearch } from './ajax-search';
import { createAgreementEditor, updateBlocks } from './editor/init';

document.addEventListener('DOMContentLoaded', () => {
    const searchInput = document.querySelector('#table-search-input');
    if (searchInput) {
        initAjaxSearch('#table-search-input', '#lease-table-wrapper');
    }
});

window.createAgreementEditor = createAgreementEditor;
window.updateBlocks = updateBlocks;