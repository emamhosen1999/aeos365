import react from '@vitejs/plugin-react';
import laravel from 'laravel-vite-plugin';
import { resolve } from 'node:path';
import { defineConfig } from 'vite';
import tailwindcss from '@tailwindcss/vite';

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

    // PERFORMANCE TWEAK #1: Force pre-bundling of heavy dependencies
    optimizeDeps: {
        // Because your UI is in vendor/, Vite might miss these. 
        // Explicitly list heavy third-party packages used in your React app.
        include: [
            'react',
            'react-dom',
            'react/jsx-runtime',
            '@inertiajs/react',
            'axios',
            // Add other heavy hitters here (e.g., 'framer-motion', 'chart.js')
        ],
        // Exclude your local package so it's not cached as a static dependency
        exclude: ['aero-ui'], 
    },

    server: {
        host: '0.0.0.0', // Keeps listening on all interfaces
        port: 5173,
        strictPort: false,
        allowedHosts: ['.aeos365.test', 'aeos365.test'],
        
        // ADD THIS BACK: Tells Laravel to use the correct domain for script tags
        hmr: {
            host: 'aeos365.test',
        },
        
        cors: true,
        watch: {
            ignored: [
                '**/node_modules/**',
                '**/storage/**',
                '**/.git/**',
                '**/vendor/!(aero)/**', 
            ],
        },
    },
});