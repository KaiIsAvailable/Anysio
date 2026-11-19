import './bootstrap';
import { focusFormErrors, focusInvalidForm } from './form-validation';
window.formValidation = { focusFormErrors, focusInvalidForm };
import { enableBulkGridNavigation } from './bulk-grid-navigation';
enableBulkGridNavigation();
import { replaceTemplateVariables } from './template-variable-replacement';
window.replaceDocumentTemplateVariables = replaceTemplateVariables;
import './lease-invoice-documents';
import Alpine from 'alpinejs';
import { serviceRequestNavigation } from './service-request-navigation';
import collapse from '@alpinejs/collapse';
import { invoiceStatement } from './invoice-statement';
import { invoiceTable } from './invoice-table';
import { agreementBreakdown } from './agreement-breakdown';
import { utilityReadings, utilityAllocationEditor } from './utility-readings';
import { utilityTariffSettings, utilityTariffManager } from './utility-tariff-settings';
import { feeTypeSettings } from './fee-type-settings';

import { tenantBulkEditor } from './tenant-bulk-editor';
import { ownerBulkEditor } from './owner-bulk-editor';
import { propertyBulkEditor } from './property-bulk-editor';

Alpine.data('tenantBulkEditor', tenantBulkEditor);
Alpine.data('ownerBulkEditor', ownerBulkEditor);
Alpine.data('propertyBulkEditor', propertyBulkEditor);
Alpine.plugin(collapse);
Alpine.data('invoiceStatement', invoiceStatement);
Alpine.data('invoiceTable', invoiceTable);
Alpine.data('utilityReadings', utilityReadings);
Alpine.data('utilityAllocationEditor', utilityAllocationEditor);
Alpine.data('utilityTariffSettings', utilityTariffSettings);
Alpine.data('utilityTariffManager', utilityTariffManager);
Alpine.data('feeTypeSettings', feeTypeSettings);
window.agreementBreakdown = agreementBreakdown;
window.Alpine = Alpine;
Alpine.data('serviceRequestNavigation', serviceRequestNavigation);
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

import './maintenance';
