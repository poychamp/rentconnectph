import { defineConfig, loadEnv } from 'vite';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';
import vue from '@vitejs/plugin-vue';

export default defineConfig(({ mode }) => {
    const env = loadEnv(mode, process.cwd(), '');
    const devHost = env.VITE_DEV_SERVER_HOST || 'localhost';
    const devPort = Number(env.VITE_DEV_SERVER_PORT || 5173);
    const devProtocol = env.VITE_DEV_SERVER_PROTOCOL || 'http';
    const hmrProtocol = devProtocol === 'https' ? 'wss' : 'ws';

    return {
        plugins: [
            laravel({
                input: [
                    'resources/css/app.css',
                    'resources/js/app.js',
                    'resources/css/admin.css',
                    'resources/js/admin.js',
                ],
                refresh: true,
            }),
            tailwindcss(),
            vue({
                template: {
                    transformAssetUrls: {
                        base: null,
                        includeAbsolute: false,
                    },
                },
            }),
        ],
        server: {
            host: '0.0.0.0',
            port: devPort,
            strictPort: true,
            origin: `${devProtocol}://${devHost}:${devPort}`,
            cors: true,
            hmr: {
                host: devHost,
                protocol: hmrProtocol,
            },
            watch: {
                ignored: ['**/storage/framework/views/**'],
            },
        },
    };
});
