<script setup>
/**
 * **LA COMPRA EN LA ISLA** (T3e de `specs/isla-y-landing-nueva.md` §4.10, `DECISIONES #682` y `#692`): con la isla
 * como carcasa de la instalación, ocupa el lugar de la sección de compra del cajón —montada toda la página, con el
 * mismo puente hacia la raíz— y se enseña en la isla, teletransportada a `<body>`, cuando el controlador abre su
 * superficie. Pinta (`CE-6`): lo que decide está en `compra/useSeccionCompra.js` y `compra/vista.js`.
 *
 * ⚠️ `bloquea-pagina` apagado: la página la deja quieta el controlador, dueño único del cerrojo de scroll.
 */
import './isla.css';
import IslaFlotante from './IslaFlotante.vue';
import PantallaCuando from './compra/PantallaCuando.vue';
import { PROPS_MOTOR } from '../sidebar/props.js';
import { useSeccionCompra } from './compra/useSeccionCompra.js';

// Las MISMAS props que la raíz le pasa con `v-bind="props"`: ninguna acaba de atributo en el DOM.
defineOptions({ inheritAttrs: false });
const props = defineProps(PROPS_MOTOR);
const { abierta, textos, ck, cuando, cambiar, refreshBookingStatus, refreshIdentity, openProduct, applyIntent } = useSeccionCompra(props);

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
                v-bind="cuando"
                @cambiar="cambiar"
            />
        </IslaFlotante>
    </Teleport>
</template>
