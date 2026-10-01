<script setup>
/**
 * El aviso a isla entera (`avisoEl` de `ParkIsland.jsx`, la Z6b·2): el check que salta, el hecho y el matiz, y la barra de lo
 * que le queda. Pinta (`CE-6`): su lógica y sus estilos, en `aviso-isla.js`; su reloj, en `useAviso.js`. Se toca entero
 * para quitarlo (`quitar`); el ratón o el foco encima lo paran (`pausa`).
 */
import IconoLucide from '../ui/IconoLucide.vue';
import { CARRIL, CHECK, HECHO, MATIZ, PROPS_AVISO, useAvisoIsla } from './aviso-isla.js';

const props = defineProps(PROPS_AVISO);
const emit = defineEmits(['quitar', 'pausa']);
const { partes, estilo, barra } = useAvisoIsla(props);
</script>

<template>
    <button
        type="button"
        data-isla-aviso=""
        :aria-label="etiqueta || undefined"
        :style="estilo"
        @click="(e) => emit('quitar', e)"
        @mouseenter="emit('pausa', true)"
        @mouseleave="emit('pausa', false)"
        @focus="emit('pausa', true)"
        @blur="emit('pausa', false)"
    >
        <span :style="{ display: 'flex', alignItems: 'center', gap: '12px' }">
            <span
                aria-hidden="true"
                :style="CHECK"
            ><IconoLucide
                name="check"
                :size="20"
            /></span>
            <span :style="{ display: 'grid', gap: '2px', minWidth: 0 }">
                <b :style="HECHO">{{ partes.hecho }}</b>
                <small
                    v-if="partes.matiz"
                    :style="MATIZ"
                >{{ partes.matiz }}</small>
            </span>
        </span>
        <span
            v-if="barra"
            aria-hidden="true"
            :style="CARRIL"
        ><span :style="barra" /></span>
    </button>
</template>
