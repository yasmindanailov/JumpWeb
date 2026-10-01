<script setup>
import { computed, ref, watch } from 'vue';
import { useProfileStore } from '../../stores/profile.js';
import { NEW_EMAIL, useConfirmStore } from '../../stores/confirm.js';
import ZoneLoading from '../ZoneLoading.vue';
import { fieldError } from '../form-outcome.js';
import { pendingNotice, profileForm } from '../profile.js';
import { CONFIRM_ACTIONS } from '../confirm-code.js';
import { t as translate, tp as translateWith } from '../../i18n.js';
import BornOnField from '../../steps/BornOnField.vue';
import ConfirmCode from '../ConfirmCode.vue';

/**
 * **«Tus datos»** (`specs/area-cliente.md` §9, tanda 2 · paso 7b).
 *
 * **Pinta y recoge.** Qué se puede cambiar, si hace falta confirmar y cuánto vale el cambio
 * pendiente lo dice el SERVIDOR; `account/profile.js` lo compone y el store lo trae.
 *
 * ⚠️ **El correo nuevo, en tres tiempos, como la isla** (A4b de `acceso-con-codigo.md` §4.11, `#813`; antes, la
 * contraseña y un enlace): con el correo cambiado, un código al de AHORA confirma que eres tú («Enviarme el código» y
 * después «Guardar cambios», o la sexta cifra); el cambio queda pendiente y su código va al NUEVO, que se escribe en el
 * aviso de arriba («Confirmar el correo»). Hasta entonces se sigue entrando con el de siempre.
 * ⚠️ El código del de ahora solo se pide cuando el correo cambia de verdad, comparado contra el del perfil: pedirlo
 * siempre haría creer que hay que confirmar para corregir una errata en el teléfono.
 */
const props = defineProps({
    account: { type: Object, default: () => ({}) },
    /** El grupo `ui`: el rótulo del spinner mientras la zona trae sus datos. */
    ui: { type: Object, default: () => ({}) },
    messages: { type: Object, default: () => ({}) },
    auth: { type: Object, default: () => ({}) },
    /** Los idiomas del selector, con su nombre nativo (`Platform\Services\SiteLocales`). */
    locales: { type: Array, default: () => [] },
});

const store = useProfileStore();
const confirm = useConfirmStore();

store.ensure();

const form = ref(profileForm(store.user));
const { CHANGE_EMAIL } = CONFIRM_ACTIONS;

const ctx = computed(() => ({ messages: props.messages, auth: props.auth, account: props.account }));

// El perfil llega por red: cuando cambia —al cargar o tras guardar— el formulario se recompone.
watch(() => store.user, (user) => { form.value = profileForm(user); }, { immediate: true });
// Un cambio pendiente: su código ya salió hacia el correo NUEVO, así que su campo se ve en el aviso.
watch(() => store.user?.pending_email, (pendingEmail) => { if (pendingEmail) confirm.showNewEmail(); }, { immediate: true });

const a = (key) => translate(props.account, key);
const emailChanged = computed(() => form.value.email !== (store.user?.email ?? ''));
const pending = computed(() => pendingNotice(store.user, props.account, Date.now(), translateWith));
const busy = computed(() => store.busy || confirm.busy);
const asksCode = computed(() => emailChanged.value && ! confirm.isShown(CHANGE_EMAIL));

/** «Guardar cambios»: con el correo cambiado, con el código del de ahora (primero se pide); sin cambiarlo, sin código. */
const save = () => (emailChanged.value
    ? confirm.act(CHANGE_EMAIL, store, (code) => store.apply({ ...form.value, code }, ctx.value), ctx.value)
    : store.apply(form.value, ctx.value));
const confirmNew = () => confirm.act(NEW_EMAIL, store, (code) => store.confirmPending(code, ctx.value), ctx.value);
</script>

<template>
    <ZoneLoading v-if="store.busy && ! store.loaded" :ui="ui" />

    <div class="auth">
        <p class="purchase__note">{{ a('account.profile.intro') }}</p>

        <!-- El cambio de correo pedido: a dónde, cuánto queda, CUÁL SIGUE VALIENDO y el código que lo confirma. -->
        <div v-if="pending" class="auth__errors" role="status">
            <p><strong>{{ a('account.profile.pending_email_title') }}</strong></p>
            <p>{{ pending.message }}</p>
            <template v-if="confirm.isShown(NEW_EMAIL)">
                <ConfirmCode id="acct-pending-code" v-model="confirm.code" :account="account" :email="pending.email" shown
                             :resends="confirm.resends" :error="confirm.error" :disabled="busy"
                             @complete="confirmNew" @resend="confirm.again(ctx)" />
                <button type="button" class="btn btn--ink" :disabled="busy || ! confirm.ready" @click="confirmNew">
                    {{ a('account.profile.pending_email_confirm') }}
                </button>
            </template>
            <button type="button" class="btn btn--ghost" :disabled="busy" @click="store.cancelPending(ctx)">
                {{ a('account.profile.pending_email_cancel') }}
            </button>
        </div>

        <form class="form auth__form" novalidate @submit.prevent="save">
            <div v-if="store.notice || confirm.notice" class="auth__errors" role="alert"><p>{{ store.notice || confirm.notice }}</p></div>

            <div class="form__field">
                <label class="form__label" for="acct-name">{{ a('account.profile.name') }}</label>
                <input id="acct-name" v-model="form.name" type="text" autocomplete="name" required>
                <span v-if="fieldError(store.fields, 'name')" class="form__error">{{ fieldError(store.fields, 'name') }}</span>
            </div>

            <div class="form__field">
                <label class="form__label" for="acct-email">{{ a('account.profile.email') }}</label>
                <input id="acct-email" v-model="form.email" type="email" autocomplete="email" required>
                <span v-if="fieldError(store.fields, 'email')" class="form__error">{{ fieldError(store.fields, 'email') }}</span>
                <!-- Solo cuando el correo cambia de verdad: el código va al de AHORA, que es quien confirma que eres tú. Dentro
                     del campo, como su pista: suelta en el formulario quedaba pegada al de debajo (medido en la sonda). -->
                <ConfirmCode v-if="emailChanged" id="acct-email-code" v-model="confirm.code" :account="account"
                             :shown="confirm.isShown(CHANGE_EMAIL)" :resends="confirm.resends"
                             :error="confirm.error" :disabled="busy" @complete="save" @resend="confirm.again(ctx)" />
            </div>

            <div class="form__field">
                <label class="form__label" for="acct-phone">{{ a('account.profile.phone') }}</label>
                <input id="acct-phone" v-model="form.phone" type="tel" autocomplete="tel" required>
                <span v-if="fieldError(store.fields, 'phone')" class="form__error">{{ fieldError(store.fields, 'phone') }}</span>
            </div>

            <!-- TP·1 (`#792`): la fecha de nacimiento, opcional. Vaciarla y guardar la BORRA (`stores/profile.js::profileBody`). -->
            <BornOnField id="acct-born-on" v-model="form.born_on" :account="account" :error="fieldError(store.fields, 'born_on')" />

            <div class="form__field">
                <label class="form__label" for="acct-locale">{{ a('account.profile.locale') }}</label>
                <select id="acct-locale" v-model="form.locale">
                    <option v-for="option in locales" :key="option.value" :value="option.value">{{ option.label }}</option>
                </select>
                <span v-if="fieldError(store.fields, 'locale')" class="form__error">{{ fieldError(store.fields, 'locale') }}</span>
            </div>

            <button type="submit" class="btn btn--ink auth__submit"
                    :disabled="busy || (emailChanged && confirm.isShown(CHANGE_EMAIL) && ! confirm.ready)">
                {{ store.busy ? a('account.profile.saving') : asksCode ? a('account.confirm.send') : a('account.profile.save') }}
            </button>
        </form>
    </div>
</template>
