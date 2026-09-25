<script setup>
/**
 * El panel abierto de la isla: el menú, el selector de plan, el resumen, la cuenta o la ayuda. Es un diálogo
 * con su título (salvo que el título suba a la fila) y su propio scroll. La isla le pone la `key` de la vista:
 * cambiar de panel lo vuelve a montar, y así entra con su movimiento.
 */
import { defineAsyncComponent, ref } from 'vue';
import CabeceraPanel from './CabeceraPanel.vue';
import MenuIsla from './MenuIsla.vue';
import SelectorPlan from './SelectorPlan.vue';
import HojasIsla from './HojasIsla.vue';

// «Tus cookies» se abre poco: diferida, ni la isla en reposo ni la compra pagan sus interruptores (medido, +4,2 y
// +5,9 KiB estáticas).
const PreferenciasCookies = defineAsyncComponent(() => import('./PreferenciasCookies.vue'));

defineProps({
    vista: { type: String, required: true },
    titulo: { type: String, default: null },
    tituloEnFila: { type: Boolean, default: false },
    top: { type: Boolean, default: false },
    menuItems: { type: Array, default: () => [] },
    homeLabel: { type: String, default: null },
    contact: { type: Object, default: () => ({}) },
    lang: { type: String, default: '' },
    account: { type: Object, default: () => ({ state: 'guest' }) },
    bookingToday: { type: Object, default: null },
    help: { type: Object, default: null },
    cookies: { type: Object, default: null },
    preferencias: { type: Object, default: null },
    plans: { type: Object, default: null },
    plansFromToday: { type: Boolean, default: false },
    quote: { type: Object, default: null },
});
const emit = defineEmits(['abrir', 'capa', 'navegar', 'elegir']);

const raiz = ref(null);
defineExpose({ enfocar: () => raiz.value && raiz.value.focus({ preventScroll: true }) });
</script>

<template>
    <div
        ref="raiz"
        tabindex="-1"
        role="dialog"
        :aria-label="titulo || undefined"
        :style="{
            outline: 'none', boxShadow: 'none', padding: top ? '10px 2px 2px' : '2px 2px 10px',
            maxHeight: 'min(62vh, 520px)', overflowY: 'auto', overscrollBehavior: 'contain',
            scrollbarWidth: 'thin', scrollbarColor: 'rgba(255,255,255,0.28) transparent',
            animation: 'isla-swap var(--dur-slow) var(--ease-island) both',
        }"
    >
        <CabeceraPanel
            v-if="titulo && !tituloEnFila"
            :title="titulo"
        />
        <MenuIsla
            v-if="vista === 'menu'"
            :items="menuItems"
            :home-label="homeLabel"
            :contact="contact"
            :lang="lang"
            :account="account"
            :booking-today="bookingToday"
            :help="help"
            :cookies="cookies"
            :preferencias="preferencias"
            @abrir="(id) => emit('abrir', id)"
            @capa="(fn) => emit('capa', fn)"
            @navegar="(it, e) => emit('navegar', it, e)"
        />
        <SelectorPlan
            v-else-if="vista === 'plans' && plans"
            :plans="plans"
            :from-today="plansFromToday"
            @elegir="(o) => emit('elegir', o)"
        />
        <PreferenciasCookies
            v-else-if="vista === 'cookies' && preferencias"
            :preferencias="preferencias"
        />
        <HojasIsla
            v-else-if="vista === 'resumen' || vista === 'cuenta' || vista === 'help'"
            :vista="vista"
            :quote="quote"
            :account="account"
            :help="help"
        />
    </div>
</template>
