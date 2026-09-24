<script setup>
/**
 * El campo de texto del sistema de diseño (`Field.jsx`): etiqueta, ayuda y error, 48px como mínimo. Sirve igual
 * sobre claro y sobre tinta: solo usa tokens de control. Con `type="password"` lleva «ver» dentro, porque en el
 * móvil escribir a ciegas es lo que hace que alguien se equivoque dos veces y lo deje. Los atributos del campo
 * (`autocomplete`, `inputmode`, `placeholder`…) van al `<input>`; el `style`, a la envoltura, como en el diseño.
 * El valor, con `v-model`.
 */
import { computed, ref, useAttrs } from 'vue';
import { idDeCampo } from './piezas.js';
import { useTextos } from '../piezas/textos.js';
import IconoLucide from './IconoLucide.vue';

defineOptions({ inheritAttrs: false });

const props = defineProps({
    label: { type: String, default: '' },
    hint: { type: String, default: '' },
    error: { type: String, default: '' },
    required: { type: Boolean, default: false },
    optional: { type: Boolean, default: false },
    type: { type: String, default: 'text' },
    size: { type: String, default: 'md' },
    id: { type: String, default: undefined },
    modelValue: { type: [String, Number], default: undefined },
});
const emit = defineEmits(['update:modelValue']);
const attrs = useAttrs();
const { t } = useTextos();

const foco = ref(false);
const ver = ref(false);
const fid = computed(() => idDeCampo(props.id, props.label, props.type));
const mensaje = computed(() => (props.error || props.hint ? `${fid.value}-m` : undefined));
const clave = computed(() => props.type === 'password');
const delCampo = computed(() => Object.fromEntries(Object.entries(attrs).filter(([k]) => k !== 'style' && k !== 'class')));
</script>

<template>
    <div :style="[{ display: 'flex', flexDirection: 'column', gap: '7px', minWidth: 0 }, $attrs.style]">
        <label
            v-if="label"
            :for="fid"
            :style="{ font: 'var(--type-label)', color: 'var(--text-strong)' }"
        >{{ label }}<span
            v-if="required"
            :style="{ color: 'var(--isla-obligatorio)' }"
        > *</span><span
            v-if="optional && !required"
            :style="{ marginLeft: '8px', fontFamily: 'var(--font-ui)', fontSize: 'var(--fs-caption)', fontWeight: 'var(--fw-regular)', color: 'var(--text-muted)' }"
        >{{ t('pieza.opcional') }}</span></label>
        <div :style="{ display: 'flex', alignItems: 'center', gap: '10px', height: size === 'lg' ? 'var(--control-lg)' : 'var(--control-md)', padding: clave ? '0 4px 0 16px' : '0 16px', background: 'var(--control-bg)', border: `1px solid ${error ? 'var(--border-danger)' : foco ? 'var(--control-border-strong)' : 'var(--control-border)'}`, borderRadius: 'var(--r-md)', boxShadow: foco ? 'var(--ring)' : 'none', transition: 'var(--t-hover)' }">
            <span
                v-if="$slots.prefijo"
                :style="{ display: 'flex', color: 'var(--text-muted)' }"
            ><slot name="prefijo" /></span>
            <input
                :id="fid"
                :type="clave && ver ? 'text' : type"
                :required="required"
                :aria-invalid="error ? 'true' : undefined"
                :aria-describedby="mensaje"
                :value="modelValue"
                v-bind="delCampo"
                :style="{ flex: 1, minWidth: 0, height: '100%', border: 'none', outline: 'none', boxShadow: 'none', background: 'transparent', fontFamily: 'var(--font-ui)', fontSize: 'max(16px, var(--fs-body))', color: 'var(--control-fg)' }"
                @focus="foco = true"
                @blur="foco = false"
                @input="emit('update:modelValue', $event.target.value)"
            >
            <button
                v-if="clave"
                type="button"
                :aria-label="ver ? t('pieza.ocultar_clave') : t('pieza.mostrar_clave')"
                :aria-pressed="ver"
                :style="{ display: 'inline-flex', alignItems: 'center', justifyContent: 'center', flex: '0 0 auto', width: '44px', height: '40px', border: 'none', borderRadius: 'var(--r-sm)', background: 'transparent', color: 'var(--text-muted)', cursor: 'pointer' }"
                @click="ver = !ver"
            >
                <IconoLucide
                    :name="ver ? 'eye-off' : 'eye'"
                    :size="19"
                />
            </button>
            <span
                v-if="$slots.sufijo"
                :style="{ display: 'flex', color: 'var(--text-muted)', fontFamily: 'var(--font-mono)', fontSize: 'var(--fs-caption)' }"
            ><slot name="sufijo" /></span>
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
