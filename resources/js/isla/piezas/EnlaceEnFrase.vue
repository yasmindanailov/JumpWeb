<script setup>
/**
 * Un enlace DENTRO de una frase (`InlineLink` de `ParkIsland.jsx`, zip del 27-09: el aviso de cookies compacto). El color
 * y el subrayado del enlace de la isla, sin su caja de 44px: dentro de un texto, el objetivo de toque es la línea (WCAG
 * 2.5.8 lo exime). Sin acción, es solo el texto.
 */
defineProps({
    label: { type: String, required: true },
    pulsar: { type: Function, default: null },
});
</script>

<template>
    <button
        v-if="pulsar"
        type="button"
        :style="{ font: 'inherit', fontWeight: 'var(--fw-semibold)', color: 'var(--text-link)', background: 'none', border: 0, padding: 0, margin: 0, cursor: 'pointer', textDecoration: 'underline', textUnderlineOffset: '2px' }"
        @click="pulsar"
    >{{ label }}</button>
    <span
        v-else
        :style="{ fontWeight: 'var(--fw-semibold)' }"
    >{{ label }}</span>
</template>
