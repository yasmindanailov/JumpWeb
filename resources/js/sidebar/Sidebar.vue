<script setup>
import { computed, onMounted, ref, watch } from 'vue';
import { usePurchaseStore } from './store.js';
import { STEPS } from './machine.js';
import { api } from './api.js';
import { buildWeeks, monthOf, shiftMonth } from './calendar.js';
import CatalogStep from './steps/CatalogStep.vue';
import DateStep from './steps/DateStep.vue';
import TimeStep from './steps/TimeStep.vue';

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
});

const store = usePurchaseStore();

const sections = ref([]);
const searchEnabled = ref(false);

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
    const [catalog, config] = await Promise.all([
        api.get('/catalog/products'),
        api.get('/config'),
    ]);

    if (catalog.ok) sections.value = groupIntoSections(catalog.data?.data ?? []);

    // El umbral lo decide el SERVIDOR y viaja con su operador en la descripción del contrato
    // (`total > umbral`): el cliente compara, no reinventa la regla.
    if (config.ok) {
        const threshold = config.data?.catalog_search_min_items;
        searchEnabled.value = typeof threshold === 'number' && totalItems(sections.value) > threshold;
    }
});

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
    // La FICHA trae lo que el paso 3 necesita y el listado no lleva: el mínimo contratable y el
    // esquema de campos del evento, ya resueltos al idioma. Se pide junto a los días, no después,
    // porque los dos hacen falta antes de que el cliente pueda elegir nada.
    product.value = null;
    selectedDate.value = null;
    selectedTime.value = null;
    addons.value = { groups: [], singles: [] };
    addonChoices.value = [];
    addonQuantities.value = [];
    store.go(STEPS.DATE);

    const [dates, detail] = await Promise.all([
        api.get(`/availability/${id}/dates`),
        api.get(`/catalog/products/${id}`),
    ]);

    offeredDates.value = dates.ok ? (dates.data?.data ?? []) : [];
    if (detail.ok) product.value = detail.data;
    // El calendario abre en el PRIMER mes con oferta, no en el actual: si el producto no se vende
    // hasta dentro de dos meses, abrir en «hoy» enseñaría una rejilla vacía.
    month.value = offeredMonths.value[0] ?? monthOf(new Date().toISOString().slice(0, 10));
}

/** Lo que el paso 3 necesita. Todo llega de la API; aquí no se decide nada (`CE-4`). */
const offeredTimes = ref([]);
const selectedTime = ref(null);
const quantity = ref(0);
const product = ref(null);
const addons = ref({ groups: [], singles: [] });
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

    const response = await api.post(`/availability/${selectedProductId.value}/times`, {
        date,
        items: [],
    });

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

    const response = await api.post(`/catalog/products/${selectedProductId.value}/addons`, {
        quantity: quantity.value,
        date: selectedDate.value,
        time: selectedTime.value,
        addons: addonQuantities.value,
        choices: addonChoices.value,
    });

    if (response.ok) addons.value = { groups: response.data.groups, singles: response.data.singles };
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
</script>

<template>
    <div class="purchase" data-engine="spa">
        <CatalogStep
            v-if="store.step === STEPS.CATALOG"
            :sections="sections"
            :search-enabled="searchEnabled"
            :messages="messages"
            @select="selectProduct" />

        <!--
          El paso 2 recibe la rejilla ya compuesta. Mientras el calendario del cliente no exista
          —llega con el resto de 4.2—, se le pasa lo que la API devuelve y el componente pinta lo
          que haya: sin días ofrecidos enseña su aviso de «no hay fechas», que es la conducta
          correcta y no una pantalla en blanco.
        -->
        <DateStep
            v-else-if="store.step === STEPS.DATE"
            :progress="null"
            :weeks="weeks"
            :weekday-headers="weekdayHeaders"
            :month-label="monthLabel"
            :can-prev="canPrev"
            :can-next="canNext"
            :selected-date="selectedDate"
            :messages="messages"
            @select="selectDate"
            @prev-month="month = shiftMonth(month, -1)"
            @next-month="month = shiftMonth(month, 1)"
            @back="store.go(STEPS.CATALOG)" />

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
            :messages="messages"
            @select-time="selectTime"
            @inc="changeQuantity(1)"
            @dec="changeQuantity(-1)"
            @update-field="(key, value) => (eventData[key] = value)"
            @choose-addon="chooseAddon"
            @toggle-addon="(id) => setAddonQuantity(id, addons.singles.find((a) => a.product_id === id)?.selected ? 0 : 1)"
            @inc-addon="(id) => setAddonQuantity(id, (addons.singles.find((a) => a.product_id === id)?.quantity ?? 0) + 1)"
            @dec-addon="(id) => setAddonQuantity(id, Math.max(0, (addons.singles.find((a) => a.product_id === id)?.quantity ?? 0) - 1))" />
    </div>
</template>
