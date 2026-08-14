<script setup>
import { t as translate } from '../i18n.js';

/**
 * Paso 7 — «revisa tu correo» (Fase 4 · paso 4.4b·1).
 *
 * Tres nodos, y aun así merece explicación: **dentro de la compra esta pantalla casi nunca la ve una
 * persona**. El alta embebida es *pay-first* —crea la cuenta, inicia sesión y sigue al pago—, así que
 * aquí solo se llega cuando el servidor **fingió** un alta: el señuelo actuó. La web hace exactamente
 * lo mismo (`Register::finishGeneric()` → `registration-submitted` → paso 7), y es lo que impide que
 * un bot distinga un alta buena de una fingida.
 *
 * ⚠️ **No lleva salida, y eso es fiel**: el Blade tampoco la tiene. El escape «¿ya tienes cuenta?»
 * vive en la pantalla `sent` del componente Register, que **embebido no llega a verse** —el paso 5
 * deja de renderizarse en cuanto el cajón pasa al 7—. Verificado: el HTML del paso 7 no contiene ni el
 * formulario ni el escape.
 *
 * ⚠️ `role="status"` es contrato: lo anuncia un lector de pantalla sin robar el foco.
 */
const props = defineProps({
    messages: { type: Object, default: () => ({}) },
});

const t = (key) => translate(props.messages, key);
</script>

<template>
    <div class="purchase__confirm" role="status">
        <h3 class="wiz__title">{{ t('verify_title') }}</h3>
        <p class="purchase__note">{{ t('verify_intro') }}</p>
    </div>
</template>
