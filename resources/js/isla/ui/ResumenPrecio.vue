<script setup>
/**
 * El resultado del cálculo, del sistema de diseño (`PriceSummary.jsx`). Nunca enseña un total sin decir qué se
 * paga hoy: una sorpresa en la pasarela cuesta más que un precio alto. Cada línea puede llevar su precio unitario
 * (`sub`), un control debajo y `tone: 'positive'` para un descuento. Los colores van por token: sobre tinta
 * (`tone="ink"` o dentro de `data-surface="ink"`) cambian solos.
 * ⚠️ El control de una línea es la ranura `control` (recibe `{ linea }`), y la línea lo declara con
 * `control: true`: de eso dependen su negrita, su separación y su filete, como en el diseño.
 */
import { computed } from 'vue';
import { useTextos } from '../piezas/textos.js';
import IconoLucide from './IconoLucide.vue';
import EnlaceSistema from './EnlaceSistema.vue';

const props = defineProps({
    selection: { type: String, default: '' },
    lines: { type: Array, default: () => [] },
    total: { type: String, default: '' },
    totalLabel: { type: String, default: '' },
    provisional: { type: Boolean, default: false },
    incomplete: { type: String, default: '' },
    incompleteHref: { type: String, default: '' },
    now: { type: Object, default: null },
    later: { type: Object, default: null },
    note: { type: String, default: '' },
    ctaNote: { type: String, default: '' },
    tone: { type: String, default: 'light' },
    size: { type: String, default: 'lg' },
});
const { t } = useTextos();

const tinta = computed(() => props.tone === 'ink');
const filete = '1px solid var(--border-subtle)';
const cifras = { fontVariantNumeric: 'tabular-nums', fontFeatureSettings: '"tnum" 1' };
const conControles = computed(() => props.lines.some((l) => l.control));
const rotulo = computed(() => (props.provisional ? t('pieza.desde') : props.totalLabel || t('pieza.total')));
const separada = (l, i) => Boolean(l.control) && i < props.lines.length - 1;
const fuerte = (l) => Boolean(l.strong || l.control);
</script>

<template>
    <div
        :data-surface="tinta ? 'ink' : undefined"
        :style="{ display: 'flex', flexDirection: 'column', gap: '16px', padding: size === 'md' ? 'var(--space-4)' : 'var(--space-6)', background: tinta ? 'var(--surface-card-ink)' : 'var(--surface-card)', border: filete, borderRadius: 'var(--r-xl)', boxShadow: tinta ? 'none' : 'var(--shadow-xs)' }"
    >
        <p
            v-if="selection"
            :style="{ margin: 0, fontFamily: 'var(--font-mono)', fontSize: 'var(--fs-body-sm)', lineHeight: 1.5, color: 'var(--text-muted)' }"
        >{{ selection }}</p>

        <div
            v-if="lines.length"
            :style="{ display: 'flex', flexDirection: 'column', gap: conControles ? '14px' : '8px' }"
        >
            <div
                v-for="(l, i) in lines"
                :key="l.id || i"
                :style="{ display: 'flex', flexDirection: 'column', gap: '8px', paddingBottom: separada(l, i) ? '14px' : 0, borderBottom: separada(l, i) ? filete : 'none' }"
            >
                <div :style="{ display: 'flex', alignItems: 'baseline', justifyContent: 'space-between', gap: '14px' }">
                    <span :style="{ display: 'flex', flexDirection: 'column', gap: '2px', minWidth: 0 }">
                        <span :style="{ fontFamily: 'var(--font-ui)', fontSize: 'var(--fs-body-sm)', fontWeight: fuerte(l) ? 'var(--fw-bold)' : 'var(--fw-regular)', color: l.tone === 'positive' ? 'var(--text-positive)' : fuerte(l) ? 'var(--text-strong)' : 'var(--text-body)' }">{{ l.label }}</span>
                        <span
                            v-if="l.sub"
                            :style="{ fontFamily: 'var(--font-ui)', fontSize: 'var(--fs-caption)', color: 'var(--text-muted)' }"
                        >{{ l.sub }}</span>
                    </span>
                    <span :style="[cifras, { flexShrink: 0, fontFamily: 'var(--font-mono)', fontSize: 'var(--fs-body-sm)', fontWeight: fuerte(l) ? 'var(--fw-medium)' : 'var(--fw-regular)', color: l.tone === 'positive' ? 'var(--text-positive)' : fuerte(l) ? 'var(--text-strong)' : 'var(--text-muted)' }]">{{ l.value }}</span>
                </div>
                <slot
                    v-if="l.control"
                    name="control"
                    :linea="l"
                />
            </div>
        </div>

        <div :style="{ display: 'flex', flexWrap: 'wrap', alignItems: 'flex-end', justifyContent: 'space-between', gap: '6px 14px', paddingTop: lines.length ? '14px' : 0, borderTop: lines.length ? filete : 'none' }">
            <span :style="{ display: 'flex', flexDirection: 'column', gap: '3px' }">
                <span :style="{ font: 'var(--type-overline)', letterSpacing: 'var(--tracking-overline)', color: 'var(--text-muted)' }">{{ rotulo }}</span>
                <EnlaceSistema
                    v-if="incomplete && incompleteHref"
                    :href="incompleteHref"
                    arrow
                >{{ incomplete }}</EnlaceSistema>
                <span
                    v-else-if="incomplete"
                    :style="{ fontFamily: 'var(--font-ui)', fontSize: 'var(--fs-body)', color: 'var(--text-muted)' }"
                >{{ incomplete }}</span>
            </span>
            <span
                v-if="!incomplete"
                :style="[cifras, { fontFamily: 'var(--font-display)', fontWeight: 'var(--fw-black)', fontSize: size === 'md' ? '1.75rem' : 'var(--fs-h1)', lineHeight: 1, letterSpacing: 'var(--tracking-display)', color: 'var(--text-strong)' }]"
            >{{ total }}</span>
        </div>

        <div
            v-if="now || later"
            :style="{ display: 'flex', flexDirection: 'column', gap: '9px', padding: '14px 16px', background: 'var(--bg-subtle)', borderRadius: 'var(--r-md)' }"
        >
            <div
                v-if="now"
                :style="{ display: 'flex', alignItems: 'baseline', justifyContent: 'space-between', gap: '14px' }"
            >
                <span :style="{ display: 'inline-flex', alignItems: 'center', gap: '8px', fontFamily: 'var(--font-ui)', fontWeight: 'var(--fw-bold)', fontSize: 'var(--fs-body-sm)', color: 'var(--text-strong)' }"><IconoLucide
                    name="wallet"
                    :size="16"
                />{{ now.label }}</span>
                <span :style="[cifras, { flexShrink: 0, fontFamily: 'var(--font-mono)', fontWeight: 'var(--fw-medium)', fontSize: 'var(--fs-body)', color: 'var(--text-strong)' }]">{{ now.value }}</span>
            </div>
            <div
                v-if="later"
                :style="{ display: 'flex', alignItems: 'baseline', justifyContent: 'space-between', gap: '14px' }"
            >
                <span :style="{ fontFamily: 'var(--font-ui)', fontSize: 'var(--fs-body-sm)', color: 'var(--text-body)' }">{{ later.label }}</span>
                <span :style="[cifras, { flexShrink: 0, fontFamily: 'var(--font-mono)', fontSize: 'var(--fs-body-sm)', color: 'var(--text-muted)' }]">{{ later.value }}</span>
            </div>
        </div>

        <p
            v-if="note"
            :style="{ margin: 0, fontFamily: 'var(--font-ui)', fontSize: 'var(--fs-body-sm)', lineHeight: 1.5, color: 'var(--text-body)' }"
        >{{ note }}</p>

        <slot />

        <div
            v-if="$slots.cta"
            :style="{ display: 'flex', flexDirection: 'column', gap: '9px' }"
        >
            <slot name="cta" />
            <p
                v-if="ctaNote"
                :style="{ margin: 0, textAlign: 'center', fontFamily: 'var(--font-ui)', fontSize: 'var(--fs-caption)', lineHeight: 1.5, color: 'var(--text-muted)' }"
            >{{ ctaNote }}</p>
        </div>
    </div>
</template>
