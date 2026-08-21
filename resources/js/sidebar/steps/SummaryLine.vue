<script setup>
import { t as translate, tp as translateWith } from '../i18n.js';
import { money } from '../money.js';
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
                <span v-if="line.is_pack" class="icon ic-b1 prod-ico" aria-hidden="true">
                    <svg viewBox="0 0 40 40" width="20" height="20">
                        <path d="M 7 33 L 33 33" />
                        <path d="M 10 33 L 10 25 Q 10 22 13 22 L 27 22 Q 30 22 30 25 L 30 33" />
                        <path d="M 11.5 27.5 L 28.5 27.5" class="dashed thin" />
                        <path d="M 20 22 L 20 15" />
                        <path class="flame accent-fill" d="M 20 14.5 Q 22.4 11.6 20 8.6 Q 17.6 11.6 20 14.5 Z" />
                    </svg>
                </span>
                <span v-else class="tk prod-ico" aria-hidden="true">
                    <svg viewBox="0 0 60 36" width="22" height="13" fill="none">
                        <g class="body">
                            <path d="M 4 4 L 42 4 L 42 8 A 1.4 1.4 0 0 0 42 12 L 42 16 A 1.4 1.4 0 0 0 42 20 L 42 24 A 1.4 1.4 0 0 0 42 28 L 42 32 L 4 32 L 4 28 A 1.4 1.4 0 0 0 4 24 L 4 20 A 1.4 1.4 0 0 0 4 16 L 4 12 A 1.4 1.4 0 0 0 4 8 Z" />
                            <line x1="14" y1="14" x2="34" y2="14" class="thin" />
                            <line x1="14" y1="20" x2="34" y2="20" class="thin" />
                        </g>
                        <path class="dashed" d="M 42 5.5 L 42 30.5" />
                        <g class="stub">
                            <path d="M 42 4 L 56 4 L 56 8 A 1.4 1.4 0 0 0 56 12 L 56 16 A 1.4 1.4 0 0 0 56 20 L 56 24 A 1.4 1.4 0 0 0 56 28 L 56 32 L 42 32 L 42 28 A 1.4 1.4 0 0 1 42 24 L 42 20 A 1.4 1.4 0 0 1 42 16 L 42 12 A 1.4 1.4 0 0 1 42 8 Z" />
                            <text class="stubnum" x="49" y="22.5" text-anchor="middle">1</text>
                        </g>
                    </svg>
                </span>
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
