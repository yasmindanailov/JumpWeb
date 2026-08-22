<script setup>
import { computed } from 'vue';
import { useAccountStore } from '../stores/account.js';
import { ZONES, titleKeyOf } from '../account/navigation.js';
import { t as translate } from '../i18n.js';
import Shell from '../Shell.vue';
import AccountHomeZone from '../account/zones/AccountHomeZone.vue';
import OrdersZone from '../account/zones/OrdersZone.vue';

/**
 * **El ÁREA DE CLIENTE** (`docs/specs/area-cliente.md`), como SECCIÓN hermana del embudo de compra.
 *
 * **Enruta ZONAS y pinta. Nada más.** La navegación —qué zonas hay, la pila de retorno, cuándo
 * «volver» significa salir— vive en `account/navigation.js` y en su store, que se prueban con
 * `node --test`. Aquí no hay ninguna regla, y por eso este componente cabe holgado en el techo de
 * `SidebarComponentBudgetTest` sin necesitar excepción declarada.
 *
 * ⚠️ **Reutiliza `Shell.vue`, y se decidió midiendo** (spec §4.9). El panel se sostiene sobre una
 * cadena de HIJOS DIRECTOS —`.sidecart__body` → `#sidecart-spa` → `.purchase` → `.purchase__scroll`,
 * todos `flex: 1; min-height: 0`— que el propio CSS avisa que **ningún diff de árbol puede ver**.
 * Escribirle a la cuenta su propia cadena habría sido duplicar CSS estructural en un segundo sitio
 * que mantener. Medido antes de decidirlo: con `progress`/`footer`/`notice` a `null`, `Shell` emite
 * exactamente `.purchase` + el velo de carga + `.purchase__scroll`, y `data-engine="spa"` no lo lee
 * nadie —ni CSS, ni test, ni servidor—.
 */
const props = defineProps({
    /** El grupo `tickets` del locale activo: de ahí sale el rótulo de «Volver». */
    messages: { type: Object, default: () => ({}) },

    /** El grupo `ui` (el rótulo del velo de carga), que `Shell` necesita. */
    ui: { type: Object, default: () => ({}) },

    /** El grupo `account` PODADO (§4.5 de `sidebar-spa.md`): los rótulos de las zonas. */
    account: { type: Object, default: () => ({}) },
});

const store = useAccountStore();
const title = computed(() => translate(props.account, titleKeyOf(store.zone)));
</script>

<template>
    <Shell :messages="messages" :ui="ui">
        <!--
          ⚠️ La salida va PRIMERO y no es adorno: sin ella se entra al área y no se puede volver sin
          cerrar el cajón entero — y cerrarlo es lo que hace perder de vista la cesta.

          ⚠️⚠️ Y llama a `store.back()`, **no** a `showPurchase()`: dentro del área «volver» significa
          la zona anterior, y solo cuando no hay historia significa salir a la compra. Esa decisión
          vive en el store (probada con `node --test`), no aquí — un componente pinta.
        -->
        <button type="button" class="bk-back" @click="store.back()">
            <svg class="arrow-ico" width="16" height="16" viewBox="0 0 24 24" fill="none"
                 stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"
                 aria-hidden="true" focusable="false">
                <line x1="19" y1="12" x2="5" y2="12" />
                <polyline points="12 19 5 12 12 5" />
            </svg>
            <span>{{ translate(messages, 'back') }}</span>
        </button>

        <h2 class="wiz__title">{{ title }}</h2>

        <AccountHomeZone v-if="store.zone === ZONES.HOME" :account="account" @go="store.go" />

        <OrdersZone v-else-if="store.zone === ZONES.ORDERS" :account="account" />
    </Shell>
</template>
