<script setup>
/**
 * El descargo de responsabilidad, leído sin salir de la compra (`PjcDescargo` del diseño). El texto es el
 * documento vigente (`GET /legal/waiver`, `waiver-probatorio.md`): llega en la ranura, entero.
 */
import { useTextos } from '../piezas/textos.js';
import PasoCompra from './PasoCompra.vue';

const { t } = useTextos();
</script>

<template>
    <PasoCompra :titulo="t('compra.datos.descargo')">
        <slot />
    </PasoCompra>
</template>
