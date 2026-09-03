<script setup>
import { computed, ref, watch } from 'vue';
import { bornOnLabel, coverageKey, dependentNeedsSignature, dependentWaiverKey } from '../dependents.js';
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
});

const emit = defineEmits(['remove', 'sign']);

const accept = ref(false);
watch(() => props.reread, () => { accept.value = false; });

const a = (key) => translate(props.account, key);
const waiverText = computed(() => translateWith(props.account, dependentWaiverKey(props.dependent), { version: props.dependent.waiver?.version ?? '' }));
const coverage = computed(() => coverageKey(props.dependent));
const canSign = computed(() => dependentNeedsSignature(props.dependent) && props.document !== null);
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
            <button type="submit" class="btn auth__submit" :disabled="busy || ! accept">
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

        <button type="button" class="btn btn--ghost" :disabled="busy" @click="emit('remove')">
            {{ removing ? a('account.dependents.removing') : a('account.dependents.remove') }}
        </button>
    </section>
</template>
