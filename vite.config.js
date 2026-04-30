import react from '@vitejs/plugin-react';
import laravel from 'laravel-vite-plugin';
import { resolve } from 'node:path';
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
        alias: {
            '@': resolve(__dirname, `${uiPath}/resources/js`),
        },
    },

    server: {
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