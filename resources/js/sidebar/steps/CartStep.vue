<script setup>
import { reactive } from 'vue';
import { t as translate, tp as translateWith } from '../i18n.js';
import { money } from '../money.js';
import ProductIcon from '../ProductIcon.vue';
import DependentPicker from './DependentPicker.vue';
import { shortDate } from '../progress.js';
import { allPendingAnswered, pendingAnswers } from '../cart.js';

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

// ⚠️ Sin `back`: el «Volver» de esta pantalla lo trae la banda desde `#555`.
const emit = defineEmits(['remove', 'add-another', 'update-field', 'toggle-dependent']);

/**
 * ❗❗❗ **LO TECLEADO EN «FALTAN DATOS» NO SALE DE AQUÍ HASTA QUE SE CONFIRMA** (`#560`).
 *
 * El bloque emitía en cada pulsación, y eso **borraba el campo con la primera letra**: `line.pending`
 * lista los obligatorios que siguen VACÍOS, así que en cuanto el valor deja de estarlo el campo sale
 * de la lista y el `v-for` lo quita del DOM — con la letra dentro. Reproducido: un campo pendiente,
 * se teclea «S», y `pendingEventFields()` pasa de 1 a 0. ▶ *El cliente perdía el foco a la primera
 * tecla y el nombre del homenajeado se guardaba con un carácter.*
 *
 * ⚠️ El borrador es local y **sin estado compartido a propósito**: no es del pedido hasta que se
 * confirma, así que ni se persiste ni pasa por el store. Es también lo que hace que el componente
 * siga siendo renderizable en Node —no toca `document` ni `window`—, que es la condición del diff.
 */
const drafts = reactive({});

/** El borrador de una línea, creado al vuelo la primera vez que se escribe en ella. */
const draftOf = (index) => (drafts[index] ??= {});

/** ¿Se puede guardar ya? La REGLA vive en `cart.js`, que comparte su criterio de «vacío». */
const draftIsComplete = (line) => allPendingAnswered(line.pending, draftOf(line.index));

/** Descarta lo tecleado. El bloque sigue ahí: lo que falta sigue faltando. */
const discardPending = (line) => { delete drafts[line.index]; };

/**
 * Confirma el borrador: emite un cambio por campo y lo suelta.
 *
 * ⚠️ Emite **uno por campo y no el objeto entero** porque ése es el contrato que ya existe
 * (`update-field`), y el store escribe respuesta a respuesta.
 */
function confirmPending(line) {
    for (const answer of pendingAnswers(line.pending, draftOf(line.index))) {
        emit('update-field', line.index, answer.key, answer.value);
    }

    delete drafts[line.index];
}

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

/**
 * El nombre con su cantidad delante, **y la cantidad solo si informa** (`#559`).
 *
 * ⚠️ «1× Calcetines antideslizantes» multiplica por uno: el signo no dice nada y encima roba el
 * primer golpe de vista al nombre, que es lo único que el cliente viene a reconocer. Con dos o más sí
 * informa, y entonces va delante.
 */
const named = (quantity, name) => (quantity > 1 ? `${quantity}\u00d7 ${name}` : name);

/**
 * ¿Se pinta el importe de este complemento?
 *
 * ⚠️ **Un complemento incluido ENTERO ya lo dice con su palabra**, así que el «0,00 €» de al lado lo
 * repite — y dos formas de decir «gratis» en la misma línea hacen dudar de si son lo mismo. Con la
 * inclusión PARCIAL sí se pinta: ahí el importe es lo que se paga por el resto, que es un dato nuevo.
 */
const showsAddonPrice = (addon) => ! (addon.free_quantity >= addon.quantity);
</script>

<template>
    <!--
        ⚠️⚠️ **EL «VOLVER» DE ESTA PANTALLA SE RETIRÓ EN `#555`, y no es una pérdida**: la banda de
        progreso vive ahora en las cinco pantallas del camino y trae el suyo, así que mantener el
        propio dejaba **dos** en la misma pantalla. Su destino —el catálogo, con la selección limpia—
        se mudó a `goBack()`, que es hoy el único «Volver» del embudo.
        ▶ La historia de por qué existía se conserva porque explica el agujero que tapó: hasta el
        2026-08-28 el carrito era la única pantalla del embudo CON paso anterior y SIN salida, y lo vio
        el owner (`DECISIONES #210`). Eso ya no puede repetirse: con la banda en las cinco, una
        pantalla sin «Volver» sería una pantalla sin banda.
    -->

    <h3 class="wiz__title">{{ t('cart_title') }}</h3>

    <p v-if="lines.length === 0" class="purchase__empty">{{ t('cart_empty') }}</p>

    <template v-else>
        <ul class="cart">
            <li v-for="line in lines" :key="line.index" class="cart__item">
                <div class="cart__head">
                    <!-- ⚠️ **El icono sale del texto y pasa a su propio cuadro** (`#558`, artboard
                         `Pasos Compra PJP`): iba dentro del `<span>` del nombre, así que con un nombre
                         de dos líneas quedaba flotando a mitad de la primera. En su azulejo ancla
                         arriba, alineado con la primera línea, y la tarjeta se lee de izquierda a
                         derecha — qué es, qué es, cuánto.
                         ⚠️ El dibujo lo manda el SERVIDOR (`line.icon`, `DECISIONES #140`). -->
                    <span class="cart__ico" aria-hidden="true"><ProductIcon :icon="line.icon" /></span>

                    <!-- El nombre y el CUÁNDO son una columna: el segundo describe al primero, y
                         separarlos en dos bloques hermanos los dejaba a la misma distancia que del
                         resto de la tarjeta. -->
                    <span class="cart__main">
                        <span class="cart__when">
                            <!-- ⚠️ En un PACK la cantidad va con su sustantivo («8 invitados · …»), que es la
                                 doctrina de `#128`: sin él, «8×119,60 €» se lee como una multiplicación. En una
                                 ENTRADA el sustantivo es el propio producto, así que basta el número — y solo
                                 si hay más de una (`#559`). -->
                            <template v-if="line.is_pack">{{ tp('guests_count', { count: line.quantity }) }} · {{ line.product_name }}</template>
                            <template v-else>{{ named(line.quantity, line.product_name) }}</template>
                        </span>
                        <!-- Se emite SIEMPRE, aunque quede vacío: el condicional va DENTRO. -->
                        <span class="cart__lines">
                            <span v-if="line.date">{{ dayLabel(line.date) }} · {{ shortTime(line.time) }}</span>
                        </span>
                    </span>

                    <span class="cart__price">{{ money(line.subtotal_cents) }}</span>
                    <button type="button" class="cart__remove" :aria-label="t('remove')" @click="$emit('remove', line.index)">&times;</button>
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
                    <!-- ⚠️⚠️ **`v-model` sobre el BORRADOR, no `@input` sobre la cesta** (`#560`): emitir en
                         cada tecla sacaba el campo de `line.pending` con la primera letra, y el `v-for`
                         lo quitaba del DOM con ella dentro. Lo tecleado no es del pedido hasta que se
                         confirma. -->
                    <label v-for="field in line.pending" :key="field.key" class="eventfields__field">
                        <span class="eventfields__label">{{ field.label }}<span class="eventfields__req" aria-hidden="true">*</span></span>
                        <textarea v-if="field.type === 'textarea'" rows="2" required
                                  v-model="draftOf(line.index)[field.key]"></textarea>
                        <input v-else
                               :type="['number', 'celebrant_age'].includes(field.type) ? 'number' : 'text'"
                               :min="['number', 'celebrant_age'].includes(field.type) ? 0 : null"
                               required
                               v-model="draftOf(line.index)[field.key]">
                    </label>
                    <!-- ⚠️ «Guardar» queda INACTIVO hasta que están todos: el bloque existe porque
                         faltan obligatorios, así que confirmar a medias no cambiaría nada y solo
                         parecería que el botón no funciona. -->
                    <div class="cart__pending-actions">
                        <button type="button" class="cart__pending-discard" @click="discardPending(line)">{{ t('pending_discard') }}</button>
                        <button type="button" class="cart__pending-save" :disabled="! draftIsComplete(line)" @click="confirmPending(line)">{{ t('pending_save') }}</button>
                    </div>
                </div>

                <!-- ¿Para quién son estas entradas? (Fase 6 · tanda 4): solo ENTRADAS, solo con menores
                     que ofrecer. Aquí se edita una línea ya en la cesta —y persistida: son ids—. -->
                <DependentPicker v-if="! line.is_pack && dependentOptions.length"
                                 :scope="'l' + line.index"
                                 :options="dependentOptions" :selected="line.dependent_ids ?? []" :quantity="line.quantity" :messages="messages"
                                 @toggle="$emit('toggle-dependent', line.index, $event)" />

                <!-- ⚠️⚠️ **Sin el «+» de delante** (`#559`): su trabajo —decir «esto es un añadido a lo de
                     arriba»— lo hace la SANGRÍA, alineada con el azulejo del producto, que es como lo
                     dibuja el artboard del paso 08. Y el signo no era neutro: en este mismo embudo
                     `+` es el botón de añadir uno, así que delante de un complemento se lee como un
                     control que no se puede pulsar. -->
                <ul v-if="line.addons.length" class="cart__addons">
                    <li v-for="addon in line.addons" :key="addon.product_id">
                        <span>{{ named(addon.quantity, addon.product_name) }}<em v-if="addon.free_quantity" class="cart__addon-incl">{{ includedLabel(addon) }}</em></span>
                        <span v-if="showsAddonPrice(addon)">{{ money(addon.subtotal_cents) }}</span>
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
