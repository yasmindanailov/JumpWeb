<script setup>
/**
 * El selector de cantidad del sistema de diseño (`QuantityStepper.jsx`). `variant="card"`: tarjeta con etiqueta,
 * precio y botones, para un widget de página. `variant="bare"`: solo − cifra +, para una fila de recibo o una
 * pregunta que ya tiene su título. Con `format`, la cifra se lee con su unidad («2 niños») y así se anuncia al
 * cambiar. Sirve igual sobre claro y sobre tinta. La cantidad, con `v-model` y dentro de sus topes (`acotar()`).
 */
import { computed } from 'vue';
import { acotar } from './piezas.js';
import { useTextos } from '../piezas/textos.js';
import ControlesCantidad from './ControlesCantidad.vue';

const props = defineProps({
    label: { type: String, default: '' },
    sublabel: { type: String, default: '' },
    price: { type: String, default: '' },
    modelValue: { type: Number, default: 0 },
    min: { type: Number, default: 0 },
    max: { type: Number, default: 20 },
    format: { type: Function, default: null },
    variant: { type: String, default: 'card' },
    labels: { type: Array, default: null },
    tone: { type: String, default: 'neutral' },
});
const emit = defineEmits(['update:modelValue']);
const { t } = useTextos();

const acento = computed(() => (props.tone === 'neutral' ? 'var(--control-border-strong)' : `var(--zone-${props.tone})`));
const controles = computed(() => ({
    valor: props.modelValue,
    min: props.min,
    max: props.max,
    texto: props.format ? props.format(props.modelValue) : String(props.modelValue),
    conUnidad: Boolean(props.format),
    nombres: props.labels || [t('pieza.quitar_uno'), t('pieza.anadir_uno')],
}));
const poner = (n) => emit('update:modelValue', acotar(n, props.min, props.max));
</script>

<template>
    <div
        v-if="variant === 'bare'"
        :style="{ display: 'flex' }"
    >
        <ControlesCantidad
            v-bind="controles"
            @poner="poner"
        />
    </div>
    <div
        v-else
        :style="{ display: 'flex', flexWrap: 'wrap', alignItems: 'center', justifyContent: 'space-between', gap: 'var(--space-3) var(--space-4)', padding: 'var(--space-4) var(--space-5)', background: 'var(--surface-card)', border: `1px solid ${modelValue > 0 ? acento : 'var(--border-subtle)'}`, borderRadius: 'var(--r-lg)', transition: 'var(--t-hover)' }"
    >
        <div :style="{ display: 'flex', flexDirection: 'column', gap: '2px', minWidth: 0 }">
            <span :style="{ fontFamily: 'var(--font-ui)', fontSize: 'var(--fs-body)', fontWeight: 'var(--fw-bold)', color: 'var(--text-strong)' }">{{ label }}</span>
            <span
                v-if="sublabel"
                :style="{ fontFamily: 'var(--font-ui)', fontSize: 'var(--fs-caption)', color: 'var(--text-muted)' }"
            >{{ sublabel }}</span>
        </div>
        <!-- Envuelve: con el precio al lado, la fila pide ~395px y en un móvil de 390 estiraba la página; si no
             cabe, precio y botones bajan juntos debajo de la etiqueta, a la derecha; y si tampoco caben juntos, el
             precio sube encima de los botones (diseño del 25-09). -->
        <div :style="{ display: 'flex', flexWrap: 'wrap', alignItems: 'center', justifyContent: 'flex-end', gap: 'var(--space-2) var(--space-4)', marginLeft: 'auto', minWidth: 0, maxWidth: '100%' }">
            <span
                v-if="price"
                :style="{ fontFamily: 'var(--font-mono)', fontSize: 'var(--fs-body-sm)', fontWeight: 'var(--fw-medium)', color: 'var(--text-strong)', whiteSpace: 'nowrap' }"
            >{{ price }}</span>
            <ControlesCantidad
                v-bind="controles"
                @poner="poner"
            />
        </div>
    </div>
</template>
