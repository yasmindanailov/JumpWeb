<script setup>
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { t as translate } from '../i18n.js';
import { mountTurnstile } from '../turnstile.js';

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
 * ⚠️ **3. Los textos legales llevan HTML del servidor.** `accept_privacy` y `accept_terms` son
 * literales con un `<a href>` cuya URL compone `route()`, y el Blade los pinta con `{!! !!}`. Aquí
 * llegan **ya interpolados** en el payload del montaje y se pintan con `v-html`: es la única forma de
 * emitir el mismo árbol —`<span><a>`— y de no partir un texto legal traducido en trozos. El contenido
 * sale de `lang/` y de `route()`, nunca de una entrada de usuario, así que no hay superficie XSS.
 *
 * ⚠️ **4. El email y el teléfono van en un `.form__row`**, no sueltos: es una fila de dos columnas y
 * su contenedor es un nodo del árbol.
 *
 * ⚠️ **5. La contraseña lleva `.form__hint` DESPUÉS del `.pwd-input`**, con los requisitos. Es un
 * `<small>`, no un `<span>`: el tipo de elemento es contrato (§4.2).
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

    /** El grupo `account`, con `register` entero y sus dos textos legales ya interpolados. */
    account: { type: Object, default: () => ({}) },

    /** Clave pública del anti-bot. Vacía ⟺ apagado ⟺ no se emite el contenedor (detalle 6). */
    turnstileSiteKey: { type: String, default: '' },
});

defineEmits(['submit']);

const name = defineModel('name', { type: String, default: '' });
const email = defineModel('email', { type: String, default: '' });
const phone = defineModel('phone', { type: String, default: '' });
const password = defineModel('password', { type: String, default: '' });
const acceptPrivacy = defineModel('acceptPrivacy', { type: Boolean, default: false });
const acceptTerms = defineModel('acceptTerms', { type: Boolean, default: false });
const marketing = defineModel('marketing', { type: Boolean, default: false });

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

// Sin guarda de clave a propósito: `mountTurnstile` ya devuelve un apaño inerte si falta la clave o
// el nodo, y el componente es un PINTOR — la decisión vive en el módulo, no aquí.
onMounted(() => {
    widget = mountTurnstile(captchaEl.value, {
        sitekey: props.turnstileSiteKey,
        onToken: (token) => { turnstileToken.value = token; },
    });
});

// El padre vacía el token al fallar un envío → hay que pedirle uno nuevo a Cloudflare.
watch(turnstileToken, (v, prev) => { if (v === '' && prev !== '') widget?.reset(); });

// El paso 5 DESTRUYE este componente al cambiar a «Entrar» (`v-else` en `IdentifyStep`), así que esto
// corre de verdad y a menudo: sin `remove()` quedaría un widget huérfano en Cloudflare.
onBeforeUnmount(() => widget?.destroy());

const revealed = ref(false);

const a = (key) => translate(props.account, key);

const fieldErrors = computed(() => props.errors?.fields ?? {});
const summary = computed(() => props.errors?.summary ?? []);
</script>

<template>
    <div class="auth">
        <div class="auth__head">
            <span class="eyebrow">{{ a('register.eyebrow') }}</span>
            <h2 class="auth__title">{{ a('register.title') }}</h2>
            <p class="auth__sub">{{ a('register.subtitle') }}</p>
        </div>

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

            <div class="form__row">
                <div class="form__field">
                    <label class="form__label" for="reg-email">{{ a('register.email') }}</label>
                    <input id="reg-email" v-model="email" type="email" autocomplete="email" required>
                    <span v-if="fieldErrors.email" class="form__error">{{ fieldErrors.email }}</span>
                </div>
                <div class="form__field">
                    <label class="form__label" for="reg-phone">{{ a('register.phone') }}</label>
                    <input id="reg-phone" v-model="phone" type="tel" autocomplete="tel" required>
                    <span v-if="fieldErrors.phone" class="form__error">{{ fieldErrors.phone }}</span>
                </div>
            </div>

            <div class="form__field">
                <label class="form__label" for="reg-password">{{ a('register.password') }}</label>
                <div class="pwd-input">
                    <input id="reg-password" v-model="password" :type="revealed ? 'text' : 'password'"
                           autocomplete="new-password" required>
                    <button type="button" class="pwd-input__toggle" tabindex="-1" @click="revealed = ! revealed">
                        <svg v-show="! revealed" class="pwd-input__icon" width="18" height="18" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                <path d="M2.036 12.322a1 1 0 0 1 0-.644C3.423 7.512 7.36 4.5 12 4.5s8.577 3.012 9.964 7.178a1 1 0 0 1 0 .644C20.577 16.488 16.64 19.5 12 19.5s-8.577-3.012-9.964-7.178Z"
                                      stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" />
                                <circle cx="12" cy="12" r="3" stroke="currentColor" stroke-width="1.7" />
                            </svg>
                        <svg v-show="revealed" class="pwd-input__icon" width="18" height="18" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                <path d="M3 3l18 18M10.584 10.587a2 2 0 0 0 2.828 2.83M9.363 5.365A9.466 9.466 0 0 1 12 5c4.64 0 8.577 3.012 9.964 7.178a1 1 0 0 1 0 .644 9.46 9.46 0 0 1-3.07 4.385M6.61 6.61C4.547 7.97 2.999 9.984 2.036 12.178a1 1 0 0 0 0 .644C3.423 16.988 7.36 19.5 12 19.5a9.46 9.46 0 0 0 5.39-1.61"
                                      stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" />
                            </svg>
                    </button>
                </div>
                <span v-if="fieldErrors.password" class="form__error">{{ fieldErrors.password }}</span>
                <small class="form__hint">{{ a('register.password_hint') }}</small>
            </div>

            <div class="form__checks">
                <label class="check">
                    <input v-model="acceptPrivacy" type="checkbox">
                    <!-- eslint-disable-next-line vue/no-v-html -- literal de `lang/` + `route()`, sin entrada de usuario -->
                    <span v-html="a('register.accept_privacy')"></span>
                </label>
                <span v-if="fieldErrors.accept_privacy" class="form__error">{{ fieldErrors.accept_privacy }}</span>

                <label class="check">
                    <input v-model="acceptTerms" type="checkbox">
                    <!-- eslint-disable-next-line vue/no-v-html -- literal de `lang/` + `route()`, sin entrada de usuario -->
                    <span v-html="a('register.accept_terms')"></span>
                </label>
                <span v-if="fieldErrors.accept_terms" class="form__error">{{ fieldErrors.accept_terms }}</span>

                <label class="check check--opt">
                    <input v-model="marketing" type="checkbox">
                    <span>{{ a('register.marketing') }}</span>
                </label>
            </div>

            <div v-if="turnstileSiteKey" ref="captchaEl"></div>

            <button type="submit" class="btn btn--zone auth__submit" :disabled="submitting">
                <span v-show="! submitting">{{ a('register.submit') }}</span>
                <span v-show="submitting" class="btn__loading">
                    <span class="jj-spinner jj-spinner--xs" aria-hidden="true"></span> {{ a('register.submitting') }}
                </span>
            </button>
        </form>
    </div>
</template>
