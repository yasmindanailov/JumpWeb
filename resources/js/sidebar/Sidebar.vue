<script setup>
import { computed, onMounted, ref, watch } from 'vue';
import { usePurchaseStore } from './store.js';
import { STEPS } from './machine.js';
import { api } from './api.js';
import { buildWeeks, monthOf, shiftMonth } from './calendar.js';
import { buildProgress } from './progress.js';
import { t as translate, tp as translateWith } from './i18n.js';
import { buildFooter } from './foot.js';
import { buildNotice } from './paused.js';
import { runCheckout } from './admission.js';
import {
    addLine, cartRows, clear as clearStoredCart, decideOwnership,
    load as loadStoredCart, reconcile, removeLine as removeCartLine, save as saveCart, toApiItems,
} from './cart.js';
import Shell from './Shell.vue';
import CatalogStep from './steps/CatalogStep.vue';
import DateStep from './steps/DateStep.vue';
import TimeStep from './steps/TimeStep.vue';
import CartStep from './steps/CartStep.vue';

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
     * Quién es el titular al cargar la página, inyectado por el servidor. `null` = visitante anónimo.
     *
     * ⚠️ Llega con el HTML a propósito: el LOGOUT es una navegación completa, y es justo el caso que
     * la sesión resolvía sola con `invalidate()` y que `localStorage` no tiene. Esperar a un `fetch`
     * dejaría una ventana en la que la cesta de quien acaba de salir sigue en pantalla.
     */
    userId: { type: [Number, String], default: null },
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
const offeredDates = ref([]);
const selectedDate = ref(null);
const month = ref(null);

/**
 * La rejilla del mes que se está viendo.
 *
 * Repartir los días ofrecidos en semanas es PRESENTACIÓN —y por eso puede vivir aquí—, pero la
 * composición tiene que dar exactamente lo mismo que la del servidor o el cajón enseñaría otro
 * calendario. `SidebarCalendarParityTest` compara las dos dato a dato, incluidos un mes que empieza
 * en domingo y tres husos horarios distintos.
 */
const weeks = computed(() => (month.value ? buildWeeks(month.value, offeredDates.value, selectedDate.value) : []));

/** Los meses navegables se acotan a los que tienen oferta: no se ofrece pasear por meses vacíos. */
const offeredMonths = computed(() => [...new Set(offeredDates.value.map((d) => monthOf(d.date)))].sort());
const canPrev = computed(() => offeredMonths.value.length > 0 && month.value > offeredMonths.value[0]);
const canNext = computed(() => offeredMonths.value.length > 0 && month.value < offeredMonths.value[offeredMonths.value.length - 1]);

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
    () => {
        const alpine = window.Alpine?.store('purchase');
        if (! alpine) return;

        alpine.setMode(store.mode);
        alpine.identifying = store.identifying;
    },
    { immediate: true },
);

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

    if (catalog.ok) sections.value = groupIntoSections(catalog.data?.data ?? []);

    // El umbral lo decide el SERVIDOR y viaja con su operador en la descripción del contrato
    // (`total > umbral`): el cliente compara, no reinventa la regla.
    if (config.ok) {
        const threshold = config.data?.catalog_search_min_items;
        searchEnabled.value = typeof threshold === 'number' && totalItems(sections.value) > threshold;
        // El tope de líneas lo publica el servidor: quemarlo aquí sería el cuarto sitio del que leer
        // el mismo número.
        if (typeof config.data?.cart_max_lines === 'number') maxCartLines.value = config.data.cart_max_lines;
    }

    await restoreCart();
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

    if (cart.value.length > 0) store.enter(STEPS.CART);
}

function totalItems(list) {
    return list.reduce((n, section) => n + section.items.length, 0);
}

/**
 * Agrupa el catálogo plano en las DOS secciones que el cajón enseña.
 *
 * Es presentación, no negocio: qué se vende ya lo decidió `ProductCatalog`, y lo único que se hace
 * aquí es repartir por `type` en el mismo orden en que llegan — que es el `position` del panel.
 */
function groupIntoSections(products) {
    return [
        { key: 'entries', items: products.filter((p) => p.type === 'entry').map(toItem) },
        { key: 'services', items: products.filter((p) => p.type === 'pack').map(toItem) },
    ];
}

/** El producto de la API → la fila que el catálogo pinta. Renombra; no calcula. */
function toItem(product) {
    return {
        id: product.id,
        name: product.name,
        is_pack: product.type === 'pack',
        featured: product.featured ?? false,
        badge: product.badge ?? '',
        features: (product.features ?? []).join(' · '),
        from: product.from_price_cents ?? null,
        period_label: product.period_label ?? '',
        deposit_label: product.deposit_label ?? '',
        search: [product.name, ...(product.features ?? [])].join(' ').toLowerCase(),
    };
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
    selectedDate.value = null;
    selectedTime.value = null;
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

    offeredDates.value = dates.ok ? (dates.data?.data ?? []) : [];

    if (detail.ok) {
        product.value = detail.data;
        // Las ETIQUETAS del esquema del evento se guardan por producto porque el carrito las necesita
        // más tarde, cuando ya se está mirando otra cosa: el presupuesto NO devuelve las respuestas
        // del pack (RGPD, son datos de un menor) y sin las etiquetas no hay con qué emparejarlas.
        fieldsByProduct.value = { ...fieldsByProduct.value, [id]: detail.data.event_fields ?? [] };
    }
    // El calendario abre en el PRIMER mes con oferta, no en el actual: si el producto no se vende
    // hasta dentro de dos meses, abrir en «hoy» enseñaría una rejilla vacía.
    month.value = offeredMonths.value[0] ?? monthOf(new Date().toISOString().slice(0, 10));
}

/** Lo que el paso 3 necesita. Todo llega de la API; aquí no se decide nada (`CE-4`). */
const offeredTimes = ref([]);
const selectedTime = ref(null);
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

/** La hora elegida, con sus dos números. ⚠️ El selector se acota con `max_quantity`, NO con `available`. */
const offeredTime = computed(() => offeredTimes.value.find((t) => t.time === selectedTime.value) ?? null);
const maxQuantity = computed(() => offeredTime.value?.max_quantity ?? 0);
const minQuantity = computed(() => (product.value?.type === 'pack' ? (product.value?.min_quantity ?? 1) : 1));

/** El precio del DÍA elegido. Lo trae la oferta de días; no se deriva del «desde» del catálogo. */
const dayPriceCents = computed(() => offeredDates.value.find((d) => d.date === selectedDate.value)?.price_cents ?? null);

/**
 * Elegir día pide las HORAS, y la petición **lleva la cesta**.
 *
 * ⚠️ `AFORO-02`: `offerableTimes()` descuenta los ocupantes que la propia cesta ya retiene, así que
 * una consulta sin cesta ofrece horas que el checkout rechazaría. Hoy la cesta va vacía porque el
 * paso 4 llega después; el cuerpo ya viaja con su clave para que no se olvide al añadirla.
 */
async function selectDate(date) {
    selectedDate.value = date;
    selectedTime.value = null;
    store.go(STEPS.TIME);

    // ⚠️ `AFORO-02`: la oferta de horas **lleva la cesta**. `offerableTimes()` descuenta los ocupantes
    // que la propia cesta ya retiene, así que una consulta sin ella ofrece horas y topes que el
    // checkout rechazaría. Hasta 4.3·2 iba vacía porque no había cesta; ahora va la de verdad.
    const response = await tracked(api.post(`/availability/${selectedProductId.value}/times`, {
        date,
        items: toApiItems(cart.value),
    }));

    offeredTimes.value = response.ok ? (response.data?.data ?? []) : [];
}

/** Elegir hora fija la cantidad en el mínimo contratable y resuelve los complementos. */
async function selectTime(time) {
    selectedTime.value = time;
    quantity.value = Math.min(minQuantity.value, maxQuantity.value);

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
        date: selectedDate.value,
        time: selectedTime.value,
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
    if (next < minQuantity.value || next > maxQuantity.value) return;

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

const weekdayHeaders = computed(() => {
    const formatter = new Intl.DateTimeFormat(locale, { weekday: 'short' });
    // 2024-01-01 fue lunes: sirve de ancla para nombrar los siete días en orden.
    return Array.from({ length: 7 }, (_, i) => {
        const day = new Date(2024, 0, 1 + i);
        const label = formatter.format(day).replace('.', '');
        return label.charAt(0).toUpperCase() + label.slice(1);
    });
});

const monthLabel = computed(() => {
    if (! month.value) return '';
    const [y, m] = month.value.split('-').map(Number);
    const label = new Intl.DateTimeFormat(locale, { month: 'long', year: 'numeric' }).format(new Date(y, m - 1, 1));
    return label.charAt(0).toUpperCase() + label.slice(1);
});

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
    date: selectedDate.value,
    time: selectedTime.value,
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
    hasDate: selectedDate.value !== null,
    hasTime: selectedTime.value !== null,
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
}

/**
 * «Ir a pagar». Espejo de `Purchase::checkout()`.
 *
 * **Aquí solo está el cableado**: preguntar, aplicar la identidad y decidir es `runCheckout()`, que
 * vive en `admission.js` porque la lógica que baja a un `.vue` pierde su red (CE-6) — un árbol no
 * dice a quién se preguntó, en qué orden, ni si la cesta se purgó por el camino.
 *
 * ⚠️ **A dónde se va todavía NO se navega, y es deliberado**: los pasos 5 (identificación) y 8 (pago)
 * no están transcritos —son 4.4b y 4.5—, así que navegar dejaría el cajón en blanco, que es peor que
 * un CTA mudo. El destino se decide igualmente y `SidebarAdmissionParityTest` lo compara con el del
 * componente Livewire: cuando esas pantallas existan, esto es cablear, no volver a decidir.
 */
async function checkout() {
    const verdict = await tracked(runCheckout({
        cartCount: cart.value.length,
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
}

/** Del calendario a la hora. Espejo de `Purchase::goToTime()`: exige día elegido. */
function goToTime() {
    if (selectedDate.value === null) return;

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
        date: selectedDate.value,
        time: selectedTime.value,
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

/** «Añadir otra reserva»: vuelve al catálogo con la selección limpia. */
function addAnother() {
    clearSelection();
    store.enter(STEPS.CATALOG);
}

/** Deja la SELECCIÓN en blanco sin tocar la cesta. Espejo de `Purchase::clearSelection()`. */
function clearSelection() {
    selectedProductId.value = null;
    selectedProduct.value = null;
    product.value = null;
    selectedDate.value = null;
    selectedTime.value = null;
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
        selectedTime.value = null;
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
            :weeks="weeks"
            :weekday-headers="weekdayHeaders"
            :month-label="monthLabel"
            :can-prev="canPrev"
            :can-next="canNext"
            :selected-date="selectedDate"
            :messages="messages"
            @select="selectDate"
            @prev-month="month = shiftMonth(month, -1)"
            @next-month="month = shiftMonth(month, 1)" />

        <TimeStep
            v-else-if="store.step === STEPS.TIME"
            :times="offeredTimes.map((t) => t.time)"
            :selected-time="selectedTime"
            :quantity="quantity"
            :min-quantity="minQuantity"
            :max-quantity="maxQuantity"
            :is-pack="product?.type === 'pack'"
            :day-price-cents="dayPriceCents"
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
            @add-another="addAnother" />
    </Shell>
</template>
