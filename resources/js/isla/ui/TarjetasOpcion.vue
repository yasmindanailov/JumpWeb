<script setup>
/**
 * Un grupo de radios en forma de tarjeta, del sistema de diseño (`OptionCards.jsx`): la zona, el tiempo o el
 * menú. Radio nativo debajo, así el teclado y los lectores funcionan solos (flechas para moverse, espacio para
 * elegir). La miniatura sale solo con foto REAL: sin ella no se pinta hueco, porque un cuadrado rayado en mitad de
 * un formulario parece una imagen rota justo donde se decide pagar. Las columnas, en `columnasOpciones()`.
 * ⚠️ Sin el `icon` por opción del diseño: ninguna pantalla lo usa. Entra con la primera que lo necesite.
 */
import { computed, ref } from 'vue';
import { columnasOpciones } from './piezas.js';
import IconoLucide from './IconoLucide.vue';

const props = defineProps({
    name: { type: String, default: undefined },
    items: { type: Array, default: () => [] },
    modelValue: { type: [String, Number], default: undefined },
    label: { type: String, default: '' },
    hint: { type: String, default: '' },
    error: { type: String, default: '' },
    columns: { type: String, default: 'auto' },
    size: { type: String, default: 'md' },
});
const emit = defineEmits(['update:modelValue']);

const encima = ref(null);
const columnas = computed(() => columnasOpciones(props.columns));
const relleno = computed(() => (props.size === 'lg' ? '18px 18px' : '15px 16px'));
const cifras = { fontVariantNumeric: 'tabular-nums', fontFeatureSettings: '"tnum" 1' };
</script>

<template>
    <fieldset :style="{ border: 'none', margin: 0, padding: 0, minWidth: 0 }">
        <legend
            v-if="label"
            :style="{ padding: 0, marginBottom: hint ? '4px' : '10px', font: 'var(--type-label)', color: 'var(--text-strong)' }"
        >{{ label }}</legend>
        <p
            v-if="hint"
            :style="{ margin: '0 0 10px', fontFamily: 'var(--font-ui)', fontSize: 'var(--fs-caption)', color: 'var(--text-muted)' }"
        >{{ hint }}</p>
        <div :style="{ display: 'grid', gridTemplateColumns: columnas, gap: '12px' }">
            <label
                v-for="it in items"
                :key="it.value"
                :style="{ position: 'relative', display: 'flex', alignItems: 'flex-start', gap: '12px', minHeight: '56px', padding: relleno, background: modelValue === it.value ? 'var(--control-bg-hover)' : 'var(--control-bg)', border: modelValue === it.value ? '2px solid var(--control-border-strong)' : `1px solid ${error ? 'var(--border-danger)' : 'var(--control-border)'}`, margin: modelValue === it.value ? 0 : '1px', borderRadius: 'var(--r-lg)', boxShadow: encima === it.value && !it.disabled && modelValue !== it.value ? 'var(--shadow-sm)' : 'none', transform: encima === it.value && !it.disabled && modelValue !== it.value ? 'translateY(var(--lift-hover))' : 'none', transition: 'var(--t-card)', cursor: it.disabled ? 'not-allowed' : 'pointer', opacity: it.disabled ? 0.42 : 1 }"
                @mouseenter="encima = it.value"
                @mouseleave="encima = null"
            >
                <input
                    type="radio"
                    :name="name"
                    :value="it.value"
                    :checked="modelValue === it.value"
                    :disabled="it.disabled"
                    :style="{ position: 'absolute', opacity: 0, width: '1px', height: '1px', margin: 0 }"
                    @change="emit('update:modelValue', it.value)"
                >
                <span
                    aria-hidden="true"
                    :style="{ display: 'inline-flex', alignItems: 'center', justifyContent: 'center', width: '22px', height: '22px', flexShrink: 0, marginTop: '1px', borderRadius: 'var(--r-pill)', background: modelValue === it.value ? 'var(--control-selected-bg)' : 'transparent', boxShadow: modelValue === it.value ? 'none' : 'inset 0 0 0 2px var(--control-border)', color: 'var(--control-selected-fg)', transition: 'var(--t-hover)' }"
                >
                    <IconoLucide
                        v-if="modelValue === it.value"
                        name="check"
                        :size="14"
                    />
                </span>
                <img
                    v-if="it.image"
                    :src="it.image"
                    :alt="it.imageAlt || it.title || ''"
                    :style="{ width: '64px', height: '64px', flexShrink: 0, objectFit: 'cover', borderRadius: 'var(--r-sm)' }"
                >
                <span :style="{ display: 'flex', flexDirection: 'column', gap: '3px', minWidth: 0, flex: 1 }">
                    <span :style="{ display: 'flex', flexWrap: 'wrap', alignItems: 'baseline', gap: '4px 10px', justifyContent: 'space-between' }">
                        <span :style="{ fontFamily: 'var(--font-ui)', fontWeight: 'var(--fw-bold)', fontSize: 'var(--fs-body)', color: 'var(--text-strong)', whiteSpace: 'nowrap' }">{{ it.title }}</span>
                        <span
                            v-if="it.price"
                            :style="[cifras, { display: 'inline-flex', alignItems: 'baseline', gap: '8px', flexShrink: 0, fontFamily: 'var(--font-mono)', fontWeight: 'var(--fw-medium)', fontSize: 'var(--fs-body-sm)', color: modelValue === it.value ? 'var(--text-strong)' : 'var(--text-muted)' }]"
                        ><s
                            v-if="it.was"
                            :style="{ fontSize: 'var(--fs-caption)', color: 'var(--text-muted)' }"
                        >{{ it.was }}</s>{{ it.price }}</span>
                    </span>
                    <span
                        v-if="it.description"
                        :style="{ fontFamily: 'var(--font-ui)', fontSize: 'var(--fs-body-sm)', lineHeight: 1.45, color: 'var(--text-body)' }"
                    >{{ it.description }}</span>
                    <span
                        v-if="it.includes && it.includes.length"
                        :style="{ display: 'flex', flexDirection: 'column', gap: '4px', marginTop: '4px' }"
                    >
                        <span
                            v-for="(x, k) in it.includes"
                            :key="k"
                            :style="{ display: 'flex', alignItems: 'flex-start', gap: '7px', fontFamily: 'var(--font-ui)', fontSize: 'var(--fs-caption)', lineHeight: 1.45, color: 'var(--text-body)' }"
                        ><span :style="{ display: 'inline-flex', flexShrink: 0, marginTop: '2px', color: 'var(--icon-accent)' }"><IconoLucide
                            name="check"
                            :size="13"
                        /></span>{{ x }}</span>
                    </span>
                    <span
                        v-if="it.note"
                        :style="{ font: 'var(--type-mono)', color: 'var(--text-muted)' }"
                    >{{ it.note }}</span>
                    <span
                        v-if="it.highlight"
                        :style="{ fontFamily: 'var(--font-ui)', fontSize: 'var(--fs-caption)', fontWeight: 'var(--fw-semibold)', color: 'var(--text-positive)' }"
                    >{{ it.highlight }}</span>
                </span>
            </label>
        </div>
        <p
            v-if="error"
            :style="{ margin: '8px 0 0', fontFamily: 'var(--font-ui)', fontSize: 'var(--fs-caption)', fontWeight: 'var(--fw-semibold)', color: 'var(--text-danger)' }"
        >{{ error }}</p>
    </fieldset>
</template>
