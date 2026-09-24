<script setup>
/**
 * Un paso de la compra (`PjcPaso` del diseño): su titular —el foco va ahí al llegar— y sus bloques. Lo que va
 * antes del titular («¿Ya has venido? Entra») es la ranura `antes`.
 */
import { PASO } from './estilos.js';

defineProps({
    titulo: { type: String, default: '' },
});
</script>

<template>
    <div :style="PASO.paso">
        <slot name="antes" />
        <h1
            v-if="titulo"
            tabindex="-1"
            :style="PASO.titulo"
        >{{ titulo }}</h1>
        <slot />
    </div>
</template>
