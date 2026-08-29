<script setup>
import { t as translate, tp as translateWith } from '../i18n.js';
import { money } from '../money.js';
import ProductIcon from '../ProductIcon.vue';
import DependentPicker from './DependentPicker.vue';
import { shortDate } from '../progress.js';

/**
 * Paso 4 — el CARRITO (Fase 4 · paso 4.3·2).
 *
 * **Pinta lo que el presupuesto dice.** Los importes llegan tarificados por `POST /orders/quote`, que
 * es el mismo contrato que alimenta la creación del pedido; aquí no se suma nada (`PAY-12`). Y el
 * TOTAL no está en esta pantalla: vive en el pie, que es donde también está el «Ir a pagar».
 *
 * ⚠️ **Tres detalles del árbol que no se adivinan leyendo el Blade** y que el diff sí ve:
 *  - `.cart__lines` se emite SIEMPRE, incluso vacío: el `@if` está DENTRO del `<div>`, no fuera. Un
 *    `v-if` sobre el div —lo natural en Vue— deja un nodo de menos y se lleva la separación que da
 *    la cabecera;
 *  - el icono de producto NO es un `<svg>` suelto: es un `<span>` envoltorio con `aria-hidden`, y ese
 *    envoltorio es contrato;
 *  - el botón de quitar lleva `aria-label`, que es de los pocos atributos cuyo VALOR compara el gate:
 *    tiene que ser el texto traducido exacto.
 *
 * ⚠️ **`index` es la posición en la CESTA, no el ordinal de la lista pintada.** El presupuesto salta
 * las líneas cuyo producto dejó de venderse y conserva el índice original, así que la segunda línea
 * que se ve puede ser la número 3. Emitir el ordinal borra otra reserva, y el diff de árbol no lo ve
 * porque descarta los manejadores de evento.
 */
const props = defineProps({
    /** Las líneas del presupuesto, ya emparejadas con las respuestas del pack (`cart.js`). */
    lines: { type: Array, default: () => [] },
    /** Aviso de «carrito listo» tras confirmar un pedido y volver a empezar. */
    confirmed: { type: Boolean, default: false },
    /** Error de la cesta, ya traducido. Ocupa el sitio del `@error('cart')` del Blade. */
    error: { type: String, default: '' },
    messages: { type: Object, default: () => ({}) },
    locale: { type: String, default: 'es' },
    /**
     * Los menores a cargo que se ofrecen (Fase 6 · tanda 4, `assignment.js::assignableOptions()`).
     * Vacío sin sesión o sin menores, y entonces ninguna línea pinta el selector.
     */
    dependentOptions: { type: Array, default: () => [] },
    /** El aviso que no es un error: `'assign'` tras identificarse con menores y entradas sin asignar. */
    notice: { type: String, default: '' },
});

defineEmits(['back', 'remove', 'add-another', 'update-field', 'toggle-dependent']);

const t = (key) => translate(props.messages, key);
const tp = (key, params) => translateWith(props.messages, key, params);

/** `10:00:00` → `10:00`. El servidor guarda la hora canónica; la fila enseña la corta. */
const shortTime = (time) => String(time ?? '').slice(0, 5);

/**
 * ⚠️ La fecha de la fila la compone el cliente con `Intl` y el servidor con Carbon (§4.5). Medido: en
 * inglés y en francés coinciden; en español difieren los puntos de abreviatura. Es la divergencia ya
 * declarada, la misma que la línea de contexto de la banda.
 */
const dayLabel = (date) => {
    const label = shortDate(date, props.locale);

    return label === '' ? '' : label.charAt(0).toUpperCase() + label.slice(1);
};

/**
 * El texto de «incluido» se elige con `>=`, no con `>`: un complemento con una unidad gratis de una
 * sola pedida dice «Incluido», no «1 incluido(s) gratis».
 */
const includedLabel = (addon) => (addon.free_quantity >= addon.quantity
    ? t('addon_included')
    : tp('addon_included_partial', { count: addon.free_quantity }));
</script>

<template>
    <!--
        ⚠️ **El paso 4 no tiene banda de progreso** —`progress.js` solo la compone para el 2 y el 3—, así
        que hasta el 2026-08-28 el carrito era la única pantalla del embudo CON paso anterior y SIN
        «Volver» ni CTA propio (el 2 y el 3 lo traen por la banda; el 5 y el 8, por su `bk-back`; el
        1, 6, 7, 9, 10 y 11 no lo llevan a propósito): se entraba desde la hora y la única salida era
        «+ Añadir otra reserva», al pie. Lo vio el owner
        (`DECISIONES #210`). Mismo nodo que el «Volver» de los pasos 5 y 8 (`bk-back purchase__back`), y
        el mismo destino que «añadir otra»: el catálogo, con la selección limpia. Con la cesta VACÍA
        también se pinta —es justo la pantalla que se quedaba sin CTA y sin salida—.
    -->
    <button type="button" class="bk-back purchase__back" @click="$emit('back')">
        <!-- `arrow-left` del sistema de diseño, copiado byte a byte (`SidebarIconParityTest`).
             ⚠️ Este dibujo llevaba SIN COMPROBARSE desde que se escribió el docblock del fichero:
             la guarda arrancaba en un «`<svg>`» citado en un comentario de JS y se lo saltaba
             (`#258`). Al arreglarla aparecieron tres iconos ciegos, y éste era uno. -->
        <svg class="arrow-ico" width="16" height="16" viewBox="0 0 24 24" fill="currentColor"
             stroke="currentColor" stroke-width="3" stroke-linejoin="round" stroke-linecap="round"
             aria-hidden="true" focusable="false">
            <g transform="translate(24 0) scale(-1 1)">
                <path d="M13.6 6.4 19.2 12l-5.6 5.6z" />
                <path d="M4.6 12h9.4" fill="none" />
            </g>
        </svg>
        <span>{{ t('back') }}</span>
    </button>

    <h3 class="wiz__title">{{ t('cart_title') }}</h3>

    <p v-if="lines.length === 0" class="purchase__empty">{{ t('cart_empty') }}</p>

    <template v-else>
        <ul class="cart">
            <li v-for="line in lines" :key="line.index" class="cart__item">
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
                    <span class="cart__price">{{ money(line.subtotal_cents) }}</span>
                    <button type="button" class="cart__remove" :aria-label="t('remove')" @click="$emit('remove', line.index)">&times;</button>
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

                <!--
                    ⚠️ **El único bloque del cajón que la web NO tiene, y es la desviación declarada en
                    `DECISIONES #38(d)`**: «al restaurar, las líneas de pack piden esos campos otra vez».
                    La cesta persistida vuelve sin `event_data` —nombre de un menor, su edad y sus
                    alergias no se dejan en el navegador—, así que una línea de pack restaurada está
                    incompleta por construcción. Sin esto, el presupuesto la tarifica igual y el fallo
                    aparece al PAGAR, con un 422 que el cliente no puede arreglar desde ninguna pantalla.

                    Reutiliza el marcado del paso 3 (`.eventfields`) a propósito: es el mismo control,
                    con el mismo estilo, pidiendo lo mismo.
                -->
                <div v-if="line.pending?.length" class="eventfields cart__pending">
                    <p class="form__error">{{ t('errors.event_required') }}</p>
                    <label v-for="field in line.pending" :key="field.key" class="eventfields__field">
                        <span class="eventfields__label">{{ field.label }}<span class="eventfields__req" aria-hidden="true">*</span></span>
                        <textarea v-if="field.type === 'textarea'" rows="2" required
                                  @input="$emit('update-field', line.index, field.key, $event.target.value)"></textarea>
                        <input v-else
                               :type="field.type === 'number' ? 'number' : 'text'"
                               :min="field.type === 'number' ? 0 : null"
                               required
                               @input="$emit('update-field', line.index, field.key, $event.target.value)">
                    </label>
                </div>

                <!-- ¿Para quién son estas entradas? (Fase 6 · tanda 4): solo ENTRADAS, solo con menores
                     que ofrecer. Aquí se edita una línea ya en la cesta —y persistida: son ids—. -->
                <DependentPicker v-if="! line.is_pack && dependentOptions.length"
                                 :scope="'l' + line.index"
                                 :options="dependentOptions" :selected="line.dependent_ids ?? []" :quantity="line.quantity" :messages="messages"
                                 @toggle="$emit('toggle-dependent', line.index, $event)" />

                <ul v-if="line.addons.length" class="cart__addons">
                    <li v-for="addon in line.addons" :key="addon.product_id">
                        <span>+ {{ addon.quantity }}&times; {{ addon.product_name }}<em v-if="addon.free_quantity" class="cart__addon-incl">{{ includedLabel(addon) }}</em></span>
                        <span>{{ money(addon.subtotal_cents) }}</span>
                    </li>
                </ul>

                <!-- La señal es POR LÍNEA (#225): en una cesta mixta, etiquetar el agregado engaña. -->
                <p v-if="line.has_deposit" class="cart__deposit">{{ tp('deposit_card_note', { deposit: money(line.deposit_cents), rest: money(line.gate_remainder_cents) }) }}</p>
            </li>
        </ul>

        <div class="purchase__foot purchase__foot--info">
            <p v-if="error" class="form__error">{{ error }}</p>
            <div v-if="confirmed" class="purchase__confirm">{{ t('confirm_next') }}</div>
            <!-- La puerta 2 (`DECISIONES #202`·1): quien acaba de identificarse con menores a cargo vuelve
                 aquí a decir para quién es cada entrada. Es un aviso, no un error: mismo bloque que
                 «carrito listo». -->
            <div v-if="notice === 'assign'" class="purchase__confirm" role="status">{{ t('dependents.notice') }}</div>
            <button type="button" class="purchase__add-more" @click="$emit('add-another')">+ {{ t('add_another') }}</button>
        </div>
    </template>
</template>
