<script setup>
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';
import { usePurchaseStore } from './stores/purchase.js';
import { useDateStore } from './stores/date.js';
import { useTimeStore } from './stores/time.js';
import { STEPS, isOutcome } from './machine.js';
import { api } from './api.js';
import { searchIsEnabled, sectionsFrom } from './catalog.js';
import { initialQuantity, minQuantityFor } from './offer.js';
import { buildProgress } from './progress.js';
import { t as translate, tp as translateWith } from './i18n.js';
import { buildFooter } from './foot.js';
import { buildNotice } from './paused.js';
import { continueAfterIdentification, runCheckout } from './admission.js';
import { runLogin } from './login.js';
import { runConfirm } from './pay.js';
import { declinedReasonText, loadConfirmation, loadPaymentStatus, pollVerdict, runRetry } from './outcome.js';
import { runRegister, signupRequiresCaptcha } from './register.js';
import {
    addLine, cartRows, clear as clearStoredCart, decideOwnership, hasPendingEventFields,
    load as loadStoredCart, reconcile, removeLine as removeCartLine, save as saveCart, toApiItems,
} from './cart.js';
import Shell from './Shell.vue';
import CatalogStep from './steps/CatalogStep.vue';
import DateStep from './steps/DateStep.vue';
import TimeStep from './steps/TimeStep.vue';
import CartStep from './steps/CartStep.vue';
import IdentifyStep from './steps/IdentifyStep.vue';
import VerifyStep from './steps/VerifyStep.vue';
import PayStep from './steps/PayStep.vue';
import RedirectStep from './steps/RedirectStep.vue';
import ConfirmedStep from './steps/ConfirmedStep.vue';
import DeclinedStep from './steps/DeclinedStep.vue';
import VerifyingStep from './steps/VerifyingStep.vue';

/**
 * La raíz del cajón SPA.
 *
 * **El nodo raíz emite `class="purchase"` y eso no es decorativo**: el contrato visual es el ÁRBOL
 * (§4.2), y 90 de los 292 selectores que estilan el cajón son estructurales o dependen del tipo de
 * elemento. Lo verifica `SidebarDomContractTest` paso a paso.
 *
 * **Aquí se PIDEN los datos; los pasos solo pintan.** Esa separación es lo que hace verificable la
 * paridad: los componentes de paso reciben todo por props, así que se pueden renderizar en Node —sin
 * red ni navegador— y comparar con lo que emite Livewire.
 */
const props = defineProps({
    /** El grupo `tickets` del locale activo, inyectado por el servidor en el montaje (§4.5). */
    messages: { type: Object, default: () => ({}) },

    /** El grupo `ui` (hoy, solo el rótulo del velo de carga). Va aparte: son dos grupos de `lang/`. */
    ui: { type: Object, default: () => ({}) },

    /**
     * El grupo `account`, PODADO a lo que el paso de identificación pinta (§4.5).
     *
     * ⚠️ Va aparte y con su camino real (`account.login.email`) por lo mismo que `ui`: son grupos
     * distintos de `lang/`, y fundirlos aquí crearía una tercera forma del diccionario. Medido: el
     * grupo entero son 9,6 kB en español —tanto como `tickets`— para pintar diez rótulos.
     */
    account: { type: Object, default: () => ({}) },

    /** El grupo `auth`: los dos avisos del login (`failed`, `throttle`). Tres claves. */
    auth: { type: Object, default: () => ({}) },

    /**
     * Quién es el titular al cargar la página, inyectado por el servidor. `null` = visitante anónimo.
     *
     * ⚠️ Llega con el HTML a propósito: el LOGOUT es una navegación completa, y es justo el caso que
     * la sesión resolvía sola con `invalidate()` y que `localStorage` no tiene. Esperar a un `fetch`
     * dejaría una ventana en la que la cesta de quien acaba de salir sigue en pantalla.
     */
    userId: { type: [Number, String], default: null },

    /**
     * El código del pedido del que habla el desenlace de la pasarela. Cadena vacía si no hay ninguno.
     *
     * ⚠️ **Es lo ÚNICO que sobrevive a la ida a la pasarela.** Volver de Redsys es una navegación
     * completa desde otro dominio: el motor se remonta de cero y nada de lo que el cajón sabía sigue
     * ahí, ni siquiera el pedido que acaba de crear. Lo posee `Http\Sidebar\SidebarEntry` —el mismo
     * dueño que el `outcome`, y por el mismo motivo— y viaja ya CONSUMIDO.
     */
    orderCode: { type: String, default: '' },

    /**
     * Las rutas que el cajón pinta y no puede componer: `contact` y `my_orders`.
     *
     * ⚠️ Vienen del servidor con `route()` a propósito. Quemarlas aquí sería una segunda fuente de una
     * URL que decide `routes/web.php`, y el fallo sería INVISIBLE: `href` no es atributo de contrato del
     * diff de árbol, así que un enlace a un 404 pasaría el gate en verde.
     */
    urls: { type: Object, default: () => ({}) },
});

const store = usePurchaseStore();

const sections = ref([]);
const searchEnabled = ref(false);

/**
 * El estado de las reservas (`GET /booking/status`).
 *
 * ⚠️ Es ESTADO y se relee; el objeto `notice` que trae viaja SIEMPRE, también con las reservas
 * abiertas, así que **el único bit de pausa es `reservations_paused`**.
 */
const bookingStatus = ref(null);

/**
 * Relee el estado de las reservas.
 *
 * ⚠️ **Se llama en CADA apertura del cajón, no solo al montar**, y el matiz es el paso entero: el
 * motor SPA se monta **una vez por carga de página** y no se desmonta nunca, así que una lectura solo
 * en `onMounted` sería exactamente el snapshot que este endpoint existe para evitar — una pestaña
 * abierta antes del interruptor seguiría vendiendo hasta que alguien recargara. El motor Livewire no
 * tiene ese problema porque reevalúa la guarda en cada render.
 *
 * Un fallo de red NO inventa una pausa: se conserva lo último que se supo, que es la conducta segura
 * (el servidor rechaza igual al crear el pedido).
 */
async function refreshBookingStatus() {
    const response = await api.get('/booking/status');

    if (response.ok) bookingStatus.value = response.data;

    // Devuelve si se pudo releer: el paso al pago lo necesita para no dejar un clic mudo cuando el
    // veredicto dice «pausa» y el estado que pintaría el cartel no llega.
    return response.ok;
}

/**
 * Peticiones en vuelo. Es lo que enciende el velo de carga del armazón, y es un CONTADOR y no un
 * booleano a propósito: el cajón lanza pares de peticiones en paralelo (días + ficha del producto), y
 * con un booleano la primera en volver apagaría el velo mientras la otra sigue.
 */
const inFlight = ref(0);
const busy = computed(() => inFlight.value > 0);

/** Envuelve una llamada para que cuente en el velo. No cambia el resultado ni traga errores. */
async function tracked(promise) {
    inFlight.value++;

    try {
        return await promise;
    } finally {
        inFlight.value--;
    }
}

/** Lo que el paso 2 necesita. Llega de la API; ninguna regla de oferta se decide aquí (`AFORO-02`). */
const selectedProductId = ref(null);

/**
 * El DÍA vive en su propio store (`stores/date.js`, reorganización del 2026-08-22): los días
 * ofrecidos, el elegido, el mes visible y todo lo que de ahí se deriva —la rejilla, los meses
 * navegables, los rótulos y el precio del día—.
 *
 * ⚠️ La composición de la rejilla sigue siendo de `calendar.js`, y su paridad contra el servidor la
 * fija `SidebarCalendarParityTest` dato a dato: mover el estado no mueve la regla.
 */
const dateStore = useDateStore();

/**
 * El PUENTE de señales hacia fuera del cajón.
 *
 * ⚠️ Sin esto, dos regresiones silenciosas: el panel se queda en `is-catalog` para siempre y los
 * botones de invitado siguen activos durante la identificación. Ninguna de las dos clases aparece en
 * el marcado del cajón —viven en `layout.blade.php` y en `account-context`—, así que no se ve nada
 * roto aquí dentro. Lo escribe EL MOTOR, sea cual sea.
 */
watch(
    () => store.step,
    (step, previous) => {
        const alpine = window.Alpine?.store('purchase');
        if (! alpine) return;

        alpine.setMode(store.mode);
        alpine.identifying = store.identifying;

        // ⚠️ **El confeti también es del store de Alpine, no del cajón**, y por eso se dispara desde
        // aquí: `celebrate()` respeta `prefers-reduced-motion` y saca sus colores de los tokens de
        // marca, así que reimplementarlo en Vue sería una segunda celebración que se olvidaría de las
        // dos cosas. Solo al ENTRAR en el paso: con `immediate` en el montaje se repetiría en cada
        // repintado, y el Blade lo ata a un `x-init` que corre una vez.
        if (step === STEPS.CONFIRMED && previous !== STEPS.CONFIRMED) alpine.celebrate?.();
    },
    { immediate: true },
);

/**
 * ⚠️ **El sondeo se para al SALIR del paso 11, y va en su propio observador a propósito.**
 *
 * El de arriba se rinde en cuanto no hay store de Alpine —es lo correcto para lo que hace: publicar
 * señales hacia fuera—, así que colgar de él la parada dejaría el temporizador vivo en cualquier página
 * sin Alpine. Un intervalo suelto no rompe nada visible: sigue preguntando a la API cada cinco
 * segundos, gastando fichas del limitador, y **ningún diff de árbol puede verlo**.
 */
watch(() => store.step, (step) => {
    if (step !== STEPS.VERIFYING) stopPolling();
});

/** Y al desmontar el motor. Es la otra forma de dejarlo suelto, y la que no avisa. */
onUnmounted(stopPolling);

/**
 * El catálogo se pide al MONTAR, y montar ocurre al abrir el cajón (§4.7).
 *
 * ⚠️ Ese reparto es lo que respeta `PERF-02`: una raíz que pidiera catálogo al cargar la página
 * añadiría una petición por visita en la ruta de más tráfico del sitio.
 */
onMounted(async () => {
    // ⚠️ El estado de la pausa se PIDE y no se inyecta en el montaje, y está decidido: `/config` es
    // estático por despliegue, pero la pausa la acciona la dueña **con clientes navegando**. Una
    // landing abierta horas con un snapshot mentiría desde el segundo en que se toca el interruptor.
    const [catalog, config] = await tracked(Promise.all([
        api.get('/catalog/products'),
        api.get('/config'),
        refreshBookingStatus(),
    ]));

    if (catalog.ok) sections.value = sectionsFrom(catalog.data?.data ?? []);

    // El umbral lo decide el SERVIDOR y viaja con su operador en la descripción del contrato
    // (`total > umbral`): el cliente compara, no reinventa la regla.
    if (config.ok) {
        const threshold = config.data?.catalog_search_min_items;
        searchEnabled.value = searchIsEnabled(sections.value, threshold);
        // El tope de líneas lo publica el servidor: quemarlo aquí sería el cuarto sitio del que leer
        // el mismo número.
        if (typeof config.data?.cart_max_lines === 'number') maxCartLines.value = config.data.cart_max_lines;
        // ⚠️ El BIT del anti-bot, no su clave: no nulo ⟺ el alta exige captcha, y entonces el cajón
        // el cajón monta su propio widget de Turnstile con ella (`turnstile.js`, 4.4b·2).
        signupSiteKey.value = signupRequiresCaptcha(config.data) ? config.data.turnstile_site_key : '';
        // El enlace de registro del parque, que solo pinta el paso 6. Llega ya SANEADO (`SEC-07`): lo
        // edita un operador y un cliente JSON no tiene escape de plantilla que remate la defensa.
        registration.value = config.data?.registration ?? null;
    }

    // ⚠️ **En paralelo y no en cadena**: son independientes, y con un desenlace en pantalla el cliente
    // acaba de pagar — encadenarlas le regalaría la espera de la cesta antes de ver su reserva.
    await Promise.all([restoreCart(), loadOutcome()]);
});

/**
 * Restaura la cesta guardada y la deja lista para pintarse.
 *
 * Tres cosas en orden, y ninguna es opcional:
 *  1. **saneado y purga** las hace el módulo (formato, titular, caducidad y tope);
 *  2. **las ETIQUETAS de los campos del pack** se piden para los productos restaurados. El presupuesto
 *     no devuelve las respuestas del evento —son datos de un menor— y sin el esquema no habría con qué
 *     emparejarlas; da igual que vuelvan vacías: el esquema también dice si la línea está incompleta;
 *  3. **el presupuesto**, que además reconcilia y borra lo que ya no vale.
 *
 * ⚠️ Y como la web: con cesta, el cajón abre EN el carrito.
 */
async function restoreCart() {
    const { lines } = loadStoredCart(storage(), {
        owner: cartOwner.value,
        today: today(),
        maxLines: maxCartLines.value,
    });

    if (lines.length === 0) {
        return;
    }

    cart.value = lines;

    const ids = [...new Set(lines.map((line) => line.product_id))];
    const details = await tracked(Promise.all(ids.map((id) => api.get(`/catalog/products/${id}`))));

    const fields = { ...fieldsByProduct.value };
    details.forEach((response, i) => {
        if (response.ok) fields[ids[i]] = response.data.event_fields ?? [];
    });
    fieldsByProduct.value = fields;

    await refreshQuote();

    // ⚠️ **El desenlace MANDA sobre la cesta, y el orden es el de Livewire**: su `mount()` coloca el
    // paso 4 si hay cesta y **después** deja que la vuelta de la pasarela lo pise. Aquí la cesta se
    // restaura igual —quien pulse «hacer otra reserva» la encuentra— pero no navega. No es teórico: la
    // cesta vive en `localStorage`, así que otra pestaña puede haberla llenado mientras se pagaba en
    // ésta, y quien vuelve de pagar aterrizaría en un carrito en vez de en su reserva confirmada.
    // La precedencia vive en `machine.js` porque aquí dentro no tendría red (`CE-6`).
    if (cart.value.length > 0 && ! isOutcome(store.step)) store.enter(STEPS.CART);
}

/**
 * Elegir producto lleva al calendario y pide su oferta de días.
 *
 * ⚠️ Los días **no se calculan**: `GET availability/{id}/dates` los devuelve ya filtrados por
 * `SlotOffer` (`AFORO-02`), con su precio y su clave de tarifa. Componer la rejilla del mes a partir
 * de ellos sí es presentación.
 */
async function selectProduct(id) {
    selectedProductId.value = id;
    // El nombre y el tipo salen del CATÁLOGO, que ya está en memoria, y no de la ficha que se está
    // pidiendo: la banda de progreso los enseña de inmediato al entrar en el calendario, y esperar a
    // la ficha dejaría el contexto en blanco durante el viaje.
    selectedProduct.value = sections.value.flatMap((s) => s.items).find((item) => item.id === id) ?? null;
    // La FICHA trae lo que el paso 3 necesita y el listado no lleva: el mínimo contratable y el
    // esquema de campos del evento, ya resueltos al idioma. Se pide junto a los días, no después,
    // porque los dos hacen falta antes de que el cliente pueda elegir nada.
    product.value = null;
    dateStore.clearSelection();
    timeStore.clearSelection();
    addons.value = { groups: [], singles: [] };
    addonChoices.value = [];
    addonQuantities.value = [];
    line.value = null;
    resolvedSelection.value = [];
    eventData.value = {};
    store.go(STEPS.DATE);

    const [dates, detail] = await tracked(Promise.all([
        api.get(`/availability/${id}/dates`),
        api.get(`/catalog/products/${id}`),
    ]));

    dateStore.setOffer(dates.ok ? (dates.data?.data ?? []) : []);

    if (detail.ok) {
        product.value = detail.data;
        // Las ETIQUETAS del esquema del evento se guardan por producto porque el carrito las necesita
        // más tarde, cuando ya se está mirando otra cosa: el presupuesto NO devuelve las respuestas
        // del pack (RGPD, son datos de un menor) y sin las etiquetas no hay con qué emparejarlas.
        fieldsByProduct.value = { ...fieldsByProduct.value, [id]: detail.data.event_fields ?? [] };
    }
}

/** Lo que el paso 3 necesita. Todo llega de la API; aquí no se decide nada (`CE-4`). */
const timeStore = useTimeStore();
/** La fila del CATÁLOGO del producto elegido (nombre y tipo), disponible sin esperar a la ficha. */
const selectedProduct = ref(null);
const quantity = ref(0);
const product = ref(null);
const addons = ref({ groups: [], singles: [] });
/** El pie de la línea del paso 3, tal y como lo publica el endpoint de complementos. Nunca se suma. */
const line = ref(null);
/** La selección de complementos ya RESUELTA por el servidor: es lo que se guarda en la cesta. */
const resolvedSelection = ref([]);

// ── La CESTA ──────────────────────────────────────────────────────────────────────────────────
//
// En memoria en 4.3·2. La persistencia en `localStorage` —con su dueño, su purga al cambiar de
// identidad y su reconciliación contra el presupuesto— llega en 4.3·3, y por eso el módulo `cart.js`
// no toca el almacén: se le pasará por parámetro.

/** Las líneas tal y como viajan a la API (`product_id`, `quantity`). */
const cart = ref([]);

/**
 * De quién es la cesta que hay en memoria. Empieza siendo la del titular que pintó la página.
 *
 * Se compara con la identidad de cada momento en `decideOwnership()`, que tiene las cinco casillas —
 * incluida la que el servidor no tiene, porque allí el logout vacía la sesión entera.
 */
const cartOwner = ref(props.userId ?? null);

/** El tope de líneas lo publica `GET /config`; no se quema aquí (`cart_max_lines`). */
const maxCartLines = ref(50);

/**
 * El almacén del navegador, o `null` si no se puede usar.
 *
 * ⚠️ El acceso a la PROPIEDAD es lo que lanza (`SecurityError` con los datos de sitio bloqueados o en
 * un iframe sin `allow-same-origin`), no `getItem`. Por eso va dentro del `try` y el módulo de cesta
 * lo recibe por parámetro: así se puede doblar en las pruebas, donde no existe.
 */
function storage() {
    try {
        return window.localStorage ?? null;
    } catch {
        return null;
    }
}

/** El día de HOY en el huso del navegador, para caducar las líneas de días pasados. */
function today() {
    const now = new Date();
    const pad = (n) => String(n).padStart(2, '0');

    return `${now.getFullYear()}-${pad(now.getMonth() + 1)}-${pad(now.getDate())}`;
}

/** Persiste la cesta. Nunca con `event_data`: eso lo garantiza el módulo (`DECISIONES #38(d)`). */
function persist() {
    saveCart(storage(), { owner: cartOwner.value, lines: cart.value });
}
/** El presupuesto de la cesta. Lo tarifica `POST /orders/quote`; aquí no se suma nada (`PAY-12`). */
const quote = ref(null);
/** El aviso de la cesta, ya traducido. Ocupa el sitio del `@error('cart')` del Blade. */
const cartError = ref('');
/** Errores por campo del evento, con la misma forma que el error bag de la web. */
const fieldErrors = ref({});

/**
 * El badge y el recuento salen del PRESUPUESTO, no de `cart.length`.
 *
 * ⚠️ Fue un fallo real de la web (P8): contar el array local incluye las líneas cuyo producto dejó de
 * venderse —que el presupuesto no tarifica— y el badge decía «1 artículo» sobre un total de 0,00 €.
 */
const cartCount = computed(() => quote.value?.lines?.length ?? 0);

/**
 * Las filas que pinta el carrito: cada línea tarificada con las respuestas del pack emparejadas.
 *
 * ⚠️ El emparejado va por `index` y no por posición: una línea no vendible desaparece del presupuesto
 * y su hueco en la secuencia es la única señal de que existió.
 */
const cartLines = computed(() => cartRows(quote.value?.lines ?? [], cart.value, fieldsByProduct.value));

/** Etiquetas de los campos del evento por producto, para poder emparejarlas en el carrito. */
const fieldsByProduct = ref({});

/** Pide el presupuesto de la cesta actual. Es la ÚNICA fuente de los importes del carrito. */
async function refreshQuote() {
    if (cart.value.length === 0) {
        quote.value = null;

        return;
    }

    const response = await tracked(api.post('/orders/quote', { items: toApiItems(cart.value) }));

    quote.value = response.ok ? response.data : null;

    if (! response.ok) {
        return;
    }

    // ⚠️ **Reconciliar y volver a presupuestar es UNA sola operación.** Podar desplaza los índices, y
    // las filas y el botón de quitar se emparejan por el `index` del PRESUPUESTO: pintar el viejo
    // sobre la cesta podada enseñaría las respuestas de otra línea y dejaría el botón mudo.
    const { lines, changed } = reconcile(cart.value, quote.value.lines ?? []);

    if (changed) {
        cart.value = lines;
        persist();
        quote.value = null;

        await refreshQuote();
    }
}
const eventData = ref({});
const addonChoices = ref([]);
const addonQuantities = ref([]);

const minQuantity = computed(() => minQuantityFor(product.value));


/**
 * Elegir día pide las HORAS, y la petición **lleva la cesta**.
 *
 * ⚠️ `AFORO-02`: `offerableTimes()` descuenta los ocupantes que la propia cesta ya retiene, así que
 * una consulta sin cesta ofrece horas que el checkout rechazaría. Hoy la cesta va vacía porque el
 * paso 4 llega después; el cuerpo ya viaja con su clave para que no se olvide al añadirla.
 */
async function selectDate(date) {
    dateStore.selected = date;
    timeStore.clearSelection();
    store.go(STEPS.TIME);

    // ⚠️ `AFORO-02`: la oferta de horas **lleva la cesta**. `offerableTimes()` descuenta los ocupantes
    // que la propia cesta ya retiene, así que una consulta sin ella ofrece horas y topes que el
    // checkout rechazaría. Hasta 4.3·2 iba vacía porque no había cesta; ahora va la de verdad.
    const response = await tracked(api.post(`/availability/${selectedProductId.value}/times`, {
        date,
        items: toApiItems(cart.value),
    }));

    timeStore.setOffer(response.ok ? (response.data?.data ?? []) : []);
}

/** Elegir hora fija la cantidad en el mínimo contratable y resuelve los complementos. */
async function selectTime(time) {
    timeStore.select(time);
    // ⚠️ La regla y su equivalencia con la del servidor —que PARECE distinta y no lo es— viven en
    // `offer.js` con la medición que lo demuestra.
    quantity.value = initialQuantity(product.value, timeStore.offered, time);

    await refreshAddons();
}

/**
 * Los complementos RESUELTOS contra la selección actual.
 *
 * ⚠️ La partición en grupos, las notas, las unidades gratis, los topes y la poda EN CADENA de las
 * dependencias las decide el servidor: reimplementarlas aquí es lo que `CE-4` prohíbe. La respuesta
 * trae además el dinero de la línea, para que un clic no cueste dos peticiones.
 */
async function refreshAddons() {
    if (! selectedProductId.value || quantity.value < 1) return;

    const response = await tracked(api.post(`/catalog/products/${selectedProductId.value}/addons`, {
        quantity: quantity.value,
        date: dateStore.selected,
        time: timeStore.selected,
        addons: addonQuantities.value,
        choices: addonChoices.value,
    }));

    if (response.ok) {
        addons.value = { groups: response.data.groups, singles: response.data.singles };
        // ⚠️ **El dinero del paso 3 viene de aquí y no se compone.** `line.total_cents` lo publica el
        // endpoint desde 4.3·2 precisamente para que nadie sume `subtotal_cents` con
        // `addons_total_cents`: salen de dos recorridos distintos del servidor y pueden divergir.
        line.value = response.data.line ?? null;
        // Y la selección que hay que GUARDAR es la que el dominio acaba de resolver —obligatorios
        // inyectados, dependientes huérfanos podados—, no la que se pidió.
        resolvedSelection.value = response.data.selection ?? [];
    }
}

function changeQuantity(delta) {
    const next = quantity.value + delta;
    if (next < minQuantity.value || next > timeStore.maxQuantity) return;

    quantity.value = next;
    // La cantidad cambia lo que cuestan los complementos por-invitado: hay que volver a resolver.
    refreshAddons();
}

function chooseAddon(group, productId) {
    addonChoices.value = [...addonChoices.value.filter((c) => c.group !== group), { group, product_id: productId }];
    refreshAddons();
}

function setAddonQuantity(productId, qty) {
    addonQuantities.value = [...addonQuantities.value.filter((a) => a.product_id !== productId), { product_id: productId, quantity: qty }];
    refreshAddons();
}

/**
 * Las cabeceras de día y el nombre del mes, en el idioma del documento.
 *
 * ⚠️ El servidor los compone con Carbon y su locale; aquí se usa `Intl`, así que **el texto visible
 * puede diferir** (§4.5 lo declara y la paridad lo compara explícitamente). Es el único punto del
 * paso 2 donde los dos motores no comparten la fuente del texto.
 */
const locale = document.documentElement.lang || 'es';

// El store NO lee el DOM a propósito —así se prueba con `node --test` sin navegador—, así que el
// idioma se le INYECTA desde aquí, el único sitio del cajón que sí puede mirarlo.
dateStore.setLocale(locale);

/**
 * La banda de progreso de los pasos 2 y 3.
 *
 * ⚠️ **Hasta 4.3·1 esto era `null` fijo**, así que el cajón SPA vivo iba sin «Volver» y sin contador
 * de fases aunque el componente existiera y el gate lo comparase en verde: el diff alimenta a Vue con
 * el view-model del SERVIDOR. La composición vive en `progress.js` —módulo plano— para poder
 * compararla contra `bookingProgress()` dato a dato.
 */
const progress = computed(() => buildProgress({
    step: store.step,
    isPack: selectedProduct.value?.is_pack ?? false,
    productName: selectedProduct.value?.name ?? '',
    date: dateStore.selected,
    time: timeStore.selected,
    messages: props.messages,
    locale,
}));

/**
 * Vuelve a resolver quién es el titular y purga la cesta si ha cambiado.
 *
 * ⚠️ **Un fallo de red NO es un cierre de sesión.** Solo un 401 significa «ya no hay nadie»; con
 * cualquier otro fallo se conserva la identidad conocida, porque purgar por un corte de red destruiría
 * la cesta de quien no ha hecho nada malo — y no habría manera de recuperarla.
 */
async function refreshIdentity() {
    return applyIdentityFrom(await api.get('/me'));
}

/**
 * Traduce una respuesta de `GET /me` a la identidad de ahora y se la aplica a la cesta.
 *
 * Está aparte de `refreshIdentity()` desde 4.4a·1 porque el paso al pago pide `/me` **en paralelo**
 * con la elegibilidad —dos preguntas, un viaje— y necesita aplicar el resultado sin volver a pedirlo.
 * La regla del 401 vive aquí, en un solo sitio: es lo que impide que un corte de red purgue la cesta
 * de quien no ha hecho nada.
 *
 * @returns {'keep'|'purge'} qué se hizo con la cesta
 */
function applyIdentityFrom(response) {
    if (! response.ok && response.status !== 401) {
        return 'keep';
    }

    return applyIdentity(response.ok ? (response.data?.id ?? null) : null);
}

/**
 * Aplica una identidad a la cesta que hay en memoria.
 *
 * La purga alcanza a la cesta guardada Y a la de memoria: no basta con borrar el almacén, porque la
 * pantalla seguiría enseñando las líneas del titular anterior hasta la próxima recarga.
 *
 * @returns {'keep'|'purge'}
 */
function applyIdentity(newOwner) {
    const decision = decideOwnership(cartOwner.value, newOwner);

    if (decision === 'purge') {
        cart.value = [];
        quote.value = null;
        cartError.value = '';
        clearStoredCart(storage());
        store.enter(STEPS.CATALOG);
    }

    cartOwner.value = newOwner;

    return decision;
}

defineExpose({ refreshBookingStatus, refreshIdentity });

/**
 * El aviso de pausa, si toca en este paso (`paused.js`).
 *
 * ⚠️ No tapa los pasos de RESULTADO: quien vuelve de la pasarela tiene que ver en qué quedó su pago,
 * aunque las reservas se hayan pausado entre medias.
 */
const notice = computed(() => buildNotice({
    status: bookingStatus.value,
    step: store.step,
    messages: props.messages,
}));

/**
 * El PIE, compuesto para el estado actual (`foot.js`).
 *
 * Devolver `null` es tan significativo como devolver una barra: en el catálogo con la cesta vacía y
 * en la cesta vacía el servidor no emite pie, y pintarlo igual enseñaría «0,00 €» donde la web no
 * enseña nada.
 */
const footer = computed(() => buildFooter({
    step: store.step,
    messages: props.messages,
    locale,
    cartCount: cartCount.value,
    cartTotalCents: quote.value?.total_cents ?? 0,
    cartOnlineCents: quote.value?.online_amount_cents ?? 0,
    hasDate: dateStore.selected !== null,
    hasTime: timeStore.selected !== null,
    lineTotalCents: line.value?.total_cents ?? null,
    lineHasDeposit: line.value?.has_deposit ?? false,
    lineDepositCents: line.value?.deposit_cents ?? 0,
    lineGateRemainderCents: line.value?.gate_remainder_cents ?? 0,
}));

/**
 * Las acciones del pie, con los MISMOS nombres que las del componente Livewire.
 *
 * Se conservan los nombres porque son el vocabulario de la paridad durante la convivencia: el
 * view-model del pie los publica y el test los compara campo a campo.
 */
function runAction(action) {
    if (action === 'goToTime') return goToTime();
    if (action === 'addToCart') return addToCart();
    if (action === 'goToCart') return goToCart();
    if (action === 'checkout') return checkout();
    if (action === 'confirmReservation') return confirmReservation();
}

/**
 * Los pasos que el motor SPA sabe PINTAR hoy. Un veredicto que lleve a otro **no navega**.
 *
 * ⚠️ No es una precaución teórica: ir al paso 8 antes de 4.5 dejaría el cajón con el armazón y la
 * ranura vacía —velo, banda y pie, y nada dentro—, que es peor que un CTA mudo. La lista **solo
 * crece**: al transcribir el pago se añade aquí y en `render-sidebar.mjs`, que es su espejo.
 */
const TRANSCRIBED_STEPS = [
    STEPS.CATALOG, STEPS.DATE, STEPS.TIME, STEPS.CART, STEPS.IDENTIFY, STEPS.VERIFY_EMAIL,
    STEPS.PAY, STEPS.REDIRECTING, STEPS.CONFIRMED, STEPS.DECLINED, STEPS.VERIFYING,
];

/** Lleva el cajón al paso que diga el veredicto, si esa pantalla ya existe. */
function goToVerdict(step) {
    if (TRANSCRIBED_STEPS.includes(step)) store.go(step);
}

/**
 * «Ir a pagar». Espejo de `Purchase::checkout()`.
 *
 * **Aquí solo está el cableado**: preguntar, aplicar la identidad y decidir es `runCheckout()`, que
 * vive en `admission.js` porque la lógica que baja a un `.vue` pierde su red (CE-6) — un árbol no
 * dice a quién se preguntó, en qué orden, ni si la cesta se purgó por el camino.
 *
 * Desde 4.4a·2 el invitado SÍ llega a su pantalla; el paso de pago sigue sin transcribir (4.5), así
 * que ese destino lo filtra `goToVerdict()`.
 */
async function checkout() {
    const verdict = await tracked(runCheckout({
        cartCount: cart.value.length,
        // ⚠️ La guarda de las líneas incompletas la aplica el módulo, ANTES de preguntar nada al
        // servidor (`#38(d)`, 4.5·1): una cesta que no se puede comprar todavía no gasta dos peticiones.
        incompleteLines: hasPendingEventFields(cartLines.value),
        api,
        messages: props.messages,
        applyIdentity: applyIdentityFrom,
        // ⚠️ **La pausa se enseña releyendo el estado, no pintando un error**: el componente Livewire
        // escribe su mensaje en el bag y el cartel de mantenimiento lo tapa antes de que llegue a
        // pintarse (medido). Releer es además lo que cierra el residual de 4.3·3 — un cajón ya
        // ABIERTO cuando se acciona el interruptor no se enteraba hasta cerrarlo y volver a abrirlo.
        refreshStatus: refreshBookingStatus,
    }));

    // El titular cambió: la cesta ya se purgó y el cajón está en el catálogo. No hay compra que seguir.
    if (verdict.purged) {
        return;
    }

    cartError.value = verdict.error;
    goToVerdict(verdict.step);
}

// ── El paso 5: identificarse sin salir del cajón ──────────────────────────────────────────────

/**
 * Los campos de los DOS formularios, en un solo objeto.
 *
 * Juntos y no en dos refs porque el paso es uno: al salir se limpian de una vez, y **la contraseña no
 * puede sobrevivir** a un cambio de pantalla en una tablet compartida.
 */
const emptyForm = () => ({
    email: '', password: '', remember: false,
    name: '', phone: '', accept_privacy: false, accept_terms: false, marketing: false,
    // El señuelo: un cliente legítimo lo deja vacío y el servidor finge un alta si llega relleno.
    // Y el token del anti-bot, que escribe Cloudflare por callback (`turnstile.js`), no el usuario.
    website: '', turnstile_token: '',
});

const form = ref(emptyForm());

/** Lo que enseña cada formulario del último intento (`login.js` · `register.js`). */
const loginError = ref({ global: '', fields: {} });
const registerError = ref({ summary: [], fields: {} });

/** `true` mientras hay una petición de auth en vuelo: el botón cambia de rótulo, como en la web. */
const loggingIn = ref(false);

/** La pestaña activa del paso 5. */
const authMode = ref('login');

/**
 * ¿El alta exige captcha en esta instalación? Sale de `GET /config` (`turnstile_site_key`).
 *
 * ⚠️ **No nulo ⟺ el anti-bot está ACTIVO**, y esa equivalencia costó un arreglo del contrato: el
 * endpoint publicaba la clave pública aunque faltara la secreta, estado en el que la web **no pinta el
 * widget** y el servidor no verifica nada. Ahora el campo es el bit que decide.
 */
const signupSiteKey = ref('');

/**
 * Cambia de pestaña. Espejo de `Purchase::setAuthMode()`.
 *
 * ✅ **La delegación en el modal de Livewire se RETIRÓ en 4.4b·2**: el cajón monta su propio widget de
 * Turnstile (`turnstile.js` + `RegisterForm.vue`), así que ya pinta su formulario de alta también con
 * el anti-bot activo. Lo que había aquí era una degradación honesta mientras el widget no existía —sin
 * token, `SelfSignup` rechaza **todas** las altas con «no eres un robot», sin correo y sin log—, y su
 * único motivo era ese.
 * ⚠️ El modal de la cabecera NO desaparece con esto ni con `4.7·2b·3`: vive en `layout.blade.php`, no
 * en `purchase.blade.php`, y sigue siendo la puerta de auth de la web fuera del cajón.
 */
function setAuthMode(mode) {
    authMode.value = mode === 'register' ? 'register' : 'login';
    // Los avisos son de un intento que ya no se ve: arrastrarlos entre pestañas confunde.
    loginError.value = { global: '', fields: {} };
    registerError.value = { summary: [], fields: {} };
}

/**
 * Envía las credenciales y, si entra, continúa la compra donde la dejó.
 *
 * ⚠️ **Tres cosas pasan al entrar, y ninguna sobra**:
 *  1. **se avisa a Livewire** (`logged-in`). Fuera del cajón, `account-context` es un componente
 *     Livewire que escucha ese evento para repintar «Hola, saltador/a»; sin el aviso, el panel seguiría
 *     ofreciendo «Entrar» a alguien que acaba de entrar, y ningún test de este repo lo vería;
 *  2. **se aplica la identidad** con la respuesta del propio login —`POST auth/login` devuelve el
 *     perfil con la misma forma que `GET /me` justo para esto—, así que la cesta de invitado se queda
 *     con su nuevo dueño sin una petición más;
 *  3. **se continúa el checkout**, que es lo que hace `Purchase::onAuthenticated()` llamando a
 *     `proceed()`: quien se identifica con el tope de pendientes lleno tiene que enterarse aquí.
 */
async function submitLogin() {
    if (loggingIn.value) return;

    loggingIn.value = true;

    try {
        const result = await tracked(runLogin({
            credentials: form.value,
            api,
            messages: props.messages,
            auth: props.auth,
        }));

        loginError.value = result.errors;

        if (! result.ok) return;

        await enterWith(result.response);
    } finally {
        loggingIn.value = false;
    }
}

/**
 * Crea la cuenta y sigue la compra. Espejo de `Register::register()` embebido.
 *
 * ⚠️ **El 201 NO dice si hubo cuenta**, y por eso `runRegister()` pregunta después por `GET /me`: la
 * respuesta del alta es idéntica para un alta real y para un señuelo que actuó —si no lo fuera, un bot
 * distinguiría las dos de un vistazo—. Con sesión, la compra continúa como tras un login; sin ella, el
 * cajón va a «revisa tu correo», que es exactamente lo que hace la web con `registration-submitted`.
 */
async function submitRegister() {
    if (loggingIn.value) return;

    loggingIn.value = true;

    try {
        const result = await tracked(runRegister({
            form: form.value,
            api,
            messages: props.messages,
            auth: props.auth,
        }));

        registerError.value = result.errors;

        // Vaciarlo es la señal de «pide otro» para el widget (`RegisterForm` lo observa). Va en
        // CUALQUIER fallo, no solo en el del captcha: el token es de un solo uso y el servidor lo
        // quema antes de comprobar si el correo ya existe, así que un segundo intento con el mismo
        // token daría «no eres un robot» con el tick verde puesto. Y afinar más es imposible: los
        // tres desenlaces llegan como `validation_failed` bajo `fields.email`, indistinguibles.
        if (! result.ok) { form.value.turnstile_token = ''; return; }

        if (! result.identified) {
            // El señuelo actuó: misma pantalla que ve un alta legítima sin sesión. No se distingue.
            resetAuthForm();
            goToVerdict(STEPS.VERIFY_EMAIL);

            return;
        }

        await enterWith(result.me);
    } finally {
        loggingIn.value = false;
    }
}

/**
 * Lo que pasa cuando el cajón acaba de conseguir una sesión, venga de un login o de un alta.
 *
 * ⚠️ **Tres cosas, y ninguna sobra**:
 *  1. **se avisa a Livewire** (`logged-in`). Fuera del cajón, `account-context` es un componente
 *     Livewire que escucha ese evento para repintar «Hola, saltador/a»; sin el aviso, el panel seguiría
 *     ofreciendo «Entrar» a alguien que acaba de entrar, y ningún test de este repo lo vería;
 *  2. **se aplica la identidad** con la respuesta que ya se tiene —`POST auth/login` devuelve el perfil
 *     con la misma forma que `GET /me`, y el alta lo consulta—, así que la cesta de invitado se queda
 *     con su nuevo dueño sin una petición más;
 *  3. **se continúa el checkout**, que es lo que hacen `onAuthenticated()` y el alta embebida llamando
 *     a `proceed()`: quien entra con el tope de pendientes lleno tiene que enterarse aquí.
 */
async function enterWith(identity) {
    notifyLoggedIn();
    applyIdentityFrom(identity);
    resetAuthForm();

    const verdict = await tracked(continueAfterIdentification({
        cartCount: cart.value.length,
        me: identity,
        api,
        messages: props.messages,
        refreshStatus: refreshBookingStatus,
    }));

    cartError.value = verdict.error;
    goToVerdict(verdict.step);
}

/**
 * Avisa al resto de la página de que hay sesión.
 *
 * ⚠️ **El bus es el de Livewire y no un `CustomEvent` propio**: quien escucha es `account-context`, un
 * componente Livewire con `#[On('logged-in')]`, y Livewire solo atiende su propio canal. Con la SPA
 * montada, este motor ocupa el sitio del componente `Purchase`, que era quien lo emitía.
 */
function notifyLoggedIn() {
    window.Livewire?.dispatch('logged-in');
}

/** Deja los dos formularios en blanco. La contraseña no se queda en memoria más de lo necesario. */
function resetAuthForm() {
    form.value = emptyForm();
    loginError.value = { global: '', fields: {} };
    registerError.value = { summary: [], fields: {} };
}

// ── El paso 8: confirmar y salir hacia la pasarela ────────────────────────────────────────────

/** El formulario firmado que devuelve `POST /orders`. Mientras sea `null`, el paso 9 no pinta nada. */
const gateway = ref(null);

/**
 * El código del pedido en juego. Lo necesitan las pantallas de desenlace (4.6).
 *
 * Nace del montaje —lo que dejó la vuelta de la pasarela— y lo reescribe `confirmReservation()` con el
 * pedido recién creado. Las dos fuentes no compiten: cuando hay desenlace no hay compra en curso.
 */
const orderCode = ref(props.orderCode ?? '');

/** `true` mientras el pedido se está creando: impide el doble clic en el botón más caro del cajón. */
const confirming = ref(false);

/**
 * «Pagar con tarjeta». Espejo de `Purchase::confirmReservation()`.
 *
 * ⚠️ **Una sola petición, y no es una simplificación**: `POST /orders` admite consumiendo ficha, crea
 * el pedido con su ventana de retención (`AFORO-10`) y abre el cobro, **en ese orden**, porque el orden
 * es una regla del dominio (`CheckoutOrchestrator`, `DECISIONES #37`). Partirlo en dos llamadas desde
 * aquí sería reimplementar esa secuencia en una superficie nueva, que es lo que `CheckoutSequenceTest`
 * prohíbe fuera de `app/Domain`.
 *
 * ⚠️ **Y a partir del 201 el pedido EXISTE y retiene aforo.** Por eso la cesta se vacía aquí y no
 * antes, y por eso un fallo del formulario no se trata como «no ha pasado nada»: se avisa igual que el
 * 502 del puerto de pasarela, que es lo que el cliente ha vivido.
 */
async function confirmReservation() {
    if (confirming.value) return;

    confirming.value = true;

    try {
        const result = await tracked(runConfirm({
            items: toApiItems(cart.value),
            api,
            messages: props.messages,
        }));

        if (result.rereadStatus) {
            await tracked(refreshBookingStatus());
        }

        if (! result.ok) {
            cartError.value = result.error;
            // Espejo del componente Livewire: cualquier «no» al confirmar devuelve al CARRITO, que es
            // donde el cliente puede arreglarlo —quitar una línea, cambiar una franja—.
            goToVerdict(STEPS.CART);

            return;
        }

        // La reserva es firme: la cesta se vacía y se persiste vacía, para que una recarga no la
        // resucite y el cliente acabe comprando dos veces lo mismo.
        cart.value = [];
        quote.value = null;
        persist();

        cartError.value = '';
        orderCode.value = result.orderCode;
        gateway.value = result.form;
        store.go(STEPS.REDIRECTING);
    } finally {
        confirming.value = false;
    }
}

// ── El DESENLACE de la pasarela: los pasos 6, 10 y 11 ─────────────────────────────────────────

/**
 * El resumen del pedido que pinta el paso 6, o `null`.
 *
 * ⚠️ **`null` es un estado legítimo, no un fallo que haya que gritar**: el Blade pinta la pantalla
 * igual sin resumen —con su código de pedido y el aviso del correo—, y ese es el caso de quien perdió
 * la sesión entre la ida a la pasarela y la vuelta. Enseñarle «ha fallado algo» a quien acaba de pagar
 * sería mucho peor.
 */
const confirmation = ref(null);

/**
 * El bloque de «registro del parque» que publica `GET /config`, o `null`.
 *
 * Lo pinta SOLO el paso 6, igual que en el Blade. Se guarda del `/config` del montaje en vez de
 * pedirlo aparte: ya viaja en esa respuesta y una petición más en la pantalla del desenlace sería
 * regalar espera justo donde el cliente ya ha pagado.
 */
const registration = ref(null);

/**
 * El motivo del rechazo YA traducido que pinta el paso 10, o cadena vacía.
 *
 * ⚠️ Vacío significa «no se pudo preguntar», no «no hay motivo»: el servidor cae a `default` cuando no
 * conoce el código, así que con respuesta el bloque se pinta siempre. Es el espejo exacto del `null` de
 * Livewire, que solo aparece sin sesión o sin código de pedido.
 */
const declinedReason = ref('');

/**
 * Trae lo que el paso 6 enseña.
 *
 * ⚠️ **Solo si el cajón está de verdad en un desenlace.** El código del pedido llega en el montaje de
 * TODAS las páginas cuando hay uno en sesión; pedir su resumen sin estar en la pantalla que lo pinta
 * sería dinero de peticiones a cambio de nada.
 */
async function loadOutcome() {
    if (orderCode.value === '') {
        return;
    }

    if (store.step === STEPS.CONFIRMED) {
        confirmation.value = await tracked(loadConfirmation({ orderCode: orderCode.value, api }));

        return;
    }

    // El paso 10 necesita el motivo del rechazo, y sale del MISMO endpoint que sondea el paso 11: es
    // pequeño a propósito porque se pregunta en bucle. Livewire lo resuelve en `mount()` leyendo el
    // último `Payment` fallido; aquí lo pregunta quien lo pinta.
    if (store.step === STEPS.DECLINED) {
        const status = await tracked(loadPaymentStatus({ orderCode: orderCode.value, api }));

        // Sin respuesta no se pinta el bloque, que es el espejo del `null` de Livewire cuando no hay
        // sesión o el pedido no es de quien pregunta. Con respuesta SIEMPRE se pinta: el servidor cae a
        // `default` cuando no conoce el motivo, y el Blade también.
        declinedReason.value = status === null ? '' : declinedReasonText(props.messages, status.declined_reason);

        return;
    }

    if (store.step === STEPS.VERIFYING) startPolling();
}

// ── El paso 11: el sondeo ─────────────────────────────────────────────────────────────────────

/**
 * Cada cuánto se pregunta por el desenlace, en milisegundos.
 *
 * Son los mismos 5 s del `wire:poll.5s` de Livewire, y la cifra es paridad, no gusto. ⚠️ Lo que sí es
 * distinto es a QUIÉN le cuesta: el sondeo de Livewire va por su propio canal y éste gasta 12 fichas
 * por minuto del limitador genérico de la API (60/min, compartido con todo lo demás). Acelerarlo
 * estrecharía ese margen para el resto del cajón.
 */
const POLL_MS = 5000;

/** El temporizador del sondeo. `null` = no se está sondeando. */
let pollTimer = null;

/**
 * Arranca el sondeo del paso 11.
 *
 * ⚠️ **Un intervalo que no se para es un fallo que ningún diff de árbol puede ver**, y aquí hay dos
 * formas de dejarlo suelto: salir del paso 11 (el desenlace llegó) y desmontar el motor. Por eso hay
 * una sola función de parada, se llama desde las dos y `startPolling()` es idempotente — llamarla dos
 * veces dejaría dos temporizadores preguntando a la vez.
 */
function startPolling() {
    if (pollTimer !== null) return;

    pollTimer = setInterval(() => { void poll(); }, POLL_MS);
}

function stopPolling() {
    if (pollTimer === null) return;

    clearInterval(pollTimer);
    pollTimer = null;
}

/**
 * Una vuelta del sondeo. Espejo de `Purchase::checkPaymentStatus()`.
 *
 * ⚠️ **No pasa por `tracked()` y es deliberado**: encender el velo de carga cada cinco segundos haría
 * parpadear una pantalla cuyo mensaje es «espera». El velo es para lo que el cliente acaba de pedir.
 */
async function poll() {
    if (store.step !== STEPS.VERIFYING || orderCode.value === '') {
        stopPolling();

        return;
    }

    const verdict = pollVerdict(await loadPaymentStatus({ orderCode: orderCode.value, api }));

    if (verdict === 'wait') return;

    stopPolling();

    if (verdict === 'confirmed') {
        store.go(STEPS.CONFIRMED);
        // El paso 6 pide el pedido ENTERO, que este endpoint no trae: es pequeño justamente porque se
        // pregunta en bucle.
        await loadOutcome();

        return;
    }

    // Caducó antes de llegar la notificación. La plaza volvió al inventario, así que no hay nada que
    // reintentar: el cliente vuelve al catálogo con el mismo aviso que da la web.
    orderCode.value = '';
    cartError.value = t('errors.retry_expired');
    store.go(STEPS.CATALOG);
}

// ── El paso 10: el reintento ──────────────────────────────────────────────────────────────────

/** `true` mientras se reabre el cobro: alterna el rótulo del CTA y bloquea el doble clic. */
const retrying = ref(false);

/**
 * «Reintentar el pago». Espejo de `Purchase::retryPayment()`.
 *
 * ⚠️ **El pedido NO se toca si algo falla**, al revés que al crearlo: sigue vivo con su retención recién
 * extendida, así que el cliente puede volver a intentarlo desde esta misma pantalla. La regla es del
 * dominio (`ReservationCheckout::retry()`); aquí solo se traduce el «no».
 */
async function retryPayment() {
    if (retrying.value) return;

    retrying.value = true;

    try {
        const result = await tracked(runRetry({ orderCode: orderCode.value, api, messages: props.messages }));

        if (result.rereadStatus) await tracked(refreshBookingStatus());

        if (result.ok) {
            declinedReason.value = '';
            cartError.value = '';
            gateway.value = result.form;
            store.go(STEPS.REDIRECTING);

            return;
        }

        // ⚠️ **Este aviso hoy NO SE VE en el paso 10, y es fiel: Livewire tampoco lo pinta.** Su bloque
        // no lleva `@error('cart')` y el pie es nulo en los pasos de resultado, así que un reintento
        // denegado por pausa, por frecuencia o por la pasarela deja el botón mudo en los dos motores.
        // Medido, no supuesto. Está anotado como deuda de PRODUCTO en `DEUDA.md`: arreglarlo cambia la
        // web, no la transcripción.
        cartError.value = result.error;

        if (result.goTo === 'catalog') {
            orderCode.value = '';
            declinedReason.value = '';
            gateway.value = null;
            store.go(STEPS.CATALOG);
        }

        // La sesión se perdió por el camino: `retryPayment()` hace `$this->step = $user ? 1 : 5`, y esa
        // salida está declarada en el mapa de transiciones desde 4.6·2.
        if (result.goTo === 'identify') store.go(STEPS.IDENTIFY);
    } finally {
        retrying.value = false;
    }
}

/** Del calendario a la hora. Espejo de `Purchase::goToTime()`: exige día elegido. */
function goToTime() {
    if (dateStore.selected === null) return;

    store.go(STEPS.TIME);
}

/** Vuelve al carrito desde una compra en curso, si hay cesta. */
function goToCart() {
    if (cart.value.length > 0) store.go(STEPS.CART);
}

/**
 * Añade la línea elegida a la cesta.
 *
 * ⚠️ **No decide: pregunta.** Qué puede entrar —producto elegible, franja ofrecida, cantidad, tope de
 * líneas, campos obligatorios del pack— y en qué queda —cantidad efectiva y fusión— lo dice
 * `POST /cart/validate-line`, el mismo contrato que consume la compra web desde 4.0b·6. Lo que sí es
 * de esta pantalla es la PRESENTACIÓN del «no»: validar al pulsar en vez de deshabilitar el CTA, y un
 * aviso que NOMBRA los campos que faltan.
 */
async function addToCart() {
    cartError.value = '';
    fieldErrors.value = {};

    const candidate = {
        product_id: selectedProductId.value,
        date: dateStore.selected,
        time: timeStore.selected,
        quantity: quantity.value,
        event_data: { ...eventData.value },
        // Lo que se guarda es la selección que el dominio RESOLVIÓ (obligatorios inyectados,
        // dependientes huérfanos podados), no la que se pidió.
        addons: resolvedSelection.value,
    };

    // ⚠️ La candidata NO va dentro de `items`: `items` es lo que YA retiene cupo, y meterla ahí la
    // haría competir consigo misma y devolvería un tope menor del real.
    const response = await tracked(api.post('/cart/validate-line', {
        line: candidate,
        items: toApiItems(cart.value),
    }));

    if (! response.ok) {
        cartError.value = t('errors.choose_one');

        return;
    }

    const verdict = response.data;

    if (! verdict.valid) {
        showLineProblems(verdict.problems ?? []);

        return;
    }

    cart.value = addLine(cart.value, candidate, verdict);
    persist();
    clearSelection();
    store.go(STEPS.CART);

    await refreshQuote();
}

/**
 * Traduce el «no» del servidor a lo que esta pantalla enseña.
 *
 * Los tres motivos de SELECCIÓN —producto no elegible, franja no ofrecida, sin sitio— comparten aviso
 * a propósito: es el que el cajón ha enseñado siempre, y son el mismo callejón para quien mira el
 * paso 3. Los campos que faltan se resaltan uno a uno **y** se nombran en un resumen: sin las dos
 * cosas, un pack con cuatro campos deja al cliente adivinando cuál falla.
 *
 * ⚠️ Los `problems` vienen SIN contexto a propósito: el mínimo, el tope y las etiquetas ya los
 * publican `catalog/products/{id}` y `config`, y republicarlos sería un segundo sitio del que leer el
 * mismo valor.
 */
function showLineProblems(problems) {
    const missing = [];

    for (const problem of problems) {
        if (problem.reason === 'event_field_required' && problem.field) {
            fieldErrors.value = { ...fieldErrors.value, [problem.field]: t('errors.field_required') };

            const label = (product.value?.event_fields ?? []).find((f) => f.key === problem.field)?.label;
            if (label) missing.push(label);

            continue;
        }

        cartError.value = problem.reason === 'cart_full'
            ? t('errors.cart_too_large')
            : t('errors.choose_one');
    }

    if (missing.length > 0) {
        cartError.value = tp('errors.fields_missing', { fields: missing.join(', ') });
    }
}

/** Quita una línea. Con la cesta vacía se vuelve al catálogo, como hace la web. */
async function removeLine(index) {
    cart.value = removeCartLine(cart.value, index);
    persist();

    if (cart.value.length === 0) {
        quote.value = null;
        store.enter(STEPS.CATALOG);

        return;
    }

    await refreshQuote();
}

/**
 * Contesta un campo del evento de una línea de la CESTA (Fase 4 · paso 4.5·1).
 *
 * ⚠️ **Estas respuestas viven SOLO en memoria y no se persisten nunca.** Es la razón de que haya que
 * volver a pedirlas: son el nombre de un menor, su edad y sus alergias (`#38(d)`, art. 9 del RGPD).
 * Por eso aquí **no se llama a `persist()`** — y no es un olvido: `saveCart()` las descartaría de
 * todos modos, y el canario de `cart.test.js` busca centinelas en el volcado entero del almacén.
 */
function updateCartField(index, key, value) {
    const line = cart.value[index];
    if (! line) return;

    line.event_data = { ...(line.event_data ?? {}), [key]: value };
    // La fila se recompone sola: `cartLines` es un computed sobre `cart`.
    cartError.value = '';
}

/**
 * «Añadir otra reserva»: vuelve al catálogo con la selección limpia.
 *
 * Espejo de `Purchase::addAnother()`, incluido su barrido del contexto del pedido anterior —código,
 * formulario firmado y resumen—: no se ve en el paso 1, pero dejarlo latente es lo que hace que una
 * segunda compra herede el desenlace de la primera.
 */
function addAnother() {
    clearSelection();
    orderCode.value = '';
    gateway.value = null;
    confirmation.value = null;
    declinedReason.value = '';
    store.enter(STEPS.CATALOG);
}

/** Deja la SELECCIÓN en blanco sin tocar la cesta. Espejo de `Purchase::clearSelection()`. */
function clearSelection() {
    selectedProductId.value = null;
    selectedProduct.value = null;
    product.value = null;
    dateStore.clearSelection();
    timeStore.clearSelection();
    quantity.value = 0;
    eventData.value = {};
    addonChoices.value = [];
    addonQuantities.value = [];
    addons.value = { groups: [], singles: [] };
    line.value = null;
    resolvedSelection.value = [];
}

const t = (key) => translate(props.messages, key);
const tp = (key, params) => translateWith(props.messages, key, params);

/**
 * «Volver» de la banda. Espejo de `Purchase::back()`: desde la hora se DESHACE la elección de hora y
 * de cantidad —volver con la hora puesta dejaría el paso 2 mostrando un progreso que ya no aplica— y
 * desde el calendario se vuelve al catálogo.
 */
function goBack() {
    if (store.step === STEPS.TIME) {
        timeStore.clearSelection();
        quantity.value = 0;
        store.go(STEPS.DATE);

        return;
    }

    store.go(STEPS.CATALOG);
}
</script>

<template>
    <Shell :busy="busy" :progress="progress" :footer="footer" :notice="notice" :messages="messages" :ui="ui"
           @back="goBack" @action="runAction">
        <CatalogStep
            v-if="store.step === STEPS.CATALOG"
            :sections="sections"
            :search-enabled="searchEnabled"
            :messages="messages"
            @select="selectProduct" />

        <DateStep
            v-else-if="store.step === STEPS.DATE"
            :weeks="dateStore.weeks"
            :weekday-headers="dateStore.weekdayHeaders"
            :month-label="dateStore.monthLabel"
            :can-prev="dateStore.canPrev"
            :can-next="dateStore.canNext"
            :selected-date="dateStore.selected"
            :messages="messages"
            @select="selectDate"
            @prev-month="dateStore.shift(-1)"
            @next-month="dateStore.shift(1)" />

        <TimeStep
            v-else-if="store.step === STEPS.TIME"
            :times="timeStore.offered.map((t) => t.time)"
            :selected-time="timeStore.selected"
            :quantity="quantity"
            :min-quantity="minQuantity"
            :max-quantity="timeStore.maxQuantity"
            :is-pack="product?.type === 'pack'"
            :day-price-cents="dateStore.priceCents"
            :event-fields="product?.event_fields ?? []"
            :period-label="product?.period_label ?? ''"
            :addons="addons"
            :errors="fieldErrors"
            :messages="messages"
            @select-time="selectTime"
            @inc="changeQuantity(1)"
            @dec="changeQuantity(-1)"
            @update-field="(key, value) => (eventData[key] = value)"
            @choose-addon="chooseAddon"
            @toggle-addon="(id) => setAddonQuantity(id, addons.singles.find((a) => a.product_id === id)?.selected ? 0 : 1)"
            @inc-addon="(id) => setAddonQuantity(id, (addons.singles.find((a) => a.product_id === id)?.quantity ?? 0) + 1)"
            @dec-addon="(id) => setAddonQuantity(id, Math.max(0, (addons.singles.find((a) => a.product_id === id)?.quantity ?? 0) - 1))" />

        <CartStep
            v-else-if="store.step === STEPS.CART"
            :lines="cartLines"
            :error="cartError"
            :messages="messages"
            :locale="locale"
            @remove="removeLine"
            @add-another="addAnother"
            @update-field="updateCartField" />

        <IdentifyStep
            v-else-if="store.step === STEPS.IDENTIFY"
            v-model:form="form"
            :mode="authMode"
            :login-errors="loginError"
            :register-errors="registerError"
            :submitting="loggingIn"
            :messages="messages"
            :account="account"
            :turnstile-site-key="signupSiteKey"
            @back="goToCart"
            @set-mode="setAuthMode"
            @submit-login="submitLogin"
            @submit-register="submitRegister" />

        <VerifyStep
            v-else-if="store.step === STEPS.VERIFY_EMAIL"
            :messages="messages" />

        <PayStep
            v-else-if="store.step === STEPS.PAY"
            :lines="cartLines"
            :error="cartError"
            :messages="messages"
            :locale="locale"
            @back="goToCart" />

        <RedirectStep
            v-else-if="store.step === STEPS.REDIRECTING"
            :form="gateway"
            :messages="messages" />

        <ConfirmedStep
            v-else-if="store.step === STEPS.CONFIRMED"
            :confirmation="confirmation"
            :order-code="orderCode"
            :registration="registration"
            :messages="messages"
            :locale="locale"
            @add-another="addAnother" />

        <DeclinedStep
            v-else-if="store.step === STEPS.DECLINED"
            :order-code="orderCode"
            :reason="declinedReason"
            :retrying="retrying"
            :contact-url="urls.contact ?? ''"
            :messages="messages"
            @retry="retryPayment"
            @add-another="addAnother" />

        <VerifyingStep
            v-else-if="store.step === STEPS.VERIFYING"
            :order-code="orderCode"
            :orders-url="urls.my_orders ?? ''"
            :messages="messages" />
    </Shell>
</template>
