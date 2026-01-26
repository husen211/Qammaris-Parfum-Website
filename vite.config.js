import { defineConfig } from 'vite';
import react from '@vitejs/plugin-react';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig({
    assetsInclude: ['**/*.glb'],
    plugins: [
        react(),
        laravel({
            input: [
                'resources/css/app.css',
                'resources/js/app.js',
                'resources/js/reactbits/about-lanyard-loader.js',
                'resources/js/reactbits/card-nav.jsx'
            ],
            refresh: true,
        }),
        tailwindcss(),
    ],
});
