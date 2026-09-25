<script setup>
import { computed, ref, watch } from 'vue';
import ConfirmInline from '../ConfirmInline.vue';
import { usePrivacyStore } from '../../stores/privacy.js';
import { useWaiverStore } from '../../stores/waiver.js';
import { useAccountContextStore } from '../../stores/accountContext.js';
import ZoneLoading from '../ZoneLoading.vue';
import { consentRows } from '../privacy.js';
import { waiverNeedsSignature, waiverStatusKey } from '../waiver.js';
import { fieldError } from '../form-outcome.js';
import { t as translate, tp as translateWith } from '../../i18n.js';
import PasswordInput from '../../steps/PasswordInput.vue';
import NoPasswordHint from '../NoPasswordHint.vue';
import WaiverDoc from '../../WaiverDoc.vue';

/**
 * **Privacidad y datos**: los dos derechos RGPD del titular (`specs/area-cliente.md` §9, paso 8).
 *
 * **Pinta y recoge; no decide nada.** Componer el documento, comprobar la contraseña y purgar la
 * cuenta es del SERVIDOR; `stores/privacy.js` coloca lo que responda.
 *
 * ⚠️ **La confirmación nativa antes de borrar no es adorno**: es la ÚNICA acción irreversible del
 * producto, y la web la lleva desde siempre (`wire:confirm`). Quitarla aquí habría dejado el borrado
 * a un clic de distancia justo en la superficie donde el cliente está tocando otras cosas.
 *
 * ⚠️ **Al borrar bien se SALE de la página**, no se repinta el cajón: la sesión ya no vale —el
 * servidor revoca todas las credenciales— y todo lo que hay alrededor (cabecera, bloque de cuenta,
 * cesta) habla de una cuenta que acaba de dejar de existir. La web hace lo mismo (`redirect('/')`) y
 * el aviso de despedida lo deja el servidor en la sesión nueva, así que los dos caminos acaban igual.
 *
 * ⚠️ **Y el campo NO se vacía al rechazar**, igual que en las otras tres pantallas que reconfirman
 * contraseña: quien se equivoca escribiendo tiene que poder corregir, no volver a teclear. La primera
 * versión lo vaciaba y el recorrido de navegador lo cazó (`V10`) — vaciarlo solo tiene sentido al
 * SALIR bien, y aquí salir bien significa que esta página deja de existir.
 */
const props = defineProps({
    account: { type: Object, default: () => ({}) },
    /** El grupo `ui`: el rótulo del spinner mientras la zona trae sus datos. */
    ui: { type: Object, default: () => ({}) },
    messages: { type: Object, default: () => ({}) },
    auth: { type: Object, default: () => ({}) },
});

const store = usePrivacyStore();

// Al entrar se limpia lo que dijo el servidor la vez anterior, como en las demás zonas.
store.reset();

// Y se piden los consentimientos, solo si no están: la lista es contexto, no cambia sola.
store.ensureConsents();

const consents = computed(() => consentRows(store.consents, { revokedWord: a('account.privacy.consent_revoked') }));

// **El interruptor de marketing** (art. 7.3, `#344`), que hasta hoy no existía: el consentimiento se
// daba en el alta y no había forma de retirarlo. El estado y su lectura viven en el store —el techo
// de componentes manda, y esto es una pantalla que PINTA—; de dónde sale, en su getter.
store.ensureMarketing();

const current = ref('');

const a = (key) => translate(props.account, key);

const ctx = () => ({ messages: props.messages, auth: props.auth });

/**
 * **BORRAR LA CUENTA — la única acción irreversible del producto** (`#565`, grieta 09).
 *
 * ⚠️⚠️ Se preguntaba con `window.confirm`: lo pinta el NAVEGADOR, con su tipografía y un
 * «Aceptar/Cancelar» que no habla nuestros tres idiomas, y encima del cajón — por un segundo la
 * pantalla que estabas usando deja de ser tuya. Hoy la pregunta se hace dentro, con la pieza que ya
 * existía en «renovar mi QR» y el botón de confirmar en el rojo de error.
 *
 * ⚠️ El formulario deja de enviar directamente: su `submit` ABRE la pregunta. La contraseña se sigue
 * validando donde se validaba —en el servidor—, y lo que cambia es que entre teclearla y perder la
 * cuenta hay un gesto más, que es lo que esta pantalla necesitaba.
 */
async function remove() {
    if (await store.deleteAccount({ currentPassword: current.value }, ctx())) window.location.assign('/');
}

/**
 * **El waiver** (Fase 6, `specs/waiver-probatorio.md` §4.5, §4.8): el estado según el modo, la firma
 * —o la re-firma cuando el texto cambió— y mis PDF. Qué frase y si se ofrece firmar lo decide
 * `account/waiver.js` sobre lo que el servidor publica; aquí solo se pinta. Tras firmar se refresca el
 * contexto de cuenta, que es donde el índice lee «tienes pendiente el waiver».
 */
const waiver = useWaiverStore();
waiver.reset(); waiver.ensureStatus(); waiver.ensureLegal();
const acceptWaiver = ref(false);
// CAJ-REREAD (`#181`): volver a marcar tras releer apaga la relectura — un fallo posterior que no sea de texto no desmarca.
watch(acceptWaiver, (v) => { if (v) waiver.reread = false; });
const waiverText = computed(() => translateWith(props.account, waiverStatusKey(waiver.status), { version: waiver.status?.version ?? '' }));

// Tras un fallo, la casilla solo se desmarca si el texto se RELEYÓ (409 `waiver_document_stale`): lo
// que se leyó ya no es lo que se firma, y un segundo clic no puede firmar sin volver a marcar (CAJ-3).
async function sign() {
    if (! await waiver.accept(ctx())) { if (waiver.reread) acceptWaiver.value = false; return; }
    acceptWaiver.value = false;
    useAccountContextStore().refresh();
}
</script>

<template>
    <!--
      ⚠️⚠️ **Las DOS secciones van en tarjeta, y son las que la página retirada ya tenía**
      (2026-08-23). Hasta hoy colgaban de un `<div class="auth">` que **no tiene ninguna regla**, así
      que la de descargar y la de borrar salían pegadas, sin separación ni jerarquía: dos derechos
      distintos leídos como un bloque. `/mi-cuenta` las pintaba con `account__card` y
      `account__card--danger`, y ésas son las que se usan aquí.
      ▶ `account__grid` es quien pone la separación (su `gap`), así que **el ritmo no lo inventa esta
      pantalla**: lo hereda de la que sustituye.
    -->
    <div class="auth account__grid">
        <p class="purchase__note">{{ a('account.privacy.intro') }}</p>

        <div v-if="store.notice" class="auth__errors" role="alert"><p>{{ store.notice }}</p></div>

        <!--
          La prueba visible del art. 7.1: a qué dijo que sí, cuándo y sobre qué versión. Se publica
          porque `/mi-cuenta` lo enseña y esa página se retira (tanda 3).
          ⚠️ Sin IP, igual que la página: es parte de la prueba y viaja en el export, que es un acto
          explícito del titular.
        -->
        <section class="account__card">
            <h3 class="account__card-title">{{ a('account.privacy.consents_title') }}</h3>

                <!-- ⚠️ El spinner ANTES del «no hay consentimientos»: mientras se piden, decir que no hay
                 es decir algo falso. -->
            <ZoneLoading v-if="store.consentsLoading && ! store.consentsLoaded" :ui="ui" />

            <p v-else-if="store.consentsLoaded && ! consents.length" class="account__card-sub">{{ a('account.privacy.no_consents') }}</p>

            <!--
              ⚠️⚠️ **La meta va en TROZOS y no en una cadena** (`#346`). `#344` pegó «retirado el …»
              al final de la misma línea, que se pinta con `white-space: nowrap` desde que solo
              tenía fecha y versión: medido en navegador, **82 px de desborde** en el carril del
              cajón y una barra de scroll horizontal que aparecía **al pulsar el interruptor**.
              Cada trozo sigue siendo indivisible —una fecha no se parte— y entre trozos ya se
              puede saltar de línea. El «·» lo dibuja la hoja, así que un trozo que falte no deja
              separador colgando.
            -->
            <ul v-else-if="consents.length" class="account__consents">
                <li v-for="consent in consents" :key="consent.key"
                    :class="{ 'account__consent--revoked': consent.revoked }">
                    <span class="account__consent-type">{{ consent.label }}</span>
                    <span class="account__consent-meta">
                        <span v-for="(part, i) in consent.parts" :key="i" class="account__consent-part">{{ part }}</span>
                    </span>
                </li>
            </ul>

            <!--
              ⚠️⚠️ **El interruptor del art. 7.3, y va AQUÍ por dos razones que se refuerzan.** La
              primera es de significado: la lista de arriba dice a qué se dijo que sí, y el único de
              esos consentimientos que se puede retirar es éste — separarlo en otra tarjeta habría
              contado dos veces la misma historia. La segunda es que así **no necesita título propio**,
              y el rótulo que se ahorra son 47 B en cada página con sesión (`SidebarMountTest`).
              ▶ Y va **antes del borrado**: retirar el marketing es la salida proporcionada para quien
              no quiere que le escriban, y tenerla debajo de «eliminar mi cuenta» empuja a la
              irreversible a quien solo quería dejar de recibir correos.
            -->
            <!--
              ⚠️⚠️ **Es un INTERRUPTOR, no una casilla** (`[DECIDIDO owner, 2026-09-02]`, `#346`), y la
              diferencia no es estética: una casilla es una elección que se ENVÍA con un formulario y
              esto se guarda **al soltarlo**, sin botón. `role="switch"` es lo que se lo dice al
              lector de pantalla — con `checkbox` anuncia «casilla, no marcada» y quien no ve espera
              un «Guardar» que no existe.
              ⚠️ El control es el propio `<input>` con `appearance: none`: así el anillo de foco cae
              sobre su caja real, que es la que `landing.css` ya tiene en su lista blanca cerrada
              (`input[type="checkbox"]:focus-visible`). Un input escondido a 0×0 con la pista pintada
              al lado dibujaría el anillo sobre nada — y la trampa de `#295` es justo ésa: hacer algo
              enfocable no es hacerlo accesible.
            -->
            <label class="switch">
                <input class="switch__input" type="checkbox" role="switch"
                       :checked="store.marketing" :disabled="store.busy"
                       @change="store.setMarketing($event.target.checked)">
                <span class="switch__label">{{ a('account.privacy.marketing_label') }}</span>
            </label>
            <small class="form__hint">{{ a('account.privacy.marketing_hint') }}</small>

            <!--
              **El análisis de uso vinculado a la cuenta** (art. 21, `specs/analitica.md` §4.3, T3a·3): el
              segundo interruptor, con la misma pieza y por las mismas razones. Encendido = «vincula»; el
              consentimiento real es la categoría «análisis» del aviso de cookies, y esto es la puerta de la
              cuenta para retirarlo. Al apagarlo el servidor desvincula, sella la prueba y pide el olvido.
            -->
            <label class="switch">
                <input class="switch__input" type="checkbox" role="switch"
                       :checked="store.analytics" :disabled="store.busy"
                       @change="store.setAnalytics($event.target.checked)">
                <span class="switch__label">{{ a('account.privacy.analytics_label') }}</span>
            </label>
            <small class="form__hint">{{ a('account.privacy.analytics_hint') }}</small>

            <!--
              **La encuesta del día siguiente** (`specs/encuestas.md` §4.3, T3): el tercer interruptor, con la
              misma pieza. Es un correo de SERVICIO, no marketing: sin consentimiento que sellar, solo la
              preferencia; la baja de un toque del propio correo apaga esto mismo.
            -->
            <label class="switch">
                <input class="switch__input" type="checkbox" role="switch"
                       :checked="store.surveys" :disabled="store.busy"
                       @change="store.setSurveys($event.target.checked)">
                <span class="switch__label">{{ a('account.privacy.surveys_label') }}</span>
            </label>
            <small class="form__hint">{{ a('account.privacy.surveys_hint') }}</small>

            <!-- El derecho de PORTABILIDAD (art. 20). No pide contraseña: descargarse los datos propios
                 no destruye ni cede nada, y es lo que hace hoy la web. -->
            <button type="button" class="btn btn--ink auth__submit" :disabled="store.busy" @click="store.exportData(ctx())">
                {{ a('account.privacy.export_btn') }}
            </button>

            <p v-if="store.savedAs" class="account__card-sub" role="status">{{ store.savedAs }} ✓</p>
        </section>

        <!-- El WAIVER (Fase 6): solo si la instalación lo comprueba. En modo interno, la firma o la
             re-firma se hacen AQUÍ —nunca en el mostrador—, y cada firma tiene su PDF. -->
        <section v-if="waiver.status && waiver.status.mode !== 'desactivado'" class="account__card">
            <h3 class="account__card-title">{{ a('account.privacy.waiver.title') }}</h3>
            <p class="account__card-sub">{{ waiverText }}</p>

            <div v-if="waiver.notice" class="auth__errors" role="alert"><p>{{ waiver.notice }}</p></div>

            <form v-if="waiverNeedsSignature(waiver.status) && waiver.document" class="form auth__form" novalidate @submit.prevent="sign">
                <WaiverDoc :document="waiver.document" :label="a('register.waiver_read')" />
                <div class="form__checks">
                    <label class="check">
                        <input v-model="acceptWaiver" type="checkbox">
                        <span>{{ a('register.accept_waiver') }}</span>
                    </label>
                </div>
                <button type="submit" class="btn btn--ink auth__submit" :disabled="waiver.busy || ! acceptWaiver">
                    {{ waiver.busy ? a('account.privacy.waiver.signing') : a('account.privacy.waiver.sign_btn') }}
                </button>
            </form>

            <p v-if="waiver.done" class="account__card-sub" role="status">{{ a('account.privacy.waiver.signed_ok') }}</p>

            <ul v-if="waiver.signatures.length" class="account__consents">
                <li v-for="signature in waiver.signatures" :key="signature.id">
                    <span class="account__consent-type">{{ signature.version_label }}<template v-if="signature.declared"> · {{ a('account.privacy.waiver.declared') }}</template></span>
                    <span class="account__consent-meta">{{ signature.accepted_label }} · <a :href="signature.pdf_url" target="_blank" rel="noopener">{{ a('account.privacy.waiver.pdf') }}</a></span>
                </li>
            </ul>
        </section>

        <!-- El derecho de SUPRESIÓN (art. 17). En su propia tarjeta y con el borde de peligro que la
             página retirada ya le daba: es irreversible y no debe leerse como una opción más. -->
        <section class="account__card account__card--danger">
            <h3 class="account__card-title">{{ a('account.privacy.delete_title') }}</h3>
            <p class="account__card-sub">{{ a('account.privacy.delete_intro') }}</p>

            <!-- ⚠️⚠️ **Aquí el disparador es el FORMULARIO ENTERO** (`#565`), no un botón: se envía
                 con su botón y también con la tecla Intro desde el campo de la contraseña, y las dos
                 vías tienen que preguntar. Al abrirse, el formulario desaparece y la pregunta ocupa
                 su sitio —que es lo que la pieza hace con cualquier disparador—; la contraseña
                 tecleada **sobrevive**, porque vive en el estado de la zona y no en el DOM.
                 ▶ Y de paso la pregunta no compite con el campo: lo único en pantalla es la decisión. -->
            <ConfirmInline id="acct-delete-q"
                           :question="a('account.privacy.delete_confirm')"
                           :confirm-label="a('account.privacy.delete_confirm_yes')"
                           :cancel-label="a('account.privacy.delete_confirm_no')"
                           :busy-label="a('account.privacy.deleting')"
                           :busy="store.busy" danger
                           @confirm="remove">
                <template #trigger="{ ask }">
                    <form class="form auth__form" novalidate @submit.prevent="ask">
                        <div class="form__field">
                            <label class="form__label" for="acct-delete-password">{{ a('account.privacy.delete_password') }}</label>
                            <PasswordInput :id="'acct-delete-password'" v-model="current" autocomplete="current-password" />
                            <span v-if="fieldError(store.fields, 'current_password')" class="form__error">{{ fieldError(store.fields, 'current_password') }}</span>
                            <NoPasswordHint :account="account" />
                        </div>

                        <button type="submit" class="btn account__delete-btn" :disabled="store.busy">
                            {{ store.busy ? a('account.privacy.deleting') : a('account.privacy.delete_btn') }}
                        </button>
                    </form>
                </template>
            </ConfirmInline>
        </section>
    </div>
</template>
