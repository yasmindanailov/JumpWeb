<script setup>
/**
 * **Las formas de pago con sus logotipos OFICIALES** (`#784`): una fila de chapas iguales, para el final del recibo de
 * «Pagar» y bajo «Reservar y pagar» de la calculadora. `marcas` llega hecha del servidor (`[{ id, nombre, src }]`:
 * solo las que la instalación acepta y tienen su fichero, `MarcasDePago`); sin ninguna, no pinta nada. `centro`, bajo
 * un botón a lo ancho. El nombre de cada marca es su texto alternativo, y la lista dice qué es.
 */
import { useTextos } from '../piezas/textos.js';
import { MARCA } from './marcas.js';

defineProps({
    marcas: { type: Array, default: () => [] },
    centro: { type: Boolean, default: false },
});
const { t } = useTextos();
</script>

<template>
    <ul
        v-if="marcas.length"
        :aria-label="t('pieza.formas_pago')"
        :style="[MARCA.fila, centro ? { justifyContent: 'center' } : null]"
    >
        <li
            v-for="m in marcas"
            :key="m.id"
            :style="MARCA.chapa"
        >
            <img
                :src="m.src"
                :alt="m.nombre"
                :style="MARCA.logo"
                decoding="async"
            >
        </li>
    </ul>
</template>
