<script setup>
import { t as translate, tp as translateWith } from '../i18n.js';
import { money } from '../money.js';
import { shortDate } from '../progress.js';

/**
 * Paso 4 — el CARRITO (Fase 4 · paso 4.3·2).
 *
 * **Pinta lo que el presupuesto dice.** Los importes llegan tarificados por `POST /orders/quote`, que
 * es el mismo contrato que alimenta la creación del pedido; aquí no se suma nada (`PAY-12`). Y el
 * TOTAL no está en esta pantalla: vive en el pie, que es donde también está el «Ir a pagar».
 *
 * ⚠️ **Tres detalles del árbol que no se adivinan leyendo el Blade** y que el diff sí ve:
 *  - `.cart__lines` se emite SIEMPRE, incluso vacío: el `@if` está DENTRO del `<div>`, no fuera. Un
 *    `v-if` sobre el div —lo natural en Vue— deja un nodo de menos y se lleva la separación que da
 *    la cabecera;
 *  - el icono de producto NO es un `<svg>` suelto: es un `<span>` envoltorio con `aria-hidden`, y ese
 *    envoltorio es contrato;
 *  - el botón de quitar lleva `aria-label`, que es de los pocos atributos cuyo VALOR compara el gate:
 *    tiene que ser el texto traducido exacto.
 *
 * ⚠️ **`index` es la posición en la CESTA, no el ordinal de la lista pintada.** El presupuesto salta
 * las líneas cuyo producto dejó de venderse y conserva el índice original, así que la segunda línea
 * que se ve puede ser la número 3. Emitir el ordinal borra otra reserva, y el diff de árbol no lo ve
 * porque descarta los manejadores de evento.
 */
const props = defineProps({
    /** Las líneas del presupuesto, ya emparejadas con las respuestas del pack (`cart.js`). */
    lines: { type: Array, default: () => [] },
    /** Aviso de «carrito listo» tras confirmar un pedido y volver a empezar. */
    confirmed: { type: Boolean, default: false },
    /** Error de la cesta, ya traducido. Ocupa el sitio del `@error('cart')` del Blade. */
    error: { type: String, default: '' },
    messages: { type: Object, default: () => ({}) },
    locale: { type: String, default: 'es' },
});

defineEmits(['remove', 'add-another']);

const t = (key) => translate(props.messages, key);
const tp = (key, params) => translateWith(props.messages, key, params);

/** `10:00:00` → `10:00`. El servidor guarda la hora canónica; la fila enseña la corta. */
const shortTime = (time) => String(time ?? '').slice(0, 5);

/**
 * ⚠️ La fecha de la fila la compone el cliente con `Intl` y el servidor con Carbon (§4.5). Medido: en
 * inglés y en francés coinciden; en español difieren los puntos de abreviatura. Es la divergencia ya
 * declarada, la misma que la línea de contexto de la banda.
 */
const dayLabel = (date) => {
    const label = shortDate(date, props.locale);

    return label === '' ? '' : label.charAt(0).toUpperCase() + label.slice(1);
};

/**
 * El texto de «incluido» se elige con `>=`, no con `>`: un complemento con una unidad gratis de una
 * sola pedida dice «Incluido», no «1 incluido(s) gratis».
 */
const includedLabel = (addon) => (addon.free_quantity >= addon.quantity
    ? t('addon_included')
    : tp('addon_included_partial', { count: addon.free_quantity }));
</script>

<template>
    <h3 class="wiz__title">{{ t('cart_title') }}</h3>

    <p v-if="lines.length === 0" class="purchase__empty">{{ t('cart_empty') }}</p>

    <template v-else>
        <ul class="cart">
            <li v-for="line in lines" :key="line.index" class="cart__item">
                <div class="cart__head">
                    <span class="cart__when">
                        <span v-if="line.is_pack" class="icon ic-b1 prod-ico" aria-hidden="true">
                            <svg viewBox="0 0 40 40" width="20" height="20"></svg>
                        </span>
                        <span v-else class="tk prod-ico" aria-hidden="true">
                            <svg viewBox="0 0 60 36" width="22" height="13" fill="none"></svg>
                        </span>
                        <template v-if="line.is_pack">{{ tp('guests_count', { count: line.quantity }) }} · {{ line.product_name }}</template>
                        <template v-else>{{ line.quantity }}&times; {{ line.product_name }}</template>
                    </span>
                    <span class="cart__price">{{ money(line.subtotal_cents) }}</span>
                    <button type="button" class="cart__remove" :aria-label="t('remove')" @click="$emit('remove', line.index)">&times;</button>
                </div>

                <!-- Se emite SIEMPRE, aunque quede vacío: el condicional del Blade está DENTRO. -->
                <div class="cart__lines">
                    <span v-if="line.date">{{ dayLabel(line.date) }} · {{ shortTime(line.time) }}</span>
                </div>

                <ul v-if="line.event.length" class="cart__event">
                    <li v-for="answer in line.event" :key="answer.key">
                        <span class="cart__event-label">{{ answer.label }}:</span> {{ answer.value }}
                    </li>
                </ul>

                <ul v-if="line.addons.length" class="cart__addons">
                    <li v-for="addon in line.addons" :key="addon.product_id">
                        <span>+ {{ addon.quantity }}&times; {{ addon.product_name }}<em v-if="addon.free_quantity" class="cart__addon-incl">{{ includedLabel(addon) }}</em></span>
                        <span>{{ money(addon.subtotal_cents) }}</span>
                    </li>
                </ul>

                <!-- La señal es POR LÍNEA (#225): en una cesta mixta, etiquetar el agregado engaña. -->
                <p v-if="line.has_deposit" class="cart__deposit">{{ tp('deposit_card_note', { deposit: money(line.deposit_cents), rest: money(line.gate_remainder_cents) }) }}</p>
            </li>
        </ul>

        <div class="purchase__foot purchase__foot--info">
            <p v-if="error" class="form__error">{{ error }}</p>
            <div v-if="confirmed" class="purchase__confirm">{{ t('confirm_next') }}</div>
            <button type="button" class="purchase__add-more" @click="$emit('add-another')">+ {{ t('add_another') }}</button>
        </div>
    </template>
</template>
