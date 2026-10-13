import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import swal from 'sweetalert';

export default defineConfig({
    plugins: [
        laravel({
            input: [
                'resources/sass/app.scss', // Updated to reference SCSS
                'resources/js/app.js',
                // Ops admin panel (isolated Tailwind config via @config)
                'resources/css/ops.css',
                'resources/js/ops.js',
            ],
            refresh: true,
        }),
    ],
});
