<script setup>
/**
 * El panel abierto de la isla: el menú, el selector de plan, el resumen, la cuenta o la ayuda. Es un diálogo
 * con su título (salvo que el título suba a la fila) y su propio scroll. La isla le pone la `key` de la vista:
 * cambiar de panel lo vuelve a montar, y así entra con su movimiento: desde el 26-09 (Z3), sin fundido de bloque, el
 * contenido sube desde la acción fila a fila (`entradaPorFilas`); y al cerrar se va en 100ms (`elemento()`, la isla).
 */
import { defineAsyncComponent, onMounted, ref } from 'vue';
import { entradaPorFilas } from '../movimiento.js';
import CabeceraPanel from './CabeceraPanel.vue';
import MenuIsla from './MenuIsla.vue';
import SelectorPlan from './SelectorPlan.vue';
import HojasIsla from './HojasIsla.vue';
import HojaRazon from './HojaRazon.vue';

// «Tus cookies» se abre poco: diferida, ni la isla en reposo ni la compra pagan sus interruptores (medido, +4,2 y
// +5,9 KiB estáticas).
const PreferenciasCookies = defineAsyncComponent(() => import('./PreferenciasCookies.vue'));

const props = defineProps({
    vista: { type: String, required: true },
    titulo: { type: String, default: null },
    tituloEnFila: { type: Boolean, default: false },
    top: { type: Boolean, default: false },
    menuItems: { type: Array, default: () => [] },
    homeLabel: { type: String, default: null },
    contact: { type: Object, default: () => ({}) },
    lang: { type: String, default: '' },
    onLanguage: { type: Function, default: null },
    account: { type: Object, default: () => ({ state: 'guest' }) },
    help: { type: Object, default: null },
    cookies: { type: Object, default: null },
    preferencias: { type: Object, default: null },
    plans: { type: Object, default: null },
    plansFromToday: { type: Boolean, default: false },
    quote: { type: Object, default: null },
    /** La razón abierta (la sexta vista, Z6b): la que el banner decía al tocarlo. */
    razon: { type: Object, default: null },
});
const emit = defineEmits(['abrir', 'navegar', 'elegir']);

const raiz = ref(null);
defineExpose({ enfocar: () => raiz.value && raiz.value.focus({ preventScroll: true }), elemento: () => raiz.value });
onMounted(() => entradaPorFilas(raiz.value, props.top));
</script>

<template>
    <div
        ref="raiz"
        tabindex="-1"
        role="dialog"
        :aria-label="titulo || (vista === 'razon' && razon ? razon.title || razon.text : undefined)"
        :style="{
            outline: 'none', boxShadow: 'none', padding: top ? '10px 2px 2px' : '2px 2px 10px',
            maxHeight: 'min(62vh, 520px)', overflowY: 'auto', overscrollBehavior: 'contain',
            scrollbarWidth: 'thin', scrollbarColor: 'rgba(255,255,255,0.28) transparent',
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
            :on-language="onLanguage"
            :help="help"
            :cookies="cookies"
            :preferencias="preferencias"
            @abrir="(id) => emit('abrir', id)"
            @navegar="(it, e) => emit('navegar', it, e)"
        />
        <SelectorPlan
            v-else-if="vista === 'plans' && plans"
            :plans="plans"
            :from-today="plansFromToday"
            @elegir="(o, e) => emit('elegir', o, e)"
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
        <HojaRazon
            v-else-if="vista === 'razon' && razon"
            :bn="razon"
        />
    </div>
</template>
