<script setup>
import { t as translate } from '../i18n.js';

/**
 * Paso 10 — **el pago denegado**, con su reintento (Fase 4 · paso 4.6·2).
 *
 * El pedido **sigue vivo y sigue reteniendo aforo**: el banco no autorizó el cobro, pero la reserva
 * está ahí hasta que su ventana venza. Por eso la pantalla no es un error sino una segunda
 * oportunidad, y su CTA principal reabre el cobro sobre el MISMO pedido —sin consumir aforo nuevo y
 * sin aplicar el tope de pedidos pendientes—.
 *
 * ⚠️ **El bloque del motivo se emite SIEMPRE que se pudo preguntar, y está medido**: el Blade lo
 * condiciona a `$declinedReasonText`, pero `RedsysResponseCode::reasonText()` **nunca devuelve null**
 * —cae a `default`—, así que con sesión y con código de pedido el bloque está siempre. Condicionarlo a
 * «hay motivo conocido» habría emitido un nodo de menos justo en el caso más frecuente.
 *
 * ⚠️ **La jerarquía de los tres CTA es contrato visual**: principal reintenta (`btn--zone btn--lg`),
 * secundario empieza otra reserva (`btn--ghost`) y terciario escribe a soporte (`btn--ghost`, y es un
 * `<a>`, no un `<button>` — el diff de árbol compara el tipo de elemento).
 *
 * ⚠️ **Los dos `<span>` del botón principal están SIEMPRE en el árbol.** En Livewire `wire:loading` es
 * un atributo, no un condicional de servidor: el rótulo y el `.btn__loading` con su spinner viajan los
 * dos en el HTML y se alternan en el navegador. Aquí se hace con `v-show` por lo mismo — con `v-if`
 * faltaría un nodo y el gate lo vería.
 */
const props = defineProps({
    /** El código del pedido. Sin él no hay nada que reintentar y el bloque no se pinta. */
    orderCode: { type: String, default: '' },

    /**
     * El motivo del rechazo YA traducido, o cadena vacía si no se pudo preguntar al servidor.
     *
     * Vacío es el espejo del `null` de Livewire cuando no hay sesión o no hay pedido: entonces el
     * bloque entero desaparece, en los dos motores.
     */
    reason: { type: String, default: '' },

    /** `true` mientras se reabre el cobro: alterna el rótulo del CTA y lo desactiva. */
    retrying: { type: Boolean, default: false },

    /** Adónde escribir. Lo compone el servidor con `route()`: el cajón no conoce las rutas. */
    contactUrl: { type: String, default: '' },

    messages: { type: Object, default: () => ({}) },
});

defineEmits(['retry', 'add-another']);

const t = (key) => translate(props.messages, key);
</script>

<template>
    <div class="purchase__failed" role="alert">
        <!-- ⚠️ Esta pantalla NO tenía ningún icono (`#258`): decía «pago rechazado» solo con texto,
             mientras la de al lado celebraba con un dibujo de 56 px. El artboard declara el error
             como pegatina —Rojo Goteo— igual que el éxito.
             ⚠️ Va `aria-hidden`: el estado ya lo anuncia el `role="alert"` del contenedor y el
             titular. Un icono anunciado además sería decirlo dos veces.
             `close` del sistema de diseño, copiado byte a byte (`SidebarIconParityTest`). -->
        <div class="purchase__party" aria-hidden="true">
            <span class="state-badge state-badge--err">
                <svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                    <rect x="2.6" y="10.55" width="18.8" height="2.9" rx="1.45" transform="rotate(45 12 12)" />
                    <rect x="2.6" y="10.55" width="18.8" height="2.9" rx="1.45" transform="rotate(-45 12 12)" />
                </svg>
            </span>
        </div>
        <h3 class="wiz__title">{{ t('payment_failed_title') }}</h3>
        <p class="purchase__note">{{ t('payment_failed_intro') }}</p>

        <p v-if="reason" class="purchase__note purchase__note--reason">
            <strong>{{ t('payment_failed_reason_label') }}:</strong>
            {{ reason }}
        </p>

        <p v-if="orderCode" class="purchase__code">{{ t('order_code') }}: <strong>{{ orderCode }}</strong></p>
        <p class="purchase__note">{{ t('payment_failed_retry') }}</p>

        <div class="purchase__final-actions">
            <button type="button" class="btn btn--zone btn--lg purchase__cta" :disabled="retrying" @click="$emit('retry')">
                <span v-show="! retrying">{{ t('payment_failed_retry_cta') }}</span>
                <span v-show="retrying" class="btn__loading">
                    <span class="jj-spinner jj-spinner--xs" aria-hidden="true"></span> {{ t('pay_redirecting') }}
                </span>
            </button>
            <button type="button" class="btn btn--ghost purchase__cta-secondary" @click="$emit('add-another')">
                {{ t('new_purchase') }}
            </button>
            <a :href="contactUrl" class="btn btn--ghost purchase__cta-tertiary">{{ t('payment_failed_contact') }}</a>
        </div>
    </div>
</template>
