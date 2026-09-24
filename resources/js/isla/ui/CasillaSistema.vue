<script setup>
/**
 * La casilla del sistema de diseño (`Checkbox.jsx`): el descargo de responsabilidad, las condiciones, un extra.
 * Sirve igual sobre claro y sobre tinta: solo usa tokens de control. Con `error` (texto) lo dice con palabras
 * debajo, no solo en rojo (WCAG 3.3.1). Lo que va en la ranura sale debajo de la casilla, fuera de la zona que la
 * marca (el enlace a leer el documento, su pista). Los atributos van a la `<label>`, como en el diseño; el
 * `style`, a la envoltura. Marcada, con `v-model`.
 */
import { computed, ref, useAttrs } from 'vue';
import { idDeCasilla } from './piezas.js';
import IconoLucide from './IconoLucide.vue';

defineOptions({ inheritAttrs: false });

const props = defineProps({
    label: { type: String, default: '' },
    description: { type: String, default: '' },
    modelValue: { type: Boolean, default: false },
    disabled: { type: Boolean, default: false },
    required: { type: Boolean, default: false },
    error: { type: String, default: '' },
    id: { type: String, default: undefined },
});
const emit = defineEmits(['update:modelValue']);
const attrs = useAttrs();

const encima = ref(false);
const anillo = ref(false);
const cid = computed(() => idDeCasilla(props.id, props.label));
const aviso = computed(() => (props.error.length > 1 ? props.error : ''));
const deLaEtiqueta = computed(() => Object.fromEntries(Object.entries(attrs).filter(([k]) => k !== 'style' && k !== 'class')));
</script>

<template>
    <div :style="[{ display: 'flex', flexDirection: 'column', gap: '6px' }, $attrs.style]">
        <label
            v-bind="deLaEtiqueta"
            :for="cid"
            :style="{ position: 'relative', display: 'flex', alignItems: 'flex-start', gap: '12px', padding: '12px 14px', background: modelValue || (encima && !disabled) ? 'var(--control-bg-hover)' : 'transparent', border: `1px solid ${error ? 'var(--border-danger)' : modelValue ? 'var(--control-border-strong)' : 'var(--control-border)'}`, borderRadius: 'var(--r-md)', cursor: disabled ? 'not-allowed' : 'pointer', opacity: disabled ? 0.5 : 1, transition: 'var(--t-hover)' }"
            @mouseenter="encima = true"
            @mouseleave="encima = false"
        >
            <input
                :id="cid"
                type="checkbox"
                :checked="modelValue"
                :disabled="disabled"
                :required="required"
                :aria-invalid="error ? 'true' : undefined"
                :aria-describedby="aviso ? `${cid}-m` : undefined"
                :style="{ position: 'absolute', opacity: 0, width: '1px', height: '1px', margin: 0 }"
                @change="emit('update:modelValue', $event.target.checked)"
                @focus="anillo = $event.currentTarget.matches(':focus-visible')"
                @blur="anillo = false"
            >
            <span
                aria-hidden="true"
                :style="{ display: 'inline-flex', alignItems: 'center', justifyContent: 'center', flex: '0 0 auto', width: '22px', height: '22px', marginTop: '1px', borderRadius: 'var(--r-xs)', background: modelValue ? 'var(--control-selected-bg)' : 'var(--control-bg)', border: `2px solid ${modelValue ? 'var(--control-selected-bg)' : 'var(--control-border)'}`, boxShadow: anillo ? 'var(--ring)' : 'none', color: 'var(--control-selected-fg)', transition: 'var(--t-hover)' }"
            >
                <IconoLucide
                    v-if="modelValue"
                    name="check"
                    :size="14"
                />
            </span>
            <span :style="{ display: 'flex', flexDirection: 'column', gap: '3px', minWidth: 0 }">
                <span :style="{ fontFamily: 'var(--font-ui)', fontSize: 'var(--fs-body-sm)', lineHeight: 1.4, fontWeight: 'var(--fw-semibold)', color: 'var(--text-strong)' }">{{ label }}<span
                    v-if="required"
                    :style="{ color: 'var(--isla-obligatorio)' }"
                > *</span></span>
                <span
                    v-if="description"
                    :style="{ fontFamily: 'var(--font-ui)', fontSize: 'var(--fs-caption)', lineHeight: 1.45, color: 'var(--text-muted)' }"
                >{{ description }}</span>
            </span>
        </label>
        <span
            v-if="aviso"
            :id="`${cid}-m`"
            :style="{ paddingLeft: '48px', fontFamily: 'var(--font-ui)', fontSize: 'var(--fs-caption)', fontWeight: 'var(--fw-semibold)', color: 'var(--text-danger)' }"
        >{{ aviso }}</span>
        <div
            v-if="$slots.default"
            :style="{ display: 'flex', flexDirection: 'column', gap: '2px', paddingLeft: '48px' }"
        >
            <slot />
        </div>
    </div>
</template>
