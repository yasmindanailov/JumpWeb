<script setup>
/**
 * La acción de la isla (`ActionButton` del diseño), en la fila. Pinta (`CE-6`): su lógica —la secundaria con su giro de
 * tono, la etiqueta que nunca se corta y el cruce, Z6a— vive en `boton-accion.js`.
 */
import CargaRebote from '../ui/CargaRebote.vue';
import { ENTRA, PROPS_BOTON, SALE, useBotonAccion } from './boton-accion.js';

const props = defineProps(PROPS_BOTON);
const { hover, press, ring, bloqueado, apretada, tagRef, lblRef, estilo, click } = useBotonAccion(props);
</script>

<template>
    <component
        :is="href ? 'a' : 'button'"
        ref="tagRef"
        :href="href"
        :type="href ? undefined : 'button'"
        :aria-expanded="expanded"
        :aria-disabled="bloqueado || undefined"
        :aria-busy="loading ? true : undefined"
        data-isla-accion=""
        data-cara="boton"
        :style="estilo"
        @click="click"
        @mouseenter="hover = true"
        @mouseleave="hover = false; press = false"
        @pointerdown="press = true"
        @pointerup="press = false"
        @focus="ring = $event.currentTarget.matches(':focus-visible')"
        @blur="ring = false"
    >
        <span
            ref="lblRef"
            :key="label"
            :style="{ display: 'inline-flex', alignItems: 'center', gap: '10px', whiteSpace: 'nowrap', fontSize: apretada ? '15px' : undefined, animation: entra ? ENTRA : 'none' }"
        ><CargaRebote
            v-if="loading"
            size="sm"
            :label="loading === true ? label : loading"
        />{{ label }}</span>
        <span
            v-if="sale && sale !== label"
            :key="`sale|${sale}`"
            aria-hidden="true"
            :style="{ position: 'absolute', inset: 0, display: 'flex', alignItems: 'center', justifyContent: 'center', whiteSpace: 'nowrap', pointerEvents: 'none', animation: SALE }"
        >{{ sale }}</span>
    </component>
</template>
