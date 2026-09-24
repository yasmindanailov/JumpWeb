<script setup>
import { useReservationsStore } from '../../stores/reservations.js';
import { useAccountContextStore } from '../../stores/accountContext.js';
import { HOME_ENTRIES, ZONES, titleKeyOf } from '../navigation.js';
import { WAIVER_NOTICE_DEPENDENTS, WAIVER_NOTICE_VERIFY, accountNoticeFrom } from '../waiver.js';
import { useAuthStore } from '../../stores/auth.js';
import { resendGate } from '../verify.js';
import { signOut } from '../sign-out.js';
import { api } from '../../api.js';
import { computed, ref, watch } from 'vue';
import { t as translate, tp as translateWith } from '../../i18n.js';
import ZoneLoading from '../ZoneLoading.vue';
import ZoneIcon from '../ZoneIcon.vue';

/**
 * **El índice del área de cliente**: quién eres, qué tienes por delante y desde dónde se llega a todo
 * lo demás (`docs/specs/area-cliente.md` §4.2).
 *
 * ⚠️ **La próxima reserva se pinta aquí aunque `.acct` también la enseñe, y no es duplicación**:
 * medido en `VERIFICACION-E2E-CAJON.md` **V4**, dentro de esta sección ese bloque **se colapsa** —lo
 * hace el modo `account`— y su altura es 0. Dentro del área, esa información no se ve.
 *
 * ⚠️ **Las entradas salen de `HOME_ENTRIES`, no de marcado repetido**: añadir una zona es una línea
 * en `navigation.js`.
 */
const props = defineProps({
    account: { type: Object, default: () => ({}) },
    /** El grupo `ui`: el rótulo del spinner mientras la zona trae sus datos. */
    ui: { type: Object, default: () => ({}) },
    messages: { type: Object, default: () => ({}) },
    /** `#332`: las rutas del servidor. Solo para el cierre de sesión, que navega a la home. */
    urls: { type: Object, default: () => ({}) },
});

const emit = defineEmits(['go']);

const store = useReservationsStore();

store.ensure();

// Fase 6 · waiver (§4.8): «la re-firma se pide al entrar». Lo dice el contexto de cuenta —el que se
// repinta al conseguir sesión—, y la decisión de avisar vive en `account/waiver.js`, no aquí.
const context = useAccountContextStore();

// `#329`/`#331` — el aviso son DOS y dicen cosas distintas: `sign` lleva a firmar; `verify` es «te
// falta verificar el correo» y ofrece REENVIARLO, que es lo único que desbloquea la firma
// (`POST /me/waiver` responde 409 sin el correo verificado). Cuál toca lo decide `waiver.js`.
const auth = useAuthStore();
const notice = computed(() => accountNoticeFrom(context.context));

// ⚠️ **La misma puerta del reenvío que la pantalla del alta** (`account/verify.js`), y no un `disabled`
// escrito a mano: cuántos quedan y cuándo se puede volver a pulsar es UNA regla, con sus `node --test`.
// Escribirla otra vez aquí es cómo el botón acaba ofreciéndose cuando el servidor ya lo descarta.
const gate = computed(() => resendGate({ secondsLeft: auth.resendSeconds, resendsLeft: auth.resendsLeft }));

// ⚠️ El contador se arma con un `watch` inmediato y no en el `setup`: el contexto de cuenta llega
// por red, así que al montarse la zona `notice` todavía vale `null` — armarlo una sola vez aquí
// dejaría el botón muerto justo en el caso que existe para resolver. El store lleva su propio
// pestillo, así que repetirlo no rellena el cupo de reenvíos.
watch(notice, (n) => {
    if (n?.kind === WAIVER_NOTICE_VERIFY) auth.allowVerificationResend();
}, { immediate: true });

const resend = () => auth.resendVerification({ api });

// T3a·4 de la analítica (`specs/analitica.md` §4.3): el aviso de que la navegación puede vincularse a la
// cuenta, para las cuentas que existían antes de la v3 de la política. Viene CON su texto en el contexto y
// solo mientras está pendiente; despedirlo es un `DELETE` que el store confirma antes de quitarlo (la
// decisión vive en `stores/accountContext.js`, con sus `node --test`; aquí solo se pinta y se llama).
const analyticsNotice = computed(() => context.context?.analytics_notice ?? null);
const dismissAnalyticsNotice = () => context.dismissAnalyticsNotice({ api });

// `#332` — **cerrar sesión DESDE aquí** (`[DECIDIDO owner]`). El botón del bloque `.acct` existe, pero
// **dentro de esta sección ese bloque se colapsa** (modo `account`, medido en `VERIFICACION-E2E-CAJON`
// V4: altura 0), así que el cliente entraba a su cuenta y se quedaba sin salida a la vista.
// ⚠️ Se reutiliza `account/sign-out.js` ENTERO —el mismo que el bloque— y no un `<form>` con `@csrf`:
// el `_token` de la página está caducado en cuanto alguien pasa por el paso 5, y daría 419.
const leaving = ref(false);
const leave = async () => {
    if (leaving.value) return;
    leaving.value = true;
    // Solo se re-habilita si NO se navegó: cuando la sesión muere de verdad, la página se va.
    if (! await signOut({ api, urls: props.urls })) leaving.value = false;
};
</script>

<template>
    <!--
      El contexto de cortesía. Va antes que los accesos porque es lo que el cliente viene a mirar:
      medido en la web, «¿cuándo es lo mío?» es la pregunta que trae aquí a la mayoría.
    -->
    <ZoneLoading v-if="store.loading && ! store.loaded" :ui="ui" />

    <p v-else-if="store.next" class="acct__sub">
        {{ store.next.date_label }}<template v-if="store.next.time_window"> · {{ store.next.time_window }}</template> · {{ store.next.product_name }}
    </p>

    <!--
      El aviso del waiver (Fase 6) — y son DOS (`#329`):
       · `verify`: la aceptó al registrarse y falta que verifique su correo. Ofrece REENVIARLO, no
         firmar: `POST /me/waiver` responde 409 sin el correo verificado, así que el botón de firmar
         que había aquí solo podía dar error.
       · `sign`: hay que firmar o re-firmar. Lleva a la tarjeta de privacidad, como siempre.
    -->
    <!--
      `#332` — **el aviso de verificar, DENTRO del cajón y con su hardening** (`[DECIDIDO owner]`):
      el mensaje, el botón de reenviar con su cuenta atrás, cuántos reenvíos quedan y el aviso de
      límite alcanzado. Es la misma puerta que la pantalla del alta (`account/verify.js`), no un
      `disabled` escrito a mano.
      ⚠️ **La línea de la exención va DEBAJO y solo si hay una esperando**: son dos hechos y una sola
      acción del cliente, así que se apilan en UN bloque en vez de en dos avisos.
    -->
    <template v-if="notice?.kind === WAIVER_NOTICE_VERIFY">
        <p class="auth__switch" role="status">
            {{ translate(account, 'verify.pending_notice') }}
            <button type="button" :disabled="! gate.canResend" @click="resend">
                <template v-if="gate.waiting">{{ translate(account, 'verify.resend_in') }} {{ auth.resendSeconds }}s</template>
                <template v-else>{{ translate(account, 'verify.resend') }}</template>
            </button>
        </p>

        <p v-if="gate.exhausted" class="auth__sub">{{ translate(account, 'verify.resend_limit') }}</p>
        <p v-else class="form__hint">{{ translateWith(account, 'verify.resends_left', { n: auth.resendsLeft }) }}</p>

        <p v-if="notice.withWaiver" class="auth__sub">{{ translate(account, 'account.privacy.waiver.status_awaiting_verification') }}</p>
    </template>

    <!--
      `#441` · falta la firma de un MENOR. ⚠️ **Va a la zona de MENORES y no a Privacidad**: allí
      está la suya, no la de ellos — mandarle a una pantalla donde no está lo que le falta es el
      callejón de `#329` por la otra puerta.
    -->
    <p v-else-if="notice?.kind === WAIVER_NOTICE_DEPENDENTS" class="auth__switch">
        {{ translate(account, 'account.dependents.waiver_pending_notice') }}
        <!-- ⚠️ El rótulo es el TÍTULO de la zona a la que lleva, que ya viaja en el montaje: el
             botón y la pantalla a la que va se llaman igual (la regla del atajo «Mi QR») y el
             presupuesto de textos no paga una clave más. -->
        <button type="button" @click="emit('go', ZONES.DEPENDENTS)">{{ translate(account, 'account.dependents.title') }}</button>
    </p>

    <p v-else-if="notice" class="auth__switch">
        {{ translate(account, 'account.privacy.waiver.pending_notice') }}
        <button type="button" @click="emit('go', ZONES.PRIVACY)">{{ translate(account, 'account.privacy.waiver.pending_cta') }}</button>
    </p>

    <!--
      ⚠️⚠️ **REJILLA DE TARJETAS, y no la fila del catálogo** (`identidad-qr-puerta.md` §9.7 C·1,
      `DECISIONES #217`). El owner recorrió el área en staging y describió el índice como «una lista
      que hay que leer entera»: ocho filas con el mismo peso, el icono a 18 px pegado al texto y una
      flecha idéntica en las ocho. En dos columnas las ocho entran de un vistazo y **el icono pasa a
      ser lo que se reconoce**, que es como se navega un índice que no se lee.

      ⚠️ **`.catalog__item` NO se reutiliza aquí, y esto es lo importante de la clase nueva**: esa
      clase es la fila del CATÁLOGO DE PRODUCTOS del paso 1 del embudo. Reaprovecharla obligaba a
      cambiar sus reglas para que aquí se vieran tarjetas — y con ellas, la primera pantalla de la
      compra. Vocabulario propio (`acc-tile*`), en un bloque aislado al final de `site.css`.

      ⚠️ **La flecha desaparece**: era del sistema de la fila (`catalog__go`), y en una tarjeta
      centrada no señala nada. La tarjeta entera sigue siendo el botón.
    -->
    <div class="acc-tiles">
        <button v-for="zone in HOME_ENTRIES" :key="zone" type="button" class="acc-tile" @click="emit('go', zone)">
            <!--
              ⚠️ El icono se agranda POR CSS (`.acc-tile__ico svg`), no cambiando los `width`/`height`
              del `<svg>`: esos atributos son la copia byte a byte del `<x-icons.*>` del sistema de
              diseño que `SidebarIconParityTest` compara. Tocarlos aquí pondría el gate en rojo y
              dejaría el dibujo del cajón desincronizado del de la web.
            -->
            <span class="acc-tile__ico" aria-hidden="true"><ZoneIcon :zone="zone" /></span>
            <span class="acc-tile__name">{{ translate(account, titleKeyOf(zone)) }}</span>

            <!-- `store.upcoming`, no `upcoming`: sin el prefijo resolvía a `undefined` y el
                 contador NO se pintaba nunca (revisión `#169` §10.3, CAJ-5). -->
            <template v-if="zone === HOME_ENTRIES[0] && store.upcoming > 0">
                <!--
                  ⚠️ El número va `aria-hidden` y su lectura la da el `sr-only` de al lado, con la
                  MISMA clave que usa el bloque `.acct` del panel para lo mismo. Medido en
                  navegador: el primer intento puso ahí el subtítulo de la zona, y un lector de
                  pantalla leía «Mis reservas 1 Aquí tienes tus reservas y su estado» — que no
                  dice qué es ese 1.
                  ⚠️ **Conserva `acct__count`** y solo AÑADE su sitio en la tarjeta: el aspecto de
                  la píldora es el mismo dato en las dos superficies, y duplicar la regla sería la
                  deriva de siempre.
                -->
                <span class="acct__count acc-tile__count" aria-hidden="true">{{ store.upcoming }}</span>
                <span class="sr-only">{{ translateWith(account, 'sidecart.upcoming_count', { count: store.upcoming }) }}</span>
            </template>
        </button>
    </div>

    <!--
      T3a·4 de la analítica · el aviso de que la navegación puede vincularse a la cuenta, a las cuentas que
      existían antes de la v3 de la política de cookies (`specs/analitica.md` §4.3). ⚠️ **Va DEBAJO de las
      tarjetas y FUERA de la cadena de avisos de arriba**, y las dos cosas se midieron: en la cadena —«un solo
      aviso a la vez», `#331`— lo tapaba cualquier waiver sin firmar, que puede quedarse semanas así, y con él
      se perdía en silencio el segundo canal de un aviso legal (la sonda lo encontró en la primera cuenta que
      probó). Aquí es información, no una tarea: no compite con lo que tiene plazo y no apila dos alertas en la
      cabecera. Se queda hasta que el titular lo despide; el texto y el rótulo de despedir viajan en el
      contexto, y el botón que lleva a «Privacidad y datos» se llama como la zona, que ya viaja.
    -->
    <!-- ⚠️ Los dos botones van en UNA línea con el punto medio entre ellos: Vue condensa el espacio entre
         elementos y RETIRA el que lleva un salto de línea, así que en dos líneas salían pegados
         («Privacidad y datosEntendido»; lo vio la captura de la sonda). Ir a «Privacidad y datos» desde el
         aviso también lo despide: quien ha ido a donde se opone ya lo ha leído (dos llamadas en el manejador,
         no una función más en el `<script>`: el componente PINTA, `CE-6`). -->
    <p v-if="analyticsNotice" class="auth__switch" role="status">
        {{ analyticsNotice.text }}
        <button type="button" @click="dismissAnalyticsNotice(); emit('go', ZONES.PRIVACY)">{{ translate(account, 'account.privacy.title') }}</button> · <button type="button" @click="dismissAnalyticsNotice">{{ analyticsNotice.dismiss }}</button>
    </p>

    <!--
      `#332` — la salida. Va DEBAJO de las tarjetas y como acción de texto, no como una tarjeta más:
      las de arriba son sitios a los que se va, y esto no lleva a ninguna parte — es lo último que se
      hace, y una tarjeta idéntica a las otras invitaría a pulsarla mirando el icono.
    -->
    <p class="auth__switch">
        <button type="button" :disabled="leaving" @click="leave()">
            {{ translate(account, 'nav.sign_out') }}
        </button>
    </p>
</template>
