<script setup>
/** Lo que ya está fijado y no se elige aquí (`PjcFijo` del diseño): «Sáb 26 · 17:00», «Pack Kids». */
import IconoLucide from '../ui/IconoLucide.vue';

defineProps({
    icono: { type: String, default: '' },
});
</script>

<template>
    <p :style="{ display: 'inline-flex', alignItems: 'center', gap: '8px', justifySelf: 'start', margin: 0, padding: '8px 14px', borderRadius: 'var(--r-pill)', background: 'var(--control-bg)', fontFamily: 'var(--font-ui)', fontSize: 'var(--fs-body-sm)', fontWeight: 'var(--fw-semibold)', color: 'var(--text-strong)' }">
        <IconoLucide
            v-if="icono"
            :name="icono"
            :size="16"
        /><slot />
    </p>
</template>
