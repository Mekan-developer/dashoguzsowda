import { defineConfig, loadEnv } from 'vite';
import laravel from 'laravel-vite-plugin';
import vue from '@vitejs/plugin-vue';

export default defineConfig(({ mode }) => {
    const env = loadEnv(mode, process.cwd(), '');

    // Адрес, по которому браузер тянет ассеты и HMR. Он же уезжает в public/hot,
    // поэтому для доступа с других машин в локальной сети здесь должен стоять
    // IP хоста, а не localhost.
    const devHost = env.VITE_DEV_HOST || 'localhost';

    return {
        server: {
            host: '0.0.0.0',
            port: 5173,
            strictPort: true,
            hmr: {
                host: devHost,
            },
            watch: {
                usePolling: true,   // нужно на Windows/WSL и macOS
            },
        },
        plugins: [
            laravel({
                input: 'resources/js/app.js',
                refresh: true,
            }),
            vue({
                template: {
                    transformAssetUrls: {
                        base: null,
                        includeAbsolute: false,
                    },
                },
            }),
        ],
    };
});
