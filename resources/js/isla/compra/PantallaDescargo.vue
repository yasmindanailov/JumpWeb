<script setup>
/**
 * El descargo de responsabilidad, leído sin salir de la compra (`PjcDescargo` del diseño). El texto es el
 * documento vigente (`GET /legal/waiver`, `waiver-probatorio.md`), entero: por `secciones` (su `{ h, p }`, ya
 * interpolado por el servidor) o en la ranura.
 */
import { useTextos } from '../piezas/textos.js';
import { PASO } from './estilos.js';
import PasoCompra from './PasoCompra.vue';

defineProps({
    secciones: { type: Array, default: null },
});
const { t } = useTextos();
</script>

<template>
    <PasoCompra :titulo="t('compra.datos.descargo')">
        <template v-if="secciones">
            <p
                v-for="(s, i) in secciones"
                :key="i"
                :style="PASO.cuerpo"
            ><strong v-if="s.h">{{ s.h }} </strong>{{ s.p }}</p>
        </template>
        <slot v-else />
    </PasoCompra>
</template>
