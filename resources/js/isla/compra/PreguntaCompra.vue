<script setup>
/**
 * Una pregunta de un paso (`PjcPregunta` del diseño): su título, su control (la ranura) y, si la hay, su pista. Y, al pulsar
 * «Continuar» sin contestarla, lo que falta, en rojo bajo el título (M2, `#881`): la marca la da la compra (`falta.js`,
 * `FALTA`) y la pinta la pregunta de su `id`; fuera de la compra, nada. `nivel`: el de su título —3 dentro de una tarjeta
 * que ya tiene el suyo, la de la otra zona (`#882`)—.
 */
import { computed, inject } from 'vue';
import IconoLucide from '../ui/IconoLucide.vue';
import { PASO } from './estilos.js';
import { FALTA } from './falta.js';

const props = defineProps({
    titulo: { type: String, required: true },
    pista: { type: String, default: '' },
    id: { type: String, required: true },
    nivel: { type: Number, default: 2, validator: (n) => n === 2 || n === 3 },
});
const marca = inject(FALTA, null);
const falta = computed(() => (marca?.value?.id === props.id ? marca.value.texto : ''));
</script>

<template>
    <section
        :style="{ display: 'grid', gap: '12px' }"
        :aria-labelledby="id"
    >
        <component
            :is="`h${nivel}`"
            :id="id"
            :style="PASO.pregunta"
        >{{ titulo }}</component>
        <p
            v-if="falta"
            role="alert"
            :style="PASO.falta"
        ><IconoLucide
            name="circle-alert"
            :size="16"
        />{{ falta }}</p>
        <slot />
        <p
            v-if="pista"
            :style="PASO.pista"
        >{{ pista }}</p>
    </section>
</template>
