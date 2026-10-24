import './bootstrap';
import Alpine from 'alpinejs';
import collapse from '@alpinejs/collapse';
import { invoiceStatement } from './invoice-statement';
import { agreementBreakdown } from './agreement-breakdown';

Alpine.plugin(collapse);
Alpine.data('invoiceStatement', invoiceStatement);
window.agreementBreakdown = agreementBreakdown;
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
import './customer-service';
import './tenant-lease-navigation';
