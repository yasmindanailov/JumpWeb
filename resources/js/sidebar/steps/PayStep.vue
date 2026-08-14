<script setup>
import { t as translate, tp as translateWith } from '../i18n.js';
import { money } from '../money.js';
import { shortDate } from '../progress.js';

/**
 * Paso 8 — la pantalla de PAGO (Fase 4 · paso 4.5·2).
 *
 * Es el carrito otra vez, pero en modo **resumen**: lo que aquí se enseña ya no se toca, se paga. El
 * total y el CTA viven en el pie, y el desglose de la señal sube a su propia banda
 * (`bk-paybreakdown`), porque en esta pantalla el cliente tiene que ver **siempre** lo que se le va a
 * cobrar, no detrás de un ⓘ.
 *
 * ⚠️ **Cuatro diferencias con el paso 4 que el diff SÍ ve y que no se adivinan** (medidas contra el
 * Blade el 2026-08-14):
 *  1. la lista lleva `cart--summary` además de `cart`;
 *  2. **no hay botón de quitar**: el pedido está a un clic de crearse y retener aforo;
 *  3. el precio de la línea es un `<span>` **sin clase**, no el `.cart__price` del carrito;
 *  4. el pie de aviso (`purchase__foot--info`) se emite **siempre**, con el error dentro o vacío —
 *     no lleva ni «añadir otra reserva» ni el aviso de «carrito listo».
 *
 * ⚠️ Y una que el diff **no** ve: el CTA del pie estrena `icon: 'card'`. El normalizador no desciende
 * dentro de un `<svg>`, así que la tarjeta y la flecha son el mismo nodo para el gate; lo que cambia
 * es el dibujo, y de eso responde `SidebarCartParityTest` comparando el view-model del pie.
 */
const props = defineProps({
    /** Las líneas del presupuesto, ya emparejadas con las respuestas del pack (`cart.js`). */
    lines: { type: Array, default: () => [] },

    /** Aviso de la cesta, ya traducido. Ocupa el sitio del `@error('cart')` del Blade. */
    error: { type: String, default: '' },

    messages: { type: Object, default: () => ({}) },
    locale: { type: String, default: 'es' },
});

defineEmits(['back']);

const t = (key) => translate(props.messages, key);
const tp = (key, params) => translateWith(props.messages, key, params);

const shortTime = (time) => String(time ?? '').slice(0, 5);

const dayLabel = (date) => {
    const label = shortDate(date, props.locale);

    return label === '' ? '' : label.charAt(0).toUpperCase() + label.slice(1);
};

const includedLabel = (addon) => (addon.free_quantity >= addon.quantity
    ? t('addon_included')
    : tp('addon_included_partial', { count: addon.free_quantity }));
</script>

<template>
    <!-- El pedido AÚN NO existe (se crea al confirmar), así que volver al carrito es seguro y no
         pierde la cesta. Este paso no tiene banda de progreso, igual que la identificación. -->
    <button type="button" class="bk-back purchase__back" @click="$emit('back')">
        <svg class="arrow-ico" viewBox="0 0 24 24" aria-hidden="true"></svg>
        <span>{{ t('back_to_cart') }}</span>
    </button>

    <h3 class="wiz__title">{{ t('pay_title') }}</h3>
    <p class="purchase__note">{{ t('pay_intro') }}</p>

    <ul class="cart cart--summary">
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
                <!-- ⚠️ Sin clase, al contrario que en el carrito: aquí no hay botón de quitar al lado
                     y el precio no necesita reservar su hueco. -->
                <span>{{ money(line.subtotal_cents) }}</span>
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

            <!-- La señal se detalla en la card del producto que la cobra (#225): en una cesta mixta,
                 etiquetar el agregado engaña. -->
            <p v-if="line.has_deposit" class="cart__deposit">{{ tp('deposit_card_note', { deposit: money(line.deposit_cents), rest: money(line.gate_remainder_cents) }) }}</p>
        </li>
    </ul>

    <div class="purchase__foot purchase__foot--info">
        <p v-if="error" class="form__error">{{ error }}</p>
    </div>
</template>
