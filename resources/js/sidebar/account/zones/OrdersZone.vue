<script setup>
import { computed, ref } from 'vue';
import { useOrdersStore } from '../../stores/orders.js';
import { orderRows, pageInfo } from '../orders.js';

/**
 * **«Mis reservas»**: el historial de pedidos del cliente (`docs/specs/area-cliente.md` §4.2).
 * Espeja `/mi-cuenta/pedidos`, que de cara al cliente se titula así — medido en `lang/`:
 * `account.orders.title` es literalmente «Mis reservas», no «Mis pedidos».
 *
 * **Pinta.** Qué se enseña de cada pedido lo compone `account/orders.js` y de dónde salen los datos
 * lo sabe `stores/orders.js`; los dos se prueban con `node --test`. Aquí no hay ninguna regla y no se
 * habla con la API (`CE-6`).
 *
 * ⚠️ **La zona pide lo suyo al MONTARSE, y compone sus propias filas.** Antes lo hacía la sección,
 * con una cadena de `if` y un `computed` por zona que crecían con cada pantalla nueva: eso es
 * conocimiento de LAS ZONAS, no del enrutado, y empujaba la sección contra el techo de
 * `SidebarComponentBudgetTest` — que existe justo para provocar esta pregunta. `ensure()` garantiza
 * que volver a entrar no repita la petición.
 */
const props = defineProps({
    /** El grupo `account` podado: rótulos y textos de la zona. */
    account: { type: Object, default: () => ({}) },
    /** El grupo `tickets`: estados del pedido y aviso de señal. */
    messages: { type: Object, default: () => ({}) },
});

defineEmits(['sign-in']);

const store = useOrdersStore();

store.ensure();

const rows = computed(() => orderRows(store.payload, { messages: props.messages, account: props.account }));
const page = computed(() => pageInfo(store.payload, props.account));

/**
 * Qué desglose de puerta está abierto, por código de pedido. Uno cada vez.
 *
 * ⚠️ La página usa un `x-data` por tarjeta, así que ahí pueden estar varios abiertos; aquí es un
 * acordeón. Es una diferencia de INTERACCIÓN y no de datos, que es lo que esta tanda protege
 * (`specs/area-cliente.md` §1.3): en un panel estrecho, dos desgloses abiertos empujan el resto de
 * pedidos fuera de la vista.
 */
const openBreakdown = ref('');
</script>

<template>
    <!-- La sesión caducó con el cajón abierto: se dice y se ofrece la puerta, en vez de una lista vacía. -->
    <p v-if="store.unauthenticated" class="purchase__empty">
        <button type="button" class="btn btn--zone" @click="$emit('sign-in')">{{ account?.login?.cta ?? '' }}</button>
    </p>

    <template v-else>
        <p v-if="store.error" class="bk-error" role="alert">{{ store.error }}</p>

        <p v-if="! rows.length && ! store.busy" class="purchase__empty">{{ account?.orders?.empty ?? '' }}</p>

        <ul v-else class="orders">
            <li v-for="row in rows" :key="row.code" class="orders__card">
                <div class="orders__head">
                    <span class="orders__code">{{ row.code }}</span>
                    <span class="orders__status" :class="'orders__status--' + row.status">{{ row.statusLabel }}</span>
                </div>
                <div class="orders__meta">{{ row.createdLabel }}</div>

                <ul class="orders__lines">
                    <li v-for="line in row.lines" :key="line.id" class="orders__line">
                        <span class="orders__line-name">{{ line.name }}</span>
                        <span class="orders__line-when">{{ line.whenLabel }}</span>
                        <span v-if="line.badge" class="orders__line-badge" :class="'orders__line-badge--' + line.badge.key">{{ line.badge.label }}</span>
                        <span class="orders__line-price">{{ line.priceLabel }}</span>

                        <span v-for="addon in line.addons" :key="addon.id" class="orders__line-name">
                            + {{ addon.name }} <span class="orders__line-unit">· {{ addon.quantity }}×</span>
                            <span class="orders__line-price">{{ addon.priceLabel }}</span>
                        </span>

                        <span v-if="line.depositNote" class="orders__product-deposit">{{ line.depositNote }}</span>

                        <span v-if="line.guestForm" class="orders__product-form">
                            <button v-if="! line.guestForm.url" type="button" class="btn btn--ghost orders__guestform-btn" disabled>{{ line.guestForm.label }}</button>
                            <a v-else :href="line.guestForm.url" class="btn orders__guestform-btn"
                               :class="line.guestForm.state === 'pending' ? 'btn--zone' : 'btn--ghost'">{{ line.guestForm.label }}</a>
                        </span>
                    </li>
                </ul>

                <!--
                  El LEDGER, en el mismo orden que `/mi-cuenta/pedidos`: subtotal → señal pagada →
                  a cobrar en puerta (con su desglose plegado) → devuelto → pendiente de devolver →
                  total final. Qué líneas salen lo decide `financialsOf`, con `node --test`; aquí no
                  hay ninguna condición que no sea «¿hay dato?».
                -->
                <div class="orders__foot">
                    <div class="orders__total">
                        <span>{{ row.financials.firstLabel }}</span>
                        <strong>{{ row.totalLabel }}</strong>
                    </div>

                    <div v-if="row.financials.online" class="orders__gate">
                        <span class="orders__gate-label">{{ row.financials.online.label }}</span>
                        <strong>{{ row.financials.online.amountLabel }}</strong>
                    </div>

                    <div v-if="row.financials.gate">
                        <div class="orders__gate">
                            <span class="orders__gate-label">{{ row.financials.gate.label }}</span>
                            <strong class="orders__gate-amount">+{{ row.financials.gate.amountLabel }}</strong>
                        </div>
                        <button type="button" class="orders__gate-toggle"
                                :aria-expanded="openBreakdown === row.code ? 'true' : 'false'"
                                @click="openBreakdown = openBreakdown === row.code ? '' : row.code">
                            {{ openBreakdown === row.code ? row.financials.gate.hideLabel : row.financials.gate.showLabel }}
                        </button>
                        <div v-if="openBreakdown === row.code">
                            <div v-for="(gateLine, i) in row.financials.gate.lines" :key="i" class="orders__gate-line">
                                <span>↳ {{ gateLine.label }}</span><strong>+{{ gateLine.amountLabel }}</strong>
                            </div>
                        </div>
                        <p class="orders__gate-caption">{{ row.financials.gate.caption }}</p>
                    </div>

                    <div v-if="row.refund" class="orders__refund">
                        <span class="orders__refund-label">{{ row.refund.label }}</span>
                        <strong class="orders__refund-amount">−{{ row.refund.amountLabel }}</strong>
                    </div>

                    <div v-if="row.financials.pendingRefund">
                        <div class="orders__refund">
                            <span class="orders__refund-label">{{ row.financials.pendingRefund.label }}</span>
                            <strong class="orders__refund-amount">−{{ row.financials.pendingRefund.amountLabel }}</strong>
                        </div>
                        <p class="orders__gate-caption">{{ row.financials.pendingRefund.caption }}</p>
                    </div>

                    <div v-if="row.financials.final" class="orders__final">
                        <span>{{ row.financials.final.label }}</span>
                        <strong>{{ row.financials.final.amountLabel }}</strong>
                    </div>
                </div>

                <div v-if="row.canRetry" class="orders__retry">
                    <button type="button" class="btn btn--zone" :disabled="store.busy" @click="store.retry(row.code, { messages })">{{ account?.orders?.retry_payment ?? '' }}</button>
                    <p class="orders__retry-hint">{{ account?.orders?.retry_hint ?? '' }}</p>
                </div>
            </li>
        </ul>

        <nav v-if="page" class="orders__pagination" :aria-label="page.label">
            <button type="button" class="btn btn--ghost" :disabled="! page.canPrev || store.busy" @click="store.load(page.current - 1)">{{ page.prevLabel }}</button>
            <span class="orders__pagination-page">{{ page.pageLabel }}</span>
            <button type="button" class="btn btn--ghost" :disabled="! page.canNext || store.busy" @click="store.load(page.current + 1)">{{ page.nextLabel }}</button>
        </nav>
    </template>
</template>
