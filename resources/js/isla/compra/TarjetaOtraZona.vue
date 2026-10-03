<script setup>
/**
 * **La tarjeta de una LÍNEA AÑADIDA** en la pantalla 0 de las entradas —la otra zona, y desde la K3 también lo añadido en
 * «Pagar»— (K2·b de `specs/otra-zona.md` §4.7, `DECISIONES #882`, `[DECIDIDO owner]`): una entrada completa en pequeño, con
 * las piezas de la pantalla —para quién es (sus edades), «¿Cuánto tiempo?» con los de su zona y su precio de ese día (de
 * partida, el parecido al del pedido; el que ya está en la reserva, apagado), «¿Cuántos?», sus complementos (la hora extra de
 * su tiempo; nunca marcados) y «Quitar»—. Sus preguntas, un nivel por debajo de su nombre (h3): «¿Cuánto tiempo?» ya está
 * arriba, la del pedido. Su `id` es a donde lleva «lo que falta» (M2) si su zona no se vende ese día, y entonces su pista lo
 * dice en rojo; si solo su tiempo, la marca va a su «¿Cuánto tiempo?». Todo con su ÍNDICE (`otra.indice`): una por línea.
 *
 * Pinta y avisa (`cambiar(campo, { i, … })`): qué hay y a qué precio llega hecho de `otra-zona.js::otraDeLaPantalla`.
 */
import { computed } from 'vue';
import { useTextos } from '../piezas/textos.js';
import PreguntaCompra from './PreguntaCompra.vue';
import CantidadCompra from './CantidadCompra.vue';
import { PASO } from './estilos.js';
import EnlaceSistema from '../ui/EnlaceSistema.vue';
import TarjetasOpcion from '../ui/TarjetasOpcion.vue';
import { ComplementosCompra } from './datos-reserva.js';

const props = defineProps({
    otra: { type: Object, required: true },
    // Los títulos de las preguntas de la pantalla (`tiempo`, `cuantos`): los mismos que los de la línea del pedido.
    preguntas: { type: Object, required: true },
});
const emit = defineEmits(['cambiar']);
const { t } = useTextos();
const i = computed(() => props.otra.indice ?? 0);
</script>

<template>
    <section
        :id="`pjc-q-otra-${i}`"
        :aria-labelledby="`pjc-otra-titulo-${i}`"
        :style="{ display: 'grid', gap: '16px', padding: '14px', borderRadius: 'var(--r-md)', border: '1px solid var(--border-subtle)', background: 'var(--surface-card)' }"
    >
        <div :style="{ display: 'flex', alignItems: 'flex-start', justifyContent: 'space-between', gap: '12px' }">
            <div :style="{ display: 'grid', gap: '2px' }">
                <h2
                    :id="`pjc-otra-titulo-${i}`"
                    :style="PASO.pregunta"
                >{{ otra.titulo }}</h2>
                <p
                    v-if="otra.pista"
                    :style="otra.noSeVende ? PASO.falta : PASO.pista"
                >{{ otra.pista }}</p>
            </div>
            <EnlaceSistema @click="emit('cambiar', 'quitarOtra', { i })">{{ t('compra.cuando.quitar') }}</EnlaceSistema>
        </div>
        <PreguntaCompra
            :id="`pjc-q-otra-tiempo-${i}`"
            :nivel="3"
            :titulo="preguntas.tiempo"
        >
            <TarjetasOpcion
                :name="`pjc-otra-tiempo-${i}`"
                columns="1"
                :model-value="otra.fila"
                :items="otra.filas"
                @update:model-value="emit('cambiar', 'otraFila', { i, valor: $event })"
            />
        </PreguntaCompra>
        <PreguntaCompra
            :id="`pjc-q-otra-cuantos-${i}`"
            :nivel="3"
            :titulo="preguntas.cuantos"
        >
            <CantidadCompra
                :model-value="otra.n"
                :min="1"
                :max="20"
                :uno="otra.uno"
                :varios="otra.varios"
                @update:model-value="emit('cambiar', 'otraN', { i, valor: $event })"
            />
        </PreguntaCompra>
        <ComplementosCompra
            v-if="otra.complementos.length"
            :ambito="`otra-${i}-`"
            :nivel="3"
            :items="otra.complementos"
            @cambiar="(id, n) => emit('cambiar', 'otraExtra', { i, id, n })"
            @elegir="(grupo, valor) => emit('cambiar', 'otraEleccion', { i, grupo, valor })"
        />
    </section>
</template>
