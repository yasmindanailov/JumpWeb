<script setup>
import { useCardStore } from '../../stores/card.js';
import ZoneLoading from '../ZoneLoading.vue';
import { cardImageUrl, cardIsDrawable, tokenGroups } from '../card.js';
import { t as translate } from '../../i18n.js';

/**
 * **«Mi carné»** (Fase 6 · A, `docs/specs/identidad-qr-puerta.md` §4.1, §4.5, §9.6 B·2): el carné QR
 * que el cliente enseña en la puerta — verlo, dictarlo, descargarlo y renovarlo.
 *
 * **Pinta y recoge; no decide nada.** El dibujo lo hace el SERVIDOR (`png_url`: los mismos bytes que
 * el adjunto del correo, B·1), si hay carné que dibujar lo dice él (`token` nulo con la clave rotada,
 * §8.1) y qué mata una renovación también (§4.5). `stores/card.js` coloca lo que responda y
 * `account/card.js` compone los grupos del token y la URL con su versión.
 *
 * ⚠️ **Renovar pide confirmación explícita**: el carné actual —el del correo y el impreso— deja de
 * valer EN EL ACTO, sin ventana de gracia (`[DECIDIDO owner]` §4.5). Un clic sin querer dejaría a un
 * cliente en la puerta con un QR que ya no vale.
 *
 * ⚠️ Sin clases CSS nuevas: la pantalla se compone con el vocabulario de las tarjetas de la cuenta
 * (`account__card`, `auth__link`, `btn`), que `SidebarStyleWiringTest` ya vigila. El `<img>` lleva su
 * tamaño natural (264 px: versión 2 con zona de silencio de 4, a escala 8) en atributos.
 */
const props = defineProps({
    account: { type: Object, default: () => ({}) },
    /** El grupo `ui`: el rótulo del spinner mientras la zona trae el carné. */
    ui: { type: Object, default: () => ({}) },
    messages: { type: Object, default: () => ({}) },
    auth: { type: Object, default: () => ({}) },
});

const store = useCardStore();
store.reset();
store.ensure();

const a = (key) => translate(props.account, key);

async function rotate() {
    if (! window.confirm(a('account.card.rotate_confirm'))) return;

    await store.rotate({ messages: props.messages, auth: props.auth });
}
</script>

<template>
    <div class="auth account__grid">
        <p class="purchase__note">{{ a('account.card.intro') }}</p>

        <div v-if="store.notice" class="auth__errors" role="alert"><p>{{ store.notice }}</p></div>
        <div v-if="store.done" class="purchase__confirm" role="status">{{ a('account.card.rotated') }}</div>
        <p v-if="store.expired" class="account__card-sub">{{ a('account.card.expired') }}</p>

        <!-- El spinner ANTES de cualquier estado: mientras se pide, no hay nada que afirmar. -->
        <ZoneLoading v-if="store.loading && ! store.loaded" :ui="ui" />

        <section v-else-if="store.loaded" class="account__card">
            <template v-if="cardIsDrawable(store.card)">
                <!-- El dibujo es del servidor; el `src` lleva la versión para que renovar lo repinte. -->
                <img :src="cardImageUrl(store.card)" :alt="a('account.card.alt')" width="264" height="264" decoding="async">
                <p class="account__card-sub">{{ a('account.card.token_label') }}</p>
                <p class="account__card-title">{{ tokenGroups(store.card.token) }}</p>
                <a class="auth__link" :href="cardImageUrl(store.card)" download="carne-qr.png">{{ a('account.card.download') }}</a>
            </template>

            <!-- La clave del servidor rotó (§8.1): no hay nada que dibujar, y renovar lo arregla. -->
            <p v-else class="account__card-sub">{{ a('account.card.unavailable') }}</p>

            <p class="purchase__note">{{ a('account.card.hint') }}</p>

            <button type="button" class="btn btn--zone auth__submit" :disabled="store.busy" @click="rotate">
                {{ store.busy ? a('account.card.rotating') : a('account.card.rotate') }}
            </button>
        </section>
    </div>
</template>
