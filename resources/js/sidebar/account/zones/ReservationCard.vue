<script setup>
/**
 * **Una reserva, como tarjeta** (`docs/specs/mis-reservas-por-reserva.md` §4.4).
 *
 * La pintan LAS DOS pantallas —«Mis reservas» y «Historial»—, que son la misma lista partida por un
 * predicado del servidor. Un componente por pantalla habría duplicado el ledger, el post-form y las
 * respuestas del pack en dos sitios que arreglar.
 *
 * ⚠️⚠️ **AQUÍ NO SE PINTA DINERO** (2026-08-24, `DECISIONES #130`, decisión del owner). Esta pantalla
 * responde a «¿qué tengo y cuándo?»; el desglose es del PEDIDO y vive entero en «Mis pedidos», a un
 * clic de «Ver pedido». Tener aquí el importe de la línea y la nota de la señal repetía media
 * contabilidad en la pantalla que menos la necesita, y competía con lo único que el cliente viene a
 * mirar: la fecha. Lo vigila `LedgerSingleSourceTest`, **sobre este marcado** — porque la composición
 * sigue teniendo el importe, que es lo que pinta la otra pantalla.
 *
 * **Pinta y no decide.** Qué se enseña de la reserva lo compone `account/orders.js::cardRow()` y de
 * dónde salen los datos lo sabe `stores/orders.js`; los dos se prueban con `node --test`. Aquí no hay
 * ninguna regla y no se habla con la API (`CE-6`).
 *
 * ⚠️ **La atenuación llega por PROP, no se deduce.** Es propiedad de la pantalla —el historial atenúa
 * lo que pinta— y calcularla aquí sería una segunda definición del predicado que reparte los dos
 * ámbitos, que vive en SQL. El distintivo «cancelada»/«disfrutada» sí es de la reserva y viene ya
 * compuesto en `row.badge`.
 */
import GuestMinorsPanel from './GuestMinorsPanel.vue';

defineProps({
    /** La fila ya compuesta por `cardRow()`. */
    row: { type: Object, required: true },
    /** Las respuestas del pack de ESTA reserva, o lista vacía. */
    answers: { type: Array, default: () => [] },
    /** ¿Están desplegadas sus respuestas? */
    openEvent: { type: Boolean, default: false },
    /** Los menores a cargo para los que es ESTA entrada (`event-data`, Fase 6 · tanda 4), o lista vacía. */
    dependents: { type: Array, default: () => [] },
    /** ¿Se ofrece «ver para quién»? Solo en ENTRADAS y solo si el titular tiene menores declarados. */
    offerDependents: { type: Boolean, default: false },
    /** ¿Está desplegado «para quién»? */
    openDependents: { type: Boolean, default: false },
    /** ¿Va atenuada? Lo decide la pantalla. */
    dimmed: { type: Boolean, default: false },
    /** ¿Hay una petición en vuelo? Bloquea el reintento. */
    busy: { type: Boolean, default: false },
    account: { type: Object, default: () => ({}) },
    messages: { type: Object, default: () => ({}) },
});

defineEmits(['open-order', 'toggle-event', 'toggle-dependents', 'retry']);
</script>

<template>
    <li class="orders__item" :class="{ 'orders__item--past': dimmed }">
        <div class="orders__head">
            <span class="orders__line-name">{{ row.name }}</span>
            <span v-if="row.badge" class="orders__line-badge" :class="'orders__line-badge--' + row.badge.key">{{ row.badge.label }}</span>
        </div>

        <!--
          ⚠️⚠️ **AQUÍ NO HAY DINERO, y es una decisión del owner** (2026-08-24, `DECISIONES #130`).
          Esta pantalla responde a «¿qué tengo y cuándo?»; el dinero es del PEDIDO y vive entero en
          «Mis pedidos», a un clic. Tener aquí el importe de la línea y la nota de la señal repetía
          media contabilidad en la pantalla que menos la necesita, y competía con lo único que el
          cliente viene a mirar: la fecha.
          ⚠️ La cantidad se queda —dice de cuántos es la reserva— con su SUSTANTIVO (`L2`).
        -->
        <div class="orders__meta">
            {{ row.whenLabel }}
            <span class="orders__line-unit">· {{ row.quantityLabel }}</span>
        </div>

        <!-- Qué llevas contratado, SIN precio: es parte de «qué tengo», no de «cuánto cuesta». -->
        <ul v-if="row.addons.length" class="orders__lines">
            <li v-for="addon in row.addons" :key="addon.id" class="orders__line">
                <span class="orders__line-name">+ {{ addon.name }} <span class="orders__line-unit">· {{ addon.quantityLabel }}</span></span>
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
          Para quién es la ENTRADA (Fase 6 · tanda 4, `menores-a-cargo.md` §9.9.3 D7): bajo demanda y
          por `event-data`, como las respuestas del pack y por lo mismo — es el nombre de un menor y no
          viaja en la lista. Se ofrece solo si el titular tiene menores declarados.
        -->
        <button v-if="offerDependents && ! row.isPack" type="button" class="orders__gate-toggle"
                :aria-expanded="openDependents ? 'true' : 'false'" @click="$emit('toggle-dependents')">
            {{ openDependents ? account?.account?.dependents?.assigned_hide : account?.account?.dependents?.assigned_show }}
        </button>
        <ul v-if="openDependents" class="orders__event">
            <li v-if="dependents.length">
                <span class="orders__event-label">{{ account?.account?.dependents?.for_label }}</span> {{ dependents.map((d) => d.name).join(', ') }}
            </li>
            <li v-else>{{ account?.account?.dependents?.assigned_none }}</li>
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
          Los JUSTIFICANTES de menores invitados de ESTA reserva (`specs/waiver-por-reserva.md` §13).
          ❗ **Aquí es donde el owner lo buscó** —*«sigo sin ver el enlace para copiar en mis reservas»*—
          y donde tiene sentido desde que el justificante cuelga de la VISITA y no del pedido.
          ⚠️ El componente se pinta solo si esta reserva TIENE justificantes o enlace: en una reserva
          normal no aparece nada.
        -->
        <GuestMinorsPanel v-if="row.orderCode" :code="row.orderCode" :reservation-id="row.id" :account="account" />

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
