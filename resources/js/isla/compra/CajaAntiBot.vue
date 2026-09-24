<script setup>
/**
 * El anti-bot del alta, dentro de «Tus datos» de la isla (T3e·3), solo si la instalación lo tiene ACTIVO
 * (`register.js::signupRequiresCaptcha`, la clave de `GET /config`). Montar, esperar a la clave y al nodo, y
 * rendirse son de `sidebar/turnstile.js`, lo mismo que el alta del cajón (`steps/RegisterForm.vue`).
 *
 * ⚠️ El token es de un solo uso y el servidor lo quema antes de mirar el correo: quien lleva el alta lo VACÍA tras
 * un intento fallido, y eso es la señal de pedir otro.
 */
import { onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { mountTurnstile } from '../../sidebar/turnstile.js';

const props = defineProps({
    sitekey: { type: String, required: true },
});
const token = defineModel('token', { type: String, default: '' });
const caja = ref(null);
let widget = null;

onMounted(() => {
    widget = mountTurnstile(() => caja.value, { sitekey: () => props.sitekey, onToken: (valor) => { token.value = valor; } });
});
watch(token, (valor, antes) => { if (valor === '' && antes !== '') widget?.reset(); });
onBeforeUnmount(() => widget?.destroy());
</script>

<template>
    <div ref="caja" />
</template>
