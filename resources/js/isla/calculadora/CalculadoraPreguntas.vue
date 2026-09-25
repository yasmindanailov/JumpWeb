<script setup>
/**
 * **Las cinco preguntas de la calculadora de la página** (`PrecioEntradas` de `paginas/entradas/pieza-3.jsx` con su
 * widget; T4d de `specs/isla-y-landing-nueva.md` §4.12): cuántos, cuánto tiempo, qué día, a qué hora y los calcetines,
 * cada una con su eco de lo elegido y su pista. PINTA: todo llega hecho en `v` (`vista.js` de la calculadora; de
 * dinero, ni una cuenta, `PAY-12`) y avisa de cada cambio con `cambiar(campo, valor)`.
 * Sin calcetines que vender, la pregunta no sale y la de la hora cierra la lista.
 */
import CalendarioMes from '../ui/CalendarioMes.vue';
import ContadorCantidad from '../ui/ContadorCantidad.vue';
import EtiquetaSistema from '../ui/EtiquetaSistema.vue';
import IconoLucide from '../ui/IconoLucide.vue';
import SelectorHoras from '../ui/SelectorHoras.vue';
import TarjetasOpcion from '../ui/TarjetasOpcion.vue';
import PreguntaCalculadora from './PreguntaCalculadora.vue';
import { CIFRA, ECO, PISTA } from './estilos.js';

defineProps({ v: { type: Object, required: true } });
const emit = defineEmits(['cambiar']);
const cambiar = (campo, valor) => emit('cambiar', campo, valor);
</script>

<template>
    <PreguntaCalculadora id="p3-cuantos" :titulo="v.cuantos.titulo">
        <ContadorCantidad :label="v.cuantos.label" :sublabel="v.cuantos.sub" :model-value="v.cuantos.n" :min="v.cuantos.min" :max="v.cuantos.max" :price="v.cuantos.precio" @update:model-value="cambiar('n', $event)" />
        <p :style="PISTA">{{ v.cuantos.nota }}</p>
    </PreguntaCalculadora>
    <PreguntaCalculadora :titulo="v.tiempo.titulo">
        <TarjetasOpcion :name="v.tiempo.name" :model-value="v.tiempo.fila" :columns="v.tiempo.columnas" :items="v.tiempo.items" @update:model-value="cambiar('fila', $event)" />
    </PreguntaCalculadora>
    <PreguntaCalculadora id="p3-dia" :titulo="v.dia.titulo">
        <CalendarioMes :month="v.dia.mes" :min-month="v.dia.desde" :max-month="v.dia.hasta" :today="v.dia.hoy" :days="v.dia.dias" :model-value="v.dia.valor" :locale="v.locale" @update:model-value="cambiar('dia', $event)" />
        <p v-if="v.dia.nota" :style="PISTA">{{ v.dia.nota }}</p>
        <span v-if="v.dia.eco" :style="ECO"><IconoLucide name="check" :size="16" /><span>{{ v.dia.eco.antes }}<span :style="CIFRA">{{ v.dia.eco.cifra }}</span>{{ v.dia.eco.despues }}</span></span>
    </PreguntaCalculadora>
    <PreguntaCalculadora id="p3-hora" :titulo="v.hora.titulo" :ultima="! v.calcetines">
        <SelectorHoras v-if="v.hora.horas" :model-value="v.hora.valor" :slots="v.hora.horas" counts="low" columns="repeat(auto-fill, minmax(96px, 1fr))" @update:model-value="cambiar('hora', $event)" />
        <p v-else :style="PISTA">{{ v.hora.espera }}</p>
        <span v-if="v.hora.eco" :style="ECO"><IconoLucide name="clock" :size="16" /><span :style="CIFRA">{{ v.hora.eco }}</span></span>
        <p :style="PISTA">{{ v.hora.regla }}</p>
    </PreguntaCalculadora>
    <PreguntaCalculadora v-if="v.calcetines" :titulo="v.calcetines.titulo" :sub="v.calcetines.sub" ultima>
        <ContadorCantidad :label="v.calcetines.label" :sublabel="v.calcetines.sublabel" :model-value="v.calcetines.n" :min="0" :max="v.calcetines.max" :price="v.calcetines.precio" @update:model-value="cambiar('cal', $event)" />
        <div><EtiquetaSistema :selected="v.calcetines.cadaUno.elegido" @click="cambiar('cal', v.calcetines.cadaUno.n)">{{ v.calcetines.cadaUno.texto }}</EtiquetaSistema></div>
        <p :style="PISTA">{{ v.calcetines.nota }}</p>
    </PreguntaCalculadora>
</template>
