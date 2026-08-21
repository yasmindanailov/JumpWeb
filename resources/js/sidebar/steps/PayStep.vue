<script setup>
import { t as translate } from '../i18n.js';
import SummaryLine from './SummaryLine.vue';

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
 *
 * ⚠️ **La FILA vive en `SummaryLine.vue` desde 4.6·1**, porque la pantalla de reserva creada emite
 * exactamente el mismo árbol: dos copias de un marcado que el CSS mira por estructura divergirían en
 * silencio, con el diff de cada pantalla verde por separado.
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
</script>

<template>
    <!-- El pedido AÚN NO existe (se crea al confirmar), así que volver al carrito es seguro y no
         pierde la cesta. Este paso no tiene banda de progreso, igual que la identificación. -->
    <button type="button" class="bk-back purchase__back" @click="$emit('back')">
        <svg class="arrow-ico" width="16" height="16" viewBox="0 0 24 24" fill="none"
             stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"
             aria-hidden="true" focusable="false">
            <line x1="19" y1="12" x2="5" y2="12" />
            <polyline points="12 19 5 12 12 5" />
        </svg>
        <span>{{ t('back_to_cart') }}</span>
    </button>

    <h3 class="wiz__title">{{ t('pay_title') }}</h3>
    <p class="purchase__note">{{ t('pay_intro') }}</p>

    <ul class="cart cart--summary">
        <SummaryLine v-for="line in lines" :key="line.index" :line="line" :messages="messages" :locale="locale" />
    </ul>

    <div class="purchase__foot purchase__foot--info">
        <p v-if="error" class="form__error">{{ error }}</p>
    </div>
</template>
