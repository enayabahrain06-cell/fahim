import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';

export default defineConfig({
    plugins: [
        laravel({
            input: [
                'resources/css/invite.css',
                'resources/js/invite.js',
                'resources/css/admin.css',
                'resources/js/admin.js',
                'resources/js/install.js',
            ],
            refresh: true,
        }),
    ],
    // Keep the old files while a build runs: emptying the folder first made pages fail with a 500 ("Vite manifest not found").
    build: { emptyOutDir: false },
});
