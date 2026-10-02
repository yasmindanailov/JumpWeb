<script setup>
/**
 * La cara de barra de la acción (el experimento B3, Z6c: la rama `sub` de `ActionButton` en el diseño): la etiqueta, la
 * frase corta con el punto de su tono y el círculo con la flecha, que le da la isla por su ranura. Pinta (`CE-6`): su
 * lógica y sus estilos, en `barra-accion.js`. ⚠️ El círculo va siempre en naranja (`#868`).
 */
import { ENTRA, SALE } from './boton-accion.js';
import { ETIQUETA, FRASE, FRASE_TEXTO, PROPS_BARRA, useBarraAccion } from './barra-accion.js';

const props = defineProps(PROPS_BARRA);
const { hover, press, ring, bloqueado, sale, clave, estilo, circulo, punto, click } = useBarraAccion(props);
</script>

<template>
    <component
        :is="href ? 'a' : 'button'"
        :href="href"
        :type="href ? undefined : 'button'"
        :aria-expanded="expanded"
        :aria-disabled="bloqueado || undefined"
        data-isla-accion=""
        data-cara="barra"
        :style="estilo"
        @click="click"
        @mouseenter="hover = true"
        @mouseleave="hover = false; press = false"
        @pointerdown="press = true"
        @pointerup="press = false"
        @pointercancel="press = false"
        @focus="ring = $event.currentTarget.matches(':focus-visible')"
        @blur="ring = false"
    >
        <span :style="{ position: 'relative', flex: '1 1 auto', minWidth: 0, display: 'grid' }">
            <span
                :key="clave"
                :style="{ display: 'grid', gap: '2px', minWidth: 0, animation: sale || entra ? ENTRA : 'none' }"
            >
                <b :style="ETIQUETA">{{ label }}</b>
                <small
                    v-if="sub"
                    aria-live="polite"
                    :style="FRASE"
                ><span
                    v-if="punto"
                    :style="punto"
                /><span :style="FRASE_TEXTO">{{ sub }}</span></small>
            </span>
            <span
                v-if="sale"
                :key="`sale|${sale.clave}`"
                aria-hidden="true"
                :style="{ position: 'absolute', inset: 0, display: 'grid', alignContent: 'center', gap: '2px', minWidth: 0, pointerEvents: 'none', animation: SALE }"
            >
                <b :style="ETIQUETA">{{ sale.label }}</b>
                <small
                    v-if="sale.sub"
                    :style="FRASE"
                ><span :style="FRASE_TEXTO">{{ sale.sub }}</span></small>
            </span>
        </span>
        <span
            aria-hidden="true"
            :style="circulo"
        ><slot /></span>
    </component>
</template>
