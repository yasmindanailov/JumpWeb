<script setup>
/**
 * El aviso destacado del sistema de diseño (`InfoCallout.jsx`): horarios especiales, aforo, condiciones y, dentro
 * de la compra, la hora llena, la cuenta que ya existe o la línea de menores. Un tono son tres tokens (fondo,
 * borde e icono con título), con los mismos nombres sobre claro y sobre tinta, así que cambia solo. `size="sm"`
 * para dentro de la isla: menos relleno, el mismo orden. La franja del peligro grande es un rol
 * (`--isla-franja-peligro`). Ranuras: `icono`, la de siempre (el cuerpo) y `accion`.
 */
import { computed } from 'vue';

const TONOS = ['info', 'warn', 'danger', 'success', 'neutral'];

const props = defineProps({
    tone: { type: String, default: 'info' },
    title: { type: String, default: '' },
    size: { type: String, default: 'md' },
    role: { type: String, default: undefined },
});

const k = computed(() => (TONOS.includes(props.tone) ? props.tone : 'info'));
const sm = computed(() => props.size === 'sm');
</script>

<template>
    <aside
        :role="role"
        :style="{ position: 'relative', overflow: 'hidden', display: 'flex', alignItems: 'flex-start', gap: sm ? '10px' : 'var(--space-4)', padding: sm ? '12px 14px' : tone === 'danger' ? 'calc(var(--space-5) + 4px) var(--space-5) var(--space-5)' : 'var(--space-5)', background: `var(--notice-${k}-bg)`, borderRadius: sm ? 'var(--r-md)' : 'var(--r-lg)', boxShadow: `inset 0 0 0 1px var(--notice-${k}-border)` }"
    >
        <span
            v-if="tone === 'danger' && !sm"
            aria-hidden="true"
            :style="{ position: 'absolute', top: 0, left: 0, right: 0, height: '6px', background: 'var(--isla-franja-peligro)' }"
        />
        <span
            v-if="$slots.icono"
            :style="{ color: `var(--notice-${k}-fg)`, display: 'flex', flex: '0 0 auto', marginTop: '1px' }"
        ><slot name="icono" /></span>
        <div :style="{ display: 'flex', flexDirection: 'column', gap: '4px', minWidth: 0, flex: 1 }">
            <strong
                v-if="title"
                :style="{ fontFamily: 'var(--font-ui)', fontSize: sm ? 'var(--fs-body-sm)' : 'var(--fs-body)', fontWeight: 'var(--fw-bold)', lineHeight: 1.35, color: sm ? 'var(--text-strong)' : `var(--notice-${k}-fg)` }"
            >{{ title }}</strong>
            <div
                v-if="$slots.default"
                :style="{ font: 'var(--type-body)', fontSize: 'var(--fs-body-sm)', lineHeight: 1.5, color: 'var(--notice-text)', textWrap: 'pretty' }"
            >
                <slot />
            </div>
        </div>
        <div
            v-if="$slots.accion"
            :style="{ flex: '0 0 auto' }"
        >
            <slot name="accion" />
        </div>
    </aside>
</template>
