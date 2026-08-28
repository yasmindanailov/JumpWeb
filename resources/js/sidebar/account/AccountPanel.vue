<script setup>
import { computed, ref } from 'vue';
import { useAccountContextStore } from '../stores/accountContext.js';
import { useAccountStore } from '../stores/account.js';
import { usePurchaseStore } from '../stores/purchase.js';
import { useSectionStore } from '../stores/section.js';
import { publishedIdentifying } from '../section.js';
import { ZONES } from './navigation.js';
import { panelOf } from './panel.js';
import { signOut } from './sign-out.js';

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

const leaving = ref(false);

/**
 * ⚠️ Se re-habilita si el cierre NO se confirma. Un botón que se queda muerto tras un fallo deja al
 * cliente sin forma de reintentar, creyéndose fuera de una sesión que sigue abierta.
 */
async function leave() {
    if (leaving.value) return;

    leaving.value = true;

    if (! await signOut({ urls: props.urls })) leaving.value = false;
}
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
                <button type="button" class="acct__qr"
                        @click="accountStore.openZone(ZONES.CARD)">
                    <span class="acct__qr-ico" aria-hidden="true">
                        <!-- `qr` del sistema de diseño, copiado byte a byte (`SidebarIconParityTest`). -->
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" aria-hidden="true" focusable="false">
                            <rect x="3.6" y="3.6" width="6.6" height="6.6" rx="1.4" stroke="currentColor" stroke-width="1.7" />
                            <rect x="13.8" y="3.6" width="6.6" height="6.6" rx="1.4" stroke="currentColor" stroke-width="1.7" />
                            <rect x="3.6" y="13.8" width="6.6" height="6.6" rx="1.4" stroke="currentColor" stroke-width="1.7" />
                            <rect x="13.8" y="13.8" width="2.8" height="2.8" rx="0.6" fill="currentColor" />
                            <rect x="17.6" y="13.8" width="2.8" height="2.8" rx="0.6" fill="currentColor" />
                            <rect x="13.8" y="17.6" width="2.8" height="2.8" rx="0.6" fill="currentColor" />
                            <rect x="17.6" y="17.6" width="2.8" height="2.8" rx="0.6" fill="currentColor" />
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
              ⚠️⚠️ **TRES destinos en una fila, y el orden no es casual**: primero lo que el cliente
              viene a ver (sus reservas), luego el resto de su cuenta, y **al final la salida** — que
              es la única acción de la que uno no vuelve. Por eso además es la ÚNICA que no lleva
              peso visual: un «cerrar sesión» que grita se pulsa sin querer.
            -->
            <div class="acct__cta">
                <a :href="urls.my_orders" class="acct__btn acct__btn--primary acct__btn--reservas"
                   @click="$event.preventDefault(), accountStore.openZone(ZONES.ORDERS)">
                    {{ panel.reservations }}
                    <template v-if="panel.counter">
                        <span class="acct__count" aria-hidden="true">{{ panel.counter.count }}</span>
                        <span class="sr-only">{{ panel.counter.label }}</span>
                    </template>
                </a>

                <a :href="urls.account" class="acct__btn acct__btn--ghost"
                   @click="$event.preventDefault(), accountStore.openZone(ZONES.HOME)">
                    {{ panel.account }}
                </a>

                <!--
                  ⚠️⚠️ **Solo icono, así que su nombre accesible va en `aria-label`.** Sin texto
                  visible no hay «label in name» que respetar (WCAG 2.5.3 habla de cuando SÍ lo hay),
                  y sin `aria-label` un lector de pantalla anunciaría «botón» a secas. El `title` es
                  para el ratón, y no sustituye al anterior: no lo lee todo el mundo.

                  ⚠️ NO es un `<form>` con `@csrf`: el `_token` de la página está caducado en cuanto
                  alguien entra en el paso 5, y daría 419. El porqué, en `account/sign-out.js`.
                -->
                <button type="button" class="acct__btn acct__btn--ghost acct__btn--icon"
                        :disabled="leaving" :aria-label="panel.signOut" :title="panel.signOut"
                        @click="leave()">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none"
                         stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                         aria-hidden="true" focusable="false">
                        <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4" />
                        <polyline points="16 17 21 12 16 7" />
                        <line x1="21" y1="12" x2="9" y2="12" />
                    </svg>
                </button>
            </div>
        </template>

        <template v-else>
            <div class="acct__row">
                <span class="acct__avatar" aria-hidden="true">?</span>
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
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none"
                         stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                         aria-hidden="true" focusable="false">
                        <path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4" />
                        <polyline points="10 17 15 12 10 7" />
                        <line x1="15" y1="12" x2="3" y2="12" />
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
