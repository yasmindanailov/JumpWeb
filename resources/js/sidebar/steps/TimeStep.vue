<script setup>
import { computed } from 'vue';

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
    /** Horas ofrecidas, en formato canónico `HH:MM:SS`. Las decide `SlotOffer` con la cesta delante. */
    times: { type: Array, default: () => [] },
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
});

defineEmits(['select-time', 'inc', 'dec', 'update-field', 'choose-addon', 'toggle-addon', 'inc-addon', 'dec-addon']);

const t = (key) => props.messages[key] ?? '';

/** Traduce con parámetros `:clave`, como hace `__()` en servidor. */
const tp = (key, params) => Object.entries(params).reduce((text, [k, v]) => text.replace(':' + k, v), t(key));

const money = (cents) => (cents / 100).toFixed(2).replace('.', ',') + ' €';

/** `10:00:00` → `10:00`. El servidor guarda la hora canónica; el chip enseña la corta. */
const shortTime = (time) => time.slice(0, 5);

const hasAddons = computed(() => props.addons.groups.length > 0 || props.addons.singles.length > 0);

const canDecrease = computed(() => props.quantity > (props.isPack ? props.minQuantity : 0));
const canIncrease = computed(() => props.quantity < props.maxQuantity);
</script>

<template>
    <h3 class="wiz__title">{{ t('step_time') }}</h3>

    <div class="purchase__chips">
        <button v-for="time in times" :key="time"
                type="button"
                class="purchase__chip"
                :class="time === selectedTime ? 'is-active' : ''"
                @click="$emit('select-time', time)">{{ shortTime(time) }}</button>
    </div>

    <template v-if="selectedTime">
        <div class="qtybox">
            <div class="qtybox__row">
                <span class="qtybox__label">{{ isPack ? t('guests') : t('quantity') }}</span>
                <div class="entry__stepper">
                    <button type="button" :disabled="! canDecrease" @click="$emit('dec')">&minus;</button>
                    <span class="entry__qty">{{ quantity }}</span>
                    <button type="button" :disabled="! canIncrease" @click="$emit('inc')">+</button>
                </div>
            </div>
            <p class="qtybox__avail">
                <template v-if="maxQuantity > 0">{{ tp(isPack ? 'guests_left' : 'seats_left', { count: maxQuantity }) }}</template>
                <template v-else>{{ t('sold_out') }}</template>
                <span v-if="dayPriceCents !== null" class="qtybox__price"> · {{ money(dayPriceCents) }}<template v-if="isPack"> {{ periodLabel || t('per_child') }}</template></span>
            </p>
        </div>

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
                        <button type="button" class="addons__moreinfo">{{ t('addon_more_info') }} <span aria-hidden="true"></span></button>
                        <ul class="addons__features">
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
                    <button v-if="opt.features.length" type="button" class="addons__moreinfo">{{ t('addon_more_info') }} <span aria-hidden="true"></span></button>
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

                <ul v-if="opt.features.length" class="addons__features addons__features--single">
                    <li v-for="(f, i) in opt.features" :key="i">{{ f }}</li>
                </ul>
            </div>
        </div>
    </template>
</template>
