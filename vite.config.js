import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';

export default defineConfig({
    plugins: [
        laravel({
            input: [
                'resources/css/app.css',
                'resources/js/app.js', 
                'resources/js/userManagement.js',
                'resources/js/tenants.js',
                'resources/js/room.js',
                'resources/js/editor/init.js',
                'resources/js/editor/agreement.js',
                'resources/js/editor/invoice.js',
                'resources/js/editor/receipt.js',
                'resources/js/editor/privacy.js',
                'resources/js/editor/term_of_service.js',
            ],
            
            refresh: true,
        }),
    ],
});
