<script setup>
/**
 * Una reserva, del sistema de diseño (`BookingCard.jsx`), portada 1:1 con sus estilos en línea. `variant="next"`: la
 * próxima, entera —el día en su hoja, la hora grande, qué y cuántos, el número y, debajo, lo que la acompaña (la
 * ranura por defecto) y sus acciones (`acciones`)—. `variant="row"`: una línea de «Otras reservas» o del historial, que
 * se toca entera si es `clicable`; con `status`, atenuada y tachada. Sirve sobre claro y sobre tinta (tokens).
 *
 * ⚠️ `aria`, el nombre accesible entero (la hoja del día es `aria-hidden`): en la fila que se toca, el del botón; en la
 * que no, un texto oculto a la vista con lo visible callado (ARIA 1.2 prohíbe `aria-label` en un `div` genérico, y los
 * lectores de pantalla lo ignoran: el diseño dejaba el historial sin fecha).
 */
import { ref } from 'vue';
import IconoLucide from './IconoLucide.vue';

const props = defineProps({
    variant: { type: String, default: 'next' },
    day: { type: Object, required: true },
    time: { type: String, default: '' },
    title: { type: String, default: '' },
    code: { type: String, default: '' },
    aria: { type: String, default: '' },
    status: { type: String, default: '' },
    clicable: { type: Boolean, default: false },
});
const emit = defineEmits(['click']);
const hover = ref(false);
const hoja = (pequena) => ({ display: 'grid', justifyItems: 'center', alignContent: 'center', gap: pequena ? '1px' : '2px', flex: '0 0 auto', width: pequena ? '52px' : '64px', minHeight: pequena ? '52px' : '68px', borderRadius: 'var(--r-md)', background: 'var(--control-selected-bg)', color: 'var(--control-selected-fg)' });
const fila = () => ({ display: 'flex', alignItems: 'center', gap: '14px', width: '100%', boxSizing: 'border-box', padding: '10px 12px 10px 10px', border: '1px solid var(--border-subtle)', borderRadius: 'var(--r-lg)', textAlign: 'left', font: 'inherit', color: 'inherit', background: props.clicable && hover.value ? 'var(--control-bg-hover)' : 'var(--surface-card)', cursor: props.clicable ? 'pointer' : 'default', opacity: props.status ? 0.78 : 1, transition: 'var(--t-hover)' });
const pulsar = () => { if (props.clicable) emit('click'); };
const OCULTO = { position: 'absolute', width: '1px', height: '1px', margin: '-1px', overflow: 'hidden', clip: 'rect(0 0 0 0)', whiteSpace: 'nowrap' };
</script>

<template>
    <component
        :is="clicable ? 'button' : 'div'"
        v-if="variant === 'row'"
        :type="clicable ? 'button' : undefined"
        :aria-label="clicable && aria ? aria : undefined"
        :style="fila()"
        @click="pulsar"
        @mouseenter="hover = true"
        @mouseleave="hover = false"
    >
        <span
            v-if="! clicable && aria"
            :style="OCULTO"
        >{{ aria }}</span>
        <span
            aria-hidden="true"
            :style="hoja(true)"
        >
            <span :style="{ fontFamily: 'var(--font-ui)', fontSize: 'var(--fs-overline)', fontWeight: 'var(--fw-bold)', textTransform: 'uppercase', letterSpacing: '0.06em', lineHeight: 1 }">{{ day.dow }}</span>
            <span :style="{ fontFamily: 'var(--font-display)', fontSize: '1.375rem', fontWeight: 'var(--fw-black)', lineHeight: 1, fontVariantNumeric: 'tabular-nums' }">{{ day.n }}</span>
            <span
                v-if="day.month"
                :style="{ fontFamily: 'var(--font-ui)', fontSize: 'var(--fs-overline)', fontWeight: 'var(--fw-semibold)', lineHeight: 1 }"
            >{{ day.month }}</span>
        </span>
        <span
            :aria-hidden="! clicable && aria ? 'true' : undefined"
            :style="{ display: 'grid', gap: '3px', minWidth: 0, flex: 1 }"
        >
            <span :style="{ fontFamily: 'var(--font-ui)', fontSize: 'var(--fs-body-sm)', fontWeight: 'var(--fw-bold)', color: 'var(--text-strong)', textDecoration: status ? 'line-through' : 'none' }">{{ title }}</span>
            <span :style="{ fontFamily: 'var(--font-ui)', fontSize: 'var(--fs-caption)', color: 'var(--text-muted)' }">{{ [time, code].filter(Boolean).join(' · ') }}</span>
        </span>
        <span
            v-if="status"
            :aria-hidden="! clicable && aria ? 'true' : undefined"
            :style="{ fontFamily: 'var(--font-ui)', fontSize: 'var(--fs-caption)', fontWeight: 'var(--fw-bold)', color: 'var(--text-muted)' }"
        >{{ status }}</span>
        <span
            v-if="clicable"
            aria-hidden="true"
            :style="{ display: 'inline-flex', color: 'var(--text-muted)' }"
        ><IconoLucide
            name="chevron-right"
            :size="18"
        /></span>
    </component>
    <article
        v-else
        :aria-label="aria || undefined"
        :style="{ display: 'grid', gap: '16px', padding: '18px', borderRadius: 'var(--r-xl)', border: '1px solid var(--border-subtle)', background: 'var(--surface-card)', boxShadow: 'var(--shadow-xs)' }"
    >
        <div :style="{ display: 'flex', alignItems: 'center', gap: '16px' }">
            <span
                aria-hidden="true"
                :style="hoja(false)"
            >
                <span :style="{ fontFamily: 'var(--font-ui)', fontSize: 'var(--fs-overline)', fontWeight: 'var(--fw-bold)', textTransform: 'uppercase', letterSpacing: '0.06em', lineHeight: 1 }">{{ day.dow }}</span>
                <span :style="{ fontFamily: 'var(--font-display)', fontSize: '1.75rem', fontWeight: 'var(--fw-black)', lineHeight: 1, fontVariantNumeric: 'tabular-nums' }">{{ day.n }}</span>
                <span
                    v-if="day.month"
                    :style="{ fontFamily: 'var(--font-ui)', fontSize: 'var(--fs-overline)', fontWeight: 'var(--fw-semibold)', lineHeight: 1 }"
                >{{ day.month }}</span>
            </span>
            <div :style="{ display: 'grid', gap: '4px', minWidth: 0, flex: 1 }">
                <span :style="{ display: 'flex', alignItems: 'center', flexWrap: 'wrap', gap: '8px 10px' }">
                    <span :style="{ fontFamily: 'var(--font-display)', fontSize: '1.75rem', fontWeight: 'var(--fw-black)', lineHeight: 1, letterSpacing: '-0.02em', color: 'var(--text-strong)', fontVariantNumeric: 'tabular-nums' }">{{ time }}</span>
                    <slot name="badge" />
                </span>
                <span :style="{ fontFamily: 'var(--font-ui)', fontSize: 'var(--fs-body)', fontWeight: 'var(--fw-bold)', lineHeight: 1.35, color: 'var(--text-strong)', textWrap: 'pretty' }">{{ title }}</span>
                <span
                    v-if="code"
                    :style="{ font: 'var(--type-mono)', color: 'var(--text-muted)' }"
                >{{ code }}</span>
            </div>
        </div>
        <div
            v-if="$slots.default"
            :style="{ display: 'grid', gap: '10px', paddingTop: '14px', borderTop: '1px solid var(--border-subtle)' }"
        ><slot /></div>
        <div
            v-if="$slots.acciones"
            :style="{ display: 'flex', flexWrap: 'wrap', alignItems: 'center', gap: '8px 18px' }"
        ><slot name="acciones" /></div>
    </article>
</template>
