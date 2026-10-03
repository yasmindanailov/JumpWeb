<script setup>
/**
 * **Los complementos que se venden al reservar**, en la pantalla 0 de la compra (M1 de `specs/isla-y-landing-nueva.md`
 * §4.29, `DECISIONES #880`: «los que sean»). Cada fila, con la forma que dicen sus datos (`complementos.js`): un sí o un no
 * con la fila de la mejora del diseño (`UpgradeRow`, `ui/FilaMejora.vue`: la hora extra), lo que se suma con su − / + y lo
 * incluido, a la vista. Apagada, dice por qué; nunca viene marcada. Y los GRUPOS de elección que no tienen su pregunta
 * («esto o aquello», `#881`), con las tarjetas de opción del sistema. Pinta y avisa (`cambiar(id, n)`, `elegir(grupo,
 * producto)`): qué se ofrece y a qué precio lo dice el servidor.
 * `ambito` y `nivel` (`#882`): la lista de la OTRA ZONA, dentro de su tarjeta, con sus ids y sus grupos aparte —un mismo
 * complemento en las dos líneas no puede repetir `id` ni compartir grupo de radios— y sus títulos un nivel por debajo.
 */
import { useTextos } from '../piezas/textos.js';
import PreguntaCompra from './PreguntaCompra.vue';
import CantidadCompra from './CantidadCompra.vue';
import DatoFijo from './DatoFijo.vue';
import FilaMejora from '../ui/FilaMejora.vue';
import TarjetasOpcion from '../ui/TarjetasOpcion.vue';

defineProps({
    items: { type: Array, required: true },
    ambito: { type: String, default: '' },
    nivel: { type: Number, default: 2 },
});
const emit = defineEmits(['cambiar', 'elegir']);
const { t } = useTextos();
</script>

<template>
    <template
        v-for="c in items"
        :key="c.id"
    >
        <FilaMejora
            v-if="c.forma === 'si-no'"
            :id="`pjc-q-${ambito}extra-${c.id}`"
            :titulo="c.titulo"
            :descripcion="c.descripcion"
            :precio="c.precio"
            :marcada="c.marcada"
            :disponible="c.disponible"
            :nota-no="c.porQue"
            @cambiar="emit('cambiar', c.id, $event ? 1 : 0)"
        />
        <!-- Lo que se suma: apagado, su − / + se queda quieto (tope 0) y la pista dice por qué. -->
        <PreguntaCompra
            v-else-if="c.forma === 'cantidad'"
            :id="`pjc-q-${ambito}extra-${c.id}`"
            :nivel="nivel"
            :titulo="c.titulo"
            :pista="c.disponible ? c.precio : c.porQue"
        >
            <CantidadCompra
                :model-value="c.n"
                :min="0"
                :max="c.disponible ? c.max : 0"
                :uno="t('compra.cuando.unidad')"
                :varios="t('compra.cuando.unidades')"
                @update:model-value="emit('cambiar', c.id, $event)"
            />
        </PreguntaCompra>
        <!-- Un grupo de elección («esto o aquello», `#881`): una pregunta con sus opciones; la elegida, la del servidor de serie. -->
        <PreguntaCompra
            v-else-if="c.forma === 'grupo'"
            :id="`pjc-q-${ambito}${c.id}`"
            :nivel="nivel"
            :titulo="c.titulo"
        >
            <TarjetasOpcion
                :name="`pjc-${ambito}${c.id}`"
                columns="1"
                :model-value="c.valor"
                :items="c.items"
                @update:model-value="emit('elegir', c.grupo, $event)"
            />
        </PreguntaCompra>
        <DatoFijo
            v-else
            :id="`pjc-q-${ambito}extra-${c.id}`"
            icono="check"
        >{{ c.precio ? `${c.titulo} · ${c.precio}` : c.titulo }}</DatoFijo>
    </template>
</template>
