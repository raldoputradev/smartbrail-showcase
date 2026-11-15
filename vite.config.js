import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import react from '@vitejs/plugin-react';
import { bunny } from 'laravel-vite-plugin/fonts';
import tailwindcss from '@tailwindcss/vite';
import { fileURLToPath } from 'node:url';

export default defineConfig({
    // Base kosong: chunk dynamic-import di-resolve relatif ke URL modul
    // (import.meta.url) — aman untuk subfolder Apache (/Simalas/public)
    // maupun root artisan serve.
    base: '',
    resolve: {
        alias: {
            '@': fileURLToPath(new URL('./resources/js/student', import.meta.url)),
            '@admin': fileURLToPath(new URL('./resources/js/admin', import.meta.url)),
        },
    },
    plugins: [
        laravel({
            input: [
                'resources/css/app.css',
                'resources/js/app.js',
                'resources/js/public-site/main.jsx',
                'resources/js/student/main.tsx',
                'resources/js/admin/main.tsx',
            ],
            refresh: true,
            fonts: [
                bunny('Instrument Sans', {
                    weights: [400, 500, 600],
                }),
            ],
        }),
        react(),
        tailwindcss(),
    ],
    server: {
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
});
