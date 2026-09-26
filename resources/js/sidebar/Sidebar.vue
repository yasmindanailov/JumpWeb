<script setup>
import { defineAsyncComponent, inject, ref, watch } from 'vue';
import PurchaseSection from './sections/PurchaseSection.vue';
import AccountSection from './sections/AccountSection.vue';
import AccountPanel from './account/AccountPanel.vue';
import { useSectionStore } from './stores/section.js';
import { usePurchaseStore } from './stores/purchase.js';
import { useOutcomeStore } from './stores/outcome.js';
import { publishedIdentifying, publishedMode, publishedPurchase } from './section.js';
import { cajonHost } from './host-bridge.js';
import { CARCASA, ISLA } from './carcasa.js';
import { PROPS_MOTOR } from './props.js';

/**
 * La RAÍZ del cajón: monta, enruta secciones y publica hacia fuera. Nada más.
 *
 * ⚠️⚠️ **Existe por una decisión de arquitectura, no por estética** (2026-08-22). `DECISIONES #66`
 * dice que el cajón hospedará también el **ÁREA DE CLIENTE** —sus pedidos, sus reservas, sus
 * ajustes—. Hasta entonces el embudo de compra ERA la raíz: 438 líneas y once ramas `v-else-if` en
 * un solo fichero. Añadir ahí las pantallas de cuenta habría metido **dos dominios en el mismo
 * componente**, y cada pantalla nueva habría hecho más caro separarlos después.
 *
 * ⚠️ El movimiento fue posible **porque el estado ya vivía en stores**: una sección no necesita que
 * la raíz le pase su estado por props —lo pide con `useXStore()`—, así que partir el componente no
 * obligó a inventar un puente de props entre padre e hijo. Ese es el orden correcto y no al revés.
 *
 * ⚠️ Sus props viven en `props.js` desde la T3e·2: son también las de la compra de la isla, que las recibe con
 * `v-bind="props"`, y dos listas que tienen que coincidir se escriben una vez.
 */
const props = defineProps(PROPS_MOTOR);

/**
 * ⚠️ **La compra de la ISLA** (T3e·2, `DECISIONES #682`): con la isla como carcasa de la instalación, sustituye a
 * la sección de compra del cajón —y como ella vive montada toda la página, con el mismo `ref`—. Va en su trozo y se
 * trae solo con la isla: una instalación con el cajón no paga sus bytes.
 */
const IslaSeccionCompra = defineAsyncComponent(() => import('../isla/SeccionCompra.vue'));
const enIsla = inject(CARCASA, null) === ISLA;

/**
 * ⚠️ **Y Mi cuenta de la ISLA** (T5, `DECISIONES #773`): con la isla, ocupa el sitio de la sección de cuenta del cajón
 * —que no cambia—, en su trozo y solo a la primera entrada, como aquélla. Pero se QUEDA montada desde entonces: pasar a
 * la compra y volver («Reservar otra vez») la encuentra donde estaba, que es lo que pide el diseño.
 */
const IslaSeccionCuenta = defineAsyncComponent(() => import('../isla/SeccionCuenta.vue'));
const cuentaVista = ref(false);

const purchase = ref(null);
const section = useSectionStore();
watch(() => section.onAccount, (dentro) => { if (dentro) cuentaVista.value = true; }, { immediate: true });
const purchaseStore = usePurchaseStore();
const outcomeStore = useOutcomeStore();

/**
 * **El PUENTE de señales hacia fuera del cajón**, que sube aquí porque desde 2026-08-22 depende de
 * DOS cosas: el paso del embudo y la sección activa (`specs/area-cliente.md` §4.5).
 *
 * ⚠️ Vivía en la sección de compra y ahí ya no puede vivir: un `watch` sobre el paso **no se dispara
 * al conmutar de sección** —el paso no ha cambiado—, así que el panel se quedaría con el último modo
 * de la compra mientras el cliente mira sus pedidos. Las reglas están en `section.js`, módulo plano
 * con su `node --test`; aquí solo se conecta con el mundo.
 *
 * ⚠️⚠️ Sin esto, dos regresiones que **no se ven desde dentro del cajón**: el panel se queda en
 * `is-catalog` para siempre y los botones de invitado siguen activos durante la identificación.
 * Ninguna de las dos clases aparece en este marcado —viven en `layout.blade.php` y en
 * `account-context`—, y por eso ya estuvieron muertas sin que nadie lo notara (`DECISIONES #118`).
 */
watch(
    [() => section.active, () => purchaseStore.mode, () => purchaseStore.identifying, () => purchaseStore.confirmed, () => outcomeStore.orderCode],
    ([active, mode, identifying, confirmed, orderCode]) => {
        // ⚠️ El anfitrión, no Alpine (F4 · T2): `window.JumpWeb.cajon` es el controlador sin framework —y,
        // cuando Alpine está, su proxy reactivo, así que la carcasa reacciona igual—. Nombrar a Alpine aquí
        // ataba el motor a un framework que una página ajena no tiene por qué cargar.
        const host = cajonHost();
        if (! host) return;

        host.setMode(publishedMode(active, mode));
        host.identifying = publishedIdentifying(active, identifying);
        // La TERCERA señal (F4 · T3b): la compra confirmada, que el anfitrión anuncia como
        // `jw:cajon:purchased`. La regla vive en `section.js` con sus hermanas y quien no la repite es el
        // controlador; aquí, como con las otras dos, solo se conecta con el mundo.
        host.purchased?.(publishedPurchase(active, confirmed, orderCode));
    },
    { immediate: true },
);

/**
 * Las dos señales que el motor publica hacia FUERA, y que `index.js` invoca sobre la raíz.
 *
 * ⚠️⚠️ **Se REENVÍAN a la sección; la raíz NO las reimplementa, y ese matiz costó un fallo el mismo
 * día.** Al partir el componente se escribió aquí un atajo que delegaba directo a los stores
 * (`cartStore.identify()`), y con él se perdía otra vez lo que hace `actOnIdentity()`: **si la cesta
 * se purga hay que volver al catálogo**. Reenviar hace que corra EXACTAMENTE el mismo código que
 * antes del movimiento, que es lo único que un renombrado debe garantizar.
 */
defineExpose({
    refreshBookingStatus: () => purchase.value?.refreshBookingStatus(),
    refreshIdentity: () => purchase.value?.refreshIdentity(),
    // `#568` · abrir el cajón EN un producto desde la landing. Mismo reenvío, mismo motivo.
    openProduct: (id) => purchase.value?.openProduct(id),
    // T3e·2 · con la isla, la intención de la landing la toma SU compra de la máquina (`index.js`).
    applyIntent: () => purchase.value?.applyIntent?.(),
});
</script>

<template>
    <!--
      ⚠️⚠️ **Las dos secciones se ocultan de formas DISTINTAS, y cada asimetría se decidió MIDIENDO**
      (`specs/area-cliente.md` §4.1):

      · la **compra** con `v-show`: es la sección por defecto y **su `ref` sostiene el puente de
        `defineExpose`**. Si se desmontara —o se desactivara— Vue anula la template ref, y las dos
        señales que `index.js` invoca en CADA apertura del cajón se las comería el `?.` **en
        silencio**: la pausa dejaría de releerse y un cambio de titular no purgaría la cesta. Es la
        familia de fallos que este proyecto ya ha pagado tres veces.
        ⚠️⚠️ **Y desde el 2026-08-23 hay un SEGUNDO motivo, que no se adivina leyendo esto**
        (`specs/auth-en-cajon.md` §4.3): el `onMounted` de esa sección es **el único sitio que pide
        `GET /config`**, y de ahí sale la clave del anti-bot — que usa también el ALTA del área de
        cliente. Desmontarla dejaría el formulario de registro **sin widget**, y el servidor
        rechazaría cada alta con «no eres un robot», sin correo y sin una línea de log. Lo vigila
        `SidebarComponentBudgetTest::test_the_root_routes_sections_and_does_not_paint_screens`;

      · la **cuenta** con `v-if` a secas: montarla siempre le regalaría a quien viene a comprar las
        peticiones de `/me/*` en cada apertura del cajón.

      ⚠️ **Aquí hubo un `<KeepAlive>` durante media hora, y lo quitó una medición.** Se puso para que
      volver a la cuenta no repitiera su carga; medido, **costaba 2,3 KiB de chunk —él solo hacía
      saltar el presupuesto de `SidebarBundleBudgetTest`— y no resolvía nada que no resuelva mejor su
      store**: pedir solo si no hay datos es una regla explícita, probable con `node --test`, en vez
      de una caché del framework que además rompe las template refs. Sin él, el chunk cabe en el
      techo que ya había.

      ⚠️ Y lo que NO se puede hacer aquí: envolver las secciones en un `<div>` de conveniencia.
      Partiría la cadena de HIJOS DIRECTOS que sostiene el panel (§4.9) **sin que falte una sola
      clase**, y ningún test puede verlo — lo dice el propio CSS.
    -->
    <PurchaseSection v-show="section.onPurchase" v-if="! enIsla" ref="purchase" v-bind="props" />
    <!--
      Con la ISLA como carcasa (T3e·2), su compra ocupa el lugar de la del cajón: montada toda la página y con el
      mismo `ref`, así que el puente de señales y el `GET /config` del anti-bot siguen saliendo de aquí. La elección
      no cambia nunca dentro de una página: es de la instalación.
    -->
    <IslaSeccionCompra v-else ref="purchase" v-bind="props" />

    <AccountSection v-if="section.onAccount && ! enIsla" v-bind="props" />
    <!-- Con la ISLA (T5), Mi cuenta es una capa suya: se enseña cuando el controlador la abre, no con la sección. -->
    <IslaSeccionCuenta v-if="enIsla && cuentaVista" v-bind="props" />

    <!--
      ⚠️⚠️ **El bloque de cuenta NO es una sección: es CROMO del panel**, y por eso se teletransporta
      (`specs/account-context-vue.md` §4.2). Vive FUERA de `.sidecart__body` —hermano del hueco donde
      monta esta app—, así que no puede ser un hijo más de esta raíz.

      ⚠️ Va aquí y no dentro de `AccountSection` por un motivo concreto: aquélla monta con `v-if`, y
      el bloque tiene que existir con la sección de cuenta apagada — que es la mayoría del tiempo.

      ⚠️ Las props van UNA A UNA y no con `v-bind="props"`: lo segundo le pasaría también `auth`,
      `locales`, `userId` y `orderCode`, que este componente no declara y que Vue volcaría como
      ATRIBUTOS sobre su raíz. Saldrían en el DOM del cliente.

      ⚠️ El destino lo emite el servidor ya colapsado y con el suelo de «cerrar sesión» dentro; quien
      lo vacía y lo expande es `account/host.js`, su dueño ÚNICO.
    -->
    <Teleport to="#sidecart-account">
        <AccountPanel :account="account" :messages="messages" :urls="urls" />
    </Teleport>
</template>
