<script setup>
/**
 * El campo de texto del sistema de diseño (`Field.jsx`): etiqueta, ayuda y error, 48px como mínimo. Sirve igual
 * sobre claro y sobre tinta: solo usa tokens de control. Los atributos del campo (`autocomplete`, `inputmode`,
 * `placeholder`…) van al `<input>`; el `style`, a la envoltura, como en el diseño. El valor, con `v-model`.
 * ▶ Sin el «ver» de `type="password"` desde la A5 (`#869`): la isla ya no pide ninguna contraseña.
 * **«¿Querías decir…?»** (`suggest` del zip del 26-09, §4.16): con `type="email"`, al salir del campo propone el correo
 * bien escrito —el QR, el recibo y la invitación llegan por correo: un «gmial.com» no da error, da una reserva que no
 * llega—, y un toque lo escribe. La regla es la MISMA de la fiesta del SPA (`ui/correo.js::sugerirCorreo`, que
 * `fiesta/logica.js` reexporta): una sola forma de decidir qué es un correo mal escrito.
 */
import { computed, ref, useAttrs } from 'vue';
import { idDeCampo } from './piezas.js';
import { useTextos } from '../piezas/textos.js';
import { sugerirCorreo } from '../../ui/correo.js';

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
    /** Con `type="email"`, proponer el correo bien escrito al salir del campo. */
    suggest: { type: Boolean, default: true },
});
const emit = defineEmits(['update:modelValue']);
const attrs = useAttrs();
const { t } = useTextos();

const foco = ref(false);
const fid = computed(() => idDeCampo(props.id, props.label, props.type));
const mensaje = computed(() => (props.error || props.hint ? `${fid.value}-m` : undefined));
const sugerencia = computed(() => (props.type === 'email' && props.suggest && ! foco.value ? sugerirCorreo(String(props.modelValue ?? '')) : null));
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
        <div :style="{ display: 'flex', alignItems: 'center', gap: '10px', height: size === 'lg' ? 'var(--control-lg)' : 'var(--control-md)', padding: '0 16px', background: 'var(--control-bg)', border: `1px solid ${error ? 'var(--border-danger)' : foco ? 'var(--control-border-strong)' : 'var(--control-border)'}`, borderRadius: 'var(--r-md)', boxShadow: foco ? 'var(--ring)' : 'none', transition: 'var(--t-hover)' }">
            <span
                v-if="$slots.prefijo"
                :style="{ display: 'flex', color: 'var(--text-muted)' }"
            ><slot name="prefijo" /></span>
            <input
                :id="fid"
                :type="type"
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
        <!-- La sugerencia es un botón entero (44px para el dedo): un toque y queda escrita. -->
        <div
            role="status"
            aria-live="polite"
            :style="{ display: 'contents' }"
        >
            <button
                v-if="sugerencia"
                type="button"
                :style="{ alignSelf: 'flex-start', display: 'inline-flex', alignItems: 'center', minHeight: '44px', margin: '-6px 0 -8px', padding: 0, border: 'none', background: 'none', cursor: 'pointer', textAlign: 'left', fontFamily: 'var(--font-ui)', fontSize: 'var(--fs-body-sm)', lineHeight: 1.35, color: 'var(--text-body)' }"
                @click="emit('update:modelValue', sugerencia)"
            >
                <span>{{ t('pieza.sugerencia_antes') }}<b :style="{ color: 'var(--text-link)', fontWeight: 'var(--fw-bold)', textDecoration: 'underline', textUnderlineOffset: '3px', wordBreak: 'break-all' }">{{ sugerencia }}</b>{{ t('pieza.sugerencia_despues') }}</span>
            </button>
        </div>
    </div>
</template>
