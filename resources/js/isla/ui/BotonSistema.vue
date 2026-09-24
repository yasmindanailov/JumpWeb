<script setup>
/**
 * El botón del sistema de diseño (`Button.jsx`), portado a Vue con sus mismos estilos en línea.
 *
 * Solo las variantes que se escriben con ROLES (`primary`, `secondary`, `outline`, `ghost`, `quiet`): las
 * del diseño que nombraban la paleta de su marca (`volt`, `inverse`, `glass`) no entran en el producto. Las
 * de control pasan solas a blanco dentro de la isla (`data-surface="ink"`). `loading` bloquea el botón: un
 * segundo toque no paga dos veces (la bola de carga llega con la T3). El estilo lo compone `estiloBoton()`
 * (`estilos.js`): un componente pinta, y las tablas van en un módulo plano (`CE-6`).
 */
import { computed, ref } from 'vue';
import { estiloBoton } from './estilos.js';

defineOptions({ inheritAttrs: false });

const props = defineProps({
    variant: { type: String, default: 'primary' },
    size: { type: String, default: 'md' },
    full: { type: Boolean, default: false },
    disabled: { type: Boolean, default: false },
    href: { type: String, default: undefined },
    type: { type: String, default: 'button' },
    loading: { type: Boolean, default: false },
});
const emit = defineEmits(['click']);

const hover = ref(false);
const press = ref(false);
const bloqueado = computed(() => props.disabled || props.loading);
const etiqueta = computed(() => (props.href && !bloqueado.value ? 'a' : 'button'));
const estilo = computed(() => estiloBoton({
    variant: props.variant,
    size: props.size,
    full: props.full,
    loading: props.loading,
    bloqueado: bloqueado.value,
    hover: hover.value,
    press: press.value,
}));

function pulsar(e) {
    if (!bloqueado.value) emit('click', e);
}
</script>

<template>
    <component
        :is="etiqueta"
        :href="etiqueta === 'a' ? href : undefined"
        :type="etiqueta === 'button' ? type : undefined"
        :disabled="etiqueta === 'button' ? bloqueado : undefined"
        v-bind="$attrs"
        :style="[estilo, $attrs.style]"
        :aria-busy="loading || undefined"
        @click="pulsar"
        @mouseenter="hover = true"
        @mouseleave="hover = false; press = false"
        @pointerdown="press = true"
        @pointerup="press = false"
    >
        <slot name="icono-izquierda" />
        <slot />
        <slot name="icono-derecha" />
    </component>
</template>
