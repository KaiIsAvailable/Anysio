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
    //server: {
    //    host: '0.0.0.0', // Listen on all network interfaces
    //    port: 5173,
    //    hmr: {
    //        host: '192.168.1.105', // Replace with your laptop's actual local IP
    //    },
    //},
});
