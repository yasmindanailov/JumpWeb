<script setup>
/**
 * El hueco de CARGA del sistema de diseño (`Skeleton.jsx`): el sitio exacto de un dato que viene del sistema
 * (las horas, un total, una tarjeta), para que nada salte al llegar. No confundir con el hueco rayado de «falta
 * material real». Sobre tinta usa su brillo propio (`tone="ink"`). Las formas, en `barrasEsqueleto()`.
 * ▶ `delay` (26-09; Z3, `#782`): si el dato llega antes de 300ms, el hueco no se llega a ver —un parpadeo gris se nota
 * más que la espera—; mientras, ocupa su sitio invisible, y al cumplirse aparece fundido.
 */
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import { barrasEsqueleto } from './piezas.js';

const props = defineProps({
    kind: { type: String, default: 'text' },
    lines: { type: Number, default: 3 },
    width: { type: String, default: '100%' },
    height: { type: String, default: undefined },
    radius: { type: String, default: undefined },
    aspect: { type: String, default: undefined },
    gap: { type: String, default: '10px' },
    tone: { type: String, default: 'light' },
    label: { type: String, default: '' },
    delay: { type: Number, default: 300 },
});

const ver = ref(! (props.delay > 0));
let reloj = 0;
onMounted(() => { if (! ver.value) reloj = setTimeout(() => { ver.value = true; }, props.delay); });
onBeforeUnmount(() => clearTimeout(reloj));
const forma = computed(() => barrasEsqueleto(props));
const brillo = computed(() => (props.tone === 'ink'
    ? 'linear-gradient(100deg, rgba(255,255,255,0.08) 20%, rgba(255,255,255,0.16) 42%, rgba(255,255,255,0.08) 64%)'
    : 'linear-gradient(100deg, var(--skeleton-base) 20%, var(--skeleton-sheen) 42%, var(--skeleton-base) 64%)'));
const accesible = computed(() => (props.label
    ? { role: 'status', 'aria-label': props.label, 'aria-live': 'polite' }
    : { 'aria-hidden': 'true' }));
const barra = (b) => ({
    display: 'block', width: b.w, height: b.h, aspectRatio: b.ar, borderRadius: b.r, flexShrink: 0,
    background: brillo.value, backgroundSize: '220% 100%', animation: 'isla-sheen 1.6s linear infinite', animationDelay: b.delay || '0s',
});
</script>

<template>
    <div
        v-bind="accesible"
        :style="[{ display: 'flex', flexDirection: 'column', gap, width, visibility: ver ? 'visible' : 'hidden', animation: ver && delay > 0 ? 'isla-fade-in var(--dur-fast) var(--ease-out) both' : undefined }, forma.extra]"
    >
        <span
            v-if="forma.bloque"
            :style="{ display: 'block', width: '100%', height: forma.bloque.h, aspectRatio: forma.bloque.ar, borderRadius: forma.bloque.r, background: brillo, backgroundSize: '220% 100%', animation: 'isla-sheen 1.6s linear infinite' }"
        />
        <span
            v-else-if="forma.fila"
            :style="{ display: 'flex', alignItems: 'flex-end', gap: '10px' }"
        >
            <span
                v-for="(b, i) in forma.barras"
                :key="i"
                :style="barra(b)"
            />
        </span>
        <template v-else>
            <span
                v-for="(b, i) in forma.barras"
                :key="i"
                :style="barra(b)"
            />
        </template>
    </div>
</template>
