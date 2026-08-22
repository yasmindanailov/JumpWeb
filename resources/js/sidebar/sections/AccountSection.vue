<script setup>
import { computed } from 'vue';
import { useAccountStore } from '../stores/account.js';
import { ZONES, titleKeyOf } from '../account/navigation.js';
import { t as translate } from '../i18n.js';
import Shell from '../Shell.vue';
import AccountHomeZone from '../account/zones/AccountHomeZone.vue';
import OrdersZone from '../account/zones/OrdersZone.vue';
import ProfileZone from '../account/zones/ProfileZone.vue';
import PasswordZone from '../account/zones/PasswordZone.vue';
import SessionsZone from '../account/zones/SessionsZone.vue';
import PrivacyZone from '../account/zones/PrivacyZone.vue';

/**
 * **El ÁREA DE CLIENTE** (`docs/specs/area-cliente.md`), como SECCIÓN hermana del embudo de compra.
 *
 * **Enruta ZONAS y pinta el armazón. Nada más.** Cada zona pide lo suyo al montarse y habla con su
 * propio store; la navegación —qué zonas hay, la pila de retorno, cuándo «volver» significa salir—
 * vive en `account/navigation.js` y en su store, con `node --test`.
 *
 * ⚠️⚠️ **Y esta sección NO conoce los datos de sus zonas, aunque los conoció durante dos pasos.**
 * Tenía un `watch` con una cadena de `if` —«al entrar en pedidos, pide pedidos»— y un `computed` por
 * cada lista. Las dos cosas crecían con cada pantalla nueva y la empujaron a **38 de 40** líneas en el
 * paso 7b: el techo de `SidebarComponentBudgetTest` hizo su trabajo, que es **provocar la pregunta**.
 * ▶ La respuesta correcta no era subir el techo: era que **cada zona sepa qué necesita**. `ensure()`
 * («pedir solo si no hay datos») ya garantizaba que volver a entrar no repitiera la petición, así que
 * mover la carga a cada zona no costó ni una petición más.
 *
 * ⚠️ **Reutiliza `Shell.vue`, y se decidió midiendo** (spec §4.9). El panel se sostiene sobre una
 * cadena de HIJOS DIRECTOS que el propio CSS avisa que **ningún diff de árbol puede ver**; escribirle
 * a la cuenta su propia cadena habría duplicado CSS estructural en un segundo sitio.
 */
const props = defineProps({
    /** El grupo `tickets` del locale activo: de ahí sale el rótulo de «Volver» y los estados. */
    messages: { type: Object, default: () => ({}) },

    /** El grupo `ui` (el rótulo del velo de carga), que `Shell` necesita. */
    ui: { type: Object, default: () => ({}) },

    /** El grupo `account` PODADO (§4.5 de `sidebar-spa.md`): los rótulos de las zonas. */
    account: { type: Object, default: () => ({}) },

    /** El grupo `auth`: de ahí sale el aviso del limitador, el MISMO texto que usa el login. */
    auth: { type: Object, default: () => ({}) },

    /** Los idiomas que ofrece el selector del perfil, con su nombre nativo (`SiteLocales`). */
    locales: { type: Array, default: () => [] },
});

const store = useAccountStore();

const title = computed(() => translate(props.account, titleKeyOf(store.zone)));

/** Abre el modal de auth de la cabecera, que sigue siendo la puerta de entrada (spec §4.6). */
const signIn = () => window.Alpine?.store('auth')?.open('login');
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

        <AccountHomeZone
            v-if="store.zone === ZONES.HOME"
            :account="account"
            :messages="messages"
            @go="store.go" />

        <ProfileZone
            v-else-if="store.zone === ZONES.PROFILE"
            :messages="messages"
            :auth="auth"
            :account="account"
            :locales="locales" />

        <PasswordZone
            v-else-if="store.zone === ZONES.PASSWORD"
            :messages="messages"
            :auth="auth"
            :account="account" />

        <SessionsZone
            v-else-if="store.zone === ZONES.SESSIONS"
            :messages="messages"
            :auth="auth"
            :account="account" />

        <PrivacyZone
            v-else-if="store.zone === ZONES.PRIVACY"
            :messages="messages"
            :auth="auth"
            :account="account" />

        <OrdersZone
            v-else-if="store.zone === ZONES.ORDERS"
            :messages="messages"
            :account="account"
            @sign-in="signIn" />
    </Shell>
</template>
