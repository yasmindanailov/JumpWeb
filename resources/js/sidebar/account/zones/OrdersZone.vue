<script setup>
/**
 * **«Mis reservas»**: el historial de pedidos del cliente (`docs/specs/area-cliente.md` §4.2).
 * Espeja `/mi-cuenta/pedidos`, que de cara al cliente se titula así — medido en `lang/`.
 *
 * **PINTA y nada más.** Qué se enseña de cada pedido lo compone `account/orders.js` y de dónde salen
 * los datos lo sabe `stores/orders.js`; los dos se prueban con `node --test`. Este componente no
 * conoce la API ni recompone ninguna regla (`CE-6`).
 */
defineProps({
    /** Las filas ya compuestas por `account/orders.js`. */
    rows: { type: Array, default: () => [] },
    /** La paginación ya compuesta, o `null` si todo cabe en una página. */
    page: { type: Object, default: null },
    busy: { type: Boolean, default: false },
    error: { type: String, default: '' },
    expired: { type: Boolean, default: false },
    account: { type: Object, default: () => ({}) },
});

defineEmits(['go-page', 'retry', 'sign-in']);
</script>

<template>
    <!-- La sesión caducó con el cajón abierto: se dice y se ofrece la puerta, en vez de una lista vacía. -->
    <p v-if="expired" class="purchase__empty">
        <button type="button" class="btn btn--zone" @click="$emit('sign-in')">{{ account?.login?.cta ?? '' }}</button>
    </p>

    <template v-else>
        <p v-if="error" class="bk-error" role="alert">{{ error }}</p>

        <p v-if="! rows.length && ! busy" class="purchase__empty">{{ account?.orders?.empty ?? '' }}</p>

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

                <div class="orders__foot">
                    <span class="orders__total">{{ row.totalLabel }}</span>
                    <span v-if="row.refund" class="orders__refund">{{ row.refund.label }} · {{ row.refund.amountLabel }}</span>
                </div>

                <div v-if="row.canRetry" class="orders__retry">
                    <button type="button" class="btn btn--zone" :disabled="busy" @click="$emit('retry', row.code)">{{ account?.orders?.retry_payment ?? '' }}</button>
                    <p class="orders__retry-hint">{{ account?.orders?.retry_hint ?? '' }}</p>
                </div>
            </li>
        </ul>

        <nav v-if="page" class="orders__pagination" :aria-label="page.label">
            <button type="button" class="btn btn--ghost" :disabled="! page.canPrev || busy" @click="$emit('go-page', page.current - 1)">{{ page.prevLabel }}</button>
            <span class="orders__pagination-page">{{ page.pageLabel }}</span>
            <button type="button" class="btn btn--ghost" :disabled="! page.canNext || busy" @click="$emit('go-page', page.current + 1)">{{ page.nextLabel }}</button>
        </nav>
    </template>
</template>
