<script setup>
/**
 * El chip del sistema de diseño (`Tag.jsx`): un atributo o un filtro; se PULSA cuando recibe `@click` —entonces es un
 * botón, con su `hover`— y si no, es un `<span>`. En la calculadora, «Un par para cada niño» (T4d). El icono, en su
 * ranura; `count`, la cifra pequeña al final.
 */
import { computed, ref, useAttrs } from 'vue';
import { estiloEtiqueta } from './compartir.js';

defineOptions({ inheritAttrs: false });

const props = defineProps({
    selected: { type: Boolean, default: false },
    disabled: { type: Boolean, default: false },
    count: { type: [Number, String], default: null },
});
const attrs = useAttrs();
const pulsable = computed(() => typeof attrs.onClick === 'function');
const resto = computed(() => Object.fromEntries(Object.entries(attrs).filter(([k]) => k !== 'onClick' && k !== 'style')));
const sobre = ref(false);
const pulsar = (e) => { if (! props.disabled) attrs.onClick?.(e); };
</script>

<template>
    <component
        :is="pulsable ? 'button' : 'span'"
        v-bind="resto"
        :type="pulsable ? 'button' : undefined"
        :disabled="pulsable ? disabled : undefined"
        :style="[estiloEtiqueta({ selected, disabled, pulsable, sobre }), attrs.style]"
        @click="pulsar"
        @mouseenter="sobre = true"
        @mouseleave="sobre = false"
    ><slot name="icono" /><slot /><span v-if="count != null" :style="{ fontFamily: 'var(--font-mono)', fontSize: 'var(--fs-overline)', opacity: 0.6 }">{{ count }}</span></component>
</template>
