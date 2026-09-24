// Compila el lado «B» del banco de piezas (`scripts/banco-piezas/entrada.js`) a un único `piezas.js` + `piezas.css`.
// Aparte del `vite.config.js` del producto a propósito: el banco no entra en el paquete que se despliega.
//   docker compose exec -u sail -T laravel.test npx vite build --config scripts/banco-piezas/vite.config.mjs
import { defineConfig } from 'vite';
import vue from '@vitejs/plugin-vue';

export default defineConfig({
    plugins: [vue()],
    logLevel: 'warn',
    // Sin esto Vite copia TODO `public/` del producto dentro de la salida (subidas y vídeos incluidos).
    publicDir: false,
    define: { 'process.env.NODE_ENV': '"production"' },
    build: {
        outDir: 'storage/app/pixel/banco-piezas/b',
        emptyOutDir: true,
        cssCodeSplit: false,
        lib: {
            entry: 'scripts/banco-piezas/entrada.js',
            name: 'BancoPiezas',
            formats: ['iife'],
            fileName: () => 'piezas.js',
            cssFileName: 'piezas',
        },
    },
});
