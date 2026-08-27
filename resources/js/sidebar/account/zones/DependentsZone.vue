<script setup>
import { ref } from 'vue';
import { useDependentsStore } from '../../stores/dependents.js';
import { useWaiverStore } from '../../stores/waiver.js';
import ZoneLoading from '../ZoneLoading.vue';
import DependentCard from './DependentCard.vue';
import { fieldError } from '../form-outcome.js';
import { dependentForm } from '../dependents.js';
import { t as translate, tp as translateWith } from '../../i18n.js';

/**
 * **«Menores a cargo»** (Fase 6 · C, `docs/specs/menores-a-cargo.md` §4.1–§4.5, §9.8): declarar de
 * quién se hace responsable el titular, quitarlo, y firmar la exención EN SU NOMBRE.
 *
 * **Pinta y recoge; no decide nada.** El tope por cuenta, la regla de los 18, si «quitar» borra o
 * desvincula, y si la exención de cada uno está al día lo dice el SERVIDOR; `stores/dependents.js`
 * coloca lo que responda y `account/dependents.js` lo traduce a frases.
 *
 * ⚠️ **El texto que se firma es el que se ENSEÑA** (`stores/waiver.js`, CAJ-1): el `document_id`
 * sale del texto en pantalla. Si el servidor dice que cambió (`stale`), se relee y las casillas se
 * desmarcan: lo que se leyó ya no es lo que se firma.
 *
 * ⚠️ Sin clases CSS nuevas a propósito: la pantalla se compone con el vocabulario de las tarjetas
 * de la cuenta (`account__card`, `form`, `btn`), que `SidebarStyleWiringTest` ya vigila.
 */
const props = defineProps({
    account: { type: Object, default: () => ({}) },
    /** El grupo `ui`: el rótulo del spinner mientras la zona trae sus datos. */
    ui: { type: Object, default: () => ({}) },
    messages: { type: Object, default: () => ({}) },
    auth: { type: Object, default: () => ({}) },
});

const store = useDependentsStore();
store.reset();
store.ensure();

// El texto firmable, el MISMO que enseña Privacidad: sin él no hay casilla que ofrecer.
const waiver = useWaiverStore();
waiver.ensureLegal();

const form = ref(dependentForm());
/** Sube cada vez que el texto se relee tras un 409: las tarjetas desmarcan su casilla al verlo cambiar. */
const rereadToken = ref(0);

const a = (key) => translate(props.account, key);
const ctx = () => ({ messages: props.messages, auth: props.auth });

async function add() {
    if (await store.add(form.value, ctx())) form.value = dependentForm();
}

async function remove(dependent) {
    if (! window.confirm(translateWith(props.account, 'account.dependents.remove_confirm', { name: dependent.name }))) return;

    await store.remove(dependent.id, ctx());
}

async function sign(dependent) {
    const { ok, stale } = await store.signWaiver({ id: dependent.id, documentId: waiver.currentDocumentId }, ctx());

    if (! ok && stale) { await waiver.reloadLegal(); rereadToken.value += 1; }
}
</script>

<template>
    <div class="auth account__grid">
        <p class="purchase__note">{{ a('account.dependents.intro') }}</p>

        <div v-if="store.notice" class="auth__errors" role="alert"><p>{{ store.notice }}</p></div>

        <!-- El spinner ANTES del «no hay ninguno»: mientras se piden, decir que no hay es decir algo falso. -->
        <ZoneLoading v-if="store.listLoading && ! store.loaded" :ui="ui" />

        <p v-else-if="store.loaded && ! store.items.length" class="account__card-sub">{{ a('account.dependents.empty') }}</p>

        <DependentCard
            v-for="dependent in store.items"
            :key="dependent.id"
            :dependent="dependent"
            :account="account"
            :document="waiver.document"
            :busy="store.busy"
            :signing="store.signingId === dependent.id"
            :removing="store.removingId === dependent.id"
            :signed-ok="store.signedId === dependent.id"
            :reread="rereadToken"
            @remove="remove(dependent)"
            @sign="sign(dependent)" />

        <!-- Declarar uno: nombre y fecha de nacimiento, NADA MÁS (§4.2). -->
        <section class="account__card">
            <h3 class="account__card-title">{{ a('account.dependents.add_title') }}</h3>

            <form class="form auth__form" novalidate @submit.prevent="add">
                <div class="form__field">
                    <label class="form__label" for="acct-dep-name">{{ a('account.dependents.name') }}</label>
                    <input id="acct-dep-name" v-model="form.name" type="text" autocomplete="off" required maxlength="120">
                    <span class="purchase__note">{{ a('account.dependents.name_hint') }}</span>
                    <span v-if="fieldError(store.fields, 'name')" class="form__error">{{ fieldError(store.fields, 'name') }}</span>
                </div>

                <div class="form__field">
                    <label class="form__label" for="acct-dep-born">{{ a('account.dependents.born_on') }}</label>
                    <input id="acct-dep-born" v-model="form.born_on" type="date" required>
                    <span v-if="fieldError(store.fields, 'born_on')" class="form__error">{{ fieldError(store.fields, 'born_on') }}</span>
                </div>

                <button type="submit" class="btn btn--zone auth__submit" :disabled="store.busy">
                    {{ store.busy && ! store.signingId && ! store.removingId ? a('account.dependents.adding') : a('account.dependents.add') }}
                </button>
            </form>
        </section>
    </div>
</template>
