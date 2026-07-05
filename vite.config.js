import react from '@vitejs/plugin-react';
import laravel from 'laravel-vite-plugin';
import { resolve } from 'node:path';
import { readFileSync } from 'node:fs';
import { defineConfig } from 'vite';
import tailwindcss from '@tailwindcss/vite';

/**
 * Aero Enterprise Suite - Vite Configuration
 */

const uiPath = 'vendor/aero/ui';

export default defineConfig({
    plugins: [
        laravel({
            input: [
                `${uiPath}/resources/css/app.css`,
                `${uiPath}/resources/js/app.jsx`,
            ],
            refresh: [
                `${uiPath}/resources/js/**/*.{js,jsx,ts,tsx}`,
                `${uiPath}/resources/css/**/*.css`,
                'resources/**/*.{blade.php,js,jsx}',
            ],
        }),
        react(),
        tailwindcss(),
    ],

    esbuild: {
        jsx: 'automatic',
    },

    resolve: {
        preserveSymlinks: true,
        // Force shared third-party deps to a single copy in the HOST node_modules.
        // aero-ui is a path-repo junction; with preserveSymlinks the dev dep-optimizer
        // resolves its imports from the junction's node_modules, where these heavy deps
        // are NOT installed (only hoisted to the host) — one ENOENT aborts the whole
        // optimize run and 504s every dep. dedupe + the recharts alias point them home.
        dedupe: ['react', 'react-dom', '@inertiajs/react', '@heroui/react', 'recharts', 'framer-motion'],
        alias: {
            '@': resolve(__dirname, `${uiPath}/resources/js`),
            recharts: resolve(__dirname, 'node_modules/recharts'),
        },
    },

    server: {
        // Serve over HTTPS to match Laragon's SSL setup
        https: {
            key: readFileSync('C:/laragon/etc/ssl/laragon.key'),
            cert: readFileSync('C:/laragon/etc/ssl/laragon.crt'),
        },
        // Allow the server to be accessible via the custom domain
        host: '0.0.0.0', 
        port: 5173,
        strictPort: true, 
        hmr: {
            // This ensures HMR connects to the main domain 
            // even when browsing a subdomain
            host: 'aeos365.test',
        },
        // Required for cross-subdomain/domain requests
        cors: true,
        // Explicitly allow the .test domains to prevent "Blocklisted Host" errors
        allowedHosts: [
            'aeos365.test',
            '.aeos365.test'
        ],
    },
});