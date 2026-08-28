<script setup>
import { onMounted } from 'vue';
import { t as translate } from '../i18n.js';
import { dayPrice } from '../money.js';
import { useStrip } from '../useStrip.js';

/**
 * Paso 2 — el DÍA (Fase 4 · paso 4.2; rehecho en `DECISIONES #239`).
 *
 * **Ninguna regla de oferta vive aquí** (`CE-4`, `AFORO-02`): qué días se ofrecen, cuáles caen fuera
 * del mes, cuál lleva tarifa especial y a qué precio lo decide `SlotOffer` a través de
 * `GET /availability/{product}/dates`. Este componente coloca celdas.
 *
 * ### Dos vías, y cuál es la normal
 *
 * La **TIRA** es la vía normal: los días RESERVABLES, en orden, agrupados por mes. El **calendario**
 * mensual sigue existiendo detrás de «ver más fechas» para el salto largo —un cumpleaños dentro de
 * tres meses no se alcanza deslizando—. `[DECIDIDO owner, 2026-08-28]`.
 *
 * ⚠️ **Por qué cambió, medido el 2026-08-28**: el calendario pinta **42 celdas** y abre en el mes en
 * curso, donde la mayoría ya han pasado — a 28 de agosto quedaban **4 seleccionables de 42**. El
 * cliente no elegía, buscaba. Y no era por falta de días: hay **182** en el horizonte.
 *
 * ⚠️ **Una celda no seleccionable del CALENDARIO es un `<span>`, no un `<button>` deshabilitado**, y
 * eso es contrato: `.cal__day` se estila distinto según el tipo de elemento (§4.2). El diff de árbol
 * lo comprueba. La tira no tiene ese caso: **solo lleva días reservables**, y todos son botones.
 *
 * ⚠️ **El precio se pinta SIN decimales** (`0` posiciones), a diferencia del resto del cajón. No es un
 * descuido del original: ni en una rejilla de siete columnas ni en un chip de tira caben los céntimos.
 * Su formato lo pone `money.js`, que espeja `number_format`: el `Math.round` que había aquí perdía el
 * separador de millares desde 999,50 € (a cero decimales el redondeo cruza el millar antes que el
 * importe), y el diff de árbol no lo veía por ser texto.
 *
 * ⚠️ **La banda de progreso no se emite aquí**: vive en `Shell.vue`, porque en el Blade está FUERA de
 * la zona scrollable y es de los pasos 2 y 3, no solo del 2.
 */
const props = defineProps({
    /**
     * La TIRA: `[{month, label, days: [{date, day, weekday, price_cents, type, selected}]}]`, tal y
     * como la compone `calendar.js::buildStrip()`. **Solo días reservables.**
     */
    strip: { type: Array, default: () => [] },
    /** Semanas del mes para el calendario plegable: `[[celda, …], …]`. */
    weeks: { type: Array, default: () => [] },
    weekdayHeaders: { type: Array, default: () => [] },
    monthLabel: { type: String, default: '' },
    canPrev: { type: Boolean, default: false },
    canNext: { type: Boolean, default: false },
    /** ¿Está desplegado el calendario mensual? Lo guarda el store, no el componente. */
    calendarOpen: { type: Boolean, default: false },
    selectedDate: { type: String, default: null },
    messages: { type: Object, default: () => ({}) },
});

defineEmits(['select', 'prev-month', 'next-month', 'toggle-calendar']);

const t = (key) => translate(props.messages, key);

/**
 * El carril desplazable: su referencia, si cada flecha lleva a algún sitio, y el clic que las mueve.
 *
 * ⚠️ **Las flechas existen porque con RATÓN no se desliza** (`#241`, `[OWNER]`): la barra va oculta
 * a propósito —en 390 px se comía 15 de los 350 útiles— así que en escritorio la única salida era
 * desplegar el calendario. En una pantalla táctil sobran, y ahí las apaga el CSS.
 */
const { track, nav, move } = useStrip();

/**
 * Al entrar con un día YA elegido, la tira se coloca en él.
 *
 * ⚠️ **Se mueve `scrollLeft` y NO se llama a `scrollIntoView()`**, que desplaza también a los
 * ANCESTROS: el cajón entero saltaría a media pantalla por colocar un chip. Es la misma clase de
 * efecto colateral que el `preventScroll` del foco de la pantalla de puerta (`#234`·6).
 *
 * ⚠️ `offsetLeft` se mide contra el `offsetParent`, así que `.daystrip__track` lleva
 * `position: relative` en la hoja: sin eso el origen sería un ancestro cualquiera y el cálculo daría
 * un número plausible y equivocado.
 *
 * Sin día elegido no se hace nada: la tira ya empieza en el primero ofrecido, que es el más cercano.
 */
onMounted(() => {
    const carril = track.value;
    const elegido = carril?.querySelector('.is-selected');

    if (! carril || ! elegido) {
        return;
    }

    carril.scrollLeft = Math.max(0, elegido.offsetLeft - ((carril.clientWidth - elegido.offsetWidth) / 2));
});

/** Las clases de un chip de la tira. Se componen aquí porque en línea son ilegibles. */
const stripClasses = (cell) => [
    'daystrip__day',
    'daystrip__day--' + cell.type,
    cell.selected ? 'is-selected' : '',
];

/**
 * Las clases de una celda reservable del calendario. Se componen aquí y no en la plantilla porque son
 * cuatro condiciones —tipo de tarifa, fuera de mes, seleccionada— y en línea se vuelven ilegibles.
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

    <div v-if="strip.length" class="daystrip">
        <!-- Las flechas de RATÓN. `v-show` y no `v-if` a propósito: así el nodo existe siempre y el
             contrato de árbol lo fija; quién las ve lo deciden el estado (¿hay recorrido?) y el CSS
             (¿hay ratón?), que son dos preguntas distintas. -->
        <button type="button" class="daystrip__nav daystrip__nav--prev" v-show="nav.prev"
                :aria-label="t('strip_prev')" @click="move(-1)"><span aria-hidden="true"></span></button>
        <button type="button" class="daystrip__nav daystrip__nav--next" v-show="nav.next"
                :aria-label="t('strip_next')" @click="move(1)"><span aria-hidden="true"></span></button>

        <!-- El carril desplazable. `role="group"` porque son botones hermanos que forman UNA
             elección; el nombre lo pone el mismo rótulo que titula el paso. -->
        <div ref="track" class="daystrip__track" role="group" :aria-label="t('step_date')">
            <template v-for="group in strip" :key="group.month">
                <span class="daystrip__month">{{ group.label }}</span>
                <button v-for="cell in group.days" :key="cell.date"
                        type="button"
                        :class="stripClasses(cell)"
                        :aria-current="cell.selected ? 'date' : null"
                        @click="$emit('select', cell.date)">
                    <span class="daystrip__wd">{{ cell.weekday }}</span>
                    <span class="daystrip__num">{{ cell.day }}</span>
                    <span v-if="cell.price_cents !== null" class="daystrip__price">{{ dayPrice(cell.price_cents) }}</span>
                </button>
            </template>
        </div>
    </div>

    <!-- «Ver más fechas»: el calendario entero, para el salto largo. `aria-expanded` lo anuncia; sin
         él un lector de pantalla no sabe que el botón despliega algo. -->
    <button v-if="strip.length" type="button" class="cal-more"
            :class="calendarOpen ? 'is-open' : ''"
            :aria-expanded="calendarOpen ? 'true' : 'false'"
            @click="$emit('toggle-calendar')">
        <span>{{ calendarOpen ? t('calendar_hide') : t('calendar_show') }}</span>
        <span class="cal-more__chev" aria-hidden="true"></span>
    </button>

    <div v-if="calendarOpen" class="cal">
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

    <!-- La leyenda explica los COLORES de la rejilla, así que va con ella: fuera del calendario
         explicaría un código que no se está viendo. Los chips de la tira llevan su precio escrito. -->
    <div v-if="calendarOpen" class="cal__legend">
        <span class="cal__legend-item"><i class="cal__dot cal__dot--normal"></i> {{ t('legend_normal') }}</span>
        <span class="cal__legend-item"><i class="cal__dot cal__dot--special"></i> {{ t('legend_special') }}</span>
    </div>

    <!-- ⚠️ La condición es que NO HAYA OFERTA, no que el mes visible esté vacío. Son equivalentes
         —la navegación se acota a los meses con oferta— pero esta lo dice directamente. -->
    <p v-if="! strip.length" class="purchase__empty">{{ t('no_dates') }}</p>
</template>
