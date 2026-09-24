<script setup>
/**
 * El selector de plan (`PlanPicker`, `PlanFeatured`, `PlanToday` y `PlanOption` del diseño): vive en la isla y
 * solo ahí. Abierto desde «Reservar para hoy», no repite la opción [Hoy]: ya lo ha dicho el botón.
 * El plan destacado (`featured`) es el que el parque quiere vender más en cada momento: va primero, grande y con
 * su foto; solo uno. [Hoy] va después, en su color, y el resto en filas limpias, con el «desde» grande.
 */
import { computed, ref } from 'vue';
import { useTextos } from './textos.js';
import IconoLucide from '../ui/IconoLucide.vue';

const props = defineProps({
    plans: { type: Object, required: true },
    fromToday: { type: Boolean, default: false },
});
const emit = defineEmits(['elegir']);
const { t } = useTextos();

const opciones = computed(() => props.plans.options.filter((o) => !(props.fromToday && o.today)));
const hoy = computed(() => opciones.value.find((o) => o.today));
const destacado = computed(() => opciones.value.find((o) => o.featured && !o.today));
const resto = computed(() => opciones.value.filter((o) => !o.today && o !== destacado.value));

const hover = ref(null);
const anillo = ref(null);
const enfocar = (e, o) => { anillo.value = e.currentTarget.matches(':focus-visible') ? o.title : null; };

/* El precio llega como «desde 14,95 €»: la palabra se pinta aparte, pequeña, y la cifra grande. */
const precio = (o) => {
    const palabra = t('panel.plan_desde').replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
    return String(o.price || '').replace(new RegExp(`^${palabra}\\s*`, 'i'), '');
};
const nombre = (...partes) => partes.filter(Boolean).join(', ');
</script>

<template>
    <div :style="{ display: 'grid', gap: '10px' }">
        <button
            v-if="destacado"
            type="button"
            :aria-label="nombre(destacado.badge, destacado.title, destacado.note, destacado.price, destacado.offer)"
            :style="{ display: 'grid', width: '100%', padding: 0, overflow: 'hidden', border: `1px solid ${hover === destacado.title ? 'var(--control-border-strong)' : 'var(--control-border)'}`, borderRadius: 'var(--r-xl)', background: 'var(--surface-card)', color: 'var(--text-strong)', textAlign: 'left', cursor: 'pointer', transition: 'var(--t-hover)', boxShadow: anillo === destacado.title ? 'var(--ring)' : 'none' }"
            @click="emit('elegir', destacado)"
            @mouseenter="hover = destacado.title"
            @mouseleave="hover = null"
            @focus="enfocar($event, destacado)"
            @blur="anillo = null"
        >
            <span
                v-if="destacado.image"
                aria-hidden="true"
                :style="{ position: 'relative', display: 'block', height: '132px', overflow: 'hidden' }"
            >
                <img
                    :src="destacado.image"
                    alt=""
                    :style="{ width: '100%', height: '100%', objectFit: 'cover', objectPosition: destacado.focus || '50% 50%', display: 'block', transform: hover === destacado.title ? 'scale(1.03)' : 'none', transition: 'transform var(--dur-slow) var(--ease-out)' }"
                >
                <span
                    v-if="destacado.badge"
                    :style="{ position: 'absolute', top: '10px', left: '10px', padding: '5px 10px', borderRadius: 'var(--r-pill)', background: 'var(--isla-vivo)', color: 'var(--isla-tinta)', fontFamily: 'var(--font-ui)', fontSize: 'var(--fs-overline)', fontWeight: 'var(--fw-bold)' }"
                >{{ destacado.badge }}</span>
            </span>
            <span :style="{ display: 'grid', gridTemplateColumns: 'minmax(0,1fr) auto', alignItems: 'end', gap: '12px', padding: '14px 16px 16px' }">
                <span :style="{ display: 'grid', gap: '3px', minWidth: 0 }">
                    <b :style="{ fontFamily: 'var(--font-display)', fontWeight: 'var(--fw-black)', fontSize: '1.25rem', lineHeight: 1.1, letterSpacing: '-0.01em' }">{{ destacado.title }}</b>
                    <span
                        v-if="destacado.note"
                        :style="{ fontFamily: 'var(--font-ui)', fontSize: 'var(--fs-caption)', color: 'var(--text-muted)' }"
                    >{{ destacado.note }}</span>
                </span>
                <span
                    v-if="precio(destacado)"
                    :style="{ display: 'grid', justifyItems: 'end', gap: '2px' }"
                >
                    <span
                        v-if="destacado.offer"
                        :style="{ fontFamily: 'var(--font-ui)', fontSize: 'var(--fs-overline)', fontWeight: 'var(--fw-bold)', color: 'var(--isla-vivo)', whiteSpace: 'nowrap' }"
                    >{{ destacado.offer }}</span>
                    <span :style="{ display: 'flex', alignItems: 'baseline', gap: '4px', whiteSpace: 'nowrap' }">
                        <small :style="{ fontFamily: 'var(--font-ui)', fontSize: 'var(--fs-overline)', fontWeight: 'var(--fw-semibold)', color: 'var(--text-muted)' }">{{ t('panel.plan_desde') }}</small>
                        <em :style="{ fontStyle: 'normal', fontFamily: 'var(--font-display)', fontWeight: 'var(--fw-black)', fontSize: '1.5rem', fontVariantNumeric: 'tabular-nums' }">{{ precio(destacado) }}</em>
                    </span>
                </span>
            </span>
        </button>
        <button
            v-if="hoy"
            type="button"
            :style="{ display: 'flex', alignItems: 'center', gap: '12px', width: '100%', boxSizing: 'border-box', minHeight: '60px', padding: '10px 12px 10px 14px', border: '1px solid var(--isla-vivo)', borderRadius: 'var(--r-lg)', background: hover === hoy.title ? 'var(--isla-destacado-fondo-hover)' : 'var(--isla-destacado-fondo)', color: 'var(--text-strong)', textAlign: 'left', cursor: 'pointer', transition: 'var(--t-hover)' }"
            @click="emit('elegir', hoy)"
            @mouseenter="hover = hoy.title"
            @mouseleave="hover = null"
        >
            <span
                aria-hidden="true"
                :style="{ width: '10px', height: '10px', borderRadius: '50%', background: 'var(--isla-vivo)', flex: '0 0 auto', animation: 'isla-pulse 2.4s var(--ease-in-out) infinite' }"
            />
            <span :style="{ display: 'grid', gap: '1px', flex: 1, minWidth: 0 }">
                <b :style="{ fontFamily: 'var(--font-ui)', fontWeight: 'var(--fw-bold)', fontSize: 'var(--fs-body)' }">{{ hoy.title }}</b>
                <span
                    v-if="hoy.note"
                    :style="{ fontFamily: 'var(--font-ui)', fontSize: 'var(--fs-caption)', color: 'var(--isla-vivo)', fontWeight: 'var(--fw-semibold)' }"
                >{{ hoy.note }}</span>
            </span>
            <IconoLucide
                name="arrow-right"
                :size="18"
                color="var(--isla-vivo)"
            />
        </button>
        <ul :style="{ listStyle: 'none', margin: 0, padding: 0, display: 'grid', gap: '8px' }">
            <li
                v-for="o in resto"
                :key="o.title"
            >
                <button
                    type="button"
                    :aria-label="nombre(o.title, o.note, o.price, o.offer)"
                    :style="{ display: 'grid', gridTemplateColumns: 'minmax(0,1fr) auto auto', alignItems: 'center', gap: '12px', width: '100%', boxSizing: 'border-box', minHeight: '64px', padding: '12px 10px 12px 16px', border: `1px solid ${hover === o.title ? 'var(--control-border)' : 'var(--border-subtle)'}`, borderRadius: 'var(--r-lg)', background: hover === o.title ? 'var(--control-bg-hover)' : 'var(--surface-card)', color: 'var(--text-strong)', textAlign: 'left', cursor: 'pointer', transition: 'var(--t-hover)', boxShadow: anillo === o.title ? 'var(--ring)' : 'none' }"
                    @click="emit('elegir', o)"
                    @mouseenter="hover = o.title"
                    @mouseleave="hover = null"
                    @focus="enfocar($event, o)"
                    @blur="anillo = null"
                >
                    <span :style="{ display: 'grid', gap: '2px', minWidth: 0 }">
                        <b :style="{ fontFamily: 'var(--font-ui)', fontWeight: 'var(--fw-bold)', fontSize: 'var(--fs-body)', lineHeight: 1.25 }">{{ o.title }}</b>
                        <span
                            v-if="o.note"
                            :style="{ fontFamily: 'var(--font-ui)', fontSize: 'var(--fs-caption)', color: 'var(--text-muted)' }"
                        >{{ o.note }}</span>
                    </span>
                    <span
                        v-if="precio(o)"
                        :style="{ display: 'grid', justifyItems: 'end', gap: '2px' }"
                    >
                        <span
                            v-if="o.offer"
                            :style="{ fontFamily: 'var(--font-ui)', fontSize: 'var(--fs-overline)', fontWeight: 'var(--fw-bold)', color: 'var(--isla-vivo)', whiteSpace: 'nowrap' }"
                        >{{ o.offer }}</span>
                        <span :style="{ display: 'flex', alignItems: 'baseline', gap: '4px', whiteSpace: 'nowrap' }">
                            <small :style="{ fontFamily: 'var(--font-ui)', fontSize: 'var(--fs-overline)', fontWeight: 'var(--fw-semibold)', color: 'var(--text-muted)' }">{{ t('panel.plan_desde') }}</small>
                            <em :style="{ fontStyle: 'normal', fontFamily: 'var(--font-display)', fontWeight: 'var(--fw-black)', fontSize: '1.125rem', fontVariantNumeric: 'tabular-nums' }">{{ precio(o) }}</em>
                        </span>
                    </span>
                    <IconoLucide
                        name="chevron-right"
                        :size="18"
                        color="var(--text-muted)"
                        :style="{ transform: hover === o.title ? 'translateX(2px)' : 'none', transition: 'var(--t-hover)' }"
                    />
                </button>
            </li>
        </ul>
        <p
            v-if="plans.footer"
            :style="{ display: 'flex', alignItems: 'flex-start', justifyContent: 'center', gap: '8px', margin: '4px 0 0', fontFamily: 'var(--font-ui)', fontSize: 'var(--fs-caption)', lineHeight: 1.45, color: 'var(--text-muted)', textAlign: 'center', textWrap: 'pretty' }"
        >
            <IconoLucide
                name="shield-check"
                :size="15"
                color="var(--icon-accent)"
                :style="{ flex: '0 0 auto', marginTop: '1px' }"
            />{{ plans.footer }}
        </p>
    </div>
</template>
