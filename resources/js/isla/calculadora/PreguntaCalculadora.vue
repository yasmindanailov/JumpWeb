<script setup>
/**
 * Una pregunta de la calculadora de la página (el `Q` de `paginas/entradas/pieza-3.jsx`): su título, su línea de
 * apoyo y lo que se elige, separada de la siguiente por una raya. La última, sin raya (el `:last-child` del diseño).
 */
defineProps({
    titulo: { type: String, required: true },
    sub: { type: String, default: '' },
    id: { type: String, default: undefined },
    ultima: { type: Boolean, default: false },
});
</script>

<template>
    <div :id="id" :style="{ display: 'grid', gridTemplateColumns: 'minmax(0, 1fr)', gap: '11px', paddingBottom: ultima ? 0 : '22px', borderBottom: ultima ? 'none' : '1px solid var(--border-subtle)', scrollMarginTop: '96px' }">
        <div :style="{ display: 'flex', alignItems: 'baseline' }"><span :style="{ font: 'var(--type-h3)', fontSize: '1.25rem', color: 'var(--text-strong)' }">{{ titulo }}</span></div>
        <p v-if="sub" :style="{ margin: '-4px 0 0', fontFamily: 'var(--font-ui)', fontSize: 'var(--fs-body-sm)', color: 'var(--text-muted)' }">{{ sub }}</p>
        <slot />
    </div>
</template>
