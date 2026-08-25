<script setup>
import { t as translate, tp as translateWith } from '../i18n.js';
import { money } from '../money.js';
import ProductIcon from '../ProductIcon.vue';
import { shortDate } from '../progress.js';

/**
 * Una fila del RESUMEN de una reserva (Fase 4 · paso 4.6·1).
 *
 * La pintan dos pantallas: la de **pagar** (paso 8) y la de **reserva creada** (paso 6). Se midió
 * contra el Blade nodo a nodo antes de extraerla: los dos bloques son **el mismo árbol**, hasta el
 * `<span>` sin clase del precio y el `<div class="cart__lines">` que se emite aunque quede vacío.
 *
 * ⚠️ **Extraer aquí es lo correcto y en 4.0b·5 quedó escrito el criterio: la pregunta que decide es
 * «¿hay dos copias?»**, no «¿debería reutilizarse?». Al transcribir el paso 6 iba a haberlas, y con el
 * agravante de que dos copias de un marcado que el CSS mira por estructura divergen **en silencio**:
 * el diff de árbol de cada pantalla seguiría verde por separado mientras una de las dos pierde el
 * estilo.
 *
 * ⚠️ **Lo que NO comparte es el paso 4** (el carrito), y no por descuido: aquella fila lleva botón de
 * quitar, el precio en un `.cart__price` y los avisos de campos pendientes. Son cuatro diferencias que
 * el diff SÍ ve, así que unificarla también sería fabricar un componente con banderas para tapar un
 * parecido que no es igualdad.
 *
 * **Raíz única y sin envoltorio**: el `<li>` es el nodo raíz porque `.cart > .cart__item` es
 * descendencia directa y un nodo de más rompería el estilo con todas las clases correctas (§4.2).
 */
const props = defineProps({
    /**
     * La fila en la forma del PRESUPUESTO: `product_name`, `quantity`, `subtotal_cents`, `date`,
     * `time`, `is_pack`, `event[]`, `addons[]` y el trío de la señal.
     *
     * El resumen del pedido llega traducido a esta misma forma por `outcome.js`, que es lo que permite
     * que las dos pantallas compartan el marcado sin que el componente sepa de dónde vienen los datos.
     */
    line: { type: Object, required: true },

    messages: { type: Object, default: () => ({}) },
    locale: { type: String, default: 'es' },
});

const t = (key) => translate(props.messages, key);
const tp = (key, params) => translateWith(props.messages, key, params);

const shortTime = (time) => String(time ?? '').slice(0, 5);

const dayLabel = (date) => {
    const label = shortDate(date, props.locale);

    return label === '' ? '' : label.charAt(0).toUpperCase() + label.slice(1);
};

const includedLabel = (addon) => (addon.free_quantity >= addon.quantity
    ? t('addon_included')
    : tp('addon_included_partial', { count: addon.free_quantity }));
</script>

<template>
    <li class="cart__item">
        <div class="cart__head">
            <span class="cart__when">
                <!-- ⚠️ El icono lo manda el SERVIDOR (`line.icon`, `DECISIONES #140`). Aquí vivía un
                     `v-if="line.is_pack"` con la geometría entera escrita dentro, y el mismo bloque
                     estaba copiado en el otro paso: cuatro copias de dos dibujos, y un catálogo
                     entero repartido en esos dos. -->
                <ProductIcon :icon="line.icon" />
                <template v-if="line.is_pack">{{ tp('guests_count', { count: line.quantity }) }} · {{ line.product_name }}</template>
                <template v-else>{{ line.quantity }}&times; {{ line.product_name }}</template>
            </span>
            <!-- ⚠️ Sin clase, al contrario que en el carrito: aquí no hay botón de quitar al lado
                 y el precio no necesita reservar su hueco. -->
            <span>{{ money(line.subtotal_cents) }}</span>
        </div>

        <!-- Se emite SIEMPRE, aunque quede vacío: el condicional del Blade está DENTRO. -->
        <div class="cart__lines">
            <span v-if="line.date">{{ dayLabel(line.date) }} · {{ shortTime(line.time) }}</span>
        </div>

        <ul v-if="line.event.length" class="cart__event">
            <li v-for="answer in line.event" :key="answer.key">
                <span class="cart__event-label">{{ answer.label }}:</span> {{ answer.value }}
            </li>
        </ul>

        <ul v-if="line.addons.length" class="cart__addons">
            <!-- La clave es la POSICIÓN a propósito: la fila la comparten dos fuentes —el presupuesto
                 trae `product_id` y el pedido no— y la lista es estática dentro de un render. -->
            <li v-for="(addon, i) in line.addons" :key="i">
                <span>+ {{ addon.quantity }}&times; {{ addon.product_name }}<em v-if="addon.free_quantity" class="cart__addon-incl">{{ includedLabel(addon) }}</em></span>
                <span>{{ money(addon.subtotal_cents) }}</span>
            </li>
        </ul>

        <!-- La señal se detalla en la card del producto que la cobra (#225): en una cesta mixta,
             etiquetar el agregado engaña. -->
        <p v-if="line.has_deposit" class="cart__deposit">{{ tp('deposit_card_note', { deposit: money(line.deposit_cents), rest: money(line.gate_remainder_cents) }) }}</p>
    </li>
</template>
