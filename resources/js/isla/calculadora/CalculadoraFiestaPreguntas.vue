<script setup>
/**
 * **Las cinco preguntas de la calculadora de la fiesta** (`Pieza6` de `paginas/piezas-5-6.jsx` del diseño; T6b·3 de
 * `specs/isla-y-landing-nueva.md` §4.18): cuántos años cumple (que elige el pack, con su eco), cuántos niños, qué día, a
 * qué hora y qué menú. PINTA: todo llega hecho en `v` (`vistaFiesta.js`; de dinero, ni una cuenta, `PAY-12`) y avisa de
 * cada cambio con `cambiar(campo, valor)`.
 */
import CalendarioMes from '../ui/CalendarioMes.vue';
import ContadorCantidad from '../ui/ContadorCantidad.vue';
import EtiquetaSistema from '../ui/EtiquetaSistema.vue';
import IconoLucide from '../ui/IconoLucide.vue';
import SelectorHoras from '../ui/SelectorHoras.vue';
import TarjetasOpcion from '../ui/TarjetasOpcion.vue';
import PreguntaCalculadora from './PreguntaCalculadora.vue';
import { ECO, PISTA } from './estilos.js';

defineProps({ v: { type: Object, required: true } });
const emit = defineEmits(['cambiar']);
const cambiar = (campo, valor) => emit('cambiar', campo, valor);
</script>

<template>
    <PreguntaCalculadora id="p6-edad" :titulo="v.edad.titulo">
        <div :style="{ display: 'flex', flexWrap: 'wrap', gap: '8px' }">
            <EtiquetaSistema v-for="a in v.edad.edades" :key="a.time" :selected="v.edad.valor === a.time" @click="cambiar('edad', a.time)">{{ a.time }}</EtiquetaSistema>
        </div>
        <span v-if="v.edad.pack" :style="ECO"><IconoLucide name="check" :size="16" /><span>{{ v.edad.pack }}</span></span>
    </PreguntaCalculadora>
    <PreguntaCalculadora id="p6-ninos" :titulo="v.ninos.titulo">
        <ContadorCantidad :label="v.ninos.label" :sublabel="v.ninos.sub" :model-value="v.ninos.n" :min="v.ninos.min" :max="v.ninos.max" :price="v.ninos.precio" @update:model-value="cambiar('n', $event)" />
    </PreguntaCalculadora>
    <PreguntaCalculadora id="p6-dia" :titulo="v.dia.titulo">
        <CalendarioMes :month="v.dia.mes" :months="v.dia.meses" :min-month="v.dia.desde" :max-month="v.dia.hasta" :today="v.dia.hoy" :days="v.dia.dias" :model-value="v.dia.valor" :locale="v.locale" @update:model-value="cambiar('dia', $event)" />
    </PreguntaCalculadora>
    <PreguntaCalculadora id="p6-hora" :titulo="v.hora.titulo">
        <SelectorHoras v-if="v.hora.horas" :model-value="v.hora.valor" :slots="v.hora.horas" :dia="v.dia.valor" counts="low" :low-threshold="1" columns="repeat(auto-fill, minmax(96px, 1fr))" @update:model-value="cambiar('hora', $event)" />
        <p v-else :style="PISTA">{{ v.hora.espera }}</p>
    </PreguntaCalculadora>
    <PreguntaCalculadora id="p6-menu" :titulo="v.menu.titulo" ultima>
        <TarjetasOpcion name="p6-menu" columns="2" :model-value="v.menu.valor" :items="v.menu.items" @update:model-value="cambiar('menu', $event)" />
    </PreguntaCalculadora>
</template>
