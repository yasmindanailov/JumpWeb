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
 * ⚠️ **Con `"shell": {…}` renderiza el paso DENTRO del armazón** (Fase 4 · paso 4.3·1), que es la
 * única forma de comparar los nodos que no son de ningún paso —el velo de carga, la banda de progreso
 * y la zona scrollable— porque en el Blade viven fuera del bloque de cada paso. `Shell.vue` es
 * SSR-renderizable justo para esto: recibe todo por props y no toca `document` ni `window`.
 * `Sidebar.vue` NO puede pasar por aquí (lee `window.Alpine` y el idioma del documento), y por eso el
 * armazón es un componente propio y no parte de la raíz.
 *
 * Uso:  echo '{"step":1,"props":{…}}' | node scripts/render-sidebar.mjs
 *       echo '{"step":2,"props":{…},"shell":{…}}' | node scripts/render-sidebar.mjs
 */
import { createSSRApp, h } from 'vue';
import { renderToString } from '@vue/server-renderer';
import { createPinia } from 'pinia';
import Shell from '../resources/js/sidebar/Shell.vue';
import CatalogStep from '../resources/js/sidebar/steps/CatalogStep.vue';
import DateStep from '../resources/js/sidebar/steps/DateStep.vue';
import TimeStep from '../resources/js/sidebar/steps/TimeStep.vue';
import CartStep from '../resources/js/sidebar/steps/CartStep.vue';
import IdentifyStep from '../resources/js/sidebar/steps/IdentifyStep.vue';
import { STEPS } from '../resources/js/sidebar/machine.js';

/** Los pasos que ya están transcritos. Un paso que no esté aquí falla en voz alta. */
const COMPONENTS = {
    [STEPS.CATALOG]: CatalogStep,
    [STEPS.DATE]: DateStep,
    [STEPS.TIME]: TimeStep,
    [STEPS.CART]: CartStep,
    [STEPS.IDENTIFY]: IdentifyStep,
};

async function main() {
    const input = await new Promise((resolve, reject) => {
        let raw = '';
        process.stdin.setEncoding('utf8');
        process.stdin.on('data', (chunk) => { raw += chunk; });
        process.stdin.on('end', () => resolve(raw));
        process.stdin.on('error', reject);
    });

    const { step, props = {}, shell = null } = JSON.parse(input);
    const component = COMPONENTS[step];

    // ⚠️ **Con el aviso de PAUSA no hace falta paso**, y eso es lo fiel al Blade: el aviso sustituye el
    // contenido ENTERO, así que no hay ranura que rellenar. Sin esta salida no se podrían comparar los
    // pasos 5 y 8 —donde el servidor también tapa el flujo— porque todavía no están transcritos y el
    // script abortaría con un mensaje que habla de otra cosa.
    const paused = shell !== null && shell.notice;

    if (! component && ! paused) {
        process.stderr.write(`El paso ${step} todavía no está transcrito a Vue.\n`);
        process.exit(2);
    }

    // Con armazón, el paso va en la ranura por defecto de `Shell` — igual que en `Sidebar.vue`, para
    // que lo que compara el gate sea la composición real y no una aproximación.
    const app = shell === null
        ? createSSRApp(component, props)
        : createSSRApp({ render: () => h(Shell, shell, paused ? null : { default: () => h(component, props) }) });

    app.use(createPinia());

    process.stdout.write(await renderToString(app));
}

main().catch((e) => {
    process.stderr.write(String(e?.stack || e) + '\n');
    process.exit(1);
});
