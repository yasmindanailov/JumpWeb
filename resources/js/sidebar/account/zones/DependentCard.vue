<script setup>
import { computed, ref, watch } from 'vue';
import ConfirmInline from '../ConfirmInline.vue';
import { DEPENDENT_WAIVER_SIGN, DEPENDENT_WAIVER_VERIFY, bornOnLabel, coverageKey, dependentWaiverAction, dependentWaiverKey } from '../dependents.js';
import { t as translate, tp as translateWith } from '../../i18n.js';

/**
 * **Un menor a cargo, en su tarjeta** (`docs/specs/menores-a-cargo.md` §4.1, §4.3): quién es —para
 * el titular, que es el único que necesita distinguirlos—, cuántos años tiene y si sigue cubierto,
 * y el estado de SU exención con lo que toque hacer: firmarla en su nombre, o su PDF.
 *
 * **Pinta.** Todo lo que enseña lo publicó el servidor (`age`, `is_minor`, `waiver.*`); qué frase
 * toca y si se ofrece la firma lo decide `account/dependents.js`. Los dos actos —quitar y firmar—
 * suben a la zona, que es quien habla con el store.
 *
 * ⚠️ La casilla se desmarca cuando el texto se relee (`reread` cambia): lo que se leyó ya no es lo
 * que se firma (CAJ-3, `#175`). Y solo se ofrece a quien sigue siendo menor: a los 18 la exención
 * del adulto ya no le cubre y el servidor la rechazaría.
 */
const props = defineProps({
    dependent: { type: Object, required: true },
    account: { type: Object, default: () => ({}) },
    /** El texto firmable en pantalla (`stores/waiver.js::document`), o `null` si aquí no se firma. */
    document: { type: Object, default: null },
    busy: { type: Boolean, default: false },
    signing: { type: Boolean, default: false },
    removing: { type: Boolean, default: false },
    signedOk: { type: Boolean, default: false },
    reread: { type: Number, default: 0 },
    /**
     * ⚠️ `#441` · si el correo del titular está verificado. Lo dice el CONTEXTO DE CUENTA, no esta
     * tarjeta: sin él se ofrecía un botón que solo podía devolver 409.
     */
    emailVerified: { type: Boolean, default: undefined },
});

const emit = defineEmits(['remove', 'sign']);

/**
 * ⚠️⚠️ **La pregunta de quitar vive AQUÍ y no en la zona** (`#565`): quitar a un menor se confirma
 * en SU tarjeta, no en un sitio común — con varios menores a cargo, una pregunta suelta arriba no
 * diría a cuál se refiere. Y su texto lleva el nombre, que es justo lo que **no** puede salir en un
 * diálogo del sistema operativo. La mecánica entera la trae `ConfirmInline`.
 */
const accept = ref(false);
watch(() => props.reread, () => { accept.value = false; });

const a = (key) => translate(props.account, key);
const waiverText = computed(() => translateWith(props.account, dependentWaiverKey(props.dependent), { version: props.dependent.waiver?.version ?? '' }));
const coverage = computed(() => coverageKey(props.dependent));
const action = computed(() => dependentWaiverAction(props.dependent, props.emailVerified));
const canSign = computed(() => action.value === DEPENDENT_WAIVER_SIGN && props.document !== null);
const mustVerify = computed(() => action.value === DEPENDENT_WAIVER_VERIFY);
// La pregunta lleva el NOMBRE del menor, que es lo que la hace inequívoca con varios a cargo.
const removeQuestion = computed(() => translateWith(props.account, 'account.dependents.remove_confirm', { name: props.dependent.name }));
</script>

<template>
    <section class="account__card">
        <h3 class="account__card-title">{{ dependent.name }}</h3>
        <p class="account__card-sub">
            {{ translateWith(account, 'account.dependents.age', { age: dependent.age }) }} · {{ bornOnLabel(dependent.born_on) }}
        </p>

        <!-- §4.1: a los 18 se MARCA, no se borra. -->
        <p v-if="coverage" class="auth__errors" role="status">{{ a(coverage) }}</p>

        <p v-if="waiverText" class="account__card-sub">{{ waiverText }}</p>

        <!--
          `#441` · Falta su firma y esta cuenta todavía no puede darla: se DICE, y no se ofrece un
          formulario que solo puede acabar en 409. El bloque de reenviar el correo vive en el índice
          de la cuenta y **no se duplica aquí**: son dos hechos y una sola acción del cliente
          (`#331`, «para no saturar»).
        -->
        <p v-if="mustVerify" class="auth__sub" role="status">{{ a('account.dependents.waiver_awaiting_verification') }}</p>

        <form v-if="canSign" class="form auth__form" novalidate @submit.prevent="emit('sign')">
            <details class="form__hint">
                <summary>{{ a('register.waiver_read') }}</summary>
                <p v-for="(section, i) in document.sections" :key="i">
                    <strong v-if="section.h">{{ section.h }}</strong> {{ section.p }}
                </p>
            </details>
            <div class="form__checks">
                <label class="check">
                    <input v-model="accept" type="checkbox">
                    <span>{{ a('register.accept_waiver') }}</span>
                </label>
            </div>
            <button type="submit" class="btn btn--ink auth__submit" :disabled="busy || ! accept">
                {{ signing ? a('account.privacy.waiver.signing') : a('account.privacy.waiver.sign_btn') }}
            </button>
        </form>

        <p v-if="signedOk" class="account__card-sub" role="status">{{ a('account.privacy.waiver.signed_ok') }}</p>

        <ul v-if="dependent.waiver?.pdf_url" class="account__consents">
            <li>
                <span class="account__consent-type">v{{ dependent.waiver.version }}</span>
                <span class="account__consent-meta">{{ dependent.waiver.accepted_label }} · <a :href="dependent.waiver.pdf_url" target="_blank" rel="noopener">{{ a('account.privacy.waiver.pdf') }}</a></span>
            </li>
        </ul>

        <ConfirmInline :id="'acct-dep-remove-q-' + dependent.id"
                       :question="removeQuestion"
                       :confirm-label="a('account.dependents.remove_confirm_yes')"
                       :cancel-label="a('account.dependents.remove_confirm_no')"
                       :busy-label="a('account.dependents.removing')"
                       :busy="busy" danger
                       @confirm="emit('remove')">
            <template #trigger="{ ask }">
                <button type="button" class="btn btn--ghost" :disabled="busy" @click="ask">
                    {{ removing ? a('account.dependents.removing') : a('account.dependents.remove') }}
                </button>
            </template>
        </ConfirmInline>
    </section>
</template>
