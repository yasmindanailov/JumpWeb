<script setup>
/**
 * La frase de la situación (`ContextLine` del diseño): un punto de color si la situación está viva, y el texto. Desde la
 * Z6a, `entra`: llega enfocándose cuando la isla ya casi tiene su forma (nunca se ve cortada), y solo a partir del primer
 * cambio —la llegada no se mueve—; `quieta`: la copia que se va, sin animar ni latir (la anima quien la envuelve).
 */
import { computed, ref } from 'vue';
import IconoLucide from '../ui/IconoLucide.vue';

const PUNTO = { live: 'var(--isla-vivo)', alert: 'var(--isla-alerta)', focus: 'var(--isla-foco)', neutral: null };

const props = defineProps({
    situation: { type: Object, required: true },
    abrir: { type: Function, default: null },
    expanded: { type: Boolean, default: false },
    entra: { type: Boolean, default: false },
    quieta: { type: Boolean, default: false },
});

const hover = ref(false);
const punto = computed(() => PUNTO[props.situation.tone]);
const estilo = computed(() => ({
    display: 'flex', alignItems: 'center', gap: '8px', width: '100%', minWidth: 0,
    padding: props.abrir ? '5px 8px 5px 10px' : '0 6px', margin: props.abrir ? '-5px 0' : 0,
    border: 'none', borderRadius: 'var(--r-pill)',
    background: props.abrir && hover.value ? 'rgba(255,255,255,0.12)' : 'transparent',
    textAlign: 'left', cursor: props.abrir ? 'pointer' : 'default',
    fontFamily: 'var(--font-ui)', fontSize: '14px', fontWeight: 'var(--fw-semibold)',
    color: 'var(--isla-sobre)', lineHeight: 1.35, transition: 'var(--t-hover)',
    animation: props.entra && ! props.quieta ? 'isla-swap calc(var(--dur-island) * 0.8) var(--ease-out) calc(var(--dur-island) * 0.12) both' : 'none',
}));
</script>

<template>
    <component
        :is="abrir ? 'button' : 'div'"
        :type="abrir ? 'button' : undefined"
        :aria-expanded="abrir ? expanded : undefined"
        :aria-live="quieta ? undefined : 'polite'"
        :style="estilo"
        @click="abrir && abrir($event)"
        @mouseenter="hover = true"
        @mouseleave="hover = false"
    >
        <span
            v-if="punto"
            :style="{ width: '8px', height: '8px', borderRadius: '50%', background: punto, flex: '0 0 auto', animation: situation.tone === 'live' && ! quieta ? 'isla-pulse 2.4s var(--ease-in-out) infinite' : 'none' }"
        />
        <!-- La nota, en blanco como la frase (Z6a): se lee a la primera. -->
        <span :style="{ flex: '1 1 auto', minWidth: 0, display: '-webkit-box', WebkitLineClamp: 2, WebkitBoxOrient: 'vertical', overflow: 'hidden' }">{{ situation.line }}<span
            v-if="situation.note"
            :style="{ display: 'block', marginTop: '1px', fontSize: '12.5px', fontWeight: 'var(--fw-semibold)', color: 'var(--isla-sobre)' }"
        >{{ situation.note }}</span></span>
        <IconoLucide
            v-if="abrir"
            :name="expanded ? 'chevron-down' : 'chevron-up'"
            :size="16"
        />
    </component>
</template>
