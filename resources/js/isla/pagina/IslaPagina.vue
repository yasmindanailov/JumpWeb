<script setup>
/**
 * **LA ISLA EN REPOSO DE UNA PÁGINA DECLARADA** (T4e de `specs/isla-y-landing-nueva.md` §4.12): la misma `IslaFlotante`
 * de la compra, con lo que la página le da. PINTA (`CE-6`): lo que decide está en `pagina.js` y lo que cambia, en
 * `usePaginaIsla.js`. Con la compra abierta se aparta: la isla de la compra (`SeccionCompra.vue`) ocupa su sitio.
 */
import IslaFlotante from '../IslaFlotante.vue';
import { usePaginaIsla } from './usePaginaIsla.js';

const props = defineProps({
    config: { type: Object, required: true },
    textos: { type: Object, default: () => ({}) },
});
const { props: isla, visible } = usePaginaIsla({ config: props.config, textos: props.textos });
</script>

<template>
    <IslaFlotante
        v-if="visible"
        v-bind="isla"
        :textos="textos"
    />
</template>
