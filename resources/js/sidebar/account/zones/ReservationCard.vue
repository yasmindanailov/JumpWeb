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
    /** Las respuestas del pack de ESTA reserva, o lista vacía. */
    answers: { type: Array, default: () => [] },
    /** ¿Están desplegadas sus respuestas? */
    openEvent: { type: Boolean, default: false },
    /** ¿Va atenuada? Lo decide la pantalla. */
    dimmed: { type: Boolean, default: false },
    /** ¿Hay una petición en vuelo? Bloquea el reintento. */
    busy: { type: Boolean, default: false },
    account: { type: Object, default: () => ({}) },
    messages: { type: Object, default: () => ({}) },
});

defineEmits(['open-order', 'toggle-event', 'retry']);
</script>

<template>
    <li class="orders__item" :class="{ 'orders__item--past': dimmed }">
        <div class="orders__head">
            <span class="orders__line-name">{{ row.name }}</span>
            <span v-if="row.badge" class="orders__line-badge" :class="'orders__line-badge--' + row.badge.key">{{ row.badge.label }}</span>
        </div>

        <div class="orders__meta">
            {{ row.whenLabel }}
            <!--
              ⚠️⚠️ **La cantidad va con su SUSTANTIVO** («8 invitados»), no como `8×` pegado al
              importe: eso se leía como 8 × 216 € = 1.728 € cuando son 8 invitados y 216 € en total
              (`specs/desglose-dinero-cliente.md` §17.1 · `L2`). La compone el servidor.
            -->
            <span class="orders__line-unit">· {{ row.quantityLabel }}</span>
            <span class="orders__line-price">· {{ row.priceLabel }}</span>
        </div>

        <p v-if="row.depositNote" class="orders__product-deposit">{{ row.depositNote }}</p>

        <ul v-if="row.addons.length" class="orders__lines">
            <li v-for="addon in row.addons" :key="addon.id" class="orders__line">
                <span class="orders__line-name">+ {{ addon.name }} <span class="orders__line-unit">· {{ addon.quantityLabel }}</span></span>
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
          ⚠️⚠️ **La referencia del pedido, y «Ver pedido» LLEVA A LA PANTALLA DE PEDIDOS**
          (decisión del owner, `specs/desglose-dinero-cliente.md` §5·2 · `DECISIONES #129`).

          Hasta el 2026-08-24 desplegaba el desglose aquí dentro, y eso era el defecto: el ledger es
          del PEDIDO, así que en un pedido con tres reservas se pintaba tres veces y en dos de ellas
          los importes no cuadraban con la tarjeta que los rodeaba. Ahora vive en su propia pantalla
          y esta tarjeta solo dice a qué pedido pertenece — que es lo que sí es de la reserva.
        -->
        <div class="orders__order">
            <span class="orders__code">{{ (account?.orders?.order_ref ?? '').replace(':code', row.orderCode ?? '') }}</span>
            <span class="orders__status" :class="'orders__status--' + row.orderStatus">{{ row.orderStatusLabel }}</span>
        </div>

        <button type="button" class="orders__gate-toggle" @click="$emit('open-order')">
            {{ account?.orders?.order_show }}
        </button>

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
