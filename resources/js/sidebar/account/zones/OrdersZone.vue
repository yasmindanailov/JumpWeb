<script setup>
import { computed, ref } from 'vue';
import { useOrdersStore } from '../../stores/orders.js';
import ZoneLoading from '../ZoneLoading.vue';
import ReservationCard from './ReservationCard.vue';
import { cardRows, orderRow, pageInfo } from '../orders.js';
import { answersByReservation } from '../../outcome.js';
import { ZONES } from '../navigation.js';

/**
 * **«Mis reservas» y «Historial»**, que son LA MISMA lista partida por un predicado del servidor
 * (`docs/specs/mis-reservas-por-reserva.md`).
 *
 * ⚠️⚠️ **Un solo componente con un `scope`, y no dos zonas casi iguales.** Lo único que las
 * distingue es qué ámbito piden, si atenúan y si ofrecen el CTA al historial: tres props. Con dos
 * componentes, cada arreglo de la lista habría que hacerlo dos veces — y el primero que se olvidara
 * dejaría las dos pantallas discrepando sin que nada fallara.
 *
 * ⚠️ **Lista por RESERVA, no por pedido**: un pedido puede llevar tres reservas de tres fechas
 * distintas y enseñarlas juntas no le dice nada a quien solo quiere saber qué tiene. El ledger sigue
 * siendo del pedido y se pide al desplegarlo (`ReservationCard`).
 *
 * **Pinta.** Qué se enseña lo componen `account/orders.js` y de dónde salen los datos lo sabe
 * `stores/orders.js`; los dos con `node --test`. Aquí no hay reglas ni API (`CE-6`).
 */
const props = defineProps({
    /** `upcoming` o `past`. Lo fija la sección al enrutar la zona, no se deduce aquí. */
    scope: { type: String, required: true },
    /** El grupo `account` podado: rótulos y textos de la zona. */
    account: { type: Object, default: () => ({}) },
    /** El grupo `ui`: el rótulo del spinner mientras la zona trae sus datos. */
    ui: { type: Object, default: () => ({}) },
    /** El grupo `tickets`: estados del pedido y aviso de señal. */
    messages: { type: Object, default: () => ({}) },
});

const emit = defineEmits(['sign-in', 'go']);

const store = useOrdersStore();

store.ensure(props.scope);

const rows = computed(() => cardRows(store.pages[props.scope], { messages: props.messages, account: props.account }));
const page = computed(() => pageInfo(store.pages[props.scope], props.account));
const past = computed(() => props.scope === 'past');
const emptyText = computed(() => (past.value ? props.account?.orders?.history?.empty : props.account?.orders?.empty) ?? '');

/** Qué tarjeta tiene desplegado su pedido, y cuál sus respuestas. Uno cada vez: el panel es estrecho. */
const openOrder = ref(0);
const openEvent = ref(0);

const orderOf = (code) => (store.orders[code] ? orderRow(store.orders[code], { messages: props.messages, account: props.account }) : null);
const answersOf = (row) => (answersByReservation(store.eventData[row.orderCode])[String(row.id)] ?? []);

function toggle(which, row) {
    const target = which === 'order' ? openOrder : openEvent;

    if (target.value === row.id) {
        target.value = 0;

        return;
    }

    target.value = row.id;
    if (which === 'order') store.ensureOrder(row.orderCode);
    else store.ensureEventData(row.orderCode);
}
</script>

<template>
    <p v-if="store.unauthenticated" class="purchase__empty">
        <button type="button" class="btn btn--zone" @click="emit('sign-in')">{{ account?.login?.cta ?? '' }}</button>
    </p>

    <template v-else>
        <p v-if="store.error" class="auth__errors" role="alert">{{ store.error }}</p>

        <!-- ⚠️ Primero el spinner, y solo DESPUÉS el «no tienes nada»: mientras se pide, decir que no
             hay nada es decir algo falso. -->
        <ZoneLoading v-if="store.loading && ! store.loaded(scope)" :ui="ui" />

        <p v-else-if="! rows.length" class="purchase__empty">{{ emptyText }}</p>

        <ul v-else class="orders">
            <ReservationCard
                v-for="row in rows"
                :key="row.id"
                :row="row"
                :order="orderOf(row.orderCode)"
                :answers="answersOf(row)"
                :open-order="openOrder === row.id"
                :open-event="openEvent === row.id"
                :dimmed="past"
                :busy="store.loading"
                :account="account"
                :messages="messages"
                @toggle-order="toggle('order', row)"
                @toggle-event="toggle('event', row)"
                @retry="store.retry(row.orderCode, { messages })" />
        </ul>

        <nav v-if="page" class="pagination" :aria-label="page.label">
            <button type="button" class="btn btn--ghost" :disabled="! page.canPrev || store.loading" @click="store.load(scope, page.current - 1)">{{ page.prevLabel }}</button>
            <span class="pagination__info">{{ page.pageLabel }}</span>
            <button type="button" class="btn btn--ghost" :disabled="! page.canNext || store.loading" @click="store.load(scope, page.current + 1)">{{ page.nextLabel }}</button>
        </nav>

        <!--
          ⚠️ **El CTA al historial va DESPUÉS de la paginación**, y es una decisión del owner: lo
          pasado no compite con lo vivo. Solo lo ofrece la pantalla de las vivas — desde el historial
          se vuelve con «Volver», que es lo que hace la pila de la navegación.
        -->
        <p v-if="! past" class="orders__history-cta">
            <button type="button" class="btn btn--ghost" @click="emit('go', ZONES.ORDERS_HISTORY)">
                {{ account?.orders?.history?.cta ?? '' }}
            </button>
        </p>
    </template>
</template>
