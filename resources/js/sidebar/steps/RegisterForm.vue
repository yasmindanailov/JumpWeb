<script setup>
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { t as translate } from '../i18n.js';
import BornOnField from './BornOnField.vue';
import GoogleButton from './GoogleButton.vue';
import WaiverDoc from '../WaiverDoc.vue';
import { mountTurnstile } from '../turnstile.js';
import { useWaiverStore } from '../stores/waiver.js';

/**
 * El formulario de ALTA del paso 5 (Fase 4 · paso 4.4b·1).
 *
 * Es el más largo del cajón —51 nodos— y el que más detalles tiene que no se adivinan leyendo el
 * Blade. Los cinco que importan:
 *
 * ⚠️ **1. El HONEYPOT es el primer nodo del formulario y es CONTRATO.** `.hp` lo oculta el CSS
 * (`display: none`, para que el autocompletar no lo rellene) y su `aria-hidden` lo esconde del lector
 * de pantalla. Un motor que no lo emitiera dejaría al servidor sin su señuelo, y **el diff de árbol es
 * lo único que puede verlo**: el campo no se ve, no se rellena y no cambia nada visible.
 *
 * ⚠️ **2. El banner lista TODOS los avisos y además cada uno va bajo su campo.** Las dos cosas, no una
 * (A11y de formulario largo): el banner deja ver el conjunto y los de debajo permiten corregir uno a
 * uno. El banner lleva `<strong>` + `<ul>`, y el número de `<li>` es parte del árbol.
 *
 * ⚠️⚠️ **3. El texto legal ya NO lleva HTML, desde `#566`** (grieta 13, `[DECIDIDO owner]`). Hasta
 * entonces `privacy_notice` era un literal con un `<a href>` interpolado por el servidor y se pintaba
 * con `v-html`; medido a 390 px, **ese enlace daba 20 px de alto contra el suelo táctil de 48** que
 * declara el propio producto. Hoy el párrafo es TEXTO y el documento se abre desde su propia fila
 * —la receta de «Ver más fechas» y «Leer las condiciones», compartida y no copiada—, con la URL
 * viajando suelta en `urls.privacy`.
 * ▶ Y al quedarse en texto plano desaparece el `v-html`: un literal de `lang/` que ya no trae marcado
 * no necesita inyectarse como HTML. ⚠️ **Si alguien le devuelve el `<a>` al literal, saldría ESCRITO
 * en pantalla** —y ni el diff de árbol ni la suite lo verían, porque para los dos es texto—; lo
 * vigilan `SidebarAuthScreensTest` y `PrivacyNoticeIsVisibleTest`.
 *
 * ⚠️⚠️ **Y ya NO es una casilla, desde la T8·c** (`[DECIDIDO owner, 2026-09-02]`,
 * `specs/auth-con-google.md` §21.4.3): el art. 13 del RGPD pide **informar**, no que se acepte, y la
 * base legal de una reserva es el contrato (art. 6.1.b). El enlace queda visible y el rastro lo
 * escribe el servidor igual —`privacy_accepted_at` y su fila de `consents`—: *lo que desaparece es la
 * casilla, no la constancia*. Las condiciones se fueron al checkout y el marketing al interruptor de
 * «Mi cuenta → Privacidad», así que aquí solo queda la casilla del DESCARGO.
 *
 * ⚠️⚠️ **4. Es la TERCERA CARA de la puerta, desde la A4a** (`docs/specs/acceso-con-codigo.md` §4.11, `#849`): se llega
 * cuando el correo NO tiene cuenta, así que el correo ya está escrito —va a la vista, con «Cambiar», que vuelve a la
 * puerta— y el subtítulo lo dice («Aún no tienes cuenta con ese correo…»). Sin CONTRASEÑA (la cuenta nace sin ella y se
 * entra con un código) y sin TELÉFONO: lo pide el paso de pagar cuando el pedido lo exige (`buyer-due.js`, `#787`).
 * ▶ Y por eso se fueron la fila `.form__row` de correo y teléfono y el `<small>` de los requisitos de la contraseña.
 *
 * ⚠️⚠️ **6. El contenedor del anti-bot va con `v-if` y PELADO** (4.4b·2). Las dos cosas son contrato
 * de árbol, medidas contra el manifiesto congelado:
 * · **`v-if`**, porque el Blade lo envuelve en `@if ($turnstileEnabled)` y la suite NUNCA siembra las
 *   claves `security.turnstile_*` → con el anti-bot apagado el motor Livewire no emite NADA ahí. Un
 *   `<div>` incondicional pone en rojo el diff de árbol Y el manifiesto a la vez.
 * · **Sin clase, sin id, sin `data-*`**: el normalizador del diff SÍ imprime las clases, y el nodo
 *   Livewire es un `<div>` a secas. Además `class="cf-turnstile"` no serviría de nada: el auto-render
 *   de Cloudflare solo ve los `.cf-turnstile` presentes en la carga inicial, no los inyectados — por
 *   eso los dos motores renderizan EXPLÍCITAMENTE sobre el nodo.
 * · Y va por `ref`, no por `querySelector`: con el anti-bot activo hay **dos** widgets vivos en la
 *   página (este y el del modal de la cabecera), y un selector se llevaría el que no es.
 */
const props = defineProps({
    /** Avisos del intento anterior: `{summary, fields}` (`register.js`). */
    errors: { type: Object, default: () => ({ summary: [], fields: {} }) },

    /** `true` mientras la petición está en vuelo: cambia el rótulo del botón. */
    submitting: { type: Boolean, default: false },

    /** El grupo `account`, con `register` entero y su texto legal. */
    account: { type: Object, default: () => ({}) },

    /**
     * La POLÍTICA DE PRIVACIDAD, para la fila que la abre (`#566`).
     *
     * ⚠️ Viaja SUELTA y no dentro del literal, por el mismo motivo que las condiciones en `#562`: al
     * salir el enlace de la frase, quien decide el slug es `routes/web.php`, y un `href` no es
     * atributo de contrato del diff de árbol — un enlace roto aquí pasaría el gate en verde.
     */
    privacyUrl: { type: String, default: '' },

    /** Clave pública del anti-bot. Vacía ⟺ apagado ⟺ no se emite el contenedor (detalle 6). */
    turnstileSiteKey: { type: String, default: '' },

    /**
     * La ida a Google. Vacía = esta instalación no la ofrece y no se pinta nada, ni botón ni separador.
     *
     * ⚠️ **Va DENTRO del formulario y encima de sus campos** (T8·d, `#350`, `[DECIDIDO owner]`): la misma decisión que
     * en la primera cara de la puerta (`EntryForm.vue`): el camino alternativo, después del título y antes de los campos.
     */
    googleUrl: { type: String, default: '' },
});

defineEmits(['submit', 'change-email']);

const name = defineModel('name', { type: String, default: '' });
/** El correo de la PUERTA: se enseña, no se edita (para cambiarlo, «Cambiar» vuelve a ella). */
const email = defineModel('email', { type: String, default: '' });
const bornOn = defineModel('bornOn', { type: String, default: '' });

/**
 * ⚠️ **7. La casilla del waiver solo existe si hay TEXTO que firmar** (Fase 6,
 * `specs/waiver-probatorio.md` §4.4): `GET /legal/waiver` se pide al MONTAR —nunca en el SSR del
 * contrato de árbol, que no ejecuta `onMounted`—, y con `document: null` (modo externo, o sin versión
 * publicada) no se emite ningún nodo, así que el manifiesto congelado del alta sigue valiendo. Separada
 * de privacidad y condiciones y desmarcada por defecto: es aceptación contractual, no consentimiento RGPD.
 */
const acceptWaiver = defineModel('acceptWaiver', { type: Boolean, default: false });
const waiverStore = useWaiverStore();

/** El señuelo. Un cliente legítimo lo deja vacío; que exista es lo que hace que sirva. */
const website = defineModel('website', { type: String, default: '' });

/**
 * El token del anti-bot. Lo escribe Cloudflare por callback, no el usuario.
 *
 * ⚠️ El padre lo VACÍA tras un envío fallido, y ese vaciado es la señal de «pide uno nuevo»: el token
 * es de un solo uso y el servidor lo quema ANTES de mirar si el correo ya existe, así que reenviar con
 * el mismo daría «no eres un robot» con el tick verde puesto y sin salida salvo recargar.
 */
const turnstileToken = defineModel('turnstileToken', { type: String, default: '' });

const captchaEl = ref(null);
let widget = null;

// Nodo y clave van como FUNCIONES, no como valores: en `/registro` este componente se monta ANTES de
// que `GET /config` traiga la clave, y el contenedor (`v-if`) ni existe todavía. `mountTurnstile` los
// re-lee en cada sondeo y monta cuando ambos llegan (`DECISIONES #169`). El componente sigue siendo un
// PINTOR: la decisión —esperar, cuándo cargar el script, cuándo rendirse— vive en el módulo.
onMounted(() => {
    widget = mountTurnstile(() => captchaEl.value, {
        sitekey: () => props.turnstileSiteKey,
        onToken: (token) => { turnstileToken.value = token; },
    });
    waiverStore.ensureLegal();
});

// El padre vacía el token al fallar un envío → hay que pedirle uno nuevo a Cloudflare.
watch(turnstileToken, (v, prev) => { if (v === '' && prev !== '') widget?.reset(); });

// El paso 5 DESTRUYE este componente al cambiar a «Entrar» (`v-else` en `IdentifyStep`), así que esto
// corre de verdad y a menudo: sin `remove()` quedaría un widget huérfano en Cloudflare.
onBeforeUnmount(() => widget?.destroy());


const a = (key) => translate(props.account, key);

const fieldErrors = computed(() => props.errors?.fields ?? {});
const summary = computed(() => props.errors?.summary ?? []);
</script>

<template>
    <div class="auth">
        <!-- Sin antetítulo (`#566`): el «Únete» era la costura del modal; en las otras pantallas el cajón titula y punto. -->
        <div class="auth__head">
            <h2 class="auth__title">{{ a('register.title') }}</h2>
            <p class="auth__sub">{{ a('register.subtitle') }}</p>
        </div>

        <!-- Crear la cuenta con Google: cuatro campos y una contraseña menos. Encima del formulario y
             con su «o» (T8·d, `#350`). -->
        <GoogleButton :href="googleUrl" :label="a('register.google_cta')" :separator="a('register.or')" />

        <form class="form auth__form" novalidate @submit.prevent="$emit('submit')">
            <!-- ⚠️ El señuelo. Lo oculta el CSS, no un atributo: si estuviera `hidden` o fuera de la
                 vista por `type`, un bot lo detectaría igual de rápido que un humano no lo ve. -->
            <div class="hp" aria-hidden="true">
                <label>{{ a('register.leave_blank') }}
                    <input v-model="website" type="text" tabindex="-1" autocomplete="off">
                </label>
            </div>

            <div v-if="summary.length > 0" class="auth__errors" role="alert">
                <strong>{{ a('register.fix_errors') }}</strong>
                <ul>
                    <li v-for="(message, i) in summary" :key="i">{{ message }}</li>
                </ul>
            </div>

            <div class="form__field">
                <label class="form__label" for="reg-name">{{ a('register.name') }}</label>
                <input id="reg-name" v-model="name" type="text" autocomplete="name" required>
                <span v-if="fieldErrors.name" class="form__error">{{ fieldErrors.name }}</span>
            </div>

            <!-- El correo de la PUERTA, a la vista (detalle 4): «Cambiar» vuelve a ella. Su aviso, si el servidor dice algo
                 de él (la cuenta nació entre la puerta y el alta), va aquí debajo. -->
            <div class="form__field">
                <span class="form__label">{{ a('register.email') }}</span>
                <!-- ⚠️ El correo como TEXTO, nunca `v-html`: lo escribió el cliente. Clases que ya existen (`auth__sent`,
                     `auth__link`): ni una regla nueva en la hoja. -->
                <p class="auth__sent">
                    {{ email }}
                    <button type="button" class="auth__link" @click="$emit('change-email')">{{ a('login.change_email') }}</button>
                </p>
                <span v-if="fieldErrors.email" class="form__error">{{ fieldErrors.email }}</span>
            </div>

            <!-- ⚠️ **8. La fecha de nacimiento, OPCIONAL** (TP·1, `#792`): es un dato de la persona. -->
            <BornOnField id="reg-born-on" v-model="bornOn" :account="account" :error="fieldErrors.born_on ?? ''" />

            <div class="form__checks">
                <!-- El enlace de privacidad, VISIBLE y sin casilla (detalle 3). Mismo literal y mismo
                     tratamiento que en la pantalla de completar el alta con Google: es la misma
                     información, y una segunda redacción acabaría diciendo otra cosa. -->
                <p class="form__hint">{{ a('register.privacy_notice') }}</p>

                <!-- ⚠️⚠️ **El enlace SALE de la frase** (`#566`, `[DECIDIDO owner]`, grieta 13): metido
                     dentro medía **20 px** de alto contra el suelo táctil de **48** que declara el
                     producto, y es el único sitio donde las dos altas enseñan la política. Es la misma
                     regla que la parada 03 aplicó al descargo y `#562` a las condiciones del paso de
                     pagar — y la MISMA receta, compartida y no copiada.
                     ▶ Lleva `arrow-right` y no el chevron: esto SALE a otra página, no despliega aquí.
                     ⚠️ Al quedarse el párrafo en texto plano desaparece el `v-html`: un literal de
                     `lang/` que ya no trae marcado no necesita inyectarse como HTML. -->
                <a :href="privacyUrl" target="_blank" rel="noopener" class="cal-more legal-more">
                    <span>{{ a('register.privacy_read') }}</span>
                    <!-- `arrow-right` del set, copiado byte a byte (`SidebarIconParityTest`). -->
                    <svg class="arrow-ico legal-more__ico" viewBox="0 0 24 24" fill="currentColor"
                         stroke="currentColor" stroke-width="3" stroke-linejoin="round" stroke-linecap="round"
                         aria-hidden="true" focusable="false">
                        <path d="M13.6 6.4 19.2 12l-5.6 5.6z" />
                        <path d="M4.6 12h9.4" fill="none" />
                    </svg>
                </a>

                <!-- Detalle 7: solo con texto firmable en memoria. Sin él, ni casilla ni nodo. -->
                <template v-if="waiverStore.document">
                    <label class="check">
                        <input v-model="acceptWaiver" type="checkbox">
                        <span>{{ a('register.accept_waiver') }}</span>
                    </label>
                    <!-- Qué es el descargo, junto a la casilla (`#588`, contenido T5): sola pedía aceptar un
                         documento sin decir para qué sirve. -->
                    <small class="form__hint">{{ a('register.waiver_hint') }}</small>
                    <span v-if="fieldErrors.accept_waiver || fieldErrors.waiver_document_id" class="form__error">{{ fieldErrors.accept_waiver || fieldErrors.waiver_document_id }}</span>
                    <WaiverDoc :document="waiverStore.document" :label="a('register.waiver_read')" />
                </template>
            </div>

            <div v-if="turnstileSiteKey" ref="captchaEl"></div>

            <button type="submit" class="btn btn--ink auth__submit" :disabled="submitting">
                <span v-show="! submitting">{{ a('register.submit') }}</span>
                <span v-show="submitting" class="btn__loading">
                    <span class="jj-spinner jj-spinner--xs" aria-hidden="true"></span> {{ a('register.submitting') }}
                </span>
            </button>
        </form>
    </div>
</template>
