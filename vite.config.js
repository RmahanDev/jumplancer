import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import react from '@vitejs/plugin-react';
import rtlcss from 'rtlcss';

/**
 * Flip a stylesheet to right-to-left with RTLCSS (the tool Bootstrap uses for bootstrap.rtl.css).
 * Only files matching `test` are flipped, so the existing LTR homepage bundle (app.scss) stays as is.
 */
function rtl(test) {
    return {
        postcssPlugin: 'jumplancer-rtl',
        prepare(result) {
            const file = (result.opts.from ?? '').replaceAll('\\', '/');

            return test.test(file) ? rtlcss() : {};
        },
    };
}
rtl.postcss = true;

export default defineConfig({
    plugins: [
        laravel({
            input: [
                'resources/sass/app.scss',
                'resources/sass/panel.scss',
                'resources/js/app.js',
                'resources/js/inertia.jsx',
                'resources/js/islands.jsx',
            ],
            refresh: true,
        }),
        react(),
    ],
    css: {
        postcss: {
            plugins: [rtl(/resources\/sass\/panel\.scss$/)],
        },
        preprocessorOptions: {
            scss: {
                quietDeps: true,
                silenceDeprecations: ['import', 'global-builtin', 'color-functions', 'if-function'],
            },
        },
    },
    server: {
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
});
