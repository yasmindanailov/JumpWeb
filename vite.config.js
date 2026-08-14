import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';
import vue from '@vitejs/plugin-vue';

export default defineConfig({
    plugins: [
        laravel({
            input: [
                'resources/css/app.css',
                'resources/js/app.js',
                'resources/css/filament/admin/theme.css',
                // Panel admin (Fase 7.4): calendario unificado. Entry propio para que
                // FullCalendar quede en su chunk y NO entre en el bundle de la landing.
                'resources/js/admin/calendar.js',
            ],
            refresh: true,
        }),
        tailwindcss(),
        // Fase 4 · paso 4.1 — el cajón SPA. Vue NO entra en el bundle de la landing: el entry
        // (`resources/js/sidebar/index.js`) se trae con `import()` dinámico en la primera apertura
        // del cajón, así que Vite lo saca a un chunk propio. `SidebarBundleBudgetTest` lo vigila.
        vue(),
    ],
    server: {
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
});
