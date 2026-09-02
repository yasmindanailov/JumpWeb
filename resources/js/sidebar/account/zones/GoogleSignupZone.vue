<script setup>
import { onMounted, ref } from 'vue';
import { useAuthStore } from '../../stores/auth.js';
import { useWaiverStore } from '../../stores/waiver.js';
import { emptyGoogleScreen, loadGoogleScreen, submitGoogleScreen } from '../google.js';
import { landOnAccount } from '../after-auth.js';
import { api } from '../../api.js';
import { t as translate } from '../../i18n.js';

/**
 * **COMPLETAR un alta que viene de Google** (`specs/auth-con-google.md` §7, tanda T2).
 *
 * ⚠️⚠️ **Esta pantalla existe porque el owner la pidió, contra la recomendación de cero pantallas**
 * (§4): *«no quiero el caso de que el usuario entre con Google y nadie acepte el descargo y todo sea
 * vía tablet en persona»*. La lección que la justifica: **la fricción no desaparece, se muda al
 * empleado** — y sin aceptación previa, el operador de la puerta deja de CONFIRMAR para ACREDITAR.
 *
 * ⚠️ **Se carga en DIFERIDO** (`sections/AccountSection.vue`) y por eso su estado vive aquí y no en
 * el store: lo consume una sola pantalla, así que en el store global sería peso que paga **todo el
 * que abre el cajón** por algo que se ve una vez en la vida. Lo que sí sale del store son los CAMPOS,
 * que se comparten con el alta de siempre y se limpian al salir —PII en un dispositivo compartido—.
 *
 * ⚠️ **El correo se ENSEÑA, no se pide**: es la identidad que Google acaba de verificar. Un campo
 * editable aquí sería crear una cuenta con un correo que nadie ha demostrado.
 *
 * ⚠️ **La privacidad no es casilla** (§7.1): el RGPD pide INFORMAR, y la base legal de una reserva es
 * el contrato. El enlace va visible y el rastro lo escribe el servidor igual.
 */
const props = defineProps({
    /** El grupo `account`: aquí llega además el subgrupo `google`, que viaja SOLO en esta puerta. */
    account: { type: Object, default: () => ({}) },
    /** El grupo `tickets`: el aviso genérico de «inténtalo más tarde». */
    messages: { type: Object, default: () => ({}) },
    /** El grupo `auth`: los literales del «no» del servidor. */
    auth: { type: Object, default: () => ({}) },
    /** Las rutas del servidor: dónde aterrizar al terminar, y la ida a Google si hay que repetir. */
    urls: { type: Object, default: () => ({}) },
});

const store = useAuthStore();
const waiverStore = useWaiverStore();

/**
 * El estado de esta pantalla, y **nada más**: quién espera, si ya no hay nada que completar, si hay
 * una petición en vuelo y qué avisos enseñar. La SECUENCIA vive en `account/google.js`, que es donde
 * el techo de componentes la manda y donde tiene su `node --test` (`DECISIONES #120(r)`).
 */
const ui = ref(emptyGoogleScreen());

const a = (key) => translate(props.account, key);

onMounted(async () => {
    // El texto firmable puede no existir (modo externo o sin versión): entonces no hay casilla. Se
    // pide igual porque el modo lo cambia un operador y esta pantalla no puede asumir el de ayer.
    waiverStore.ensureLegal();

    ui.value = await loadGoogleScreen({ api });

    // El nombre se copia solo si no hay nada escrito: quien vuelve de un 409 ya corrigió el suyo.
    if (ui.value.pending !== null && store.form.name === '') store.form.name = ui.value.pending.name;
});

async function submit() {
    if (ui.value.busy) return;

    ui.value = { ...ui.value, busy: true };

    const { state, result } = await submitGoogleScreen({
        state: ui.value, form: store.form, api, waiver: waiverStore.document,
        messages: props.messages, auth: props.auth,
    });

    ui.value = state;

    // El 409 del descargo RELEE y desmarca: «vuelve a leerlo» solo se cumple releyendo, y lo que se
    // leyó ya no es lo que se firmaría (`#175`, CAJ-2).
    if (result.stale) { store.form.accept_waiver = false; await waiverStore.reloadLegal({ api }); }

    // Con sesión recién abierta se NAVEGA: los textos del área viajan solo con sesión, así que
    // quedarse aquí dejaría la cuenta en blanco. Misma salida que el alta suelta.
    if (result.ok) landOnAccount({ urls: props.urls });
}
</script>

<template>
    <div class="auth">
        <!-- No hay nada que completar: la sesión caducó, ya se hizo, o se llegó de rebote. La única
             salida es empezar otra vez, y por eso es un ENLACE al servidor y no un botón que
             reintentaría algo que ya no existe. -->
        <template v-if="ui.expired">
            <div class="auth__head">
                <h2 class="auth__title">{{ a('google.title') }}</h2>
            </div>
            <p class="auth__sub" role="status">{{ a('google.expired') }}</p>
            <a v-if="urls.google" class="btn btn--zone auth__submit" :href="urls.google">{{ a('google.restart') }}</a>
        </template>

        <template v-else-if="ui.pending">
            <div class="auth__head">
                <span class="eyebrow">{{ a('google.eyebrow') }}</span>
                <h2 class="auth__title">{{ a('google.title') }}</h2>
                <p class="auth__sub">{{ a('google.intro') }}</p>
            </div>

            <form class="form auth__form" novalidate @submit.prevent="submit">
                <div v-if="ui.errors.summary.length > 0" class="auth__errors" role="alert">
                    <strong>{{ a('register.fix_errors') }}</strong>
                    <ul>
                        <li v-for="(message, i) in ui.errors.summary" :key="i">{{ message }}</li>
                    </ul>
                </div>

                <!-- El correo, en TEXTO: es lo que Google ha verificado y no se teclea. -->
                <div class="form__field">
                    <span class="form__label">{{ a('google.email_label') }}</span>
                    <p class="auth__sent">{{ ui.pending.email }}</p>
                    <small class="form__hint">{{ a('google.email_hint') }}</small>
                </div>

                <div class="form__field">
                    <label class="form__label" for="gs-name">{{ a('register.name') }}</label>
                    <input id="gs-name" v-model="store.form.name" type="text" autocomplete="name" required>
                    <span v-if="ui.errors.fields.name" class="form__error">{{ ui.errors.fields.name }}</span>
                </div>

                <div class="form__field">
                    <label class="form__label" for="gs-phone">{{ a('register.phone') }}</label>
                    <input id="gs-phone" v-model="store.form.phone" type="tel" autocomplete="tel" required>
                    <span v-if="ui.errors.fields.phone" class="form__error">{{ ui.errors.fields.phone }}</span>
                </div>

                <div class="form__checks">
                    <label class="check">
                        <input v-model="store.form.accept_terms" type="checkbox">
                        <!-- eslint-disable-next-line vue/no-v-html -- literal de `lang/` + `route()`, sin entrada de usuario -->
                        <span v-html="a('register.accept_terms')"></span>
                    </label>
                    <span v-if="ui.errors.fields.accept_terms" class="form__error">{{ ui.errors.fields.accept_terms }}</span>

                    <!-- El enlace de privacidad, VISIBLE y sin casilla (§7.1). Se reutiliza el literal
                         del alta con contraseña: es la misma información, y una segunda redacción
                         acabaría diciendo otra cosa. -->
                    <!-- eslint-disable-next-line vue/no-v-html -- literal de `lang/` + `route()`, sin entrada de usuario -->
                    <p class="form__hint" v-html="a('register.accept_privacy')"></p>

                    <!-- Solo con texto firmable en memoria. Sin él, ni casilla ni nodo. -->
                    <template v-if="waiverStore.document">
                        <label class="check">
                            <input v-model="store.form.accept_waiver" type="checkbox">
                            <span>{{ a('register.accept_waiver') }}</span>
                        </label>
                        <span v-if="ui.errors.fields.accept_waiver || ui.errors.fields.waiver_document_id" class="form__error">{{ ui.errors.fields.accept_waiver || ui.errors.fields.waiver_document_id }}</span>
                        <details class="form__hint">
                            <summary>{{ a('register.waiver_read') }}</summary>
                            <p v-for="(section, i) in waiverStore.document.sections" :key="i">
                                <strong v-if="section.h">{{ section.h }}</strong> {{ section.p }}
                            </p>
                        </details>
                    </template>
                </div>

                <button type="submit" class="btn btn--zone auth__submit" :disabled="ui.busy">
                    <span v-show="! ui.busy">{{ a('google.submit') }}</span>
                    <span v-show="ui.busy" class="btn__loading">
                        <span class="jj-spinner jj-spinner--xs" aria-hidden="true"></span> {{ a('google.submitting') }}
                    </span>
                </button>
            </form>
        </template>
    </div>
</template>
