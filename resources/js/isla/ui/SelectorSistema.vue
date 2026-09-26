<script setup>
/**
 * El DESPLEGABLE del sistema (`forms/Select.jsx`): un `<select>` nativo con el estilo del sistema —el del móvil es el
 * mejor selector que hay— con su etiqueta, su ayuda o su error, el chevron dibujado y el anillo de foco. Por tokens de
 * control, así que sirve sobre claro y sobre tinta. Lo usa «Tus datos» de Mi cuenta para el idioma (T5e, `#778`). El
 * valor, con `v-model`; las opciones, `[{ value, label }]`.
 */
import { computed, ref } from 'vue';
import { idDeCampo } from './piezas.js';
import IconoLucide from './IconoLucide.vue';

const props = defineProps({
    label: { type: String, default: '' },
    hint: { type: String, default: '' },
    error: { type: String, default: '' },
    options: { type: Array, default: () => [] },
    size: { type: String, default: 'md' },
    id: { type: String, default: undefined },
    modelValue: { type: String, default: '' },
});
const emit = defineEmits(['update:modelValue']);
const foco = ref(false);
const fid = computed(() => idDeCampo(props.id, props.label, 'select'));
const mensaje = computed(() => (props.error || props.hint ? `${fid.value}-m` : undefined));
</script>

<template>
    <div :style="{ display: 'flex', flexDirection: 'column', gap: '7px', minWidth: 0 }">
        <label
            v-if="label"
            :for="fid"
            :style="{ font: 'var(--type-label)', color: 'var(--text-strong)' }"
        >{{ label }}</label>
        <div :style="{ position: 'relative', display: 'flex', alignItems: 'center' }">
            <select
                :id="fid"
                :value="modelValue"
                :aria-invalid="error ? 'true' : undefined"
                :aria-describedby="mensaje"
                :style="{ appearance: 'none', WebkitAppearance: 'none', width: '100%', height: size === 'lg' ? 'var(--control-lg)' : 'var(--control-md)', padding: '0 44px 0 16px', background: 'var(--control-bg)', border: `1px solid ${error ? 'var(--border-danger)' : foco ? 'var(--control-border-strong)' : 'var(--control-border)'}`, borderRadius: 'var(--r-md)', boxShadow: foco ? 'var(--ring)' : 'none', fontFamily: 'var(--font-ui)', fontSize: 'max(16px, var(--fs-body))', color: 'var(--text-strong)', cursor: 'pointer', outline: 'none', transition: 'var(--t-hover)' }"
                @focus="foco = true"
                @blur="foco = false"
                @change="emit('update:modelValue', $event.target.value)"
            >
                <option
                    v-for="o in options"
                    :key="o.value"
                    :value="o.value"
                >{{ o.label }}</option>
            </select>
            <IconoLucide
                name="chevron-down"
                :size="18"
                color="var(--text-muted)"
                :style="{ position: 'absolute', right: '16px', pointerEvents: 'none' }"
            />
        </div>
        <span
            v-if="error"
            :id="mensaje"
            :style="{ fontFamily: 'var(--font-ui)', fontSize: 'var(--fs-caption)', fontWeight: 'var(--fw-semibold)', color: 'var(--text-danger)' }"
        >{{ error }}</span>
        <span
            v-else-if="hint"
            :id="mensaje"
            :style="{ fontFamily: 'var(--font-ui)', fontSize: 'var(--fs-caption)', lineHeight: 1.45, color: 'var(--text-muted)' }"
        >{{ hint }}</span>
    </div>
</template>
