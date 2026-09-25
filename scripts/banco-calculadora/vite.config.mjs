// Compila el lado «B» del banco de la calculadora (`scripts/banco-calculadora/entrada.js`) a un `calculadora.js` +
// `calculadora.css`, dentro de la salida del banco de las páginas de entradas. Aparte del `vite.config.js` del
// producto a propósito: el banco no entra en el paquete que se despliega.
//   docker compose exec -u sail -T laravel.test npx vite build --config scripts/banco-calculadora/vite.config.mjs
import { defineConfig } from 'vite';
import vue from '@vitejs/plugin-vue';

export default defineConfig({
    plugins: [vue()],
    logLevel: 'warn',
    publicDir: false,
    define: { 'process.env.NODE_ENV': '"production"' },
    build: {
        outDir: 'storage/app/pixel/banco-entradas/calc',
        emptyOutDir: true,
        cssCodeSplit: false,
        lib: {
            entry: 'scripts/banco-calculadora/entrada.js',
            name: 'BancoCalculadora',
            formats: ['iife'],
            fileName: () => 'calculadora.js',
            cssFileName: 'calculadora',
        },
    },
});
