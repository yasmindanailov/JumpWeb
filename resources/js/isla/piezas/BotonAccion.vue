<script setup>
/**
 * La acción de la isla (`ActionButton` del diseño): una sola, naranja, en la fila. En móvil puede llevar la
 * línea de situación dentro (`sublabel`). `loading` la bloquea; la bola de carga llega con la compra (T3).
 */
import { computed, ref } from 'vue';

const props = defineProps({
    top: { type: Boolean, default: false },
    label: { type: String, required: true },
    sublabel: { type: String, default: null },
    href: { type: String, default: undefined },
    pulsar: { type: Function, default: null },
    expanded: { type: Boolean, default: undefined },
    big: { type: Boolean, default: false },
    disabled: { type: Boolean, default: false },
    loading: { type: [Boolean, String], default: false },
});

const hover = ref(false);
const press = ref(false);
const ring = ref(false);
const bloqueado = computed(() => props.disabled || Boolean(props.loading));

const estilo = computed(() => ({
    display: 'flex', flexDirection: 'column', alignItems: 'center', justifyContent: 'center', gap: '1px',
    flex: props.top ? '0 0 auto' : '1 1 auto', minWidth: 0, minHeight: props.big ? '54px' : '46px', padding: props.top ? '0 24px' : '5px 14px',
    border: 'none', borderRadius: 'var(--r-pill)',
    background: hover.value && !bloqueado.value ? 'var(--action-bg-hover)' : 'var(--action-bg)', color: 'var(--action-fg)',
    fontFamily: 'var(--font-ui)', fontWeight: 'var(--fw-bold)', fontSize: props.top ? '15px' : props.big ? '17px' : '16px',
    letterSpacing: '-0.01em', textDecoration: 'none', cursor: bloqueado.value ? 'not-allowed' : 'pointer', overflow: 'hidden', opacity: bloqueado.value ? 0.42 : 1,
    transform: press.value ? 'scale(var(--scale-press))' : 'none',
    boxShadow: ring.value ? 'inset 0 0 0 2px var(--isla-tinta)' : hover.value ? 'var(--shadow-cta)' : 'none',
    transition: 'var(--t-hover)',
}));

function click(e) {
    if (!bloqueado.value && props.pulsar) props.pulsar(e);
}
</script>

<template>
    <component
        :is="href ? 'a' : 'button'"
        :href="href"
        :type="href ? undefined : 'button'"
        :aria-expanded="expanded"
        :aria-disabled="bloqueado || undefined"
        :aria-busy="loading ? true : undefined"
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
            :key="label"
            :style="{ display: 'inline-flex', alignItems: 'center', gap: '10px', whiteSpace: 'nowrap', animation: 'isla-swap var(--dur-base) var(--ease-island) both' }"
        >{{ label }}</span>
        <small
            v-if="sublabel"
            :style="{ maxWidth: '100%', fontSize: '11px', lineHeight: 1.2, fontWeight: 'var(--fw-semibold)', textAlign: 'center', display: '-webkit-box', WebkitLineClamp: 2, WebkitBoxOrient: 'vertical', overflow: 'hidden' }"
        >{{ sublabel }}</small>
    </component>
</template>
