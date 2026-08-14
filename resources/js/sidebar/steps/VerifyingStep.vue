<script setup>
import { t as translate } from '../i18n.js';

/**
 * Paso 11 — **verificando el pago** (Fase 4 · paso 4.6·2).
 *
 * Es la pantalla de los terminales que vuelven **sin los datos firmados**: el banco procesó el cobro,
 * pero la redirección no trae con qué confirmarlo, así que este lado no puede dar el pedido por pagado
 * —`PAY-01`: el único que lo hace es el receptor de la respuesta firmada— y solo queda **sondear**.
 *
 * ⚠️ **El sondeo no vive aquí.** Este componente solo pinta; quién pregunta, cada cuánto y qué hace con
 * la respuesta es de `Sidebar.vue` y `outcome.js`, por lo de siempre (`CE-6`): un árbol no dice a quién
 * se preguntó ni cuándo se dejó de preguntar, y un intervalo que no se limpia es un fallo que ningún
 * diff puede ver.
 *
 * ⚠️ **`role="status"` + `aria-live="polite"` no son decoración**: la pantalla cambia sola cuando llega
 * la confirmación, y sin ellos un lector de pantalla no anunciaría nada. Los dos son atributos de
 * contrato del normalizador, así que el gate los compara.
 *
 * La única acción es «ver mis reservas», a ancho completo — coherente con los pasos 6 y 10, donde el
 * criterio es el mismo: una sola acción ⇒ primaria.
 */
const props = defineProps({
    /** El código del pedido, que es lo que el cliente necesita si tiene que escribir. */
    orderCode: { type: String, default: '' },

    /** Adónde lleva «ver mis reservas». Lo compone el servidor con `route()`. */
    ordersUrl: { type: String, default: '' },

    messages: { type: Object, default: () => ({}) },
});

const t = (key) => translate(props.messages, key);
</script>

<template>
    <div class="purchase__verifying" role="status" aria-live="polite">
        <h3 class="wiz__title">{{ t('payment_verifying_title') }}</h3>
        <p class="purchase__note">{{ t('payment_verifying_intro') }}</p>
        <p v-if="orderCode" class="purchase__code">{{ t('order_code') }}: <strong>{{ orderCode }}</strong></p>
        <p class="purchase__note">{{ t('payment_verifying_email_note') }}</p>

        <div class="purchase__final-actions">
            <a :href="ordersUrl" class="btn btn--zone btn--lg purchase__cta">{{ t('see_my_orders') }}</a>
        </div>
    </div>
</template>
