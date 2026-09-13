<script setup>
import { computed, nextTick, ref } from 'vue';
import { t as translate, tp as translateWith } from '../i18n.js';
import { money } from '../money.js';
import { isAlmostFull, isSoldOut } from '../offer.js';
import { useStrip } from '../useStrip.js';
import { guardianIsBlocked, whoSummaryKey } from '../assignment.js';
import DependentPicker from './DependentPicker.vue';

/**
 * Paso 3 — HORA, cantidad, datos del pack y COMPLEMENTOS (Fase 4 · paso 4.2).
 *
 * El paso más denso del embudo y el que más dinero enseña, así que conviene decir qué NO decide:
 *
 * - **la cantidad máxima**: llega en `max_quantity`, no en `available`. ⚠️ **No son el mismo número**
 *   y confundirlos vende de más: en una entrada coinciden, pero en un pack `available` son las
 *   plazas que le quedan a la franja y `max_quantity` cuántos invitados admite ESA fiesta, topado
 *   por el máximo del pack. Un selector construido sobre el primero deja pedir invitados que el
 *   checkout rechaza (`AFORO-02`);
 * - **los complementos**: la partición en grupos, las notas, las unidades gratis, los topes y la poda
 *   en cadena de las dependencias las resuelve `POST catalog/products/{id}/addons`. Reimplementar esa
 *   cadena aquí es exactamente lo que `CE-4` prohíbe;
 * - **el dinero**: los importes se pintan, no se suman (`PAY-12`).
 *
 * ⚠️ **Un complemento bloqueado enseña un stepper INERTE, no ninguno**: los tres controles posibles
 * —stepper, interruptor y el rótulo de por-invitado— tienen árboles distintos, y `.entry__stepper
 * button` es un selector que depende del tipo de elemento.
 */
const props = defineProps({
    /**
     * Horas ofrecidas **tal y como llegan de la API** (`{time, available, max_quantity, sellable}`),
     * no una lista de cadenas. Las decide `SlotOffer` con la cesta delante.
     *
     * ⚠️ Antes llegaban ya aplanadas a `HH:MM:SS`, y por eso el chip no podía decir nada del cupo:
     * el dato existía en el contrato desde el primer día y se tiraba en el cableado.
     */
    times: { type: Array, default: () => [] },
    /**
     * Umbral del aviso «casi llena», tal y como lo publica `GET /config` (`#239`). `0` = no avisar,
     * que es también el respaldo cuando la configuración no se pudo leer.
     */
    lowMax: { type: Number, default: 0 },
    selectedTime: { type: String, default: null },
    quantity: { type: Number, default: 0 },
    minQuantity: { type: Number, default: 0 },
    /** El techo del selector. **`max_quantity`, no `available`** — ver el aviso de arriba. */
    maxQuantity: { type: Number, default: 0 },
    isPack: { type: Boolean, default: false },
    dayPriceCents: { type: Number, default: null },
    periodLabel: { type: String, default: '' },
    /** Campos del evento del pack, con la etiqueta ya resuelta por el servidor. */
    eventFields: { type: Array, default: () => [] },
    /**
     * `{groups, singles}` **con los nombres del endpoint** (`product_id`, `quantity`,
     * `charged_cents`, `can_increase`…), no con los del view-model de Livewire.
     *
     * ⚠️ Se descubrió tarde y merece decirse: el diff de árbol alimenta este componente con el
     * view-model del SERVIDOR, así que con nombres distintos seguiría verde mientras el cajón real
     * pinta filas vacías. El componente habla el lenguaje de su fuente —la API—, y el test traduce.
     */
    addons: { type: Object, default: () => ({ groups: [], singles: [] }) },
    /** Errores por campo del evento, con la misma forma que el error bag de la web. */
    errors: { type: Object, default: () => ({}) },
    messages: { type: Object, default: () => ({}) },
    /**
     * Los menores a cargo que se ofrecen para estas entradas (Fase 6 · tanda 4): `assignment.js::
     * assignableOptions()`. Vacío sin sesión o sin menores declarados, y entonces el bloque no existe.
     */
    dependentOptions: { type: Array, default: () => [] },
    /** Los ids ya marcados para esta línea en construcción. */
    dependentIds: { type: Array, default: () => [] },
    /**
     * El JUSTIFICANTE de un menor invitado (`specs/waiver-por-reserva.md` §12.2): el modo del
     * producto (`none` · `optional` · `required`) y lo que el cliente lleva marcado.
     *
     * ⚠️ **Llega el MODO y no dos booleanos resueltos, y la primera versión hacía lo contrario.**
     * Se cambió al medirlo contra `SidebarComponentBudgetTest`: tres props costaban una línea más de
     * la excepción declarada y 0,10 KiB de chunk, a cambio de nada. Elegir cuál de tres pantallas se
     * pinta leyendo un enum que el servidor ya sanea **no es decidir una regla** (`CE-4`) — es lo
     * mismo que hace `v-if="isPack"` tres bloques más abajo.
     *
     * ⚠️ Un modo desconocido no pinta nada, que es el estado correcto: las dos ramas comparan contra
     * un literal y no hay `v-else`.
     */
    guardianMode: { type: String, default: 'none' },
    guardianChecked: { type: Boolean, default: false },
});

const emit = defineEmits(['select-time', 'inc', 'dec', 'set-qty', 'update-field', 'choose-addon', 'toggle-addon', 'inc-addon', 'dec-addon', 'toggle-dependent', 'toggle-guardian']);

const t = (key) => translate(props.messages, key);
const tp = (key, params) => translateWith(props.messages, key, params);

/** `10:00:00` → `10:00`. El servidor guarda la hora canónica; el chip enseña la corta. */
const shortTime = (time) => time.slice(0, 5);

/**
 * ¿Esta hora se anuncia como casi llena? La regla vive en `offer.js`, espejo de
 * `AvailabilitySettings::isLow()`; aquí solo se pinta (`CE-4`).
 */
const almostFull = (offered) => isAlmostFull(offered, props.lowMax);

/** Franja COMPLETA. La regla vive en `offer.js` (`#277`); aquí solo se pinta (`CE-4`). */
const soldOut = (offered) => isSoldOut(offered);

/** Las flechas de RATÓN de la tira. El porqué, en `useStrip.js` y en `DateStep.vue`. */
const { track, nav, move } = useStrip();

const hasAddons = computed(() => props.addons.groups.length > 0 || props.addons.singles.length > 0);

// Las dos REGLAS del bloque viven en `assignment.js`, con sus casos de `node --test` (`CE-6`): cuál
// de los cinco rótulos toca y si la casilla se puede marcar. Aquí solo se pinta.
// ⚠️ `offersDependents` es UNA condición con TRES lectores —abre el bloque, pinta el selector y elige
// el rótulo (`#567`)—: escrita tres veces, el rótulo podía prometer menores que el bloque no enseña.
const offersDependents = computed(() => ! props.isPack && props.dependentOptions.length > 0);
const guardianBlocked = computed(() => guardianIsBlocked({ quantity: props.quantity, dependents: props.dependentIds.length, checked: props.guardianChecked }));
const whoSummary = computed(() => tp(whoSummaryKey({ dependents: props.dependentIds.length, guardian: props.guardianMode === 'required' || props.guardianChecked, offers: offersDependents.value }), { count: props.dependentIds.length }));

/**
 * `#327` — la cantidad tecleada sale, y el campo se REPINTA desde la prop.
 *
 * ⚠️⚠️ El repintado no es cosmético: si el cliente teclea 500 en un pack de máximo 100, el padre
 * acota a 100 y —como la cantidad vigente YA era 100— la prop no cambia, así que Vue no re-renderiza
 * y **el `<input>` se queda enseñando 500**. El cajón cree 100 y la pantalla dice 500. Se restaura
 * en el `nextTick` porque hasta entonces la prop todavía tiene el valor viejo.
 */
function onQuantityTyped(event) {
    emit('set-qty', event.target.value);
    nextTick(() => { event.target.value = props.quantity; });
}

const canDecrease = computed(() => props.quantity > (props.isPack ? props.minQuantity : 0));
const canIncrease = computed(() => props.quantity < props.maxQuantity);

/**
 * **«Más info» de un complemento: qué ventajas están DESPLEGADAS.**
 *
 * ⚠️⚠️ **Esto faltaba, y el botón llevaba puesto desde el principio sin hacer nada.** El
 * `<button class="addons__moreinfo">` se emitía **sin `@click`** y la lista `.addons__features` se
 * pintaba **sin condición de estado**; en CSS tampoco había `display: none` que la ocultara. O sea:
 * la ficha salía siempre abierta y el botón era decoración. Lo vio el owner usando el cajón.
 *
 * ⚠️ **Y el comentario de `addon-chip.blade.php` afirmaba lo contrario**: decía que la landing usaba
 * «el mismo patrón que el sidebar de compra … + toggle Alpine». La landing SÍ lo tenía; el cajón no.
 * *Un comentario que describe la paridad con otra pantalla no es prueba de que esa pantalla la
 * cumpla* — aquí llevaba meses citando una conducta que nunca existió.
 *
 * ▶ Se guarda por `product_id` en un `Set`: un complemento aparece o como opción de un grupo o como
 * suelto, nunca en los dos sitios, así que la clave no colisiona. Nace vacío —todo plegado—, que es
 * lo que el botón promete.
 */
const expanded = ref(new Set());
const isExpanded = (id) => expanded.value.has(id);
const toggleInfo = (id) => {
    // Un `Set` mutado en sitio no dispara la reactividad de Vue: se reemplaza.
    const next = new Set(expanded.value);
    next.has(id) ? next.delete(id) : next.add(id);
    expanded.value = next;
};
</script>

<template>
    <!-- ⚠️⚠️ **La entradilla es HERMANA del título, no van envueltos** (`#557`). Con un envoltorio se
         leen igual y **se rompen los dos casos del contrato de árbol de este paso**: anclan en
         `wiz__title` y `treeOf()` recorre hermanos SIGUIENTES, así que el paso entero —sesenta nodos—
         se quedaba fuera y el manifiesto lo daba por bueno. *Un ancla se cae cuando cambia lo que
         tiene encima, y en verde.* El aire entre los dos lo reparte el CSS. -->
    <h3 class="wiz__title">{{ t('step_time') }}</h3>
    <p class="wiz__lede">{{ t('step_time_lede') }}</p>

    <!-- La tira de horas: deslizable con ajuste (`#239`, `[DECIDIDO owner]`). `role="group"` porque
         son botones hermanos que forman UNA elección; el nombre lo pone el rótulo del paso. -->
    <div class="timestrip">
        <button type="button" class="timestrip__nav timestrip__nav--prev" v-show="nav.prev"
                :aria-label="t('strip_prev')" @click="move(-1)"><span aria-hidden="true"></span></button>
        <button type="button" class="timestrip__nav timestrip__nav--next" v-show="nav.next"
                :aria-label="t('strip_next')" @click="move(1)"><span aria-hidden="true"></span></button>

        <div ref="track" class="timestrip__track" role="group" :aria-label="t('step_time')">
            <!-- ⚠️⚠️ **`sellable` llevaba desde siempre en el contrato y NADIE lo leía** (`#277`).
                 `SlotOffer` manda las franjas llenas con `sellable: false` **a propósito** —su
                 docblock dice «se muestran deshabilitadas, no se ocultan»— y aquí se pintaban como
                 un chip normal y clicable: sólo al pulsarlo aparecía «agotado» abajo. El dato
                 estaba, el cableado no.
                 ▶ Se compara con `=== false` y no por veracidad: una carga antigua sin el campo
                 tiene que seguir siendo vendible, no quedarse muda.

                 ⚠️ El índice alimenta el DESFASE de la cascada, y va topado: con 11 horas el último
                 chip ya espera 900 ms, y un día con treinta esperaría casi tres segundos. Los que
                 quedan fuera del carril no se ven entrar, así que el tope no se nota y el techo sí. -->
            <button v-for="(offered, i) in times" :key="offered.time"
                    type="button"
                    class="purchase__chip"
                    :class="[
                        offered.time === selectedTime ? 'is-active' : '',
                        soldOut(offered) ? 'is-full' : '',
                    ]"
                    :style="{ '--i': Math.min(i, 7) }"
                    :disabled="soldOut(offered)"
                    @click="$emit('select-time', offered.time)">
                <span class="purchase__chip-t">{{ shortTime(offered.time) }}</span>
                <span v-if="soldOut(offered)" class="purchase__chip-full">{{ t('sold_out') }}</span>
                <span v-else-if="almostFull(offered)" class="purchase__chip-full">{{ t('almost_full') }}</span>
            </button>
        </div>
    </div>

    <template v-if="selectedTime">
        <div class="qtybox">
            <!-- ⚠️ **La cabecera y los controles van en DOS filas** (`#557`, artboard `Pasos Compra
                 PJP`): con los controles a 48 y la cifra en rótulo, la fila única dejaba al rótulo y a
                 la disponibilidad peleando por lo que sobraba. Arriba, qué se cuenta y cuánto queda;
                 abajo, el control, ancho y cómodo. -->
            <div class="qtybox__row">
                <span class="qtybox__label">{{ isPack ? t('guests') : t('quantity') }}</span>
                <p class="qtybox__avail">
                    <template v-if="maxQuantity > 0">{{ tp(isPack ? 'guests_left' : 'seats_left', { count: maxQuantity }) }}</template>
                    <template v-else>{{ t('sold_out') }}</template>
                    <span v-if="dayPriceCents !== null" class="qtybox__price"> · {{ money(dayPriceCents) }}<template v-if="isPack"> {{ periodLabel || t('per_child') }}</template></span>
                </p>
            </div>
            <!-- `#327`: la cantidad SE ESCRIBE, no solo se pulsa. Con un mínimo de 30 (una
                 excursión de colegio) el `+` obligaba a treinta clics antes de poder comprar, y
                 cien para llenar el grupo. El acotado NO se hace aquí: se emite el número
                 tecleado y lo acota el mismo sitio que ya acota `+`/`−`, o serían dos reglas. -->
            <div class="qtybox__control">
                <div class="entry__stepper entry__stepper--lg">
                    <button type="button" :disabled="! canDecrease" :aria-label="t('qty_less')" @click="$emit('dec')">&minus;</button>
                    <input class="entry__qty" type="number" inputmode="numeric"
                           :value="quantity"
                           :min="isPack ? minQuantity : 1"
                           :max="maxQuantity"
                           :aria-label="isPack ? t('guests') : t('quantity')"
                           @change="onQuantityTyped"
                           @keydown.enter.prevent="$event.target.blur()">
                    <button type="button" :disabled="! canIncrease" :aria-label="t('qty_more')" @click="$emit('inc')">+</button>
                </div>
            </div>
        </div>

        <!--
          ¿QUIÉNES vienen? — los menores a cargo y el justificante de un menor invitado, juntos y
          **PLEGADOS** (`[DECIDIDO owner, 2026-09-02]`: *«esa parte de menores a cargo y justificantes
          de manera más sutil, es demasiado centrada en el proceso»*).

          ⚠️⚠️ **Es un `<details>` nativo y no un acordeón de JS**, y no es pereza: este paso ya es el
          más denso del embudo, y una pieza que se abre y se cierra sin una línea de JavaScript no
          puede quedarse rota si el motor falla — que es justo lo que la T2 pagó con el anti-bot.
          Sin JS se abre igual.

          ⚠️ **Nace CERRADO porque no es un paso obligatorio**: la mayoría compra sin menores de nadie.
          Pero el rótulo dice lo que hay dentro **y cuántos van marcados**, para que quien SÍ tenga que
          entrar no tenga que abrirlo para descubrirlo.

          ⚠️ Se pinta si hay algo que ofrecer: menores a cargo declarados **o** un producto que admite
          justificante. Ni una cosa ni otra → el bloque no existe (no vacío: no está).
        -->
        <details v-if="offersDependents || guardianMode !== 'none'"
                 class="whoblock" data-who-block>
            <summary class="whoblock__head">
                <span class="whoblock__title">{{ t('who_block.title') }}</span>
                <span class="whoblock__hint">{{ whoSummary }}</span>
            </summary>

            <div class="whoblock__body">
                <!-- ¿Para quién son estas entradas? (Fase 6 · tanda 4, `menores-a-cargo.md` §4.7): solo
                     en ENTRADAS, solo con sesión y menores declarados. Un pack pide a sus invitados
                     abajo. -->
                <DependentPicker v-if="offersDependents"
                                 :options="dependentOptions" :selected="dependentIds" :quantity="quantity" :messages="messages"
                                 @toggle="$emit('toggle-dependent', $event)" />

        <!--
          El JUSTIFICANTE de un menor invitado (`specs/waiver-por-reserva.md` §12.2,
          `[DECIDIDO owner, 2026-09-01]`). Va justo DEBAJO del selector de menores a cargo porque es
          la misma pregunta vista del otro lado: ahí se dice quiénes de los que vienen son tuyos, y
          aquí que viene alguien que no lo es.

          ⚠️ **Dos formas y no una casilla con texto distinto**: `optional` PREGUNTA (el cliente es el
          único que lo sabe) y `required` INFORMA (el producto ya lo sabe, y no hay nada que decidir).
          Pintar `required` como una casilla marcada e inerte invita a intentar desmarcarla.

          ⚠️ **Se pinta también sin sesión y sin menores declarados**, a diferencia del selector de
          arriba: el caso que originó esta feature es el amigo del hijo, y quien lo trae puede no
          tener ningún menor a cargo dado de alta (`specs/waiver-por-reserva.md` §1.5).
        -->
                <div v-if="guardianMode === 'required'" class="guardnote" data-guardian-note>
                    <p class="guardnote__text">{{ t('guardian_required') }}</p>
                </div>
                <label v-else-if="guardianMode === 'optional'" class="guardnote guardnote--ask"
                       :class="guardianBlocked ? 'guardnote--off' : ''" data-guardian-ask>
                    <input type="checkbox" class="guardnote__box" :checked="guardianChecked"
                           :disabled="guardianBlocked"
                           :aria-describedby="guardianBlocked ? 'guardian-why' : null"
                           @change="$emit('toggle-guardian', $event.target.checked)">
                    <span class="guardnote__body">
                        <span class="guardnote__label">{{ t('guardian_optional') }}</span>
                        <!-- ⚠️ Con la línea llena se dice POR QUÉ, no se apaga en silencio: el cliente
                             acaba de asignar todas sus plazas y tiene que poder atar los dos hechos. -->
                        <span v-if="guardianBlocked" id="guardian-why" class="guardnote__help">{{ t('guardian_no_places') }}</span>
                        <span v-else class="guardnote__help">{{ t('guardian_optional_help') }}</span>
                    </span>
                </label>
            </div>
        </details>

        <!-- Campos del evento del pack: data-driven por instalación, así que el esquema llega del
             servidor y aquí solo se pinta el control que cada tipo pide. -->
        <div v-if="isPack && eventFields.length" class="eventfields">
            <label v-for="field in eventFields" :key="field.key"
                   class="eventfields__field"
                   :class="errors[field.key] ? 'is-invalid' : ''">
                <span class="eventfields__label">{{ field.label }}<span v-if="field.required" class="eventfields__req" aria-hidden="true">*</span></span>
                <textarea v-if="field.type === 'textarea'" rows="2" :required="field.required"
                          @input="$emit('update-field', field.key, $event.target.value)"></textarea>
                <input v-else
                       :type="field.type === 'number' ? 'number' : 'text'"
                       :min="field.type === 'number' ? 0 : null"
                       :required="field.required"
                       @input="$emit('update-field', field.key, $event.target.value)">
                <span v-if="errors[field.key]" class="form__error">{{ errors[field.key] }}</span>
            </label>
        </div>

        <div v-if="hasAddons" class="addons">
            <!-- ⚠️ RÓTULO, no entradilla (`#557`): una pregunta corta en tinta encima de sus filas. -->
            <p class="addons__intro">{{ t('complements_intro') }}</p>

            <!-- Grupos EXCLUYENTES: dentro de cada uno hay exactamente uno activo. -->
            <fieldset v-for="group in addons.groups" :key="group.key" class="addons__group">
                <legend class="addons__group-label">{{ group.label }}</legend>
                <div v-for="opt in group.options" :key="opt.product_id"
                     class="addons__row addons__row--choice"
                     :class="[opt.selected ? 'is-selected' : '', opt.available ? '' : 'is-disabled']">
                    <label class="addons__choice">
                        <input type="radio" class="addons__radio" :name="'addon-group-' + group.key"
                               :checked="opt.selected" :disabled="! opt.available"
                               @click="$emit('choose-addon', group.key, opt.product_id)">
                        <span class="addons__info">
                            <span class="addons__name">{{ opt.product_name }}<span v-if="opt.badge" class="addons__badge" :class="'addons__badge--' + opt.badge">{{ t('addon_badge_' + opt.badge) }}</span></span>
                            <span class="addons__price">{{ opt.note }}<span v-if="opt.selected && opt.charged_cents > 0" class="addons__charged">+{{ money(opt.charged_cents) }}</span></span>
                            <span v-if="! opt.available && opt.requires_name" class="addons__requires">{{ tp('addon_requires', { name: opt.requires_name }) }}</span>
                        </span>
                    </label>
                    <template v-if="opt.features.length">
                        <button type="button" class="addons__moreinfo"
                                :aria-expanded="isExpanded(opt.product_id) ? 'true' : 'false'"
                                @click="toggleInfo(opt.product_id)">{{ t('addon_more_info') }} <span aria-hidden="true"></span></button>
                        <ul v-if="isExpanded(opt.product_id)" class="addons__features">
                            <li v-for="(f, i) in opt.features" :key="i">{{ f }}</li>
                        </ul>
                    </template>
                </div>
            </fieldset>

            <div v-for="opt in addons.singles" :key="opt.product_id"
                 class="addons__row" :class="opt.available ? '' : 'is-disabled'">
                <span class="addons__info">
                    <span class="addons__name">{{ opt.product_name }}<span v-if="opt.badge" class="addons__badge" :class="'addons__badge--' + opt.badge">{{ t('addon_badge_' + opt.badge) }}</span></span>
                    <span class="addons__price">{{ opt.note }}</span>
                    <span v-if="! opt.available && opt.requires_name" class="addons__requires">{{ tp('addon_requires', { name: opt.requires_name }) }}</span>
                    <button v-if="opt.features.length" type="button" class="addons__moreinfo"
                            :aria-expanded="isExpanded(opt.product_id) ? 'true' : 'false'"
                            @click="toggleInfo(opt.product_id)">{{ t('addon_more_info') }} <span aria-hidden="true"></span></button>
                </span>

                <!-- Dependiente bloqueado: stepper INERTE, no ausente. Que el control esté ahí y no
                     se pueda usar es lo que enseña que existe y de qué depende. -->
                <div v-if="! opt.available" class="entry__stepper" aria-hidden="true">
                    <button type="button" disabled>&minus;</button>
                    <span class="entry__qty">0</span>
                    <button type="button" disabled>+</button>
                </div>
                <label v-else-if="opt.can_toggle" class="addons__perguest-toggle">
                    <input type="checkbox" class="addons__check" :checked="opt.selected" @click="$emit('toggle-addon', opt.product_id)">
                    <span class="addons__perguest">{{ t('addon_per_guest_add') }}<span v-if="opt.selected && opt.charged_cents > 0" class="addons__charged">+{{ money(opt.charged_cents) }}</span></span>
                </label>
                <span v-else-if="opt.per_guest" class="addons__perguest">{{ tp('addon_per_guest_qty', { count: opt.quantity }) }}</span>
                <div v-else class="entry__stepper">
                    <button type="button" :disabled="! opt.can_decrease" @click="$emit('dec-addon', opt.product_id)">&minus;</button>
                    <span class="entry__qty">{{ opt.quantity }}</span>
                    <button type="button" :disabled="! opt.can_increase" @click="$emit('inc-addon', opt.product_id)">+</button>
                </div>

                <ul v-if="opt.features.length && isExpanded(opt.product_id)" class="addons__features addons__features--single">
                    <li v-for="(f, i) in opt.features" :key="i">{{ f }}</li>
                </ul>
            </div>
        </div>
    </template>
</template>
