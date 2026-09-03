<script setup>
import { computed, ref } from 'vue';
import { useOrdersStore } from '../../stores/orders.js';
import { useDependentsStore } from '../../stores/dependents.js';
import ZoneLoading from '../ZoneLoading.vue';
import ReservationCard from './ReservationCard.vue';
import { cardRows, pageInfo } from '../orders.js';
import { answersByReservation, dependentsByReservation } from '../../outcome.js';
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
 * distintas y enseñarlas juntas no le dice nada a quien solo quiere saber qué tiene. El DINERO es del
 * pedido y desde el 2026-08-24 vive en su propia pantalla (`ZONES.PURCHASES`): aquí «Ver pedido»
 * lleva allí, en vez de desplegar un desglose que en un pedido de tres reservas se pintaba tres veces
 * con importes que no cuadraban con la tarjeta que los rodeaba (`DECISIONES #129`).
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
// Los menores declarados (tanda 4): solo para saber si se ofrece «ver para quién» en las entradas.
const dependents = useDependentsStore();

store.ensure(props.scope);
dependents.ensure();

const rows = computed(() => cardRows(store.pages[props.scope], { messages: props.messages, account: props.account }));
const page = computed(() => pageInfo(store.pages[props.scope], props.account));
const past = computed(() => props.scope === 'past');
const emptyText = computed(() => (past.value ? props.account?.orders?.history?.empty : props.account?.orders?.empty) ?? '');

/** Qué tarjeta tiene desplegadas sus respuestas. Una cada vez: el panel es estrecho. */
const openEvent = ref(0);

const answersOf = (row) => (answersByReservation(store.eventData[row.orderCode])[String(row.id)] ?? []);
const dependentsOf = (row) => (dependentsByReservation(store.eventData[row.orderCode])[String(row.id)] ?? []);

function toggleEvent(row) {
    openEvent.value = openEvent.value === row.id ? 0 : row.id;

    if (openEvent.value) store.ensureEventData(row.orderCode);
}

/** Qué entrada tiene desplegado «para quién». Mismo endpoint y misma regla que las respuestas. */
const openDependents = ref(0);

function toggleDependents(row) {
    openDependents.value = openDependents.value === row.id ? 0 : row.id;

    if (openDependents.value) store.ensureEventData(row.orderCode);
}

</script>

<template>
    <p v-if="store.unauthenticated" class="purchase__empty">
        <button type="button" class="btn" @click="emit('sign-in')">{{ account?.login?.cta ?? '' }}</button>
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
                :answers="answersOf(row)"
                :open-event="openEvent === row.id"
                :dependents="dependentsOf(row)"
                :offer-dependents="dependents.items.length > 0"
                :open-dependents="openDependents === row.id"
                :dimmed="past"
                :busy="store.loading"
                :account="account"
                :messages="messages"
                @open-order="store.openPurchase(row.orderCode)"
                @toggle-event="toggleEvent(row)"
                @toggle-dependents="toggleDependents(row)"
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
