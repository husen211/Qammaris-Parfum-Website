import { defineConfig } from 'vite';
import react from '@vitejs/plugin-react';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';
import mouseOnlyHover from './tools/frontend/hover-guard.mjs';

export default defineConfig({
    assetsInclude: ['**/*.glb'],
    plugins: [
        react(),
        laravel({
            input: [
                'resources/css/app.css',
                'resources/css/blog-editor.css',
                'resources/css/journal.css',
                'resources/css/about.css',
                'resources/js/app.js',
                'resources/js/journal.js',
                'resources/js/about.js',
                'resources/js/blog-editor.js',
                'resources/js/reactbits/about-lanyard-loader.js',
                'resources/js/reactbits/card-nav.jsx'
            ],
            refresh: true,
        }),
        tailwindcss(),
        mouseOnlyHover(),
    ],
});
