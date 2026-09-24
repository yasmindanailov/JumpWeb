<script setup>
/**
 * La tira de días del sistema de diseño (`DayStrip.jsx`): el calendario de «Cuándo y cuántos», en una fila que se
 * desliza. Hoy va primero y elegido; el punto marca la tarifa especial (`--isla-especial`), igual que en el
 * calendario de la página. Sirve sobre claro y sobre tinta. El día elegido, con `v-model`.
 */
import { ref } from 'vue';

defineProps({
    days: { type: Array, default: () => [] },
    modelValue: { type: String, default: null },
    label: { type: String, default: '' },
});
const emit = defineEmits(['update:modelValue']);

const foco = ref(null);
const alEnfocar = (e, id) => { foco.value = e.currentTarget.matches(':focus-visible') ? id : null; };
</script>

<template>
    <div
        role="radiogroup"
        :aria-label="label || undefined"
        :style="{ display: 'flex', gap: '8px', overflowX: 'auto', padding: '3px 3px 6px', margin: '-3px', scrollSnapType: 'x proximity', scrollbarWidth: 'none' }"
    >
        <button
            v-for="d in days"
            :key="d.id"
            type="button"
            role="radio"
            :aria-checked="modelValue === d.id"
            :disabled="d.disabled"
            :aria-label="d.aria || undefined"
            :style="{ position: 'relative', flex: '0 0 auto', display: 'grid', gap: '4px', justifyItems: 'center', alignContent: 'center', minWidth: '60px', minHeight: '64px', padding: '8px 6px', scrollSnapAlign: 'start', borderRadius: 'var(--r-md)', border: `1px solid ${modelValue === d.id ? 'var(--control-selected-bg)' : 'var(--control-border)'}`, background: modelValue === d.id ? 'var(--control-selected-bg)' : d.disabled ? 'var(--control-bg-disabled)' : 'var(--control-bg)', color: modelValue === d.id ? 'var(--control-selected-fg)' : d.disabled ? 'var(--control-fg-disabled)' : 'var(--control-fg)', boxShadow: foco === d.id ? 'var(--ring)' : 'none', cursor: d.disabled ? 'not-allowed' : 'pointer', transition: 'var(--t-hover)' }"
            @click="emit('update:modelValue', d.id)"
            @focus="alEnfocar($event, d.id)"
            @blur="foco = null"
        >
            <span :style="{ fontFamily: 'var(--font-ui)', fontSize: 'var(--fs-overline)', fontWeight: 'var(--fw-semibold)', lineHeight: 1, color: modelValue === d.id ? 'var(--control-selected-fg)' : 'var(--text-muted)' }">{{ d.label }}</span>
            <span :style="{ fontFamily: 'var(--font-display)', fontSize: '1.25rem', fontWeight: 'var(--fw-black)', lineHeight: 1, fontVariantNumeric: 'tabular-nums' }">{{ d.n }}</span>
            <span
                v-if="d.special"
                aria-hidden="true"
                :style="{ position: 'absolute', top: '7px', right: '7px', width: '6px', height: '6px', borderRadius: '50%', background: 'var(--isla-especial)' }"
            />
        </button>
    </div>
</template>
