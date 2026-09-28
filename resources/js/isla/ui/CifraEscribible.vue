<script setup>
/**
 * **La cifra que se ESCRIBE del selector de cantidad** (`editable` de `QuantityStepper.jsx`, T6c·3b: «para 30–100
 * alumnos, pulsar “+” treinta veces no es una forma de decir cuántos sois»). Va en la ranura `cifra` del contador
 * (`ContadorCantidad` → `ControlesCantidad`) y solo la usa quien la necesita —la calculadora de colegios—: dentro del
 * control, viajaba también con la compra de la isla (+1,4 kB, `SidebarBundleBudgetTest`). Lo tecleado se ajusta a los
 * topes al salir (lo hace el contador, con su `poner`), Intro confirma y las flechas suman o restan uno.
 */
import { ref } from 'vue';

const props = defineProps({
    valor: { type: Number, required: true },
    etiqueta: { type: String, default: '' },
});
const emit = defineEmits(['poner']);
const borrador = ref(null);

function alSalir() {
    const n = Number.parseInt(borrador.value, 10);

    borrador.value = null;
    if (! Number.isNaN(n)) emit('poner', n);
}
function alTeclear(ev) {
    if (ev.key === 'Enter') ev.currentTarget.blur();
    if (ev.key !== 'ArrowUp' && ev.key !== 'ArrowDown') return;
    ev.preventDefault();
    borrador.value = null;
    emit('poner', props.valor + (ev.key === 'ArrowUp' ? 1 : -1));
}
</script>

<template>
    <input
        type="text"
        inputmode="numeric"
        pattern="[0-9]*"
        autocomplete="off"
        :aria-label="etiqueta"
        :value="borrador ?? String(valor)"
        :style="{ width: '68px', minHeight: '44px', boxSizing: 'border-box', padding: '0 6px', textAlign: 'center', borderRadius: 'var(--r-md)', border: '1px solid var(--control-border-strong)', background: 'var(--control-bg)', color: 'var(--text-strong)', fontFamily: 'var(--font-display)', fontSize: '1.25rem', fontWeight: 'var(--fw-black)', fontVariantNumeric: 'tabular-nums' }"
        @focus="borrador = String(valor); $event.target.select()"
        @input="borrador = $event.target.value.replace(/[^0-9]/g, '').slice(0, 4)"
        @blur="alSalir"
        @keydown="alTeclear"
    >
</template>
