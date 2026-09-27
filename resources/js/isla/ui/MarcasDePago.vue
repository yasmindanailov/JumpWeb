<script setup>
/**
 * **Las formas de pago con sus logotipos OFICIALES** (`#784`, `#786`): una fila discreta —sin chapas, pequeña, a la misma
 * altura óptica— bajo el botón de «Pagar» de la isla y bajo «Reservar y pagar» de la calculadora. `marcas` llega hecha del
 * servidor (`[{ id, nombre, src, srcTinta }]`: solo las que la instalación acepta y tienen su fichero, `MarcasDePago`);
 * sin ninguna, no pinta nada. `tinta`: sobre un fondo oscuro (la isla), la versión oficial para él. `centro`, bajo un
 * botón a lo ancho. El nombre de cada marca es su texto alternativo, y la lista dice qué es.
 */
import { useTextos } from '../piezas/textos.js';
import { MARCA } from './marcas.js';

defineProps({
    marcas: { type: Array, default: () => [] },
    tinta: { type: Boolean, default: false },
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
        >
            <img
                :src="tinta ? m.srcTinta || m.src : m.src"
                :alt="m.nombre"
                :style="MARCA.logo(m.id)"
                decoding="async"
            >
        </li>
    </ul>
</template>
