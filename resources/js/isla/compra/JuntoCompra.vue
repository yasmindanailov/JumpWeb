<script setup>
/**
 * Lo que va JUNTO a la acción de la isla en los pasos de después de la pantalla 0 (T3e·3), en el mismo trozo que
 * ellos (`pasos-diferidos.js`): las condiciones y la pasarela al pagar, los enlaces del pago no completado y «Hacer
 * otra reserva» tras «Listo». Pinta lo que le da `useSeccionCompra.js` por `inject`.
 */
import { inject } from 'vue';
import { COMPRA } from './useSeccionCompra.js';
import JuntoPagar from './JuntoPagar.vue';
import JuntoFallido from './JuntoFallido.vue';
import BotonSistema from '../ui/BotonSistema.vue';

const { paso, esperando, pago, otraReserva, rotuloOtra, urls } = inject(COMPRA);
</script>

<template>
    <JuntoPagar
        v-if="paso === 'pagar'"
        :condiciones-href="urls.terms"
        @condiciones="pago.condiciones"
    />
    <JuntoFallido
        v-else-if="paso === 'fallido'"
        :tarjeta="false"
        @escribir="pago.escribir"
    />
    <BotonSistema
        v-else-if="paso === 'listo' && !esperando"
        variant="quiet"
        full
        @click="otraReserva"
    >
        {{ rotuloOtra }}
    </BotonSistema>
</template>
