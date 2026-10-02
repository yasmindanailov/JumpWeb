<script setup>
/**
 * **Una frase con un correo dentro, en negrita** («Te hemos enviado un código de 6 cifras a **ana@correo.es**.»; Z6g·1):
 * el correo va donde lo ponga cada idioma —su `:correo`— y se parte para pintarlo, sin recomponer la frase en el cliente.
 * `overflowWrap: anywhere`: un correo largo no empuja la isla de lado en un móvil estrecho.
 */
import { computed } from 'vue';

const props = defineProps({
    texto: { type: String, required: true },
    correo: { type: String, required: true },
});
const partes = computed(() => props.texto.split(':correo'));
</script>

<template>
    <span>{{ partes[0] }}<b :style="{ color: 'var(--text-strong)', overflowWrap: 'anywhere' }">{{ correo }}</b>{{ partes[1] ?? '' }}</span>
</template>
