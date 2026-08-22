<script setup>
import { computed } from 'vue';
import { t as translate } from '../i18n.js';
import { dayPrice } from '../money.js';

/**
 * Paso 2 — el CALENDARIO (Fase 4 · paso 4.2).
 *
 * **Ninguna regla de oferta vive aquí** (`CE-4`, `AFORO-02`): qué días se ofrecen, cuáles caen fuera
 * del mes, cuál lleva tarifa especial y a qué precio lo decide `SlotOffer` a través de
 * `GET /availability/{product}/dates`. Este componente coloca celdas.
 *
 * ⚠️ **Una celda no seleccionable es un `<span>`, no un `<button>` deshabilitado**, y eso es
 * contrato: `.cal__day` se estila distinto según el tipo de elemento (§4.2). El diff de árbol lo
 * comprueba.
 *
 * ⚠️ **El precio del día se pinta SIN decimales** (`0` posiciones), a diferencia del resto del
 * cajón. No es un descuido del original: en una rejilla de siete columnas los céntimos no caben.
 * Su formato lo pone `money.js`, que espeja `number_format`: el `Math.round` que había aquí perdía el
 * separador de millares desde 999,50 € (a cero decimales el redondeo cruza el millar antes que el
 * importe), y el diff de árbol no lo veía por ser texto.
 *
 * ⚠️ **La banda de progreso ya no se emite aquí**: vive en `Shell.vue`, porque en el Blade está FUERA
 * de la zona scrollable y es de los pasos 2 y 3, no solo del 2.
 */
const props = defineProps({
    /** Semanas del mes, tal y como las compone el servidor: `[[celda, …], …]`. */
    weeks: { type: Array, default: () => [] },
    weekdayHeaders: { type: Array, default: () => [] },
    monthLabel: { type: String, default: '' },
    canPrev: { type: Boolean, default: false },
    canNext: { type: Boolean, default: false },
    selectedDate: { type: String, default: null },
    messages: { type: Object, default: () => ({}) },
});

defineEmits(['select', 'prev-month', 'next-month']);

const t = (key) => translate(props.messages, key);

/** ¿Hay al menos un día reservable en el mes que se está viendo? */
const hasSelectable = computed(() => props.weeks.flat().some((cell) => cell.selectable));

/**
 * Las clases de una celda reservable. Se componen aquí y no en la plantilla porque son cuatro
 * condiciones —tipo de tarifa, fuera de mes, seleccionada— y en línea se vuelven ilegibles.
 */
const dayClasses = (cell) => [
    'cal__day',
    'cal__day--' + cell.type,
    cell.in_month ? '' : 'is-out',
    cell.date === props.selectedDate ? 'is-selected' : '',
];
</script>

<template>
    <h3 class="wiz__title">{{ t('step_date') }}</h3>

    <div class="cal">
        <div class="cal__head">
            <button type="button" class="cal__nav" :disabled="! canPrev" :aria-label="t('prev_month')"
                    @click="$emit('prev-month')">&lsaquo;</button>
            <span class="cal__month">{{ monthLabel }}</span>
            <button type="button" class="cal__nav" :disabled="! canNext" :aria-label="t('next_month')"
                    @click="$emit('next-month')">&rsaquo;</button>
        </div>
        <div class="cal__grid cal__grid--head">
            <span v-for="wd in weekdayHeaders" :key="wd" class="cal__wd">{{ wd }}</span>
        </div>
        <div v-for="(week, w) in weeks" :key="w" class="cal__grid">
            <template v-for="(cell, c) in week" :key="c">
                <button v-if="cell.selectable"
                        type="button"
                        :class="dayClasses(cell)"
                        :aria-current="cell.date === selectedDate ? 'date' : null"
                        @click="$emit('select', cell.date)">
                    <span class="cal__day-num">{{ cell.day }}</span>
                    <span v-if="cell.price_cents !== null" class="cal__day-price">{{ dayPrice(cell.price_cents) }}</span>
                </button>
                <!-- Un día no reservable NO es un botón deshabilitado: es un `<span>`. El CSS los
                     distingue por el tipo de elemento, así que cambiarlo pierde el estilo. -->
                <span v-else class="cal__day is-disabled" :class="cell.in_month ? '' : 'is-out'">{{ cell.day }}</span>
            </template>
        </div>
    </div>

    <div class="cal__legend">
        <span class="cal__legend-item"><i class="cal__dot cal__dot--normal"></i> {{ t('legend_normal') }}</span>
        <span class="cal__legend-item"><i class="cal__dot cal__dot--special"></i> {{ t('legend_special') }}</span>
    </div>

    <p v-if="! hasSelectable" class="purchase__empty">{{ t('no_dates') }}</p>
</template>
