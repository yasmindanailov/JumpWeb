<script setup>
import { computed } from 'vue';
// ⚠️ El desenlace abre el área de CUENTA, que vive en la OTRA sección (`#563`; al índice desde `#567`).
// Se hace con el store, que es global, y con `openZone()`, que ya siembra la vuelta: ver `goToAccount()`.
import { useAccountStore } from '../stores/account.js';
import { ZONES } from '../account/navigation.js';
import { STEPS } from '../machine.js';
import { backPlan, buildProgress } from '../progress.js';
import { buildFooter } from '../foot.js';
import { buildNotice } from '../paused.js';
import { usePurchaseFlow } from '../usePurchaseFlow.js';
import Shell from '../Shell.vue';
import CatalogStep from '../steps/CatalogStep.vue';
import DateStep from '../steps/DateStep.vue';
import TimeStep from '../steps/TimeStep.vue';
import CartStep from '../steps/CartStep.vue';
import IdentifyStep from '../steps/IdentifyStep.vue';
import VerifyStep from '../steps/VerifyStep.vue';
import PayStep from '../steps/PayStep.vue';
import RedirectStep from '../steps/RedirectStep.vue';
import ConfirmedStep from '../steps/ConfirmedStep.vue';
import DeclinedStep from '../steps/DeclinedStep.vue';
import VerifyingStep from '../steps/VerifyingStep.vue';

/**
 * La raíz del cajón SPA.
 *
 * **El nodo raíz emite `class="purchase"` y eso no es decorativo**: el contrato visual es el ÁRBOL
 * (§4.2), y 90 de los 292 selectores que estilan el cajón son estructurales o dependen del tipo de
 * elemento. Lo verifica `SidebarDomContractTest` paso a paso.
 *
 * **Aquí se PIDEN los datos; los pasos solo pintan.** Esa separación es lo que hace verificable la
 * paridad: los componentes de paso reciben todo por props, así que se pueden renderizar en Node —sin
 * red ni navegador— y comparar con lo que emite Livewire.
 *
 * ⚠️⚠️ **La SECUENCIA de compra —qué se pide, en qué orden y a dónde lleva— vive en `usePurchaseFlow.js`
 * desde `#691`**, porque la comparten dos carcasas: este cajón y la isla de la landing nueva (`#682`).
 * Aquí se queda lo que es solo del cajón: la banda de progreso, el aviso de pausa, el pie y su reparto
 * de acciones, «Volver», la puerta a la cuenta y lo que la raíz le pide por `ref`.
 */
const props = defineProps({
    /** El grupo `tickets` del locale activo, inyectado por el servidor en el montaje (§4.5). */
    messages: { type: Object, default: () => ({}) },

    /** El grupo `ui` (hoy, solo el rótulo del velo de carga). Va aparte: son dos grupos de `lang/`. */
    ui: { type: Object, default: () => ({}) },

    /**
     * El grupo `account`, PODADO a lo que el paso de identificación pinta (§4.5).
     *
     * ⚠️ Va aparte y con su camino real (`account.login.email`) por lo mismo que `ui`: son grupos
     * distintos de `lang/`, y fundirlos aquí crearía una tercera forma del diccionario. Medido: el
     * grupo entero son 9,6 kB en español —tanto como `tickets`— para pintar diez rótulos.
     */
    account: { type: Object, default: () => ({}) },

    /** El grupo `auth`: los dos avisos del login (`failed`, `throttle`). Tres claves. */
    auth: { type: Object, default: () => ({}) },

    /**
     * Quién es el titular al cargar la página, inyectado por el servidor. `null` = visitante anónimo.
     *
     * ⚠️ Llega con el HTML a propósito: el LOGOUT es una navegación completa, y es justo el caso que
     * la sesión resolvía sola con `invalidate()` y que `localStorage` no tiene. Esperar a un `fetch`
     * dejaría una ventana en la que la cesta de quien acaba de salir sigue en pantalla.
     *
     * ⚠️⚠️ **Aquí NO se lee: la siembra vive en `index.js`, antes de montar.** Desde el 2026-08-22
     * hasta el 2026-08-27 este docblock prometía una siembra que no existía en ninguna parte —la
     * prop estaba declarada y muerta— y el cajón que nace abierto purgaba la cesta del propio titular
     * (`specs/menores-a-cargo.md` §9.9.6). La prop se conserva declarada porque la raíz hace
     * `v-bind="props"`: sin declararla, Vue la volcaría como atributo sobre el DOM.
     */
    userId: { type: [Number, String], default: null },

    /**
     * El código del pedido del que habla el desenlace de la pasarela. Cadena vacía si no hay ninguno.
     *
     * ⚠️ **Es lo ÚNICO que sobrevive a la ida a la pasarela.** Volver de Redsys es una navegación
     * completa desde otro dominio: el motor se remonta de cero y nada de lo que el cajón sabía sigue
     * ahí, ni siquiera el pedido que acaba de crear. Lo posee `Http\Sidebar\SidebarEntry` —el mismo
     * dueño que el `outcome`, y por el mismo motivo— y viaja ya CONSUMIDO.
     */
    orderCode: { type: String, default: '' },

    /**
     * Las rutas que el cajón pinta y no puede componer: `contact` y `my_orders`.
     *
     * ⚠️ Vienen del servidor con `route()` a propósito. Quemarlas aquí sería una segunda fuente de una
     * URL que decide `routes/web.php`, y el fallo sería INVISIBLE: `href` no es atributo de contrato del
     * diff de árbol, así que un enlace a un 404 pasaría el gate en verde.
     */
    urls: { type: Object, default: () => ({}) },
});

const {
    store, catalogStore, selectionStore, bookingStore, dateStore, timeStore, dependentsStore, cartStore, authStore, outcomeStore,
    busy, locale, unitPriceCents, guardianMode, buyerDue, buyerNeed,
    refreshBookingStatus, refreshIdentity, openProduct,
    selectProduct, selectDate, selectTime, applyQuantity, chooseAddon, setAddonQuantity,
    goToTime, goToCart, addToCart, removeLine, updateCartField, addAnother, clearSelection,
    checkout, submitLogin, submitRegister, confirmReservation, retryPayment,
} = usePurchaseFlow(props);

const accountStore = useAccountStore();

/**
 * La banda de progreso de las CINCO pantallas del camino, del día al pago (`#555`).
 *
 * ⚠️ **Hasta 4.3·1 esto era `null` fijo**, así que el cajón SPA vivo iba sin «Volver» y sin contador
 * de fases aunque el componente existiera y el gate lo comparase en verde: el diff alimenta a Vue con
 * el view-model del SERVIDOR. La composición vive en `progress.js` —módulo plano— para poder
 * compararla dato a dato.
 */
const progress = computed(() => buildProgress({
    step: store.step,
    // ⚠️ **`props.userId` es el dato del SERVIDOR al pintar la página**, y aquí eso es una virtud: no
    // cambia dentro del embudo, así que quien entra sin sesión y se identifica por el camino conserva
    // su fase «Quién eres» —por la que SÍ pasó— en vez de verla desaparecer al completarla (`#556`).
    pideIdentificarse: ! props.userId,
    productName: catalogStore.selectedRow?.name ?? '',
    date: dateStore.selected,
    time: timeStore.selected,
    messages: props.messages,
    locale,
}));

/**
 * Lo que esta sección publica hacia fuera. Lo consume la RAÍZ, que se limita a reenviar.
 *
 * ⚠️ `refreshIdentity` pasa por `actOnIdentity()`: si la cesta se purga, esta sección vuelve a su
 * catálogo. Exponer el store pelado en su lugar perdería esa navegación — pasó dos veces el
 * 2026-08-22, la segunda al partir el componente.
 */
defineExpose({ refreshBookingStatus, refreshIdentity, openProduct });

/**
 * El aviso de pausa, si toca en este paso (`paused.js`).
 *
 * ⚠️ No tapa los pasos de RESULTADO: quien vuelve de la pasarela tiene que ver en qué quedó su pago,
 * aunque las reservas se hayan pausado entre medias.
 */
const notice = computed(() => buildNotice({
    status: bookingStore.status,
    step: store.step,
    messages: props.messages,
}));

/**
 * El PIE, compuesto para el estado actual (`foot.js`).
 *
 * Devolver `null` es tan significativo como devolver una barra: en el catálogo con la cesta vacía y
 * en la cesta vacía el servidor no emite pie, y pintarlo igual enseñaría «0,00 €» donde la web no
 * enseña nada.
 */
const footer = computed(() => buildFooter({
    step: store.step,
    messages: props.messages,
    locale,
    cartCount: cartStore.count,
    cartTotalCents: cartStore.quote?.total_cents ?? 0,
    cartOnlineCents: cartStore.quote?.online_amount_cents ?? 0,
    hasDate: dateStore.selected !== null,
    hasTime: timeStore.selected !== null,
    lineTotalCents: selectionStore.line?.total_cents ?? null,
    lineHasDeposit: selectionStore.line?.has_deposit ?? false,
    lineDepositCents: selectionStore.line?.deposit_cents ?? 0,
    lineGateRemainderCents: selectionStore.line?.gate_remainder_cents ?? 0,
}));

/**
 * Las acciones del pie, con los MISMOS nombres que las del componente Livewire.
 *
 * Se conservan los nombres porque son el vocabulario de la paridad durante la convivencia: el
 * view-model del pie los publica y el test los compara campo a campo.
 */
function runAction(action) {
    if (action === 'goToTime') return goToTime();
    if (action === 'addToCart') return addToCart();
    if (action === 'goToCart') return goToCart();
    if (action === 'checkout') return checkout();
    if (action === 'confirmReservation') return confirmReservation();
}

/**
 * **La puerta a la CUENTA desde la reserva creada** (`#567`, `[DECIDIDO owner, 2026-09-12]`; hasta
 * entonces llevaba al carné, `#563`). Al ÍNDICE y no a una zona concreta: ahí están el QR, «Mis
 * reservas» y el resto, y no existe una pantalla de una sola reserva a la que mandar.
 *
 * ⚠️ **`openZone()` y no `showAccount()` + `go()`**: aquélla existe justo para «abrir el área EN una
 * zona viniendo de fuera de ella», y siembra la vuelta — sin ella, «volver» sacaría de la sección en
 * vez de llevar al índice de la cuenta. Escribirlo aquí a mano sería el tercer sitio donde recordar
 * que hay que sembrar `under`.
 *
 * ⚠️ **El desenlace NO se barre**: si el cliente vuelve a la compra, su reserva creada sigue ahí. Eso
 * lo hace `addAnother()`, que es el gesto que dice «empiezo otra».
 */
function goToAccount() {
    accountStore.openZone(ZONES.HOME);
}

/**
 * ¿Hay sesión con la que enseñar el carné?
 *
 * ⚠️⚠️ **Se mira el titular del HTML, no la sesión de ahora, y aquí eso es lo CORRECTO**: los textos
 * del área de cuenta viajan **solo con sesión** (`layout.blade.php`), así que si la página se pintó
 * anónima la zona del carné saldría en blanco — ofrecer el botón sería mandar al que acaba de pagar a
 * una pantalla vacía. Y no hay caso perdido: a esta pantalla se llega SIEMPRE por una navegación
 * completa (la vuelta del banco o el enlace del correo), así que el dato del HTML está al día.
 */
const hasSession = computed(() => props.userId !== null && props.userId !== '');

/**
 * **«Volver» de la banda, y desde `#555` el ÚNICO del embudo.**
 *
 * Hasta esta tanda la banda solo existía en los pasos 2 y 3, así que la cesta, la identificación y el
 * pago traían cada uno su propio `bk-back` encima del título — tres piezas para un gesto. Con la banda
 * en las cinco pantallas eso serían **dos «Volver» en la misma pantalla**, así que los tres se retiran.
 *
 * ⚠️ **A dónde vuelve y qué se deshace NO se decide aquí**: sale de la misma fila que su rótulo, en
 * `progress.js` (`CE-6`). Aquí solo se APLICA — que es lo que evita que el botón diga «Volver al
 * carrito» y lleve a otro sitio sin que nada falle.
 */
function goBack() {
    // ⚠️ `?? {}` y no una guarda: en un paso sin banda no hay «Volver» que pulsar, así que el plan
    // ausente no es un caso a contemplar — es que nadie puede llegar aquí. Sin destino no se navega.
    const { to, clear } = backPlan(store.step) ?? {};

    if (clear === 'time') {
        timeStore.clearSelection();
        selectionStore.setQuantity(0);
    } else if (clear === 'selection') {
        clearSelection();
        outcomeStore.clear();
    }

    if (to) store.go(to);
}
</script>

<template>
    <Shell :busy="busy" :progress="progress" :footer="footer" :notice="notice" :messages="messages" :ui="ui"
           @back="goBack" @action="runAction">
        <CatalogStep
            v-if="store.step === STEPS.CATALOG"
            :sections="catalogStore.sections"
            :search-enabled="catalogStore.searchEnabled"
            :messages="messages"
            @select="selectProduct" />

        <DateStep
            v-else-if="store.step === STEPS.DATE"
            :strip="dateStore.strip"
            :calendar-open="dateStore.calendarOpen"
            :weeks="dateStore.weeks"
            :weekday-headers="dateStore.weekdayHeaders"
            :month-label="dateStore.monthLabel"
            :can-prev="dateStore.canPrev"
            :can-next="dateStore.canNext"
            :selected-date="dateStore.selected"
            :messages="messages"
            @select="selectDate"
            @toggle-calendar="dateStore.toggleCalendar"
            @prev-month="dateStore.shift(-1)"
            @next-month="dateStore.shift(1)" />

        <TimeStep
            v-else-if="store.step === STEPS.TIME"
            :times="timeStore.offered"
            :low-max="timeStore.lowMax"
            :selected-time="timeStore.selected"
            :quantity="selectionStore.quantity"
            :min-quantity="catalogStore.minQuantity"
            :max-quantity="timeStore.maxQuantity"
            :is-pack="catalogStore.isPack"
            :day-price-cents="unitPriceCents"
            :event-fields="catalogStore.product?.event_fields ?? []"
            :period-label="catalogStore.product?.period_label ?? ''"
            :addons="selectionStore.addons"
            :errors="cartStore.fieldErrors"
            :messages="messages"
            :dependent-options="dependentsStore.optionsFor(messages)"
            :dependent-ids="selectionStore.dependentIds"
            :guardian-mode="guardianMode"
            :guardian-checked="selectionStore.guardianAuthorization"
            @select-time="selectTime"
            @toggle-guardian="selectionStore.setGuardianAuthorization"
            @inc="applyQuantity({ delta: 1 })"
            @dec="applyQuantity({ delta: -1 })"
            @set-qty="(raw) => applyQuantity({ raw })"
            @update-field="selectionStore.answer"
            @toggle-dependent="selectionStore.toggleDependent"
            @choose-addon="chooseAddon"
            @toggle-addon="(id) => setAddonQuantity(id, selectionStore.addons.singles.find((a) => a.product_id === id)?.selected ? 0 : 1)"
            @inc-addon="(id) => setAddonQuantity(id, (selectionStore.addons.singles.find((a) => a.product_id === id)?.quantity ?? 0) + 1)"
            @dec-addon="(id) => setAddonQuantity(id, Math.max(0, (selectionStore.addons.singles.find((a) => a.product_id === id)?.quantity ?? 0) - 1))" />

        <CartStep
            v-else-if="store.step === STEPS.CART"
            :lines="cartStore.rows"
            :error="cartStore.error"
            :messages="messages"
            :locale="locale"
            :dependent-options="dependentsStore.optionsFor(messages)"
            :notice="cartStore.notice"
            @remove="removeLine"
            @add-another="addAnother"
            @update-field="updateCartField"
            @toggle-dependent="cartStore.assign" />

        <IdentifyStep
            v-else-if="store.step === STEPS.IDENTIFY"
            v-model:form="authStore.form"
            :mode="authStore.mode"
            :login-errors="authStore.loginError"
            :register-errors="authStore.registerError"
            :submitting="authStore.busy"
            :messages="messages"
            :account="account"
            :turnstile-site-key="authStore.signupSiteKey"
            :google-url="urls.google ?? ''"
            :privacy-url="urls.privacy ?? ''"
            @set-mode="authStore.setMode"
            @submit-login="submitLogin"
            @submit-register="submitRegister"
            @recover="authStore.startPasswordRecovery()" />

        <VerifyStep
            v-else-if="store.step === STEPS.VERIFY_EMAIL"
            :email="authStore.pendingEmail"
            :messages="messages" />

        <PayStep
            v-else-if="store.step === STEPS.PAY"
            v-model:accept-terms="buyerDue.acceptTerms"
            v-model:phone="buyerDue.phone"
            :lines="cartStore.rows"
            :error="cartStore.error"
            :messages="messages"
            :locale="locale"
            :need="buyerNeed"
            :due-errors="buyerDue.errors"
            :terms-url="urls.terms ?? ''" />

        <RedirectStep
            v-else-if="store.step === STEPS.REDIRECTING"
            :form="outcomeStore.gateway"
            :messages="messages" />

        <ConfirmedStep
            v-else-if="store.step === STEPS.CONFIRMED"
            :confirmation="outcomeStore.confirmation"
            :order-code="outcomeStore.orderCode"
            :registration="outcomeStore.registration"
            :has-session="hasSession"
            :messages="messages"
            :locale="locale"
            @add-another="addAnother"
            @go-account="goToAccount" />

        <DeclinedStep
            v-else-if="store.step === STEPS.DECLINED"
            :order-code="outcomeStore.orderCode"
            :reason="outcomeStore.declinedReason"
            :retrying="outcomeStore.retrying"
            :contact-url="urls.contact ?? ''"
            :hold-until="outcomeStore.holdUntil"
            :messages="messages"
            @retry="retryPayment" />

        <VerifyingStep
            v-else-if="store.step === STEPS.VERIFYING"
            :order-code="outcomeStore.orderCode"
            :orders-url="urls.my_orders ?? ''"
            :messages="messages" />
    </Shell>
</template>
