<script setup>
/**
 * El botón del sistema de diseño (`Button.jsx`), portado a Vue con sus mismos estilos en línea.
 *
 * Solo las variantes que se escriben con ROLES (`primary`, `secondary`, `outline`, `ghost`, `quiet`): las
 * del diseño que nombraban la paleta de su marca (`volt`, `glass`) no entran en el producto; la `inverse`, con roles (T5a). Las
 * de control pasan solas a blanco dentro de la isla (`data-surface="ink"`). `loading` bloquea el botón —un
 * segundo toque no paga dos veces— y pone la bola pequeña, leyendo `loadingLabel` o el texto del botón. El estilo lo
 * compone `estiloBoton()` (`estilos.js`): un componente pinta, y las tablas van en un módulo plano (`CE-6`).
 * ▶ El movimiento (26-09; Z3, `#782`): cargando NO cambia de ancho —el contenido se queda en su sitio, invisible, y la
 * bola va encima—; el icono de la derecha avanza 3px al pasar; y el primario fuera de la isla LLEGA con bote y un brillo
 * la primera vez que se ve (`useLlegada`). El `done` del diseño (lima con el visto) no se porta: nadie lo usa todavía.
 */
import { computed, ref, useSlots } from 'vue';
import { BRILLO_BOTON, CAPA_BOTON, estiloBoton, huecoBoton } from './estilos.js';
import { textoDeRanura } from './piezas.js';
import { useLlegada } from './useLlegada.js';
import { useTextos } from '../piezas/textos.js';
import CargaRebote from './CargaRebote.vue';

defineOptions({ inheritAttrs: false });

const props = defineProps({
    variant: { type: String, default: 'primary' },
    size: { type: String, default: 'md' },
    full: { type: Boolean, default: false },
    disabled: { type: Boolean, default: false },
    href: { type: String, default: undefined },
    type: { type: String, default: 'button' },
    loading: { type: Boolean, default: false },
    loadingLabel: { type: String, default: '' },
});
const emit = defineEmits(['click']);
const slots = useSlots();
const { t } = useTextos();

const raiz = ref(null);
const hover = ref(false);
const press = ref(false);
const bloqueado = computed(() => props.disabled || props.loading);
const llega = useLlegada(raiz, () => props.variant === 'primary' && ! bloqueado.value);
const etiqueta = computed(() => (props.href && !bloqueado.value ? 'a' : 'button'));
const estilo = computed(() => estiloBoton({
    variant: props.variant, size: props.size, full: props.full, loading: props.loading, bloqueado: bloqueado.value,
    hover: hover.value, press: press.value, llega: llega.value,
}));

const textoCarga = () => props.loadingLabel || textoDeRanura(slots.default?.()) || t('pieza.cargando');

function pulsar(e) {
    if (!bloqueado.value) emit('click', e);
}
</script>

<template>
    <component
        :is="etiqueta"
        ref="raiz"
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
        <span :style="{ display: 'inline-flex', alignItems: 'center', justifyContent: 'center', gap: huecoBoton(size), minWidth: 0, visibility: loading ? 'hidden' : 'visible' }">
            <slot name="icono-izquierda" />
            <slot />
            <span
                v-if="$slots['icono-derecha']"
                :style="{ display: 'inline-flex', transition: 'transform var(--dur-base) var(--ease-spring)', transform: hover && !bloqueado ? 'translateX(3px)' : 'none' }"
            ><slot name="icono-derecha" /></span>
        </span>
        <span
            v-if="loading"
            :style="{ ...CAPA_BOTON, gap: huecoBoton(size) }"
        ><CargaRebote
            size="sm"
            :label="textoCarga()"
        /></span>
        <span
            v-if="llega"
            aria-hidden="true"
            :style="BRILLO_BOTON"
        />
    </component>
</template>
