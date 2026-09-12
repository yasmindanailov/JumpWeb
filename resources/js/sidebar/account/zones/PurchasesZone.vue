<script setup>
import { computed, ref } from 'vue';
import { useOrdersStore } from '../../stores/orders.js';
import ZoneLoading from '../ZoneLoading.vue';
import PurchaseCard from './PurchaseCard.vue';
import { pageInfo, purchaseRows } from '../orders.js';

/**
 * **«Mis pedidos»: el DINERO, por pedido** (`specs/desglose-dinero-cliente.md` §19,
 * `DECISIONES #129`).
 *
 * ⚠️⚠️ **No es «Mis reservas» con otro filtro: es otra UNIDAD.** Aquélla lista reservas —una tarjeta
 * por día al que el cliente va— y ésta lista pedidos, que es la unidad del dinero. Un pedido puede
 * llevar tres reservas de tres fechas distintas, y por eso su desglose **no cabía** en la tarjeta de
 * una reserva: repetido tres veces, dos de ellas decían algo que no cuadraba con lo que las rodeaba.
 *
 * ⚠️ **La zona técnica se llama `purchases` y el rótulo dice «Mis pedidos»**, mientras que la zona
 * `orders` dice «Mis reservas». La inversión es deliberada y está explicada en `account/navigation.js`:
 * el valor `orders` es la ruta `/mi-cuenta/pedidos`, a la que apuntan correos ya enviados.
 *
 * **Pinta.** Qué se enseña lo compone `account/orders.js` —el MISMO `orderRow()` de siempre— y de
 * dónde salen los datos lo sabe `stores/orders.js`; los dos con `node --test`. Aquí no hay reglas ni
 * API (`CE-6`).
 */
const props = defineProps({
    /** El grupo `account` podado: rótulos y textos de la zona. */
    account: { type: Object, default: () => ({}) },
    /** El grupo `ui`: el rótulo del spinner mientras la zona trae sus datos. */
    ui: { type: Object, default: () => ({}) },
    /** El grupo `tickets`: estados del pedido y los rótulos del desglose. */
    messages: { type: Object, default: () => ({}) },
});

const emit = defineEmits(['sign-in']);

const store = useOrdersStore();

// ⚠️ El pedido que hay que abrir desplegado lo decide el STORE, que es quien sabe si esta entrada
// viene de una reserva o del índice. Se consume antes de pedir la página porque la página depende de
// él: la elige el servidor con `containing`.
const open = ref(store.focus);

store.ensurePurchases();

const rows = computed(() => purchaseRows(store.purchases, { messages: props.messages, account: props.account }));
const page = computed(() => pageInfo(store.purchases, props.account, 'purchases'));

/** Uno desplegado cada vez: el panel es estrecho y dos desgloses a la vez no se comparan, se mezclan. */
function toggle(code) {
    open.value = open.value === code ? '' : code;
}
</script>

<template>
    <p v-if="store.unauthenticated" class="purchase__empty">
        <button type="button" class="btn btn--ink" @click="emit('sign-in')">{{ account?.login?.cta ?? '' }}</button>
    </p>

    <template v-else>
        <p v-if="store.error" class="auth__errors" role="alert">{{ store.error }}</p>

        <!-- ⚠️ Primero el spinner y solo DESPUÉS el «no tienes nada»: mientras se pide, decir que no
             hay nada es decir algo falso. Mismo criterio que «Mis reservas». -->
        <ZoneLoading v-if="store.loading && store.purchases === null" :ui="ui" />

        <p v-else-if="! rows.length" class="purchase__empty">{{ account?.purchases?.empty ?? '' }}</p>

        <ul v-else class="orders">
            <PurchaseCard
                v-for="row in rows"
                :key="row.code"
                :row="row"
                :open="open === row.code"
                :busy="store.loading"
                :account="account"
                @toggle="toggle(row.code)"
                @retry="store.retry(row.code, { messages })" />
        </ul>

        <nav v-if="page" class="pagination" :aria-label="page.label">
            <button type="button" class="btn btn--ghost" :disabled="! page.canPrev || store.loading" @click="store.loadPurchases(page.current - 1)">{{ page.prevLabel }}</button>
            <span class="pagination__info">{{ page.pageLabel }}</span>
            <button type="button" class="btn btn--ghost" :disabled="! page.canNext || store.loading" @click="store.loadPurchases(page.current + 1)">{{ page.nextLabel }}</button>
        </nav>
    </template>
</template>
