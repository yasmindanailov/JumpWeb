/**
 * Renderiza un paso del cajón SPA a HTML, en Node y sin navegador (Fase 4 · paso 4.2).
 *
 * Existe para que `SidebarDomContractTest` pueda comparar el ÁRBOL que emite cada motor. La
 * alternativa era un navegador headless —una dependencia pesada, lenta y con su propio modo de
 * fallo— cuando Vue ya trae `@vue/server-renderer` en el paquete que la SPA usa igualmente.
 *
 * Lee `{"step": N, "props": {...}}` por stdin y escribe el HTML por stdout. El estado llega
 * INYECTADO en vez de pedirse a la API a propósito: lo que este script compara es el marcado, y una
 * llamada de red dentro del gate lo haría lento y frágil por motivos que no son el marcado.
 *
 * Uso:  echo '{"step":1,"props":{…}}' | node scripts/render-sidebar.mjs
 */
import { createSSRApp } from 'vue';
import { renderToString } from '@vue/server-renderer';
import { createPinia } from 'pinia';
import CatalogStep from '../resources/js/sidebar/steps/CatalogStep.vue';
import DateStep from '../resources/js/sidebar/steps/DateStep.vue';
import { STEPS } from '../resources/js/sidebar/machine.js';

/** Los pasos que ya están transcritos. Un paso que no esté aquí falla en voz alta. */
const COMPONENTS = {
    [STEPS.CATALOG]: CatalogStep,
    [STEPS.DATE]: DateStep,
};

async function main() {
    const input = await new Promise((resolve, reject) => {
        let raw = '';
        process.stdin.setEncoding('utf8');
        process.stdin.on('data', (chunk) => { raw += chunk; });
        process.stdin.on('end', () => resolve(raw));
        process.stdin.on('error', reject);
    });

    const { step, props = {} } = JSON.parse(input);
    const component = COMPONENTS[step];

    if (! component) {
        process.stderr.write(`El paso ${step} todavía no está transcrito a Vue.\n`);
        process.exit(2);
    }

    const app = createSSRApp(component, props);
    app.use(createPinia());

    process.stdout.write(await renderToString(app));
}

main().catch((e) => {
    process.stderr.write(String(e?.stack || e) + '\n');
    process.exit(1);
});
