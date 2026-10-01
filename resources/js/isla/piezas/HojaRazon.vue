<script setup>
/**
 * La razón, abierta (`ReasonSheet` del diseño, Z6b): la sexta vista de la isla. El sobretítulo, el dato y una o dos frases.
 * La acción de la página se queda en la fila, en naranja: el velo tapa su botón, así que la isla es el camino a pulsarlo.
 */
defineProps({
    bn: { type: Object, required: true },
});
</script>

<template>
    <div :style="{ display: 'grid', gap: '6px', padding: '2px 8px 8px' }">
        <span
            v-if="bn.title"
            :style="{ font: 'var(--type-overline)', color: 'var(--text-muted)' }"
        >{{ bn.title }}</span>
        <b :style="{ fontSize: '19px', fontWeight: 700, letterSpacing: '-0.015em', lineHeight: 1.25, color: 'var(--text-strong)' }">{{ bn.text }}</b>
        <p
            v-if="bn.detail"
            :style="{ margin: 0, fontSize: '14.5px', lineHeight: 1.5, color: 'var(--text-body)', textWrap: 'pretty' }"
        >{{ bn.detail }}</p>
    </div>
</template>
