<script setup>
/**
 * La pantalla 0 de la compra para una fiesta (`PjcCuandoCumple` del diseño): las cinco preguntas del widget de
 * cumpleaños, en su orden —la edad (que decide el pack), los niños, el día, la hora y el menú— y, desde `#880`, lo que se
 * vende al reservar el pack: los calcetines y la lista de complementos (la hora extra). Sin día elegido de salida: una
 * fiesta no se reserva para hoy. Las preguntas, el pack, los mínimos, los menús y los complementos son de la instalación
 * y llegan hechos; `horas` es `null` hasta que hay día.
 */
import PasoCompra from './PasoCompra.vue';
import PreguntaCompra from './PreguntaCompra.vue';
import DatoFijo from './DatoFijo.vue';
import CantidadCompra from './CantidadCompra.vue';
import DiasCompra from './DiasCompra.vue';
import { ComplementosCompra } from './datos-reserva.js';
import SelectorHoras from '../ui/SelectorHoras.vue';
import TarjetasOpcion from '../ui/TarjetasOpcion.vue';
import AvisoDestacado from '../ui/AvisoDestacado.vue';
import IconoLucide from '../ui/IconoLucide.vue';
import { PASO } from './estilos.js';

defineProps({
    // El «no» del servidor al continuar (T3e·5): la edad fuera de tramo, las reservas en pausa… Arriba, como en las entradas.
    aviso: { type: String, default: '' },
    titulo: { type: String, required: true },
    preguntas: { type: Array, required: true },
    edades: { type: Array, required: true },
    edad: { type: String, default: null },
    pack: { type: String, default: '' },
    ninos: { type: Object, required: true },
    dias: { type: Array, required: true },
    // «Más fechas» (`#830`): una fiesta se reserva con semanas.
    calendario: { type: Object, default: null },
    dia: { type: String, default: null },
    horas: { type: Array, default: null },
    hora: { type: String, default: null },
    menus: { type: Array, required: true },
    menu: { type: String, default: null },
    // `#880`: los calcetines del pack (su pregunta, la de las entradas) y los demás complementos que se venden al reservar.
    calcetines: { type: Object, default: null },
    complementos: { type: Array, default: () => [] },
    // `#876`·7: «Los invitados y los detalles de la fiesta, después…», si el pack lleva la lista; si no, nada.
    despues: { type: String, default: '' },
});
const emit = defineEmits(['cambiar']);
</script>

<template>
    <PasoCompra :titulo="titulo">
        <AvisoDestacado
            v-if="aviso"
            tone="danger"
            size="sm"
            role="alert"
            :title="aviso"
        >
            <template #icono><IconoLucide
                name="circle-alert"
                :size="18"
            /></template>
        </AvisoDestacado>
        <PreguntaCompra
            id="pjc-q-edad"
            :titulo="preguntas[0]"
        >
            <SelectorHoras
                size="sm"
                columns="repeat(5, minmax(0, 1fr))"
                counts="low"
                :slots="edades"
                :model-value="edad"
                @update:model-value="emit('cambiar', 'edad', $event)"
            />
            <DatoFijo
                v-if="pack"
                icono="party-popper"
            >{{ pack }}</DatoFijo>
        </PreguntaCompra>
        <PreguntaCompra
            id="pjc-q-ninos"
            :titulo="preguntas[1]"
            :pista="ninos.pista"
        >
            <CantidadCompra
                :model-value="ninos.n"
                :min="ninos.min"
                :max="ninos.max"
                :uno="ninos.uno"
                :varios="ninos.varios"
                @update:model-value="emit('cambiar', 'n', $event)"
            />
        </PreguntaCompra>
        <PreguntaCompra
            id="pjc-q-dia"
            :titulo="preguntas[2]"
        >
            <DiasCompra
                :label="preguntas[2]"
                :days="dias"
                :calendario="calendario"
                :model-value="dia"
                @update:model-value="emit('cambiar', 'dia', $event)"
            />
        </PreguntaCompra>
        <PreguntaCompra
            v-if="horas"
            id="pjc-q-hora"
            :titulo="preguntas[3]"
        >
            <SelectorHoras
                size="sm"
                :slots="horas"
                :model-value="hora"
                :dia="dia"
                counts="low"
                :low-threshold="1"
                @update:model-value="emit('cambiar', 'hora', $event)"
            />
        </PreguntaCompra>
        <!-- Solo si el servidor da menús al reservar: el panel puede dejarlos para la lista de invitados (`#807`/`#808`). -->
        <PreguntaCompra
            v-if="menus.length"
            id="pjc-q-menu"
            :titulo="preguntas[4]"
        >
            <TarjetasOpcion
                name="pjc-menu"
                columns="1"
                :model-value="menu"
                :items="menus"
                @update:model-value="emit('cambiar', 'menu', $event)"
            />
        </PreguntaCompra>
        <!-- `#880`: lo que se vende al reservar el pack, «los que sean» —los calcetines, con la pregunta de las entradas, y
             los demás (la hora extra)—; lo de la lista de invitados, en ella. -->
        <PreguntaCompra
            v-if="calcetines"
            id="pjc-q-calcetines"
            :titulo="calcetines.titulo"
            :pista="calcetines.pista"
        >
            <CantidadCompra
                :model-value="calcetines.n"
                :min="0"
                :max="calcetines.max ?? 40"
                :uno="calcetines.uno"
                :varios="calcetines.varios"
                @update:model-value="emit('cambiar', 'cal', $event)"
            />
        </PreguntaCompra>
        <ComplementosCompra
            v-if="complementos.length"
            :items="complementos"
            @cambiar="(id, n) => emit('cambiar', 'extra', { id, n })"
        />
        <p
            v-if="despues"
            :style="PASO.pista"
        >{{ despues }}</p>
    </PasoCompra>
</template>
