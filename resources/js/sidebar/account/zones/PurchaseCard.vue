<script setup>
/**
 * **Un PEDIDO, como tarjeta** (`specs/desglose-dinero-cliente.md` §19, `DECISIONES #129`).
 *
 * ⚠️⚠️ **El desglose de dinero vive AQUÍ y ya no en la tarjeta de la reserva** (decisión del owner,
 * spec §5·4). Es del PEDIDO: en un pedido con tres reservas se pintaba tres veces, con importes que
 * no cuadraban con la tarjeta que los rodeaba. El marcado no se ha reescrito — se ha MUDADO, para
 * que no nazca una segunda forma de pintar los dos ejes.
 *
 * **Pinta y no decide.** Qué se enseña lo compone `account/orders.js::orderRow()`, el mismo que ya
 * componía este pedido cuando se desplegaba desde una reserva; el ledger, `financialsOf()`. Aquí no
 * hay ninguna regla y no se habla con la API (`CE-6`).
 */
defineProps({
    /** El pedido ya compuesto por `orderRow()`. */
    row: { type: Object, required: true },
    /** ¿Está desplegado su desglose? Lo decide la pantalla: uno cada vez. */
    open: { type: Boolean, default: false },
    /** ¿Hay una petición en vuelo? Bloquea el reintento. */
    busy: { type: Boolean, default: false },
    account: { type: Object, default: () => ({}) },
});

defineEmits(['toggle', 'retry']);
</script>

<template>
    <li class="orders__item">
        <div class="orders__order">
            <span class="orders__code">{{ (account?.purchases?.ref ?? '').replace(':code', row.code ?? '') }}</span>
            <span class="orders__status" :class="'orders__status--' + row.status">{{ row.statusLabel }}</span>
        </div>

        <!-- Lo que el cliente busca de un vistazo: cuándo lo hizo y cuánto vale. El detalle, dentro. -->
        <div class="orders__meta">
            {{ row.createdLabel }}
            <span class="orders__line-price">· {{ row.totalLabel }}</span>
        </div>

        <button type="button" class="orders__gate-toggle" :aria-expanded="open ? 'true' : 'false'"
                @click="$emit('toggle')">
            {{ open ? account?.purchases?.hide : account?.purchases?.show }}
        </button>

        <div v-if="open">
            <!--
              ⚠️ **Las reservas del pedido van ANTES del dinero**, y no es orden alfabético: el
              desglose habla de «Cumpleaños Jump» y de «Resto de la señal de Cumpleaños Jump», así
              que saber qué hay dentro del pedido es lo que hace legible lo de abajo.
            -->
            <p class="orders__ledger-title">{{ account?.purchases?.reservations ?? '' }}</p>
            <ul class="orders__lines">
                <template v-for="line in row.lines" :key="line.id">
                    <li class="orders__line">
                        <span class="orders__line-name">
                            {{ line.name }}
                            <span class="orders__line-unit">· {{ line.whenLabel }} · {{ line.quantityLabel }}</span>
                            <span v-if="line.badge" class="orders__line-badge" :class="'orders__line-badge--' + line.badge.key">{{ line.badge.label }}</span>
                        </span>
                        <span class="orders__line-price">{{ line.priceLabel }}</span>
                    </li>
                    <!--
                      ⚠️⚠️ **Los COMPLEMENTOS también, y omitirlos rompía la única promesa de esta
                      pantalla.** Medido sobre `R-UPFQAB`: la reserva pone 120,00 € y «Valor del
                      pedido» pone 124,00 €, y los 4,00 € que faltan son unos calcetines que la API
                      publica y esta lista no pintaba. Un desglose al que le falta una línea **no es
                      un desglose**: cuadra por dentro y no cuadra para quien lo lee.
                    -->
                    <li v-for="addon in line.addons" :key="'a' + addon.id" class="orders__line">
                        <span class="orders__line-name">+ {{ addon.name }} <span class="orders__line-unit">· {{ addon.quantityLabel }}</span></span>
                        <span class="orders__line-price">{{ addon.priceLabel }}</span>
                    </li>
                </template>
            </ul>

            <!--
              ⚠️⚠️ **DOS BLOQUES, y no se mezclan** (`DECISIONES #127`). Arriba lo que vale y por qué
              canal se paga —cierra siempre—; abajo qué ha pasado con su dinero. «Devuelto» y
              «Pendiente de devolución» son de otro eje: no restan del valor, y pintarlos dentro de su
              columna es lo que la hacía ilegible.
            -->
            <div class="orders__ledger">
                <p class="orders__ledger-title">{{ row.financials.value.title }}</p>

                <div v-for="(vRow, i) in row.financials.value.rows" :key="'v' + i" class="orders__gate">
                    <span class="orders__gate-label">{{ vRow.label }}</span>
                    <strong>{{ vRow.amountLabel }}</strong>
                </div>

                <div v-if="row.financials.value.gate">
                    <div class="orders__gate">
                        <span class="orders__gate-label">{{ row.financials.value.gate.label }}</span>
                        <strong class="orders__gate-amount">{{ row.financials.value.gate.amountLabel }}</strong>
                    </div>
                    <div v-for="(gateLine, i) in row.financials.value.gate.lines" :key="i" class="orders__gate-line">
                        <span>↳ {{ gateLine.label }}</span><strong>{{ gateLine.amountLabel }}</strong>
                    </div>
                    <p class="orders__gate-caption">{{ row.financials.value.gate.caption }}</p>
                </div>

                <div class="orders__final">
                    <span>{{ row.financials.value.total.label }}</span>
                    <strong>{{ row.financials.value.total.amountLabel }}</strong>
                </div>
            </div>

            <!--
              ⚠️⚠️ **EL EJE DE CAJA, solo cuando dice algo que la columna de arriba NO diga ya**
              (`DECISIONES #130`): hubo una devolución, se debe una, o **lo cobrado no coincide con lo
              pagado** —que en un pedido sano no puede pasar (`PAY-17`) y en uno con el dato roto sí—.
              Enseñarlo siempre repetía el mismo importe con dos nombres casi iguales, y eso enseña a
              saltarse el bloque que sí importa. Lo verificable —la FECHA del cobro— va arriba, en la
              línea del canal. La condición la decide el dominio (`ledger.cash.has_cash`), no esta
              pantalla: derivarla aquí fue el origen de `L1`.
            -->
            <div v-if="row.financials.cash" class="orders__ledger orders__ledger--cash">
                <p class="orders__ledger-title">{{ row.financials.cash.title }}</p>
                <div v-for="(cRow, i) in row.financials.cash.rows" :key="'c' + i"
                     class="orders__refund" :class="{ 'orders__refund--anchor': cRow.anchor }">
                    <span class="orders__refund-label">{{ cRow.label }}</span>
                    <strong class="orders__refund-amount">{{ cRow.amountLabel }}</strong>
                </div>
                <p class="orders__gate-caption">{{ row.financials.cash.caption }}</p>
            </div>

            <!-- ⚠️ **La FRASE, no un número.** La compone el servidor, que es quien sabe qué caso es. -->
            <p v-if="row.financials.note" class="orders__ledger-note">{{ row.financials.note }}</p>

            <!-- Trazabilidad: lo facturado al reservar, solo si ya no es lo que vale. -->
            <div v-if="row.financials.invoiced" class="orders__ledger-invoiced">
                <div class="orders__gate">
                    <span class="orders__gate-label">{{ row.financials.invoiced.label }}</span>
                    <strong>{{ row.financials.invoiced.amountLabel }}</strong>
                </div>
                <p class="orders__gate-caption">{{ row.financials.invoiced.hint }}</p>
            </div>
        </div>

        <!--
          ⚠️ El reintento va FUERA del desplegable, igual que en la tarjeta de la reserva: un pedido a
          medio pagar es lo más urgente de la pantalla y esconderlo tras un clic sería enterrar el
          único camino que le queda al cliente para no perder su plaza.
        -->
        <div v-if="row.canRetry" class="orders__retry">
            <button type="button" class="btn btn--zone" :disabled="busy" @click="$emit('retry')">{{ account?.orders?.retry_payment ?? '' }}</button>
            <p class="orders__retry-hint">{{ account?.orders?.retry_hint ?? '' }}</p>
        </div>
    </li>
</template>
