import './bootstrap';

import Alpine from 'alpinejs';
import { initAjaxSearch } from './ajax-search';

window.Alpine = Alpine;
Alpine.start();

document.addEventListener('DOMContentLoaded', () => {
    initAjaxSearch('#table-search-input', '#lease-table-wrapper');
});
