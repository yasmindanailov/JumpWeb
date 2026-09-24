<script setup>
/**
 * Icono Lucide en línea: el `Icon` del diseño (`Icon.jsx`), con el mismo `<span>` y el mismo SVG
 * (`DECISIONES #686`). Hereda `currentColor` y el trazo de 2px. `label` lo hace imagen con nombre; sin él es
 * decorativo. `fill` rellena el dibujo (las estrellas: una de trazo se lee como vacía, o sea como un cero).
 */
import { computed } from 'vue';
import { dibujo } from './iconos.js';

defineOptions({ inheritAttrs: false });

const props = defineProps({
    name: { type: String, default: 'sparkles' },
    size: { type: Number, default: 20 },
    color: { type: String, default: 'currentColor' },
    strokeBox: { type: Boolean, default: false },
    fill: { type: Boolean, default: false },
    label: { type: String, default: undefined },
});

const svg = computed(() => dibujo(props.name, props.fill));
const caja = computed(() => ({
    display: 'inline-flex', alignItems: 'center', justifyContent: 'center', flex: '0 0 auto',
    width: `${props.size}px`, height: `${props.size}px`, color: props.color, lineHeight: 0,
}));
const chip = computed(() => ({
    display: 'inline-flex', alignItems: 'center', justifyContent: 'center',
    width: `${props.size * 2}px`, height: `${props.size * 2}px`, borderRadius: 'var(--r-md)', background: 'var(--bg-muted)',
}));
</script>

<template>
    <span v-if="strokeBox" :style="chip"><span
        :aria-hidden="label ? undefined : 'true'" :role="label ? 'img' : undefined" :aria-label="label"
        v-bind="$attrs" :style="[caja, $attrs.style]" v-html="svg"
    /></span>
    <span
        v-else
        :aria-hidden="label ? undefined : 'true'" :role="label ? 'img' : undefined" :aria-label="label"
        v-bind="$attrs" :style="[caja, $attrs.style]" v-html="svg"
    />
</template>
