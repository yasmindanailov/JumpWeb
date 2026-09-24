<script setup>
/** Saliendo al banco (`PjcSaliendo` del diseño): la bola de carga y qué pasa si la pasarela no salta sola. */
import { useTextos } from '../piezas/textos.js';
import CabeceraDesenlace from '../ui/CabeceraDesenlace.vue';

const { t } = useTextos();
</script>

<template>
    <CabeceraDesenlace
        kind="pending"
        :style="{ padding: '32px 0' }"
    >
        {{ t('compra.pagar.saliendo') }}
    </CabeceraDesenlace>
</template>
