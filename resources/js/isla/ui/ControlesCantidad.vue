<script setup>
/**
 * El − cifra + del selector de cantidad (`QuantityStepper.jsx`, su `controls`): el mismo en la tarjeta y suelto.
 * Pinta y avisa; los topes y los textos los pone `ContadorCantidad`.
 */
import IconoLucide from './IconoLucide.vue';

defineProps({
    valor: { type: Number, required: true },
    min: { type: Number, required: true },
    max: { type: Number, required: true },
    texto: { type: String, required: true },
    conUnidad: { type: Boolean, default: false },
    nombres: { type: Array, required: true },
});
const emit = defineEmits(['poner']);

const boton = (activo) => ({
    display: 'inline-flex', alignItems: 'center', justifyContent: 'center', flex: '0 0 auto', width: '44px', height: '44px',
    borderRadius: 'var(--r-pill)', border: `1px solid ${activo ? 'var(--control-border-strong)' : 'var(--border-subtle)'}`,
    background: 'var(--control-bg)', color: activo ? 'var(--control-fg)' : 'var(--control-fg-disabled)',
    cursor: activo ? 'pointer' : 'not-allowed', transition: 'var(--t-hover)',
});
</script>

<template>
    <div :style="{ display: 'flex', alignItems: 'center', gap: '6px' }">
        <button
            type="button"
            :aria-label="nombres[0]"
            :disabled="valor <= min"
            :style="boton(valor > min)"
            @click="emit('poner', valor - 1)"
        >
            <IconoLucide
                name="minus"
                :size="18"
            />
        </button>
        <output
            aria-live="polite"
            :style="{ minWidth: conUnidad ? '96px' : '34px', textAlign: 'center', color: 'var(--text-strong)', fontVariantNumeric: 'tabular-nums', fontFamily: conUnidad ? 'var(--font-ui)' : 'var(--font-display)', fontSize: conUnidad ? 'var(--fs-body-sm)' : '1.25rem', fontWeight: conUnidad ? 'var(--fw-bold)' : 'var(--fw-black)' }"
        >{{ texto }}</output>
        <button
            type="button"
            :aria-label="nombres[1]"
            :disabled="valor >= max"
            :style="boton(valor < max)"
            @click="emit('poner', valor + 1)"
        >
            <IconoLucide
                name="plus"
                :size="18"
            />
        </button>
    </div>
</template>
