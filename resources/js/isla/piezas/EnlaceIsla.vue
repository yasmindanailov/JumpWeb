<script setup>
/**
 * El enlace suelto de la isla (`TextLink` del diseño): el `Enlace` del sistema, cian sobre tinta. Sin acción,
 * es solo un texto (el idioma, mientras no haya selector).
 */
import EnlaceSistema from '../ui/EnlaceSistema.vue';

defineProps({
    label: { type: String, required: true },
    pulsar: { type: Function, default: null },
});
</script>

<template>
    <EnlaceSistema
        v-if="pulsar"
        @click="pulsar"
    >{{ label }}</EnlaceSistema>
    <span
        v-else
        :style="{ fontFamily: 'var(--font-ui)', fontSize: 'var(--fs-body-sm)', fontWeight: 'var(--fw-semibold)', color: 'var(--text-muted)' }"
    >{{ label }}</span>
</template>
