<script setup>
/** Una pregunta de un paso (`PjcPregunta` del diseño): su título, su control (la ranura) y, si la hay, su pista. */
import { PASO } from './estilos.js';

defineProps({
    titulo: { type: String, required: true },
    pista: { type: String, default: '' },
    id: { type: String, required: true },
});
</script>

<template>
    <section
        :style="{ display: 'grid', gap: '12px' }"
        :aria-labelledby="id"
    >
        <h2
            :id="id"
            :style="PASO.pregunta"
        >{{ titulo }}</h2>
        <slot />
        <p
            v-if="pista"
            :style="PASO.pista"
        >{{ pista }}</p>
    </section>
</template>
