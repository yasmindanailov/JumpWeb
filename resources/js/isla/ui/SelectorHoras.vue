<script setup>
/**
 * La rejilla de horas con plazas del sistema de diseño (`TimeSlotPicker.jsx`): el motor de conversión del paso de
 * «cuándo». `size="sm"` (52px de alto) para dentro de la isla, donde el sitio es del paso. Sirve igual sobre
 * claro y sobre tinta. Lo que dice cada hora (completa, «Quedan 3», «31 libres») lo decide `estadoHora()`, con
 * sus textos de `lang/`. La hora elegida, con `v-model`.
 */
import { computed } from 'vue';
import { COLUMNAS_HORAS, estadoHora } from './piezas.js';
import { useTextos } from '../piezas/textos.js';

const props = defineProps({
    slots: { type: Array, default: () => [] },
    modelValue: { type: String, default: null },
    columns: { type: String, default: COLUMNAS_HORAS },
    lowThreshold: { type: Number, default: 6 },
    counts: { type: String, default: 'all' },
    size: { type: String, default: 'md' },
});
const emit = defineEmits(['update:modelValue']);
const { tp } = useTextos();

const sm = computed(() => props.size === 'sm');
const horas = computed(() => props.slots.map((s) => ({ s, e: estadoHora(s, { value: props.modelValue, lowThreshold: props.lowThreshold, counts: props.counts }, tp) })));
</script>

<template>
    <div
        role="group"
        :style="{ display: 'grid', gridTemplateColumns: sm && columns === COLUMNAS_HORAS ?'repeat(auto-fill, minmax(76px, 1fr))' : columns, gap: sm ? '8px' : '10px' }"
    >
        <button
            v-for="{ s, e } in horas"
            :key="s.time"
            type="button"
            :disabled="e.agotada"
            :aria-pressed="e.activa"
            :style="{ display: 'flex', flexDirection: 'column', alignItems: 'center', justifyContent: 'center', gap: '3px', minHeight: sm ? '52px' : '68px', padding: sm ? '6px 4px' : '10px 8px', background: e.activa ? 'var(--control-selected-bg)' : e.agotada ? 'var(--control-bg-disabled)' : 'var(--control-bg)', color: e.activa ? 'var(--control-selected-fg)' : e.agotada ? 'var(--control-fg-disabled)' : 'var(--control-fg)', border: `1px solid ${e.activa ? 'var(--control-selected-bg)' : e.agotada ? 'transparent' : 'var(--control-border)'}`, borderRadius: 'var(--r-md)', cursor: e.agotada ? 'not-allowed' : 'pointer', transition: 'var(--t-hover)', boxShadow: e.activa ? 'var(--shadow-md)' : 'none' }"
            @click="emit('update:modelValue', s.time)"
        >
            <span :style="{ fontFamily: 'var(--font-display)', fontSize: sm ? '1.0625rem' : '1.25rem', fontWeight: 'var(--fw-black)', letterSpacing: '-0.01em', fontVariantNumeric: 'tabular-nums', textDecoration: e.agotada ? 'line-through' : 'none' }">{{ s.time }}</span>
            <span
                v-if="e.texto"
                :style="{ fontFamily: 'var(--font-ui)', fontSize: 'var(--fs-overline)', fontWeight: 'var(--fw-bold)', letterSpacing: '0.02em', color: e.activa ? 'var(--control-selected-note)' : e.agotada ? 'var(--control-fg-disabled)' : e.pocas ? 'var(--text-low)' : 'var(--text-muted)' }"
            >{{ e.texto }}</span>
        </button>
    </div>
</template>
