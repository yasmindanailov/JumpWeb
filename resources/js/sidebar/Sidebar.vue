<script setup>
import { onMounted, ref, watch } from 'vue';
import { usePurchaseStore } from './store.js';
import { STEPS } from './machine.js';
import { api } from './api.js';
import CatalogStep from './steps/CatalogStep.vue';

/**
 * La raíz del cajón SPA.
 *
 * **El nodo raíz emite `class="purchase"` y eso no es decorativo**: el contrato visual es el ÁRBOL
 * (§4.2), y 90 de los 292 selectores que estilan el cajón son estructurales o dependen del tipo de
 * elemento. Lo verifica `SidebarDomContractTest`.
 */
const props = defineProps({
    /** El grupo `tickets` del locale activo, inyectado por el servidor en el montaje (§4.5). */
    messages: { type: Object, default: () => ({}) },
});

const store = usePurchaseStore();

const sections = ref([]);
const searchEnabled = ref(false);
const loading = ref(false);

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
 * añadiría una petición por visita en la ruta de más tráfico del sitio. Al abrir, la pide quien de
 * verdad va a comprar.
 *
 * Las dos llamadas van en paralelo porque no dependen entre sí: el umbral del buscador es un ajuste
 * de instalación y el catálogo es contenido.
 */
onMounted(async () => {
    loading.value = true;

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

    loading.value = false;
});

function totalItems(list) {
    return list.reduce((n, section) => n + section.items.length, 0);
}

/**
 * Agrupa el catálogo plano en las DOS secciones que el cajón enseña.
 *
 * Es presentación, no negocio: qué se vende ya lo decidió `ProductCatalog`, y lo único que se hace
 * aquí es repartir por `type` en el mismo orden en que llegan — que es el `position` configurado en
 * el panel.
 */
function groupIntoSections(products) {
    const entries = products.filter((p) => p.type === 'entry');
    const services = products.filter((p) => p.type === 'pack');

    return [
        { key: 'entries', items: entries.map(toItem) },
        { key: 'services', items: services.map(toItem) },
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
        // El índice del buscador lo compone el SERVIDOR, y se compara contra él y no contra el
        // nombre visible: es la semántica que el sidebar Livewire ya tenía.
        search: [product.name, ...(product.features ?? [])].join(' ').toLowerCase(),
    };
}

function selectProduct(id) {
    store.go(STEPS.DATE);
    // El paso 2 llega en el siguiente tramo de la fase; hasta entonces la elección solo avanza.
    void id;
}
</script>

<template>
    <div class="purchase" data-engine="spa">
        <CatalogStep
            v-if="store.step === STEPS.CATALOG"
            :sections="sections"
            :search-enabled="searchEnabled"
            :messages="messages"
            @select="selectProduct" />
    </div>
</template>
