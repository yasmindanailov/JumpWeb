<script setup>
/**
 * El enlace del sistema de diseño (`Link.jsx`), portado a Vue: la acción secundaria, que nunca compite con el
 * botón que vende. Con `href` pinta `<a>`; sin él, un `<button>` con la misma cara. Lleva un área de toque de
 * 44px que no mueve el texto. Solo los tonos escritos con ROLES (`default`, `quiet`, `strong`).
 * ⚠️ Sin el modo `external` del diseño: la isla no lo usa, y a medias —sin su aviso «se abre en otra pestaña»
 * para el lector de pantalla— sería peor que no tenerlo. Entra con el primero que lo necesite. El estilo lo
 * compone `estiloEnlace()` (`estilos.js`, `CE-6`).
 */
import { computed, ref } from 'vue';
import IconoLucide from './IconoLucide.vue';
import { estiloEnlace, tallaIconoEnlace } from './estilos.js';

defineOptions({ inheritAttrs: false });

const props = defineProps({
    href: { type: String, default: undefined },
    variant: { type: String, default: 'default' },
    size: { type: String, default: 'md' },
    arrow: { type: Boolean, default: false },
    block: { type: Boolean, default: false },
    underline: { type: String, default: 'hover' },
    disabled: { type: Boolean, default: false },
});
const emit = defineEmits(['click']);

const hover = ref(false);
const talla = computed(() => tallaIconoEnlace(props.size));
const encendido = computed(() => hover.value && !props.disabled);
const etiqueta = computed(() => (props.href && !props.disabled ? 'a' : 'button'));
const estilo = computed(() => estiloEnlace({
    variant: props.variant,
    size: props.size,
    block: props.block,
    underline: props.underline,
    disabled: props.disabled,
    encendido: encendido.value,
}));

function pulsar(e) {
    if (!props.disabled) emit('click', e);
}
</script>

<template>
    <component
        :is="etiqueta"
        :href="etiqueta === 'a' ? href : undefined"
        :type="etiqueta === 'button' ? 'button' : undefined"
        :disabled="etiqueta === 'button' ? disabled : undefined"
        v-bind="$attrs"
        :style="[estilo, $attrs.style]"
        @click="pulsar"
        @mouseenter="hover = true"
        @mouseleave="hover = false"
        @focus="hover = true"
        @blur="hover = false"
    >
        <span
            v-if="!block"
            aria-hidden="true"
            :style="{ position: 'absolute', left: '-4px', right: '-4px', top: '50%', height: '44px', transform: 'translateY(-50%)' }"
        />
        <span :style="{ display: 'inline-flex', alignItems: 'center', gap: '7px', minWidth: 0 }">
            <span
                v-if="$slots.icono"
                :style="{ display: 'inline-flex', flexShrink: 0 }"
            ><slot name="icono" /></span>
            <span :style="{ minWidth: 0 }"><slot /></span>
        </span>
        <span
            v-if="arrow"
            :style="{ display: 'inline-flex', alignItems: 'center', gap: '4px', flexShrink: 0, transform: encendido ? 'translateX(3px)' : 'none', transition: 'var(--t-hover)' }"
        >
            <IconoLucide
                :name="block ? 'chevron-right' : 'arrow-right'"
                :size="talla"
            />
        </span>
    </component>
</template>
