<script setup>
/**
 * **Una reserva, como tarjeta** (`docs/specs/mis-reservas-por-reserva.md` §4.4).
 *
 * La pintan LAS DOS pantallas —«Mis reservas» y «Historial»—, que son la misma lista partida por un
 * predicado del servidor. Un componente por pantalla habría duplicado el ledger, el post-form y las
 * respuestas del pack en dos sitios que arreglar.
 *
 * **Pinta y no decide.** Qué se enseña de la reserva lo compone `account/orders.js::cardRow()`, el
 * ledger del pedido `financialsOf()` y de dónde salen los datos lo sabe `stores/orders.js`; los tres
 * se prueban con `node --test`. Aquí no hay ninguna regla y no se habla con la API (`CE-6`).
 *
 * ⚠️ **La atenuación llega por PROP, no se deduce.** Es propiedad de la pantalla —el historial atenúa
 * lo que pinta— y calcularla aquí sería una segunda definición del predicado que reparte los dos
 * ámbitos, que vive en SQL. El distintivo «cancelada»/«disfrutada» sí es de la reserva y viene ya
 * compuesto en `row.badge`.
 */
defineProps({
    /** La fila ya compuesta por `cardRow()`. */
    row: { type: Object, required: true },
    /** El pedido entero, ya compuesto por `orderRow()`, o `null` si no se ha desplegado. */
    order: { type: Object, default: null },
    /** Las respuestas del pack de ESTA reserva, o lista vacía. */
    answers: { type: Array, default: () => [] },
    /** ¿Está desplegado el pedido de esta tarjeta? */
    openOrder: { type: Boolean, default: false },
    /** ¿Están desplegadas sus respuestas? */
    openEvent: { type: Boolean, default: false },
    /** ¿Va atenuada? Lo decide la pantalla. */
    dimmed: { type: Boolean, default: false },
    /** ¿Hay una petición en vuelo? Bloquea el reintento. */
    busy: { type: Boolean, default: false },
    account: { type: Object, default: () => ({}) },
    messages: { type: Object, default: () => ({}) },
});

defineEmits(['toggle-order', 'toggle-event', 'retry']);
</script>

<template>
    <li class="orders__item" :class="{ 'orders__item--past': dimmed }">
        <div class="orders__head">
            <span class="orders__line-name">{{ row.name }}</span>
            <span v-if="row.badge" class="orders__line-badge" :class="'orders__line-badge--' + row.badge.key">{{ row.badge.label }}</span>
        </div>

        <div class="orders__meta">
            {{ row.whenLabel }}
            <span v-if="row.quantity > 1" class="orders__line-unit">· {{ row.quantity }}×</span>
            <span class="orders__line-price">{{ row.priceLabel }}</span>
        </div>

        <p v-if="row.depositNote" class="orders__product-deposit">{{ row.depositNote }}</p>

        <ul v-if="row.addons.length" class="orders__lines">
            <li v-for="addon in row.addons" :key="addon.id" class="orders__line">
                <span class="orders__line-name">+ {{ addon.name }} <span class="orders__line-unit">· {{ addon.quantity }}×</span></span>
                <span class="orders__line-price">{{ addon.priceLabel }}</span>
            </li>
        </ul>

        <span v-if="row.guestForm" class="orders__product-form">
            <button v-if="! row.guestForm.url" type="button" class="btn btn--ghost orders__guestform-btn" disabled>{{ row.guestForm.label }}</button>
            <a v-else :href="row.guestForm.url" class="btn orders__guestform-btn"
               :class="row.guestForm.state === 'pending' ? 'btn--zone' : 'btn--ghost'">{{ row.guestForm.label }}</a>
        </span>

        <!--
          Las respuestas del pack, solo si el titular las ha desplegado. Una reserva sin respuestas no
          pinta nada: un bloque vacío se lee como «no contestaste».
        -->
        <button v-if="row.isPack" type="button" class="orders__gate-toggle"
                :aria-expanded="openEvent ? 'true' : 'false'" @click="$emit('toggle-event')">
            {{ openEvent ? account?.orders?.event_data_hide : account?.orders?.event_data_show }}
        </button>
        <ul v-if="openEvent && answers.length" class="orders__event">
            <li v-for="answer in answers" :key="answer.key">
                <span class="orders__event-label">{{ answer.label }}:</span> {{ answer.value }}
            </li>
        </ul>

        <!--
          ⚠️ **La referencia del pedido y su desglose, plegados.** El ledger es del PEDIDO: enseñarlo
          entero en cada tarjeta lo repetiría tantas veces como reservas tenga ese pedido, con
          importes que no cuadran con la tarjeta que los rodea. Se pide al desplegar.
        -->
        <div class="orders__order">
            <span class="orders__code">{{ (account?.orders?.order_ref ?? '').replace(':code', row.orderCode ?? '') }}</span>
            <span class="orders__status" :class="'orders__status--' + row.orderStatus">{{ row.orderStatusLabel }}</span>
        </div>

        <button type="button" class="orders__gate-toggle" :aria-expanded="openOrder ? 'true' : 'false'"
                @click="$emit('toggle-order')">
            {{ openOrder ? account?.orders?.order_hide : account?.orders?.order_show }}
        </button>

        <div v-if="openOrder && order">
            <div class="orders__meta">{{ order.createdLabel }}</div>

            <!--
              ⚠️⚠️ **DOS BLOQUES, y no se mezclan** (`DECISIONES #127`). Arriba lo que vale y por qué
              canal se paga —cierra siempre—; abajo qué ha pasado con su dinero. Hasta la tanda B,
              «Devuelto» y «Pendiente de devolución» se pintaban como restas dentro de la columna del
              valor, de la que NO restan, y por eso dejaba de leerse.
            -->
            <div class="orders__ledger">
                <p class="orders__ledger-title">{{ order.financials.value.title }}</p>

                <div v-for="(row, i) in order.financials.value.rows" :key="'v' + i" class="orders__gate">
                    <span class="orders__gate-label">{{ row.label }}</span>
                    <strong>{{ row.amountLabel }}</strong>
                </div>

                <div v-if="order.financials.value.gate">
                    <div class="orders__gate">
                        <span class="orders__gate-label">{{ order.financials.value.gate.label }}</span>
                        <strong class="orders__gate-amount">{{ order.financials.value.gate.amountLabel }}</strong>
                    </div>
                    <div v-for="(gateLine, i) in order.financials.value.gate.lines" :key="i" class="orders__gate-line">
                        <span>↳ {{ gateLine.label }}</span><strong>{{ gateLine.amountLabel }}</strong>
                    </div>
                    <p class="orders__gate-caption">{{ order.financials.value.gate.caption }}</p>
                </div>

                <div class="orders__final">
                    <span>{{ order.financials.value.total.label }}</span>
                    <strong>{{ order.financials.value.total.amountLabel }}</strong>
                </div>
            </div>

            <div v-if="order.financials.cash" class="orders__ledger orders__ledger--cash">
                <p class="orders__ledger-title">{{ order.financials.cash.title }}</p>
                <div v-for="(row, i) in order.financials.cash.rows" :key="'c' + i" class="orders__refund">
                    <span class="orders__refund-label">{{ row.label }}</span>
                    <strong class="orders__refund-amount">{{ row.amountLabel }}</strong>
                </div>
            </div>

            <!--
              ⚠️ **La FRASE, no un número.** Era el encargo: que el cliente entienda su situación ante
              cualquier situación. La compone el servidor, que es quien sabe qué caso es.
            -->
            <p v-if="order.financials.note" class="orders__ledger-note">{{ order.financials.note }}</p>

            <!-- Trazabilidad: lo facturado al reservar, solo si ya no es lo que vale. -->
            <div v-if="order.financials.invoiced" class="orders__ledger-invoiced">
                <div class="orders__gate">
                    <span class="orders__gate-label">{{ order.financials.invoiced.label }}</span>
                    <strong>{{ order.financials.invoiced.amountLabel }}</strong>
                </div>
                <p class="orders__gate-caption">{{ order.financials.invoiced.hint }}</p>
            </div>
        </div>

        <!--
          ⚠️ El reintento va FUERA del desplegable: un pedido a medio pagar es lo más urgente de la
          pantalla y esconderlo tras un clic sería enterrar el único camino que le queda al cliente
          para no perder su plaza (`openapi/v1.yaml`, `/me/orders`).
        -->
        <div v-if="row.canRetry" class="orders__retry">
            <button type="button" class="btn btn--zone" :disabled="busy" @click="$emit('retry')">{{ account?.orders?.retry_payment ?? '' }}</button>
            <p class="orders__retry-hint">{{ account?.orders?.retry_hint ?? '' }}</p>
        </div>
    </li>
</template>
