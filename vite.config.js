import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import vue from '@vitejs/plugin-vue';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js'],
            refresh: true,
        }),
        vue({
            template: { transformAssetUrls: { base: null, includeAbsolute: false } },
        }),
        tailwindcss(),
    ],
    resolve: {
        alias: { '@': '/resources/js' },
    },
    build: {
        target: 'es2022',
        cssCodeSplit: true,
        rollupOptions: {
            output: {
                // Long-term cacheable vendor chunk; every page is its own lazy chunk.
                manualChunks: (id) => (/node_modules\/(vue|@vue|vue-router)\//.test(id) ? 'vendor' : undefined),
            },
        },
    },
    server: {
        watch: { ignored: ['**/storage/framework/views/**'] },
    },
});
