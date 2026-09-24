<script setup>
/**
 * **LA COMPRA EN LA ISLA** (T3e de `specs/isla-y-landing-nueva.md` §4.10, `DECISIONES #682` y `#692`): con la isla
 * como carcasa de la instalación, ocupa el lugar de la sección de compra del cajón —montada toda la página, con el
 * mismo puente hacia la raíz— y se enseña en la isla, teletransportada a `<body>`, cuando el controlador abre su
 * superficie. Pinta (`CE-6`): lo que decide está en `compra/useSeccionCompra.js`.
 *
 * La pantalla 0 viaja aquí; los pasos de después («Tus datos», «Pagar», el banco y los desenlaces), en su propio
 * trozo, que se pide al montarse: quien abre la compra no los espera, y al pulsar «Continuar» ya están.
 *
 * ⚠️ `bloquea-pagina` apagado: la página la deja quieta el controlador, dueño único del cerrojo de scroll.
 */
import { defineAsyncComponent, onMounted, provide } from 'vue';
import './isla.css';
import IslaFlotante from './IslaFlotante.vue';
import PantallaCuando from './compra/PantallaCuando.vue';
import { PROPS_MOTOR } from '../sidebar/props.js';
import { COMPRA, useSeccionCompra } from './compra/useSeccionCompra.js';

const diferidos = () => import('./compra/pasos-diferidos.js');
const PasosCompra = defineAsyncComponent(() => diferidos().then((m) => m.PasosCompra));
const JuntoCompra = defineAsyncComponent(() => diferidos().then((m) => m.JuntoCompra));

// Las MISMAS props que la raíz le pasa con `v-bind="props"`: ninguna acaba de atributo en el DOM.
defineOptions({ inheritAttrs: false });
const props = defineProps(PROPS_MOTOR);
const compra = useSeccionCompra(props);
const { abierta, textos, ck, paso, cuando, cambiar, refreshBookingStatus, refreshIdentity, openProduct, applyIntent } = compra;

provide(COMPRA, compra);
onMounted(diferidos);
defineExpose({ refreshBookingStatus, refreshIdentity, openProduct, applyIntent });
</script>

<template>
    <Teleport to="body">
        <IslaFlotante
            v-if="abierta"
            :textos="textos"
            :checkout="ck"
            :bloquea-pagina="false"
        >
            <PantallaCuando
                v-if="paso === 'cuando'"
                v-bind="cuando"
                @cambiar="cambiar"
            />
            <PasosCompra v-else />
            <template #junto>
                <JuntoCompra />
            </template>
        </IslaFlotante>
    </Teleport>
</template>
