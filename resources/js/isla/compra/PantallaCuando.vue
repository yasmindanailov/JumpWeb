<script setup>
/**
 * La pantalla 0 de la compra, «Cuándo y cuántos», para entradas (`PjcCuando` del diseño,
 * `paginas/compra/pantalla-0.jsx`). Solo cuando la compra abre sin hora («Reservar para hoy», el selector de plan
 * o «Añadir otra entrada»): es el widget de la página dentro de la isla, en el orden del brief —la zona si hay que
 * elegirla, día (hoy elegido), horas libres, cuánto tiempo, cantidad, calcetines, [Hora extra] y la otra zona—. En el
 * hueco de la [Hora extra], desde `#880`, todos los demás complementos que se venden al reservar (`ComplementosCompra`).
 * Al cambiar de día, mientras llegan las horas, su hueco exacto (`EsqueletoCarga`), para que nada salte.
 *
 * Pinta y avisa (`cambiar(campo, valor)`, `otra`, `quitarOtra`): qué días, horas y tiempos hay, y sus precios,
 * llegan hechos del motor. Con `modo: 'otra'` es «Añadir otra entrada»: el día y la hora ya están fijados.
 * `preguntas` son las del widget de la zona (`dia`, `hora`, `tiempo`, `cuantos`, `calcetines`): de la instalación; y
 * `datos`, lo que un pack pide al reservar (T6c·3, `#839`).
 */
import { useTextos } from '../piezas/textos.js';
import PasoCompra from './PasoCompra.vue';
import PreguntaCompra from './PreguntaCompra.vue';
import DatoFijo from './DatoFijo.vue';
import CantidadCompra from './CantidadCompra.vue';
import { PASO } from './estilos.js';
import IconoLucide from '../ui/IconoLucide.vue';
import EnlaceSistema from '../ui/EnlaceSistema.vue';
import DiasCompra from './DiasCompra.vue';
import SelectorHoras from '../ui/SelectorHoras.vue';
import TarjetasOpcion from '../ui/TarjetasOpcion.vue';
import EsqueletoCarga from '../ui/EsqueletoCarga.vue';
import AvisoDestacado from '../ui/AvisoDestacado.vue';
import DatosReserva, { ComplementosCompra } from './datos-reserva.js';

defineProps({
    titulo: { type: String, required: true },
    otraEntrada: { type: Boolean, default: false },
    fijo: { type: String, default: '' },
    zonas: { type: Array, default: null },
    zona: { type: String, default: null },
    preguntas: { type: Object, default: null },
    dias: { type: Array, default: () => [] },
    // «Más fechas» (`#830`): el calendario de meses para lo que la tira no enseña, o `null`.
    calendario: { type: Object, default: null },
    dia: { type: String, default: null },
    horas: { type: Array, default: () => [] },
    hora: { type: String, default: null },
    pistaHora: { type: String, default: '' },
    cargando: { type: Boolean, default: false },
    filas: { type: Array, default: () => [] },
    fila: { type: String, default: null },
    cuantos: { type: Object, default: null },
    calcetines: { type: Object, default: null },
    // Los demás complementos que se venden al reservar (`#880`), hechos por `complementos.js`.
    complementos: { type: Array, default: () => [] },
    otra: { type: Object, default: null },
    // Con el motor (T3e·2), de los DATOS: `cuantos.min`/`max`, `calcetines.max`, el umbral de «quedan» (el aviso de
    // «casi llena» del panel) y si hay otra zona que ofrecer. Sin ellos, los valores del diseño (el banco).
    umbral: { type: Number, default: 6 },
    otraZona: { type: Boolean, default: true },
    // El «no» del servidor al continuar (T3e·3): la línea no cabe, las reservas en pausa… Arriba, como el de «Tus datos».
    aviso: { type: String, default: '' },
});
const emit = defineEmits(['cambiar', 'otra', 'quitarOtra']);
const { t } = useTextos();
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
        <DatoFijo
            v-if="otraEntrada"
            icono="calendar-check"
        >{{ fijo }}</DatoFijo>

        <PreguntaCompra
            v-if="zonas"
            id="pjc-q-zona"
            :titulo="t('compra.cuando.zona')"
        >
            <TarjetasOpcion
                name="pjc-zona"
                columns="1"
                :model-value="zona"
                :items="zonas"
                @update:model-value="emit('cambiar', 'zona', $event)"
            />
        </PreguntaCompra>

        <template v-if="preguntas">
            <PreguntaCompra
                v-if="!otraEntrada"
                id="pjc-q-dia"
                :titulo="preguntas.dia"
            >
                <DiasCompra
                    :label="preguntas.dia"
                    :days="dias"
                    :calendario="calendario"
                    :model-value="dia"
                    @update:model-value="emit('cambiar', 'dia', $event)"
                />
            </PreguntaCompra>
            <PreguntaCompra
                v-if="!otraEntrada"
                id="pjc-q-hora"
                :titulo="preguntas.hora"
                :pista="pistaHora"
            >
                <div
                    v-if="cargando"
                    role="status"
                    :aria-label="t('compra.cuando.buscando_horas')"
                    :style="{ display: 'grid', gridTemplateColumns: 'repeat(auto-fill, minmax(76px, 1fr))', gap: '8px' }"
                >
                    <EsqueletoCarga
                        v-for="i in Math.min(8, horas.length || 8)"
                        :key="i"
                        kind="block"
                        height="52px"
                        radius="var(--r-md)"
                    />
                </div>
                <SelectorHoras
                    v-else
                    size="sm"
                    :slots="horas"
                    :model-value="hora"
                    :dia="dia"
                    counts="low"
                    :low-threshold="umbral"
                    @update:model-value="emit('cambiar', 'hora', $event)"
                />
            </PreguntaCompra>
            <PreguntaCompra
                id="pjc-q-tiempo"
                :titulo="preguntas.tiempo"
            >
                <TarjetasOpcion
                    name="pjc-tiempo"
                    columns="1"
                    :model-value="fila"
                    :items="filas"
                    @update:model-value="emit('cambiar', 'fila', $event)"
                />
            </PreguntaCompra>
            <PreguntaCompra
                id="pjc-q-cuantos"
                :titulo="preguntas.cuantos"
            >
                <CantidadCompra
                    :model-value="cuantos.n"
                    :min="cuantos.min ?? 1"
                    :max="cuantos.max ?? 20"
                    :uno="cuantos.uno"
                    :varios="cuantos.varios"
                    @update:model-value="emit('cambiar', 'n', $event)"
                />
            </PreguntaCompra>
            <PreguntaCompra
                v-if="!otraEntrada && calcetines"
                id="pjc-q-calcetines"
                :titulo="preguntas.calcetines"
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
            <!-- [Hora extra]: el diseño deja su hueco rayado tras los calcetines; aquí van TODOS los demás complementos que
                 se venden al reservar (`#880`), la hora extra entre ellos. -->
            <ComplementosCompra
                v-if="!otraEntrada && complementos.length"
                :items="complementos"
                @cambiar="(id, n) => emit('cambiar', 'extra', { id, n })"
            />
            <!-- Lo que un PACK pide al reservar (`#839`: el centro y su responsable de una excursión), con las etiquetas del
                 panel: `preguntas.datos` trae su título y sus campos. El diseño no dibuja este paso: es el campo del sistema. -->
            <PreguntaCompra
                v-if="!otraEntrada && preguntas.datos"
                id="pjc-q-datos"
                :titulo="preguntas.datos.titulo"
            >
                <DatosReserva
                    :datos="preguntas.datos.campos"
                    @cambiar="emit('cambiar', 'evento', $event)"
                />
            </PreguntaCompra>
            <template v-if="!otraEntrada">
                <section
                    v-if="otra"
                    :style="{ display: 'grid', gap: '12px', padding: '14px', borderRadius: 'var(--r-md)', border: '1px solid var(--border-subtle)', background: 'var(--surface-card)' }"
                >
                    <div :style="{ display: 'flex', alignItems: 'flex-start', justifyContent: 'space-between', gap: '12px' }">
                        <div :style="{ display: 'grid', gap: '2px' }">
                            <h2 :style="PASO.pregunta">{{ otra.titulo }}</h2>
                            <p :style="PASO.pista">{{ otra.precio }}</p>
                        </div>
                        <EnlaceSistema @click="emit('quitarOtra')">{{ t('compra.cuando.quitar') }}</EnlaceSistema>
                    </div>
                    <CantidadCompra
                        :model-value="otra.n"
                        :min="1"
                        :max="20"
                        :uno="otra.uno"
                        :varios="otra.varios"
                        @update:model-value="emit('cambiar', 'otraN', $event)"
                    />
                </section>
                <EnlaceSistema
                    v-else-if="otraZona"
                    :style="{ justifySelf: 'start' }"
                    @click="emit('otra')"
                >
                    <template #icono><IconoLucide
                        name="plus"
                        :size="18"
                    /></template>{{ t('compra.cuando.otra_zona') }}
                </EnlaceSistema>
            </template>
        </template>
    </PasoCompra>
</template>
