<script setup>
/**
 * La hora perdida (`PjcPerdida` del diseño): no se ha cobrado nada, y estas horas cercanas del mismo día sí están
 * libres. En el producto la hora se guarda AL PAGAR (`#688`), así que sale cuando «Pagar» encuentra la hora llena (T3e·6).
 * Y al CONTINUAR de la pantalla 0 (`alEntrar`, `#822`, §4.16): se sabe antes de teclear nada, como pide el zip; entonces
 * no se ha pedido ni cobrado nada, y el texto es el de «Tus datos» del diseño («Estas sí:»).
 * Pulsar «Elegir esta hora» sin elegirla marca en rojo, encima de las cercanas, que falta (M2, `#881`: `falta.js`).
 * ▶ Con varias líneas (K4 de `otra-zona.md`): las cercanas son las de TODAS y la hora nueva es de toda la reserva (`todos`);
 * si la que no cupo es una añadida, el título nombra su zona (`zona`); y si fue al añadirla («Añadir otra entrada»,
 * `desdeOtra`), aún no se ha pedido nada, como al continuar.
 */
import { computed, inject } from 'vue';
import { useTextos } from '../piezas/textos.js';
import { PASO } from './estilos.js';
import { FALTA, PERDIDA } from './falta.js';
import SelectorHoras from '../ui/SelectorHoras.vue';
import IconoLucide from '../ui/IconoLucide.vue';

const props = defineProps({
    cercanas: { type: Array, default: () => [] },
    horaNueva: { type: String, default: null },
    alEntrar: { type: Boolean, default: false },
    desdeOtra: { type: Boolean, default: false },
    zona: { type: String, default: '' },
    todos: { type: Boolean, default: false },
});
const emit = defineEmits(['hora']);
const { t, tp } = useTextos();
const marca = inject(FALTA, null);
const falta = computed(() => (marca?.value?.id === PERDIDA ? marca.value.texto : ''));
const titular = computed(() => (props.zona ? tp('compra.perdida.titular_zona', { zona: props.zona }) : t('compra.perdida.titular')));
// Las claves, enteras (`IslaTextosTest` busca cada una): sin nada pedido aún, el corto («Estas sí:»).
const texto = computed(() => {
    const corto = props.alEntrar || props.desdeOtra;

    if (props.todos) return t(corto ? 'compra.perdida.texto_todos_al_entrar' : 'compra.perdida.texto_todos');

    return t(corto ? 'compra.perdida.texto_al_entrar' : 'compra.perdida.texto');
});
</script>

<template>
    <div
        role="alert"
        :style="PASO.paso"
    >
        <h1
            tabindex="-1"
            :style="PASO.titulo"
        >{{ titular }}</h1>
        <p :style="PASO.cuerpo">{{ texto }}</p>
        <div
            :id="PERDIDA"
            :style="{ display: 'grid', gap: '12px' }"
        >
            <p
                v-if="falta"
                role="alert"
                :style="PASO.falta"
            ><IconoLucide
                name="circle-alert"
                :size="16"
            />{{ falta }}</p>
            <SelectorHoras
                size="sm"
                :slots="cercanas"
                :model-value="horaNueva"
                counts="low"
                :low-threshold="6"
                @update:model-value="emit('hora', $event)"
            />
        </div>
    </div>
</template>
