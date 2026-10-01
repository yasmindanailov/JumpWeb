<script setup>
/**
 * El hueco de la acción, con su relevo (`SlotSwap` del diseño, Z6b): lo que llega (la acción o un banner) entra
 * enfocándose mientras lo que se va (`#sale`, su copia quieta) se desenfoca encima. Qué se va y cuándo, en `useHueco.js`.
 */
import { ENTRA, SALE } from './boton-accion.js';

defineProps({
    clave: { type: String, required: true },
    crece: { type: Boolean, default: false },
    saliendo: { type: Boolean, default: false },
});
</script>

<template>
    <div :style="{ position: 'relative', display: 'flex', flex: crece ? '1 1 auto' : '0 0 auto', minWidth: 0 }">
        <div
            :key="clave"
            :style="{ display: 'flex', flex: '1 1 auto', minWidth: 0, animation: saliendo ? ENTRA : 'none' }"
        >
            <slot />
        </div>
        <!-- `inert`: la copia que se va no se enfoca ni se pulsa (es un botón de verdad, quieto, durante 260 ms). -->
        <div
            v-if="saliendo"
            aria-hidden="true"
            inert
            :style="{ position: 'absolute', inset: 0, display: 'flex', pointerEvents: 'none', animation: SALE }"
        >
            <slot name="sale" />
        </div>
    </div>
</template>
