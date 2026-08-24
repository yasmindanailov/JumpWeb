<script setup>
import { computed } from 'vue';
import { useAccountStore } from '../stores/account.js';
import { useAuthStore } from '../stores/auth.js';
import { ZONES, bringsOwnHeading, titleKeyOf } from '../account/navigation.js';
import { t as translate } from '../i18n.js';
import Shell from '../Shell.vue';
import AccountHomeZone from '../account/zones/AccountHomeZone.vue';
import OrdersZone from '../account/zones/OrdersZone.vue';
import ProfileZone from '../account/zones/ProfileZone.vue';
import PasswordZone from '../account/zones/PasswordZone.vue';
import SessionsZone from '../account/zones/SessionsZone.vue';
import PrivacyZone from '../account/zones/PrivacyZone.vue';
import LoginZone from '../account/zones/LoginZone.vue';
import RegisterZone from '../account/zones/RegisterZone.vue';
import ForgotZone from '../account/zones/ForgotZone.vue';
import AuthTabs from '../account/zones/AuthTabs.vue';

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

    /** Las rutas que compone el servidor: la zona de entrar necesita la puerta del índice. */
    urls: { type: Object, default: () => ({}) },
});

const store = useAccountStore();
const auth = useAuthStore();

const title = computed(() => translate(props.account, titleKeyOf(store.zone)));

/**
 * Lleva a IDENTIFICARSE. Lo pide la zona de pedidos cuando la sesión caduca con el cajón abierto.
 *
 * ⚠️ **Ya no abre el modal de la cabecera** (2026-08-23, `specs/auth-en-cajon.md` §4.5): entrar es
 * una zona más de esta sección, así que el aviso de sesión caducada lleva a una pantalla que está
 * **dentro del mismo cajón**, sin cerrarlo y sin perder de vista la cesta. Era uno de los cinco
 * puntos que abrían el modal, y el primero en caer.
 */
const signIn = () => store.go(ZONES.LOGIN);
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
        <button type="button" class="bk-back account__back" @click="store.back()">
            <svg class="arrow-ico" width="16" height="16" viewBox="0 0 24 24" fill="none"
                 stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"
                 aria-hidden="true" focusable="false">
                <line x1="19" y1="12" x2="5" y2="12" />
                <polyline points="12 19 5 12 12 5" />
            </svg>
            <span>{{ translate(messages, 'back') }}</span>
        </button>

        <!--
          ⚠️⚠️ **Las tres pantallas de AUTH traen su propio encabezado, así que aquí no se pone**
          (2026-08-23). `LoginForm` y `RegisterForm` se reutilizan del paso 5 del embudo —donde no hay
          armazón que ponga título— y pintan su `auth__title` con el MISMO literal que este `title`:
          el cliente veía «Inicia sesión» o «Crea tu cuenta» **dos veces**, una encima de otra.
          ▶ La regla vive en `account/navigation.js::bringsOwnHeading()`, con su `node --test`, y no
          como una lista de zonas escrita aquí: una condición en una plantilla envejece en silencio en
          cuanto nace la cuarta pantalla que traiga encabezado propio.
        -->
        <h2 v-if="! bringsOwnHeading(store.zone)" class="wiz__title">{{ title }}</h2>

        <AccountHomeZone
            v-if="store.zone === ZONES.HOME"
            :account="account"
            :messages="messages"
            :ui="ui"
            @go="store.go" />

        <ProfileZone
            v-else-if="store.zone === ZONES.PROFILE"
            :messages="messages"
            :auth="auth"
            :account="account"
            :locales="locales"
            :ui="ui" />

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
            :account="account"
            :ui="ui" />

        <!--
          ⚠️ **La MISMA zona para las dos pantallas, con su ámbito por prop**
          (`specs/mis-reservas-por-reserva.md` §4.4): «Mis reservas» y «Historial» son la misma lista
          partida por un predicado del servidor, así que un componente por pantalla habría duplicado
          el ledger, el post-form y el reintento en dos sitios que arreglar.
        -->
        <OrdersZone
            v-else-if="store.zone === ZONES.ORDERS"
            scope="upcoming"
            :messages="messages"
            :account="account"
            :ui="ui"
            @sign-in="signIn"
            @go="store.go" />

        <OrdersZone
            v-else-if="store.zone === ZONES.ORDERS_HISTORY"
            scope="past"
            :messages="messages"
            :account="account"
            :ui="ui"
            @sign-in="signIn"
            @go="store.go" />

        <!-- Las zonas de INVITADO. Van al final y no es orden alfabético: son las únicas que se
             pintan SIN sesión, así que leerlas juntas dice de un vistazo dónde está esa frontera.

             ⚠️ Las pestañas NO están dentro de cada zona: son el conmutador ENTRE dos de ellas, y
             repetirlas en las dos habría dejado dos sitios que mantener sincronizados. `FORGOT` no
             las lleva a propósito — no es una tercera pestaña, es una pantalla a la que se entra
             desde entrar y de la que se vuelve. -->
        <!--
          ⚠️⚠️ **Las pestañas DESAPARECEN mientras hay un alta esperando verificación** (2026-08-23).
          Ofrecer «Entrar / Crear cuenta» junto a un «acabas de crear tu cuenta, revisa tu correo»
          invita a abandonar un paso a medias — y **pulsarlas destruía la pantalla**: salir de la zona
          borra el correo pendiente **a propósito**, porque es PII de alguien que puede no ser el
          siguiente en usar el dispositivo (lo fija `stores/auth.test.js`).
          ▶ Esa defensa no se toca. Lo que se retira es la forma ACCIDENTAL de dispararla: la salida
          deliberada sigue estando dentro de la propia pantalla («¿ya tienes cuenta?»), que además es
          la única que sabe a dónde lleva.
        -->
        <AuthTabs
            v-if="(store.zone === ZONES.LOGIN || store.zone === ZONES.REGISTER) && ! auth.awaitingVerification"
            :account="account"
            :active="store.zone" />

        <LoginZone
            v-if="store.zone === ZONES.LOGIN"
            :account="account"
            :messages="messages"
            :auth="auth"
            :urls="urls" />

        <RegisterZone
            v-else-if="store.zone === ZONES.REGISTER"
            :account="account"
            :messages="messages"
            :auth="auth"
            :urls="urls" />

        <ForgotZone
            v-else-if="store.zone === ZONES.FORGOT"
            :account="account"
            :messages="messages"
            :auth="auth" />
    </Shell>
</template>
