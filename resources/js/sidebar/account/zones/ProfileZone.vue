<script setup>
import { computed, ref, watch } from 'vue';
import { useProfileStore } from '../../stores/profile.js';
import ZoneLoading from '../ZoneLoading.vue';
import { fieldError } from '../form-outcome.js';
import { pendingNotice, profileForm } from '../profile.js';
import { t as translate, tp as translateWith } from '../../i18n.js';
import PasswordInput from '../../steps/PasswordInput.vue';
import NoPasswordHint from '../NoPasswordHint.vue';

/**
 * **«Tus datos»** (`specs/area-cliente.md` §9, tanda 2 · paso 7b).
 *
 * **Pinta y recoge.** Qué se puede cambiar, si hace falta reconfirmar y cuánto vale el enlace
 * pendiente lo dice el SERVIDOR; `account/profile.js` lo compone y el store lo trae.
 *
 * ⚠️ **La contraseña solo se pide cuando el correo cambia de verdad**, comparado contra el que trae
 * el perfil. Enseñar el campo siempre haría creer que hay que reconfirmar para corregir una errata
 * en el teléfono — y el titular acabaría evitando la pantalla.
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

store.ensure();

const form = ref(profileForm(store.user));
const currentPassword = ref('');

const ctx = computed(() => ({ messages: props.messages, auth: props.auth }));

// El perfil llega por red: cuando cambia —al cargar o tras guardar— el formulario se recompone.
watch(() => store.user, (user) => { form.value = profileForm(user); }, { immediate: true });

// ⚠️ Al salir bien se limpia la CONTRASEÑA, no el formulario entero: los datos siguen siendo los del
// titular y vaciarlos le haría creer que se han perdido.
watch(() => store.done, (done) => { if (done) currentPassword.value = ''; });

const a = (key) => translate(props.account, key);
const emailChanged = computed(() => form.value.email !== (store.user?.email ?? ''));
const pending = computed(() => pendingNotice(store.user, props.account, Date.now(), translateWith));
</script>

<template>
    <ZoneLoading v-if="store.busy && ! store.loaded" :ui="ui" />

    <div class="auth">
        <p class="purchase__note">{{ a('account.profile.intro') }}</p>

        <!-- El cambio de correo pedido: a dónde se envió, cuánto queda y CUÁL SIGUE VALIENDO. -->
        <div v-if="pending" class="auth__errors" role="status">
            <p><strong>{{ a('account.profile.pending_email_title') }}</strong></p>
            <p>{{ pending.message }}</p>
            <button type="button" class="btn btn--ghost" :disabled="store.busy" @click="store.resendPending(ctx)">
                {{ a('account.profile.pending_email_resend') }}
            </button>
            <button type="button" class="btn btn--ghost" :disabled="store.busy" @click="store.cancelPending(ctx)">
                {{ a('account.profile.pending_email_cancel') }}
            </button>
        </div>

        <form class="form auth__form" novalidate
              @submit.prevent="store.apply({ ...form, currentPassword }, ctx)">
            <div v-if="store.notice" class="auth__errors" role="alert"><p>{{ store.notice }}</p></div>

            <div class="form__field">
                <label class="form__label" for="acct-name">{{ a('account.profile.name') }}</label>
                <input id="acct-name" v-model="form.name" type="text" autocomplete="name" required>
                <span v-if="fieldError(store.fields, 'name')" class="form__error">{{ fieldError(store.fields, 'name') }}</span>
            </div>

            <div class="form__field">
                <label class="form__label" for="acct-email">{{ a('account.profile.email') }}</label>
                <input id="acct-email" v-model="form.email" type="email" autocomplete="email" required>
                <span v-if="fieldError(store.fields, 'email')" class="form__error">{{ fieldError(store.fields, 'email') }}</span>
            </div>

            <div class="form__field">
                <label class="form__label" for="acct-phone">{{ a('account.profile.phone') }}</label>
                <input id="acct-phone" v-model="form.phone" type="tel" autocomplete="tel" required>
                <span v-if="fieldError(store.fields, 'phone')" class="form__error">{{ fieldError(store.fields, 'phone') }}</span>
            </div>

            <div class="form__field">
                <label class="form__label" for="acct-locale">{{ a('account.profile.locale') }}</label>
                <select id="acct-locale" v-model="form.locale">
                    <option v-for="option in locales" :key="option.value" :value="option.value">{{ option.label }}</option>
                </select>
                <span v-if="fieldError(store.fields, 'locale')" class="form__error">{{ fieldError(store.fields, 'locale') }}</span>
            </div>

            <!-- Solo cuando el correo cambia de verdad. Su ayuda explica que el cambio no es inmediato. -->
            <div v-if="emailChanged" class="form__field">
                <label class="form__label" for="acct-profile-password">{{ a('account.profile.current_password') }}</label>
                <PasswordInput :id="'acct-profile-password'" v-model="currentPassword" autocomplete="current-password" />
                <span class="purchase__note">{{ a('account.profile.email_change_hint') }}</span>
                <span v-if="fieldError(store.fields, 'current_password')" class="form__error">{{ fieldError(store.fields, 'current_password') }}</span>
                <NoPasswordHint :account="account" />
            </div>

            <button type="submit" class="btn btn--zone auth__submit" :disabled="store.busy">
                {{ store.busy ? a('account.profile.saving') : a('account.profile.save') }}
            </button>
        </form>
    </div>
</template>
