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
    buildStrip, buildWeeks, canGoNext, canGoPrev, initialMonth, monthLabel, offeredMonths,
    weekdayHeaders,
} from '../resources/js/sidebar/calendar.js';
import { dayPriceCents, initialQuantity, maxQuantityFor, minQuantityFor } from '../resources/js/sidebar/offer.js';
import { cartRows } from '../resources/js/sidebar/cart.js';
import { assignableOptions, dependentsById } from '../resources/js/sidebar/assignment.js';
import { buildProgress } from '../resources/js/sidebar/progress.js';
import { buildFooter } from '../resources/js/sidebar/foot.js';
import { gatewayForm } from '../resources/js/sidebar/pay.js';
import { registerErrors } from '../resources/js/sidebar/register.js';
import { buildConfirmation, declinedReasonText } from '../resources/js/sidebar/outcome.js';

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
            // La TIRA (`#239`): la vía normal del paso. Se compone aquí, con el mismo código que
            // corre en el navegador, por el mismo motivo que la rejilla.
            strip: buildStrip(dates, state.selectedDate ?? null, state.locale),
            // ⚠️ El calendario nace PLEGADO, así que un caso que quiera su árbol tiene que pedirlo:
            // el estado por defecto no lo pinta y un caso que no lo diga no cubre nada de él.
            calendarOpen: state.calendarOpen ?? false,
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
            // ⚠️ **Las horas van CRUDAS, no aplanadas a `HH:MM`** (`#239`): el chip necesita
            // `available` para decir «casi llena». Aplanarlas aquí era el punto ciego que dejaba el
            // dato fuera del componente aunque el contrato lo publicara desde el primer día.
            times,
            // El umbral sale de `GET /config`, que es de donde lo saca el cajón real. Sin esa
            // respuesta en el caso vale 0 y ninguna hora se anuncia — el mismo respaldo que el store.
            lowMax: api.config?.low_availability_max ?? 0,
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
            // Los menores a cargo (Fase 6 · tanda 4): lo que ofrece `assignableOptions()` sobre la
            // respuesta REAL de `GET /me/dependents`, y los ya marcados. Sin ellos el bloque no existe.
            dependentOptions: assignableOptions(api.dependents?.data ?? [], messages),
            dependentIds: state.dependentIds ?? [],
        };
    },

    /**
     * ⚠️ Las filas las compone `cartRows()`, que **empareja por `index` y no por posición**: una línea
     * cuyo producto dejó de venderse no se tarifica y desaparece del presupuesto. Componerlas fuera
     * dejaría ese emparejamiento sin ejecutar, que es el fallo que `#56` midió.
     */
    /**
     * El paso 8 pinta las MISMAS filas que el carrito —`SummaryLine.vue` es compartida— así que se
     * compone igual, con `cartRows()` sobre el presupuesto real.
     */
    /**
     * ⚠️ El banner de errores del alta lo compone `register.js`, no el test. El orden de los avisos —el
     * de las reglas de validación— y el reparto entre el banner y cada campo son SUYOS: el test los
     * reimplementaba en PHP («el mismo orden que fija `register.js`», decía su comentario), que es la
     * definición de punto ciego. Ahora se le entrega el 422 crudo de `POST /auth/register`.
     */
    [STEPS.IDENTIFY]: (api, messages, state) => ({
        mode: state.mode ?? 'login',
        loginErrors: { global: '', fields: {} },
        registerErrors: api.register
            ? registerErrors(envelope(api.register.status, api.register.body), { messages, auth: state.auth ?? {} })
            : { summary: [], fields: {} },
        submitting: false,
        form: {},
        messages,
        account: state.account ?? {},
    }),

    [STEPS.PAY]: (api, messages, state) => ({
        lines: cartRows(api.quote?.lines ?? [], api.cart ?? [], api.fieldsByProduct ?? {}, dependentsById(api.dependents?.data ?? [])),
        error: state.error ?? '',
        messages,
        locale: state.locale ?? 'es',
    }),

    /**
     * ⚠️ El paso 9 traduce el sobre `payment` de la API a la lista de `<input>` que el navegador
     * auto-POSTea, y esa traducción es de `pay.js`. El test la hacía a mano —`fields` es un MAPA en el
     * contrato y una LISTA en el componente—, así que el gate nunca la ejecutaba; justo el sitio donde
     * un campo renombrado rompe el cobro con SIS0042 **con el pedido ya creado y el aforo retenido**.
     */
    [STEPS.REDIRECTING]: (api, messages) => ({
        form: gatewayForm(api.payment ?? null),
        messages,
    }),

    /**
     * ⚠️ El resumen de la reserva creada lo compone `buildConfirmation()` a partir del PEDIDO y de las
     * respuestas del pack, que llegan por endpoints distintos (`#39`: las de un menor no viajan en el
     * pedido). El test lo traducía a mano —`name`/`qty`/`subtotal` frente a
     * `product_name`/`quantity`/`charged_subtotal_cents`— y ese emparejado ya mordió una vez: el caso
     * de `#56` pasaba con el cliente emparejando por POSICIÓN, porque la llave y la posición coinciden
     * por casualidad cuando el orden natural es el mismo.
     */
    [STEPS.CONFIRMED]: (api, messages, state) => ({
        confirmation: api.order ? buildConfirmation(api.order, api.eventData ?? {}) : null,
        orderCode: state.orderCode ?? '',
        // El enlace de registro lo inyecta el SERVIDOR en el montaje (`RegistrationLink`): no es una
        // derivación del cliente, así que viaja como estado y no se recompone aquí.
        registration: state.registration ?? null,
        // «Ver mis reservas» (`#451`): la ruta la compone el servidor; aquí viaja como estado, como `contactUrl`.
        ordersUrl: state.ordersUrl ?? '',
        messages,
        locale: state.locale ?? 'es',
    }),

    /**
     * ⚠️ El motivo del rechazo **no lleva tabla de traducción a propósito**: `declined_reason` ES la
     * clave de `tickets.payment_failed.reasons.*`. Lo que sí es obligatorio es la caída a `default`,
     * porque `i18n.js` pinta VACÍO una clave que no existe — y eso dejaría el rótulo «Motivo:» sin
     * nada detrás. Componerlo aquí es lo que mete esa caída dentro del gate.
     */
    [STEPS.DECLINED]: (api, messages, state) => ({
        orderCode: state.orderCode ?? '',
        reason: declinedReasonText(messages, api.paymentStatus?.declined_reason ?? null),
        retrying: false,
        contactUrl: state.contactUrl ?? '',
        messages,
    }),

    [STEPS.CART]: (api, messages, state) => ({
        lines: cartRows(api.quote?.lines ?? [], api.cart ?? [], api.fieldsByProduct ?? {}, dependentsById(api.dependents?.data ?? [])),
        confirmed: state.confirmed ?? false,
        error: state.error ?? '',
        messages,
        locale: state.locale ?? 'es',
        // Los menores a cargo (Fase 6 · tanda 4), como en el paso 3. ⚠️ `state.notice` es el aviso de
        // PAUSA del armazón; el del carrito viaja como `cartNotice` para no pisarlo.
        dependentOptions: assignableOptions(api.dependents?.data ?? [], messages),
        notice: state.cartNotice ?? '',
    }),
};

/**
 * **El ARMAZÓN construido con el código del cliente** (Fase 4 · paso 4.7·2b·2·B, `DECISIONES #71`).
 *
 * ⚠️ Hasta aquí, el pie y la banda de progreso se le pasaban a Vue **tomados del servidor**, y el
 * propio helper del test lo decía: «el pie se toma del SERVIDOR… que el cliente componga el mismo
 * view-model lo comprueba `SidebarCartParityTest`». O sea: el gate comparaba el marcado de un pie que
 * `foot.js` no había compuesto. Es el mismo punto ciego que los pasos, pero peor —el armazón se monta
 * en DOCE sitios— y ya mordió una vez: en 4.3·1 la banda estaba escrita, salía verde en el gate y el
 * cajón vivo iba sin «Volver», porque `Sidebar.vue` le pasaba `progress: null`.
 *
 * ⚠️ **El aviso de pausa NO se compone aquí**: el test ya ejecuta `paused.js` en Node con la respuesta
 * real de `GET /booking/status`, así que ese lado nunca tuvo el hueco. Se recibe hecho.
 */
/**
 * El sobre que devuelve `api.js` para una respuesta ya recibida.
 *
 * ⚠️ **Se reproduce aquí a propósito y conviene saber el límite**: `api.js` es quien lo construye en
 * el navegador —con sus cuatro trampas medidas: cookie, `Accept`, CSRF url-decodificado y el reintento
 * del 419—, pero esas trampas son del TRANSPORTE y no se pueden ejercer sin red. Lo que sí se ejerce
 * desde aquí es lo que viene después: quien LEE ese sobre. La forma está tipada en `api.js`
 * (`ApiResult`), y son cinco campos.
 */
function envelope(status, body) {
    const ok = status >= 200 && status < 300;

    return { ok, status, data: body, error: ok ? null : (body?.error ?? null), offline: false };
}

function shellFromApi(api, messages, ui, state) {
    const sections = sectionsFrom(api.catalog?.data ?? []);
    const selected = sections.flatMap((s) => s.items).find((item) => item.id === state.productId) ?? null;
    const quote = api.quote ?? null;
    const line = api.addons?.line ?? null;

    return {
        // En Livewire el velo está SIEMPRE en el HTML servido y lo tapa `wire:loading`; aquí se compara
        // el marcado en reposo, así que el cajón tampoco puede estar ocupado.
        busy: false,
        notice: state.notice ?? null,
        progress: buildProgress({
            step: state.step,
            isPack: selected?.is_pack ?? false,
            productName: selected?.name ?? '',
            date: state.selectedDate ?? null,
            time: state.selectedTime ?? null,
            messages,
            locale: state.locale ?? 'es',
        }),
        footer: buildFooter({
            step: state.step,
            messages,
            locale: state.locale ?? 'es',
            cartCount: quote?.lines?.length ?? 0,
            cartTotalCents: quote?.total_cents ?? 0,
            cartOnlineCents: quote?.online_amount_cents ?? 0,
            hasDate: (state.selectedDate ?? null) !== null,
            hasTime: (state.selectedTime ?? null) !== null,
            lineTotalCents: line?.total_cents ?? null,
            lineHasDeposit: line?.has_deposit ?? false,
            lineDepositCents: line?.deposit_cents ?? 0,
            lineGateRemainderCents: line?.gate_remainder_cents ?? 0,
        }),
        messages,
        ui,
    };
}

async function main() {
    const input = await new Promise((resolve, reject) => {
        let raw = '';
        process.stdin.setEncoding('utf8');
        process.stdin.on('data', (chunk) => { raw += chunk; });
        process.stdin.on('end', () => resolve(raw));
        process.stdin.on('error', reject);
    });

    const { step, props = {}, shell = null, api = null, messages = {}, state = {}, ui = {}, shellFromServer = true } = JSON.parse(input);
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

    // ⚠️ El armazón se construye aquí SOLO si se pide: los pasos que aún no se han migrado siguen
    // pasándolo cocinado, y mezclar las dos cosas en silencio escondería cuál de los doce montajes
    // sigue comparando contra el servidor.
    const frame = (! shellFromServer && api !== null) ? shellFromApi(api, messages, ui, state) : shell;

    // ⚠️ **Con el aviso de PAUSA no hace falta paso**, y eso es lo fiel al Blade: el aviso sustituye el
    // contenido ENTERO, así que no hay ranura que rellenar. Sin esta salida no se podrían comparar los
    // pasos 5 y 8 —donde el servidor también tapa el flujo— porque todavía no están transcritos y el
    // script abortaría con un mensaje que habla de otra cosa.
    const paused = frame !== null && frame.notice;

    if (! component && ! paused) {
        process.stderr.write(`El paso ${step} todavía no está transcrito a Vue.\n`);
        process.exit(2);
    }

    // Con armazón, el paso va en la ranura por defecto de `Shell` — igual que en `Sidebar.vue`, para
    // que lo que compara el gate sea la composición real y no una aproximación.
    const app = frame === null
        ? createSSRApp(component, resolved)
        : createSSRApp({ render: () => h(Shell, frame, paused ? null : { default: () => h(component, resolved) }) });

    app.use(createPinia());

    process.stdout.write(await renderToString(app));
}

main().catch((e) => {
    process.stderr.write(String(e?.stack || e) + '\n');
    process.exit(1);
});
