<script setup>
/**
 * La rejilla de horas con plazas del sistema de diseño (`TimeSlotPicker.jsx`): el motor de conversión del paso de
 * «cuándo». `size="sm"` (52px de alto) para dentro de la isla, donde el sitio es del paso. Sirve igual sobre
 * claro y sobre tinta. Lo que dice cada hora (completa, «Quedan 3», «31 libres») lo decide `estadoHora()`, con
 * sus textos de `lang/`. La hora elegida, con `v-model`.
 * ▶ El movimiento (26-09; Z3, `#782`): la elegida es una píldora que se desliza (`usePildoraHoras`); y si las plazas
 * bajan en directo, la casilla destella una vez, la cifra baja y, si se llena, la hora se tacha con una línea.
 * ⚠️ `dia`: las horas de OTRO día no «bajan» (el diseño comparaba por hora y nada más: cambiar de día habría destellado
 * una cuenta atrás inventada). Quien pinta horas de un día lo pasa; sin él, se compara siempre (las de un mismo día).
 */
import { computed, onMounted, onUpdated, ref } from 'vue';
import { COLUMNAS_HORAS, estadoHora } from './piezas.js';
import { TRANSICION_PILDORA, cambioDeHora, usePildoraHoras } from './usePildoraHoras.js';
import { quieto } from '../movimiento.js';
import { useTextos } from '../piezas/textos.js';

const props = defineProps({
    slots: { type: Array, default: () => [] },
    modelValue: { type: String, default: null },
    columns: { type: String, default: COLUMNAS_HORAS },
    lowThreshold: { type: Number, default: 6 },
    counts: { type: String, default: 'all' },
    size: { type: String, default: 'md' },
    dia: { type: String, default: null },
});
const emit = defineEmits(['update:modelValue']);
const { tp } = useTextos();

const rejilla = ref(null);
const pildora = ref(null);
usePildoraHoras({ rejilla, pildora, valor: () => props.modelValue, cuantas: () => props.slots.length });
// Las plazas de la vez anterior: lo que dice qué ha bajado. Se guardan DESPUÉS de pintar, como el diseño.
let antes = { dia: null, plazas: {} };
const guardar = () => { antes = { dia: props.dia, plazas: Object.fromEntries(props.slots.map((s) => [s.time, s.left])) }; };
onMounted(guardar);
onUpdated(guardar);

const sm = computed(() => props.size === 'sm');
const horas = computed(() => {
    const previas = antes.dia === props.dia ? antes.plazas : {};

    return props.slots.map((s) => ({ s, e: estadoHora(s, { value: props.modelValue, lowThreshold: props.lowThreshold, counts: props.counts }, tp), c: cambioDeHora(s, previas, ! quieto()) }));
});
// Constante a propósito: la mueve `usePildoraHoras` y Vue no debe reponerle nada al repintar.
const PILDORA = { position: 'absolute', left: 0, top: 0, zIndex: 1, opacity: 0, pointerEvents: 'none', borderRadius: 'var(--r-md)', background: 'var(--control-selected-bg)', boxShadow: 'var(--shadow-md)', transition: TRANSICION_PILDORA };
</script>

<template>
    <div
        ref="rejilla"
        role="group"
        :style="{ position: 'relative', zIndex: 0, display: 'grid', gridTemplateColumns: sm && columns === COLUMNAS_HORAS ?'repeat(auto-fill, minmax(76px, 1fr))' : columns, gap: sm ? '8px' : '10px' }"
    >
        <span
            ref="pildora"
            aria-hidden="true"
            :style="PILDORA"
        />
        <button
            v-for="{ s, e, c } in horas"
            :key="s.time"
            type="button"
            :data-hora="s.time"
            :disabled="e.agotada"
            :aria-pressed="e.activa"
            :style="{ position: 'relative', display: 'flex', flexDirection: 'column', alignItems: 'center', justifyContent: 'center', gap: '3px', minHeight: sm ? '52px' : '68px', padding: sm ? '6px 4px' : '10px 8px', background: 'transparent', color: e.activa ? 'var(--control-selected-fg)' : e.agotada ? 'var(--control-fg-disabled)' : 'var(--control-fg)', border: `1px solid ${e.activa || e.agotada ? 'transparent' : 'var(--control-border)'}`, borderRadius: 'var(--r-md)', cursor: e.agotada ? 'not-allowed' : 'pointer', transition: 'color var(--dur-base) var(--ease-out), border-color var(--dur-base) var(--ease-out)' }"
            @click="emit('update:modelValue', s.time)"
        >
            <!-- El fondo de cada casilla, por debajo de la píldora; el texto, por encima. -->
            <span
                :key="`${s.time}:${s.left}`"
                aria-hidden="true"
                :style="{ position: 'absolute', inset: 0, zIndex: 0, borderRadius: 'inherit', background: e.agotada ? 'var(--control-bg-disabled)' : 'var(--control-bg)', animation: c.baja && ! c.seLlena ? 'isla-destello 700ms var(--ease-out) both' : undefined }"
            />
            <span :style="{ position: 'relative', zIndex: 2, fontFamily: 'var(--font-display)', fontSize: sm ? '1.0625rem' : '1.25rem', fontWeight: 'var(--fw-black)', letterSpacing: '-0.01em', fontVariantNumeric: 'tabular-nums', textDecoration: e.agotada && ! c.seLlena ? 'line-through' : 'none', backgroundImage: c.seLlena ? 'linear-gradient(currentColor, currentColor)' : undefined, backgroundRepeat: 'no-repeat', backgroundPosition: '0 55%', backgroundSize: c.seLlena ? '100% 2px' : undefined, animation: c.seLlena ? 'isla-tachar var(--dur-slow) var(--ease-out) both' : undefined }">{{ s.time }}</span>
            <span
                v-if="e.texto"
                :key="e.texto"
                :style="{ position: 'relative', zIndex: 2, fontFamily: 'var(--font-ui)', fontSize: 'var(--fs-overline)', fontWeight: 'var(--fw-bold)', letterSpacing: '0.02em', color: e.activa ? 'var(--control-selected-note)' : e.agotada ? 'var(--control-fg-disabled)' : e.pocas ? 'var(--text-low)' : 'var(--text-muted)', transition: 'color var(--dur-base) var(--ease-out)', animation: c.baja ? 'isla-baja var(--dur-slow) var(--ease-spring) both' : undefined }"
            >{{ e.texto }}</span>
        </button>
    </div>
</template>
