<script setup>
/**
 * El INTERRUPTOR del sistema (`forms/Switch.jsx`): un ajuste que se aplica AL MOMENTO, sin «Guardar» —si hace falta
 * confirmar, no es un interruptor, es una casilla en un formulario—; 44 px de toque, `role="switch"`, el anillo de foco
 * del sistema y el estado dicho también con palabras (`onText` / `offText`). El diseño dice «sirve sobre claro y sobre
 * tinta»: sobre tinta (`tono="tinta"`, en la isla) toma los roles de la isla —el vivo para encendido—.
 */
import { computed, ref } from 'vue';

const props = defineProps({
    label: { type: String, default: '' },
    description: { type: String, default: '' },
    checked: { type: Boolean, default: false },
    disabled: { type: Boolean, default: false },
    onText: { type: String, default: '' },
    offText: { type: String, default: '' },
    id: { type: String, required: true },
    tono: { type: String, default: 'claro' },
});
const emit = defineEmits(['cambiar']);
const anillo = ref(false);
const tinta = computed(() => props.tono === 'tinta');
const estado = computed(() => (props.checked ? props.onText : props.offText));
const pista = computed(() => {
    const encendida = tinta.value ? 'var(--isla-vivo)' : 'var(--control-selected-bg)';
    const apagada = tinta.value ? 'rgba(255,255,255,0.3)' : 'var(--control-border)';

    return {
        position: 'relative', width: '48px', height: '28px', borderRadius: 'var(--r-pill)',
        background: props.checked ? encendida : (tinta.value ? 'rgba(255,255,255,0.1)' : 'var(--control-bg-disabled)'),
        boxShadow: `inset 0 0 0 2px ${props.checked ? encendida : apagada}${anillo.value ? ', var(--ring)' : ''}`,
        transition: 'var(--t-hover)',
    };
});
const pomo = computed(() => ({
    position: 'absolute', top: '4px', left: '4px', width: '20px', height: '20px', borderRadius: '50%',
    background: props.checked ? (tinta.value ? 'var(--isla-tinta)' : 'var(--control-selected-fg)') : (tinta.value ? 'rgba(255,255,255,0.7)' : 'var(--control-fg-disabled)'),
    transform: props.checked ? 'translateX(20px)' : 'none',
    transition: 'transform var(--dur-base) var(--ease-spring), background var(--dur-fast) var(--ease-out)',
}));
</script>

<template>
    <label
        :for="id"
        :style="{ display: 'flex', alignItems: 'center', justifyContent: 'space-between', gap: '16px', minHeight: '44px', cursor: disabled ? 'not-allowed' : 'pointer', opacity: disabled ? 0.5 : 1 }"
    >
        <span :style="{ display: 'flex', flexDirection: 'column', gap: '2px', minWidth: 0 }">
            <span :style="{ fontFamily: 'var(--font-ui)', fontSize: 'var(--fs-body-sm)', fontWeight: 'var(--fw-semibold)', color: tinta ? 'var(--isla-sobre)' : 'var(--text-strong)' }">{{ label }}</span>
            <span
                v-if="description"
                :style="{ fontFamily: 'var(--font-ui)', fontSize: 'var(--fs-caption)', lineHeight: 1.45, color: tinta ? 'rgba(255,255,255,0.7)' : 'var(--text-muted)' }"
            >{{ description }}</span>
        </span>
        <span :style="{ display: 'inline-flex', alignItems: 'center', gap: '10px', flex: '0 0 auto' }">
            <span
                v-if="estado"
                aria-hidden="true"
                :style="{ fontFamily: 'var(--font-ui)', fontSize: 'var(--fs-caption)', fontWeight: 'var(--fw-bold)', color: tinta ? 'rgba(255,255,255,0.7)' : 'var(--text-muted)' }"
            >{{ estado }}</span>
            <input
                :id="id"
                type="checkbox"
                role="switch"
                :checked="checked"
                :disabled="disabled"
                :aria-checked="checked"
                :style="{ position: 'absolute', opacity: 0, width: '1px', height: '1px', margin: 0 }"
                @change="emit('cambiar', $event.target.checked)"
                @focus="anillo = $event.currentTarget.matches(':focus-visible')"
                @blur="anillo = false"
            >
            <span
                aria-hidden="true"
                :style="pista"
            ><span :style="pomo" /></span>
        </span>
    </label>
</template>
