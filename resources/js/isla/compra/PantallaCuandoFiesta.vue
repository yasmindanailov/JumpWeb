<script setup>
/**
 * La pantalla 0 de la compra para una fiesta (`PjcCuandoCumple` del diseño): las cinco preguntas del widget de
 * cumpleaños, en su orden —la edad (que decide el pack), los niños, el día, la hora y el menú—. Sin día elegido de
 * salida: una fiesta no se reserva para hoy. Las preguntas, el pack, los mínimos y los menús son de la instalación
 * y llegan hechos; `horas` es `null` hasta que hay día.
 */
import PasoCompra from './PasoCompra.vue';
import PreguntaCompra from './PreguntaCompra.vue';
import DatoFijo from './DatoFijo.vue';
import CantidadCompra from './CantidadCompra.vue';
import TiraDias from '../ui/TiraDias.vue';
import SelectorHoras from '../ui/SelectorHoras.vue';
import TarjetasOpcion from '../ui/TarjetasOpcion.vue';
import AvisoDestacado from '../ui/AvisoDestacado.vue';
import IconoLucide from '../ui/IconoLucide.vue';

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
    dia: { type: String, default: null },
    horas: { type: Array, default: null },
    hora: { type: String, default: null },
    menus: { type: Array, required: true },
    menu: { type: String, default: null },
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
            <TiraDias
                :label="preguntas[2]"
                :days="dias"
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
                counts="low"
                :low-threshold="1"
                @update:model-value="emit('cambiar', 'hora', $event)"
            />
        </PreguntaCompra>
        <PreguntaCompra
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
    </PasoCompra>
</template>
