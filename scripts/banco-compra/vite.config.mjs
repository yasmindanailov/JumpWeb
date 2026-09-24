// Compila el lado «B» del banco de la compra (`scripts/banco-compra/entrada.js`) a un único `compra.js` + `compra.css`.
// Aparte del `vite.config.js` del producto a propósito: el banco no entra en el paquete que se despliega.
//   docker compose exec -u sail -T laravel.test npx vite build --config scripts/banco-compra/vite.config.mjs
import { defineConfig } from 'vite';
import vue from '@vitejs/plugin-vue';

export default defineConfig({
    plugins: [vue()],
    logLevel: 'warn',
    // Sin esto Vite copia TODO `public/` del producto dentro de la salida (subidas y vídeos incluidos).
    publicDir: false,
    define: { 'process.env.NODE_ENV': '"production"' },
    build: {
        outDir: 'storage/app/pixel/banco-compra/b',
        emptyOutDir: true,
        cssCodeSplit: false,
        lib: {
            entry: 'scripts/banco-compra/entrada.js',
            name: 'BancoCompra',
            formats: ['iife'],
            fileName: () => 'compra.js',
            cssFileName: 'compra',
        },
    },
});
