<script setup>
import { useReservationsStore } from '../../stores/reservations.js';
import { useAccountContextStore } from '../../stores/accountContext.js';
import { HOME_ENTRIES, ZONES, titleKeyOf } from '../navigation.js';
import { WAIVER_NOTICE_VERIFY, waiverNoticeFrom } from '../waiver.js';
import { useAuthStore } from '../../stores/auth.js';
import { api } from '../../api.js';
import { computed, watch } from 'vue';
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
});

const emit = defineEmits(['go']);

const store = useReservationsStore();

store.ensure();

// Fase 6 · waiver (§4.8): «la re-firma se pide al entrar». Lo dice el contexto de cuenta —el que se
// repinta al conseguir sesión—, y la decisión de avisar vive en `account/waiver.js`, no aquí.
const context = useAccountContextStore();

// `#327` — el aviso son DOS y dicen cosas distintas: `sign` lleva a firmar; `verify` es «ya la
// aceptaste, falta que verifiques tu correo» y ofrece REENVIARLO, que es lo único que desbloquea la
// firma (`POST /me/waiver` responde 409 sin el correo verificado). Cuál toca lo decide `waiver.js`.
const auth = useAuthStore();
const notice = computed(() => waiverNoticeFrom(context.context));

// ⚠️ El contador se arma con un `watch` inmediato y no en el `setup`: el contexto de cuenta llega
// por red, así que al montarse la zona `notice` todavía vale `null` — armarlo una sola vez aquí
// dejaría el botón muerto justo en el caso que existe para resolver. El store lleva su propio
// pestillo, así que repetirlo no rellena el cupo de reenvíos.
watch(notice, (kind) => {
    if (kind === WAIVER_NOTICE_VERIFY) auth.allowVerificationResend();
}, { immediate: true });

const resend = () => auth.resendVerification({ api });
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
      El aviso del waiver (Fase 6) — y son DOS (`#327`):
       · `verify`: la aceptó al registrarse y falta que verifique su correo. Ofrece REENVIARLO, no
         firmar: `POST /me/waiver` responde 409 sin el correo verificado, así que el botón de firmar
         que había aquí solo podía dar error.
       · `sign`: hay que firmar o re-firmar. Lleva a la tarjeta de privacidad, como siempre.
    -->
    <p v-if="notice === WAIVER_NOTICE_VERIFY" class="auth__switch">
        {{ translate(account, 'account.privacy.waiver.status_awaiting_verification') }}
        <button type="button" :disabled="auth.resendSeconds > 0 || auth.resendsLeft < 1" @click="resend">
            <template v-if="auth.resendSeconds > 0">{{ translate(account, 'account.verify.resend_in') }} {{ auth.resendSeconds }}s</template>
            <template v-else>{{ translate(account, 'account.verify.resend') }}</template>
        </button>
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
</template>
