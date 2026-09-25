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
                // F4 · T5 — el CARGADOR DEL PAQUETE. Entry propio para que una página que no es del
                // producto no tenga que descargar la landing entera (`app.js`) para abrir el cajón.
                // Se sirve en ruta estable desde `/cajon/paquete.js`; lo vigila `PaqueteDelCajonTest`.
                'resources/js/cajon/paquete.js',
                // T4d — la CALCULADORA de una página declarada (`specs/isla-y-landing-nueva.md` §4.12). Entrada propia:
                // la pide la página que la lleva (`scripts` de `<x-pagina>`) y se monta al acercarse su pieza.
                'resources/js/isla/calculadora/montar.js',
                // La FIESTA del sistema nuevo (`specs/fiesta-sistema-nuevo.md` §4.5, carril del SPA): la lista de
                // invitados, con su hoja `fiesta.css` (roles neutros + piezas). La carga `<x-pagina-enfocada>`; no
                // entra en el chunk del cajón ni en `app.js`.
                'resources/js/fiesta/lista.js',
                'resources/js/fiesta/invitacion.js',
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
