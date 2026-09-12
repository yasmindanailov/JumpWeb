<script setup>
/**
 * **Un PEDIDO, como tarjeta** (`specs/desglose-dinero-cliente.md` §19, `DECISIONES #129`; desde la
 * T3·1 del libro, `specs/desglose-libro.md` §6.3, pinta el LIBRO de `#305`).
 *
 * ⚠️⚠️ **El dinero vive AQUÍ y ya no en la tarjeta de la reserva** (decisión del owner, spec §5·4).
 * Es del PEDIDO: en un pedido con tres reservas se pintaba tres veces, con importes que no cuadraban
 * con la tarjeta que los rodeaba.
 *
 * **Pinta y no decide.** Qué se enseña lo compone `account/orders.js::orderRow()`; el libro,
 * `financialsOf()`. Aquí no hay ninguna regla y no se habla con la API (`CE-6`).
 */
import GuestMinorsPanel from './GuestMinorsPanel.vue';

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
              ⚠️ **Las reservas del pedido van ANTES del dinero**, y no es orden alfabético: el libro
              habla de «Cumpleaños Jump · Cantidad: 4 → 2», así que saber qué hay dentro del pedido es
              lo que hace legible lo de abajo.
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
                      pantalla.** Medido sobre `R-UPFQAB`: la reserva pone 120,00 € y el Total
                      124,00 €, y los 4,00 € que faltan son unos calcetines que la API publica y esta
                      lista no pintaba. Un desglose al que le falta una línea **no es un desglose**:
                      cuadra por dentro y no cuadra para quien lo lee.
                    -->
                    <li v-for="addon in line.addons" :key="'a' + addon.id" class="orders__line">
                        <span class="orders__line-name">+ {{ addon.name }} <span class="orders__line-unit">· {{ addon.quantityLabel }}</span></span>
                        <span class="orders__line-price">{{ addon.priceLabel }}</span>
                    </li>
                </template>
            </ul>

            <!--
              ⚠️⚠️ **EL LIBRO** (`DECISIONES #305`): cada gestión una línea con su signo y su fecha, y
              el Total es la suma. Con el libro «en revisión» (`is_consistent = false`) esta lista
              llega VACÍA y solo quedan el Total y los cobros: si las identidades no cierran, ninguna
              línea es cierta (`#132`). La condición la decide el servidor, no esta tarjeta.
            -->
            <div class="orders__ledger">
                <p v-if="row.financials.movements.length" class="orders__ledger-title">{{ row.financials.movementsTitle }}</p>

                <div v-for="(m, i) in row.financials.movements" :key="'m' + i"
                     class="orders__mov" :class="{ 'orders__mov--neg': m.negative }">
                    <span class="orders__mov-label">{{ m.label }} <span class="orders__mov-date">· {{ m.dateLabel }}</span></span>
                    <strong>{{ m.amountLabel }}</strong>
                </div>

                <div class="orders__final">
                    <span>{{ row.financials.total.label }}</span>
                    <strong>{{ row.financials.total.amountLabel }}</strong>
                </div>
            </div>

            <!--
              Los PAGOS Y DEVOLUCIONES: lo que de verdad entró y salió, con su fecha — lo único que el
              cliente puede cotejar con su extracto. Una devolución en curso o fallida se lista
              atenuada y NO cuenta en lo pagado (`PAY-09` impide devolverla dos veces).
            -->
            <div v-if="row.financials.settlements.length" class="orders__ledger orders__ledger--cash">
                <p class="orders__ledger-title">{{ row.financials.settlementsTitle }}</p>

                <div v-for="(s, i) in row.financials.settlements" :key="'s' + i"
                     class="orders__mov" :class="{ 'orders__mov--neg': s.negative, 'orders__mov--pending': ! s.effective }">
                    <span class="orders__mov-label">{{ s.label }} <span class="orders__mov-date">· {{ s.dateLabel }}</span></span>
                    <strong>{{ s.amountLabel }}</strong>
                </div>

                <div v-if="row.financials.paid" class="orders__final">
                    <span>{{ row.financials.paid.label }}</span>
                    <strong>{{ row.financials.paid.amountLabel }}</strong>
                </div>
            </div>

            <!--
              EL SALDO, que se liquida en el parque (D2): positivo se paga, negativo se devuelve. La
              CLASE llega del servidor (`balance.kind`): «a devolver en el parque» o «pendiente de
              devolución» depende de si habrá visita, y eso no está en el número.
            -->
            <div v-if="row.financials.balance" class="orders__balance" :class="'orders__balance--' + row.financials.balance.kind">
                <span>{{ row.financials.balance.label }}</span>
                <strong>{{ row.financials.balance.amountLabel }}</strong>
                <p v-if="row.financials.balance.rest" class="orders__gate-caption">{{ row.financials.balance.rest }}</p>
            </div>

            <!-- ⚠️ **La FRASE, no un número.** La compone el servidor, que es quien sabe qué caso es. -->
            <p v-if="row.financials.note" class="orders__ledger-note">{{ row.financials.note }}</p>
        </div>

        <!--
          Los JUSTIFICANTES de menores invitados (`specs/waiver-por-reserva.md` §4.10, `#337`).
          ⚠️⚠️ **Ya NO va dentro del desplegable** (`#401`): estaba tras «Ver el desglose» y el owner
          no lo encontró —*«no me sale nada del enlace»*—. La «acción explícita» que §4.10 exige la
          sigue cumpliendo la PETICIÓN, que se hace al montar esta tarjeta y no se siembra en el
          contexto de cuenta; esconderlo además tras un clic no protegía nada, solo lo ocultaba.
          ⚠️ El componente se pinta solo si ese pedido TIENE justificantes o enlace: en un pedido
          normal no aparece nada.
        -->
        <GuestMinorsPanel v-if="row.code" :code="row.code" :account="account" />

        <!--
          ⚠️ El reintento va FUERA del desplegable, igual que en la tarjeta de la reserva: un pedido a
          medio pagar es lo más urgente de la pantalla y esconderlo tras un clic sería enterrar el
          único camino que le queda al cliente para no perder su plaza.

          ⚠️⚠️ **Y es uno de los DOS botones de relleno de ACCIÓN de las catorce pantallas de cuenta**
          (`#551`, el mapa del naranja): aquí se cobra, así que va en `.btn` pelado —que ES el relleno
          de acción— y no en `.btn--ink`. *Una cuenta no vende; reintentar un cobro sí.* Los otros doce
          botones de esta área son secundarios en tinta.
        -->
        <div v-if="row.canRetry" class="orders__retry">
            <button type="button" class="btn" :disabled="busy" @click="$emit('retry')">{{ account?.orders?.retry_payment ?? '' }}</button>
            <p class="orders__retry-hint">{{ account?.orders?.retry_hint ?? '' }}</p>
        </div>
    </li>
</template>
