<script setup>
import { computed } from 'vue';
import { useAccountContextStore } from '../stores/accountContext.js';
import { useAccountStore } from '../stores/account.js';
import { usePurchaseStore } from '../stores/purchase.js';
import { useSectionStore } from '../stores/section.js';
import { publishedIdentifying } from '../section.js';
import { ZONES } from './navigation.js';
import { panelOf } from './panel.js';

/**
 * **El bloque de cuenta del panel** (`docs/specs/account-context-vue.md` §4.2), que hasta el
 * 2026-08-23 pintaba un componente Livewire — el último que renderizaba el layout.
 *
 * ⚠️ **Vive fuera de `.sidecart__body`**, así que la raíz lo teletransporta a su hueco: es cromo del
 * panel, hermano del punto de montaje, no una pantalla del cajón.
 *
 * **Pinta y no decide.** Qué inicial lleva el avatar, a dónde va el aviso y qué sub-línea toca lo
 * resuelve `account/panel.js` con su `node --test`; el estado vive en `stores/accountContext.js`.
 *
 * ⚠️⚠️ **`identifying` se lee del store de Pinia, no de Alpine.** Antes tenía que ir por Alpine
 * porque el bloque estaba FUERA del motor; ahora está dentro, y darle la vuelta al viaje
 * Vue→Pinia→Alpine→DOM→Vue sería reintroducir a mano la frontera que este trabajo retira. Se usa la
 * MISMA función que publica la señal hacia fuera (`section.js::publishedIdentifying`), para que el
 * bloque y el resto de la página no puedan discrepar.
 *
 * ⚠️ `#568` · **«Cerrar sesión» ya no vive aquí** (`[DECIDIDO owner, 2026-09-13]`): se cierra desde el
 * índice de «Mi cuenta», bajo las tarjetas (`#332`, con `account/sign-out.js`). El SUELO sin JS de
 * `layout.blade.php` no cambia: si el motor no llega, ese formulario sigue siendo la salida.
 */
const context = useAccountContextStore();
// ⚠️ `accountStore`, no `account`: este componente declara la prop `account` (el diccionario) y una
// constante con ese nombre la SOMBREA en la plantilla. Aquí era benigno —la plantilla quería el store
// y la prop se lee por `props.account`—, pero es la misma trampa que dejó mudo el login del área
// durante cinco días (`sections/AccountSection.vue`, `DECISIONES #210`). `SidebarSetupBindingsTest`.
const accountStore = useAccountStore();
const section = useSectionStore();
const purchase = usePurchaseStore();

const props = defineProps({
    account: { type: Object, default: () => ({}) },
    messages: { type: Object, default: () => ({}) },
    urls: { type: Object, default: () => ({}) },
});

const panel = computed(() => panelOf(context.context, {
    account: props.account,
    messages: props.messages,
    urls: props.urls,
}));

/** El paso 5 ya pide identificarse abajo: ofrecer lo mismo arriba sería ruido con botones muertos. */
const identifying = computed(() => publishedIdentifying(section.active, purchase.identifying));
</script>

<template>
    <!--
      ⚠️ `.acct__inner` NO es decorativo: el colapso `1fr → 0fr` de `.acct` necesita un hijo que
      recorte (`overflow: hidden; min-height: 0`). Sin él el bloque desaparece de golpe en vez de
      plegarse, y lo vigila `SidebarAccountVisibilityTest`.
    -->
    <div class="acct__inner">
        <template v-if="panel.identified">
            <!--
              ⚠️⚠️ **La sub-línea de la próxima reserva NO está aquí desde el 2026-08-28**
              (`identidad-qr-puerta.md` §9.7 C·2, `DECISIONES #217`). Se decía en DOS sitios —aquí y
              en el índice del área— y el segundo es el que el cliente abre para mirarla; el hueco lo
              ocupa lo que sí se necesita con el móvil en la mano: el QR de la puerta.
              ▶ Con ella se fue `panel.subline` de la cara identificada. La de INVITADO conserva la
              suya, que dice otra cosa: por qué merece la pena entrar.
            -->
            <div class="acct__row">
                <!-- `#568` · el avatar del artboard `Navegacion Cuenta PJP`: círculo de tinta, con la
                     INICIAL dentro (la inicial la pidió el owner; el dibujo lleva una silueta). -->
                <span class="acct__avatar" aria-hidden="true">{{ panel.initial }}</span>
                <span class="acct__txt">
                    <span class="acct__hello">{{ panel.hello }}</span>
                </span>

                <!--
                  ⚠️ **«Mi QR» es un ATAJO, no una zona nueva**: abre la MISMA `ZONES.CARD` que la
                  tarjeta del índice. Está aquí porque el momento de usarlo es la cola de la puerta,
                  donde dos toques de más son dos toques de más. El rótulo sale de
                  `account.card.title`, que ya viaja con sesión: un texto propio sería un segundo
                  nombre para la misma pantalla.
                  ⚠️ Sin `href`: no hay página que sirva de suelo para el carné —es una credencial y
                  su única superficie es esta zona—, al revés que «Mis reservas», que sí la tiene.
                -->
                <!--
                  ⚠️ `#541` · **ENTRA EN LA FAMILIA** (`[DECIDIDO owner, 2026-09-12]`: «usa un botón
                  que tengamos, ese badge tipo chapa no»). Era una cápsula propia —radio 999, su
                  propio relleno y su propio peso— fuera de `.acct__btn`, o sea una quinta piel de
                  botón que nadie había decidido. Va en RELLENO porque es lo que más se usa de esta
                  tira: el carné es lo que se enseña en la puerta.
                -->
                <button type="button" class="acct__btn acct__btn--primary acct__btn--qr"
                        @click="accountStore.openZone(ZONES.CARD)">
                    <span class="acct__qr-ico" aria-hidden="true">
                        <!-- `qr` del sistema de diseño, copiado byte a byte (`SidebarIconParityTest`). -->
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor"
                             stroke="currentColor" stroke-width="3" aria-hidden="true" focusable="false">
                            <rect x="3.5" y="3.5" width="6.4" height="6.4" rx="1.4" fill="none" />
                            <rect x="14.1" y="3.5" width="6.4" height="6.4" rx="1.4" fill="none" />
                            <rect x="3.5" y="14.1" width="6.4" height="6.4" rx="1.4" fill="none" />
                            <rect x="13.4" y="13.4" width="3.4" height="3.4" rx="1" stroke="none" />
                            <rect x="18.2" y="17.6" width="3.4" height="3.4" rx="1" stroke="none" />
                            <rect x="13.4" y="18.2" width="3" height="3" rx="1" stroke="none" />
                        </svg>
                    </span>
                    {{ panel.card }}
                </button>
            </div>

            <!--
              ⚠️ El `href` se conserva SIEMPRE, también cuando el cajón se hace cargo: es lo que hace
              funcionar el clic central y «abrir en pestaña nueva». Con UN solo formulario pendiente
              `zone` es `null` y se deja navegar — el post-form es una página.
            -->
            <a v-if="panel.alert" :href="panel.alert.href" class="acct__alert"
               @click="panel.alert.zone && ($event.preventDefault(), accountStore.openZone(panel.alert.zone))">
                <span class="acct__alert-ico" aria-hidden="true">!</span>
                <span class="acct__alert-text">{{ panel.alert.text }}</span>
                <span class="acct__alert-arrow" aria-hidden="true">→</span>
            </a>

            <!--
              ⚠️ **DOS destinos en la fila, y el orden no es casual**: primero lo que el cliente viene
              a ver (sus reservas) y luego el resto de su cuenta. La salida se fue al índice en `#568`.
            -->
            <div class="acct__cta">
                <a :href="urls.my_orders" class="acct__btn acct__btn--ghost acct__btn--reservas"
                   @click="$event.preventDefault(), accountStore.openZone(ZONES.ORDERS)">
                    {{ panel.reservations }}
                    <template v-if="panel.counter">
                        <span class="acct__count" aria-hidden="true">{{ panel.counter.count }}</span>
                        <span class="sr-only">{{ panel.counter.label }}</span>
                    </template>
                </a>

                <!--
                  ⚠️ `#541` · FANTASMA (`[DECIDIDO owner, 2026-09-12]`): los rellenos son los que
                  hacen AVANZAR —«ir al carrito», «ir a pagar», «continuar»— y mirar la cuenta no
                  avanza nada. En esta tira el único relleno es el carné.
                -->
                <a :href="urls.account" class="acct__btn acct__btn--ghost"
                   @click="$event.preventDefault(), accountStore.openZone(ZONES.HOME)">
                    {{ panel.account }}
                </a>
            </div>
        </template>

        <template v-else>
            <div class="acct__row">
                <!-- `#568` · sin sesión, el avatar lleva `ui/registro` —persona con un «más»— como en el
                     artboard `Navegacion Cuenta PJP`. `user-plus` del sistema de diseño, copiado byte a
                     byte (`SidebarIconParityTest`). -->
                <span class="acct__avatar acct__avatar--guest" aria-hidden="true">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor"
                         aria-hidden="true" focusable="false">
                        <circle cx="9" cy="8" r="3.6" />
                        <path d="M2.6 19.4a6.4 6.4 0 0 1 12.8 0 1.2 1.2 0 0 1-1.2 1.2H3.8a1.2 1.2 0 0 1-1.2-1.2z" />
                        <rect x="17.4" y="15.4" width="2.8" height="6" rx="1.4" />
                        <rect x="15.8" y="17" width="6" height="2.8" rx="1.4" />
                    </svg>
                </span>
                <span class="acct__txt">
                    <span class="acct__hello">{{ panel.hello }}</span>
                    <span class="acct__sub">{{ panel.subline }}</span>
                </span>
            </div>

            <!--
              ⚠️ Estos dos NO llevan `href` ni les hace falta: viven dentro de un panel que solo
              existe si el JS corre, y entrar es una ZONA de este mismo cajón (no una página).
            -->
            <div class="acct__cta">
                <button type="button" class="acct__btn acct__btn--primary"
                        :disabled="identifying" @click="accountStore.openZone(ZONES.LOGIN)">
                    <!-- `login` del sistema de diseño, copiado byte a byte (`SidebarIconParityTest`).
                         ⚠️ Lleva el `<g transform>` del componente: el espejo es PARTE del dibujo, y
                         quitarlo aquí dejaría la puerta mirando al revés sin que nada fallara. -->
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="currentColor"
                         aria-hidden="true" focusable="false">
                        <g transform="translate(24 0) scale(-1 1)">
                            <path d="M11 3h-5A2.6 2.6 0 0 0 3.4 5.6v12.8A2.6 2.6 0 0 0 6 21h5a1.5 1.5 0 0 0 0-3H6.4V6H11a1.5 1.5 0 0 0 0-3z" />
                            <path d="M16.4 6.9 14.3 9l2 2h-5.5a1.5 1.5 0 0 0 0 3h5.5l-2 2 2.1 2.1 5.1-5.6z" />
                        </g>
                    </svg>
                    {{ panel.login }}
                </button>

                <button type="button" class="acct__btn acct__btn--ghost"
                        :disabled="identifying" @click="accountStore.openZone(ZONES.LOGIN)">
                    {{ panel.reservations }}
                </button>
            </div>
        </template>
    </div>
</template>
