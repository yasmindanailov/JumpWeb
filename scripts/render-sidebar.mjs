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
 * ⚠️ **Y desde 4.7·2b·2·B hay un SEGUNDO modo, que es el bueno**: con `"api": {…}` las props no
 * llegan hechas — se construyen aquí, con los módulos planos que usa `Sidebar.vue`, a partir de las
 * respuestas REALES del servidor. Es lo que cierra el punto ciego del modo `props`: allí la traducción
 * «respuesta → lo que se pinta» la hacía el test, así que el gate no la ejecutaba nunca. El modo
 * `props` sigue existiendo para los pasos que aún no se han migrado.
 *
 * ⚠️ **`state` es lo que NO viene del servidor** —el día elegido, el idioma del documento— y viaja
 * aparte a propósito: mezclarlo con `api` invitaría a colar ahí un dato derivado y a que el gate
 * volviera a comparar contra algo cocinado fuera del cliente. Todo lo que se pueda DERIVAR de la
 * respuesta (el mes en que abre el calendario, por ejemplo) se deriva aquí, no se recibe.
 *
 * Uso:  echo '{"step":1,"props":{…}}' | node scripts/render-sidebar.mjs
 *       echo '{"step":2,"props":{…},"shell":{…}}' | node scripts/render-sidebar.mjs
 *       echo '{"step":1,"api":{"catalog":{…},"config":{…}},"messages":{…}}' | node scripts/render-sidebar.mjs
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
import VerifyStep from '../resources/js/sidebar/steps/VerifyStep.vue';
import PayStep from '../resources/js/sidebar/steps/PayStep.vue';
import RedirectStep from '../resources/js/sidebar/steps/RedirectStep.vue';
import ConfirmedStep from '../resources/js/sidebar/steps/ConfirmedStep.vue';
import DeclinedStep from '../resources/js/sidebar/steps/DeclinedStep.vue';
import VerifyingStep from '../resources/js/sidebar/steps/VerifyingStep.vue';
import { STEPS } from '../resources/js/sidebar/machine.js';
import { searchIsEnabled, sectionsFrom } from '../resources/js/sidebar/catalog.js';
import {
    buildWeeks, canGoNext, canGoPrev, initialMonth, monthLabel, offeredMonths, weekdayHeaders,
} from '../resources/js/sidebar/calendar.js';
import { dayPriceCents, initialQuantity, maxQuantityFor, minQuantityFor } from '../resources/js/sidebar/offer.js';
import { cartRows } from '../resources/js/sidebar/cart.js';

/** Los pasos que ya están transcritos. Un paso que no esté aquí falla en voz alta. */
const COMPONENTS = {
    [STEPS.CATALOG]: CatalogStep,
    [STEPS.DATE]: DateStep,
    [STEPS.TIME]: TimeStep,
    [STEPS.CART]: CartStep,
    [STEPS.IDENTIFY]: IdentifyStep,
    [STEPS.VERIFY_EMAIL]: VerifyStep,
    [STEPS.PAY]: PayStep,
    [STEPS.REDIRECTING]: RedirectStep,
    [STEPS.CONFIRMED]: ConfirmedStep,
    [STEPS.DECLINED]: DeclinedStep,
    [STEPS.VERIFYING]: VerifyingStep,
};

/**
 * **De la respuesta CRUDA de la API a las props del paso, con el MISMO código que corre en el
 * navegador** (Fase 4 · paso 4.7·2b·2·B).
 *
 * ⚠️ **Por qué este mapa existe y por qué no puede volver a vivir en el test.** Hasta ahora las props
 * se inyectaban ya cocinadas, y quien las cocinaba era el test en PHP a partir del view-model del
 * componente Livewire. Eso dejaba fuera del gate justo la pieza que de verdad corre en producción: la
 * traducción «respuesta del servidor → lo que pinta el paso». Un renombre de campo ahí salía VERDE con
 * el cajón real pintando filas vacías, y ya ocurrió una vez (`#46(a)`).
 *
 * Cada entrada llama a los módulos PLANOS que importa `Sidebar.vue` — no a una copia—, así que lo que
 * el gate ejercita es el camino real. Un paso sin entrada aquí sigue funcionando con `props`
 * inyectadas: la migración es incremental y cada paso que entra deja de tener el punto ciego.
 *
 * @type {Record<number, (api: object, messages: object) => object>}
 */
const PROPS_FROM_API = {
    [STEPS.CATALOG]: (api, messages) => {
        const sections = sectionsFrom(api.catalog?.data ?? []);

        return {
            sections,
            searchEnabled: searchIsEnabled(sections, api.config?.catalog_search_min_items),
            messages,
        };
    },

    /**
     * ⚠️ El mes se DERIVA de la oferta, igual que hace `Sidebar.vue` al elegir producto: el calendario
     * abre en el primero con oferta. Recibirlo desde fuera dejaría fuera del gate justo esa regla.
     */
    [STEPS.DATE]: (api, messages, state) => {
        const dates = api.dates?.data ?? [];
        const month = initialMonth(dates);
        const months = offeredMonths(dates);

        return {
            weeks: buildWeeks(month, dates, state.selectedDate ?? null),
            weekdayHeaders: weekdayHeaders(state.locale),
            monthLabel: monthLabel(month, state.locale),
            canPrev: canGoPrev(month, months),
            canNext: canGoNext(month, months),
            selectedDate: state.selectedDate ?? null,
            messages,
        };
    },

    /**
     * ⚠️ La CANTIDAD tampoco se recibe: se deriva con la misma regla que aplica `Sidebar.vue` al
     * elegir hora (`initialQuantity`). Recibirla dejaría fuera del gate el suelo y el techo del
     * selector, que es de lo único que depende que el paso 3 no deje pedir lo que no cabe.
     */
    [STEPS.TIME]: (api, messages, state) => {
        const times = api.times?.data ?? [];
        const product = api.product ?? null;
        const time = state.selectedTime ?? null;

        return {
            times: times.map((t) => t.time),
            selectedTime: time,
            // La cantidad se DERIVA por defecto —así el gate ejercita el suelo y el techo—, pero un
            // caso puede forzarla: el cliente también la mueve con los botones del selector.
            quantity: state.quantity ?? initialQuantity(product, times, time),
            minQuantity: minQuantityFor(product),
            maxQuantity: maxQuantityFor(times, time),
            isPack: product?.type === 'pack',
            dayPriceCents: dayPriceCents(api.dates?.data ?? [], state.selectedDate ?? null),
            periodLabel: product?.period_label ?? '',
            eventFields: product?.event_fields ?? [],
            // El endpoint publica los complementos ya RESUELTOS —grupos, notas, gratis, topes y la poda
            // en cadena—: aquí se pasan tal cual, porque decidir cualquiera de esas cosas en el cliente
            // es lo que `CE-4` prohíbe.
            addons: { groups: api.addons?.groups ?? [], singles: api.addons?.singles ?? [] },
            messages,
        };
    },

    /**
     * ⚠️ Las filas las compone `cartRows()`, que **empareja por `index` y no por posición**: una línea
     * cuyo producto dejó de venderse no se tarifica y desaparece del presupuesto. Componerlas fuera
     * dejaría ese emparejamiento sin ejecutar, que es el fallo que `#56` midió.
     */
    [STEPS.CART]: (api, messages, state) => ({
        lines: cartRows(api.quote?.lines ?? [], api.cart ?? [], api.fieldsByProduct ?? {}),
        confirmed: state.confirmed ?? false,
        error: state.error ?? '',
        messages,
        locale: state.locale ?? 'es',
    }),
};

async function main() {
    const input = await new Promise((resolve, reject) => {
        let raw = '';
        process.stdin.setEncoding('utf8');
        process.stdin.on('data', (chunk) => { raw += chunk; });
        process.stdin.on('end', () => resolve(raw));
        process.stdin.on('error', reject);
    });

    const { step, props = {}, shell = null, api = null, messages = {}, state = {} } = JSON.parse(input);
    const component = COMPONENTS[step];

    // Modo «alimentado por el servidor»: las props NO llegan hechas, se construyen aquí con el código
    // del cliente a partir de lo que devolvió la API de verdad. Si el paso todavía no tiene
    // constructor, es un error en voz alta y no un silencioso «pues renderizo sin datos».
    let resolved = props;

    if (api !== null) {
        const build = PROPS_FROM_API[step];

        if (! build) {
            process.stderr.write(`El paso ${step} no sabe construir sus props desde la API todavía.\n`);
            process.exit(3);
        }

        resolved = build(api, messages, state);
    }

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
        ? createSSRApp(component, resolved)
        : createSSRApp({ render: () => h(Shell, shell, paused ? null : { default: () => h(component, resolved) }) });

    app.use(createPinia());

    process.stdout.write(await renderToString(app));
}

main().catch((e) => {
    process.stderr.write(String(e?.stack || e) + '\n');
    process.exit(1);
});
