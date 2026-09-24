// Compila el lado «B» del banco de la isla (`scripts/banco-isla/entrada.js`) a un único `isla.js` + `isla.css`.
// Aparte del `vite.config.js` del producto a propósito: el banco no entra en el paquete que se despliega.
//   docker compose exec -u sail -T laravel.test npx vite build --config scripts/banco-isla/vite.config.mjs
import { defineConfig } from 'vite';
import vue from '@vitejs/plugin-vue';

export default defineConfig({
    plugins: [vue()],
    logLevel: 'warn',
    // Sin esto Vite copia TODO `public/` del producto dentro de la salida (subidas y vídeos incluidos).
    publicDir: false,
    define: { 'process.env.NODE_ENV': '"production"' },
    build: {
        outDir: 'storage/app/pixel/banco-isla/b',
        emptyOutDir: true,
        cssCodeSplit: false,
        lib: {
            entry: 'scripts/banco-isla/entrada.js',
            name: 'BancoIsla',
            formats: ['iife'],
            fileName: () => 'isla.js',
            cssFileName: 'isla',
        },
    },
});
