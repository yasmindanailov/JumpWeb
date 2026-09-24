<script setup>
/**
 * La cabecera del desenlace de una compra, del sistema de diseño (`OutcomeHeader.jsx`): el mismo molde para los
 * tres finales. `success` —«¡Reservado!»— es el único momento con movimiento de la compra (el visto bueno con
 * muelle, un anillo y confeti con los roles de fiesta; con «reducir movimiento», quieto); `error` lleva el icono de
 * alerta sin rojo de pánico; `pending` —«Verificando»—, la bola de carga, sin botón. `celebrate=false` es un
 * «hecho» tranquilo: si todo celebra, nada celebra. `enfocar()` lleva el foco al titular al llegar.
 */
import { computed, ref } from 'vue';
import { useTextos } from '../piezas/textos.js';
import IconoLucide from './IconoLucide.vue';
import CargaRebote from './CargaRebote.vue';

const DESENLACE = {
    success: { icon: 'check', bg: 'var(--isla-vivo)', fg: 'var(--isla-tinta)', role: 'status' },
    error: { icon: 'circle-alert', bg: 'var(--isla-error-fondo)', fg: 'var(--isla-alerta-texto)', role: 'alert' },
    pending: { icon: null, bg: 'transparent', fg: 'var(--text-strong)', role: 'status' },
};

const props = defineProps({
    kind: { type: String, default: 'success' },
    title: { type: String, default: '' },
    align: { type: String, default: undefined },
    celebrate: { type: Boolean, default: true },
});
const { t } = useTextos();

const titular = ref(null);
const o = computed(() => DESENLACE[props.kind] || DESENLACE.success);
const fiesta = computed(() => props.kind === 'success' && props.celebrate);
const centro = computed(() => (props.align ? props.align === 'center' : props.kind !== 'error'));
const exito = computed(() => props.kind === 'success');

defineExpose({ enfocar: () => titular.value?.focus() });
</script>

<template>
    <div
        :role="o.role"
        :data-outcome="kind"
        :style="{ display: 'grid', gap: '12px', justifyItems: centro ? 'center' : 'start', textAlign: centro ? 'center' : 'left' }"
    >
        <span
            aria-hidden="true"
            :style="{ position: 'relative', display: 'inline-flex', width: exito ? '76px' : '52px', height: exito ? '76px' : '52px' }"
        >
            <template v-if="fiesta">
                <span :style="{ position: 'absolute', inset: 0, borderRadius: '50%', border: '2px solid var(--isla-vivo)', animation: 'isla-outcome-ring var(--dur-reveal) var(--ease-out) 120ms both' }" />
                <i
                    v-for="i in 10"
                    :key="i"
                    :style="{ position: 'absolute', left: '50%', top: '50%', width: '9px', height: '9px', margin: '-4.5px', borderRadius: '2px', background: `var(--isla-fiesta-${((i - 1) % 4) + 1})`, '--a': `${(i - 1) * 36}deg`, animation: 'isla-outcome-burst var(--dur-reveal) var(--ease-out) 160ms both' }"
                />
            </template>
            <span
                v-if="kind === 'pending'"
                :style="{ position: 'absolute', inset: 0, display: 'flex', alignItems: 'center', justifyContent: 'center' }"
            ><CargaRebote
                :size="52"
                :label="title || t('pieza.cargando')"
            /></span>
            <span
                v-else
                :style="{ position: 'absolute', inset: 0, display: 'flex', alignItems: 'center', justifyContent: 'center', borderRadius: '50%', background: o.bg, color: o.fg, animation: fiesta ? 'isla-outcome-pop var(--dur-island) var(--ease-spring) both' : exito ? 'isla-pop var(--dur-base) var(--ease-out) both' : 'none' }"
            ><IconoLucide
                :name="o.icon"
                :size="exito ? 34 : 26"
            /></span>
        </span>
        <h1
            v-if="title"
            ref="titular"
            tabindex="-1"
            :style="{ margin: 0, outline: 'none', fontFamily: 'var(--font-display)', fontWeight: 'var(--fw-black)', fontSize: exito ? '2.125rem' : '1.625rem', lineHeight: 1.05, letterSpacing: '-0.02em', color: 'var(--text-strong)', textWrap: 'balance', animation: exito ? 'isla-outcome-rise var(--dur-island) var(--ease-spring) 140ms both' : 'none' }"
        >{{ title }}</h1>
        <div
            v-if="$slots.default"
            :style="{ maxWidth: '42ch', fontFamily: 'var(--font-ui)', fontSize: 'var(--fs-body-sm)', lineHeight: 1.5, color: 'var(--text-body)', textWrap: 'pretty', animation: exito ? 'isla-outcome-rise var(--dur-island) var(--ease-spring) 220ms both' : 'none' }"
        >
            <slot />
        </div>
    </div>
</template>
