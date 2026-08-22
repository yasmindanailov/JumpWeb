<script setup>
import { ref } from 'vue';
import PurchaseSection from './sections/PurchaseSection.vue';

/**
 * La RAÍZ del cajón: monta, enruta secciones y publica hacia fuera. Nada más.
 *
 * ⚠️⚠️ **Existe por una decisión de arquitectura, no por estética** (2026-08-22). `DECISIONES #66`
 * dice que el cajón hospedará también el **ÁREA DE CLIENTE** —sus pedidos, sus reservas, sus
 * ajustes—. Hasta hoy el embudo de compra ERA la raíz: 438 líneas y once ramas `v-else-if` en un
 * solo fichero. Añadir ahí las pantallas de cuenta habría metido **dos dominios en el mismo
 * componente**, y cada pantalla nueva habría hecho más caro separarlos después.
 *
 * ▶ La compra pasa a ser **una sección** (`sections/PurchaseSection.vue`) y la cuenta será otra. Esta
 * raíz se queda con lo único que es de verdad común: el montaje y las dos señales que el motor
 * publica al mundo de fuera del cajón.
 *
 * ⚠️ El movimiento fue posible **porque el estado ya vivía en stores**: una sección no necesita que
 * la raíz le pase su estado por props —lo pide con `useXStore()`—, así que partir el componente no
 * obligó a inventar un puente de props entre padre e hijo. Ese es el orden correcto y no al revés.
 */
const props = defineProps({
    /** El grupo `tickets` del locale activo, inyectado por el servidor en el montaje (§4.5). */
    messages: { type: Object, default: () => ({}) },
    /** Textos de interfaz que no son del grupo `tickets` (velo de carga, etiquetas del armazón). */
    ui: { type: Object, default: () => ({}) },
    /** El grupo `account`, podado a lo que el paso de identificación pinta. */
    account: { type: Object, default: () => ({}) },
    /** El grupo `auth`, con los textos de login y alta. */
    auth: { type: Object, default: () => ({}) },
    /** Quién pintó la página, para que la cesta sepa de quién es antes de preguntar a nadie. */
    userId: { type: [Number, String], default: null },
    /** El pedido del que habla el desenlace. Llega ya CONSUMIDO por `Http\Sidebar\SidebarEntry`. */
    orderCode: { type: String, default: '' },
    /** Las rutas que pintan las pantallas de desenlace, compuestas con `route()` en el servidor. */
    urls: { type: Object, default: () => ({}) },
});

const purchase = ref(null);

/**
 * Las dos señales que el motor publica hacia FUERA del cajón, y que `index.js` invoca sobre la raíz.
 *
 * ⚠️⚠️ **Se REENVÍAN a la sección; la raíz NO las reimplementa, y ese matiz costó un fallo el mismo
 * día.** Al partir el componente escribí aquí un atajo que delegaba directo a los stores
 * (`cartStore.identify()`), y con él se perdía otra vez lo que hace `actOnIdentity()`: **si la cesta
 * se purga hay que volver al catálogo**. Reenviar hace que corra EXACTAMENTE el mismo código que
 * antes del movimiento, que es lo único que un renombrado debe garantizar.
 *
 * ▶ Cuando exista la sección de CUENTA, cada una decidirá qué hace con estas dos señales; la raíz
 * seguirá sin decidir nada.
 */
defineExpose({
    refreshBookingStatus: () => purchase.value?.refreshBookingStatus(),
    refreshIdentity: () => purchase.value?.refreshIdentity(),
});
</script>

<template>
    <!-- Hoy el cajón solo tiene una sección. La de CUENTA entra aquí, al lado, no dentro. -->
    <PurchaseSection ref="purchase" v-bind="props" />
</template>
