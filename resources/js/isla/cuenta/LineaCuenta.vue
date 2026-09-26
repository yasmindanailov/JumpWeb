<script setup>
/**
 * Una línea con su icono, bajo una reserva (`PmcLinea` del diseño): qué se compró, la señal, el plazo. `fuerte`, en
 * negrita y en el color fuerte (la señal pagada).
 */
import { CUENTA } from './estilos.js';
import IconoLucide from '../ui/IconoLucide.vue';

defineProps({
    icon: { type: String, required: true },
    fuerte: { type: Boolean, default: false },
});
</script>

<template>
    <p :style="[CUENTA.cuerpo, { display: 'flex', gap: '10px', alignItems: 'flex-start' }, fuerte ? { color: 'var(--text-strong)', fontWeight: 'var(--fw-semibold)' } : null]">
        <span
            aria-hidden="true"
            :style="{ flex: '0 0 auto', marginTop: '2px', color: 'var(--icon-accent)' }"
        ><IconoLucide
            :name="icon"
            :size="17"
        /></span>
        <span><slot /></span>
    </p>
</template>
