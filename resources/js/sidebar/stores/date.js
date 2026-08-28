import { defineStore } from 'pinia';
import {
    buildStrip, buildWeeks, canGoNext, canGoPrev, initialMonth, monthLabel as composeMonthLabel,
    offeredMonths as monthsWithOffer, shiftMonth, weekdayHeaders as composeWeekdayHeaders,
} from '../calendar.js';
import { dayPriceCents as priceOfDay } from '../offer.js';

/**
 * El estado del paso 2 — **el DÍA** (reorganización del SPA, 2026-08-22).
 *
 * ⚠️ **Este store no decide NADA de negocio.** Qué días se ofrecen y a qué precio lo resuelve el
 * servidor (`AFORO-02`), y el reparto en semanas, los meses navegables y los rótulos los calcula
 * `calendar.js`, que es un módulo plano con sus propios casos en `node --test`. Aquí solo vive el
 * ESTADO —qué días llegaron, cuál está elegido y qué mes se está viendo— y las derivaciones que
 * `DateStep.vue` pinta.
 *
 * ### Por qué existe, medido
 *
 * `Sidebar.vue` tenía **614 líneas de código, el 29% del cajón**, repartidas en 103 declaraciones de
 * las que **55 eran estado**. La lógica pura ya estaba fuera —18 módulos planos— pero el estado no
 * tenía casa, y ese es el fichero al que iba a llegar el ÁREA DE CLIENTE. `DECISIONES #38c` decidió
 * Pinia precisamente para eso: «con tres dominios los **stores separados** dejan de ser ceremonia».
 *
 * ### Lo que NO vive aquí, y es deliberado
 *
 * La secuencia `selectDate()` **cruza dominios** —elige el día, navega al paso 3, pide las horas con
 * la cesta dentro— así que no es del calendario: es del embudo, y se queda en la raíz. Un store por
 * dominio no significa meterle a cada uno las transiciones que salen de él.
 */
export const useDateStore = defineStore('date', {
    state: () => ({
        /** Los días que la API ofrece, tal cual llegan. */
        offered: [],

        /** El día elegido (`YYYY-MM-DD`), o `null`. */
        selected: null,

        /** El mes que se está viendo (`YYYY-MM`), o `null` si aún no hay oferta. */
        month: null,

        /**
         * El idioma con el que se componen los rótulos.
         *
         * ⚠️ **Es estado y no una lectura del DOM a propósito.** En el componente esto era
         * `document.documentElement.lang`, y leer el DOM desde el store lo haría imposible de probar
         * con `node --test` sin montar un navegador — que es justo la baratura que `#38c` compró.
         * Lo inyecta el montaje.
         */
        locale: 'es',

        /**
         * ¿Está desplegado el calendario mensual? (`DECISIONES #239`)
         *
         * ⚠️ **Nace CERRADO y vuelve a cerrarse con cada oferta nueva**: la tira es la vía normal y
         * el calendario el atajo para el salto largo. Que sobreviviera a un cambio de producto
         * dejaría al siguiente cliente con la pantalla más densa por una decisión que tomó otro.
         */
        calendarOpen: false,
    }),

    getters: {
        /** La rejilla del mes que se está viendo. Compara dato a dato con `SidebarCalendarParityTest`. */
        weeks: (state) => (state.month ? buildWeeks(state.month, state.offered, state.selected) : []),

        /**
         * La TIRA de días reservables agrupados por mes — la vía normal del paso (`#239`).
         *
         * ⚠️ **No se acota a N días.** Son los que ofrece el servidor (182 medidos), y recortarla
         * aquí sería decidir en el cliente hasta cuándo se vende. Quien no quiera deslizar tiene el
         * calendario.
         */
        strip: (state) => buildStrip(state.offered, state.selected, state.locale),

        /** Los meses navegables se acotan a los que tienen oferta: no se pasea por meses vacíos. */
        navigableMonths: (state) => monthsWithOffer(state.offered),

        canPrev() {
            return canGoPrev(this.month, this.navigableMonths);
        },

        canNext() {
            return canGoNext(this.month, this.navigableMonths);
        },

        weekdayHeaders: (state) => composeWeekdayHeaders(state.locale),

        monthLabel: (state) => composeMonthLabel(state.month, state.locale),

        /** El precio del DÍA elegido. Lo trae la oferta de días; no se deriva del «desde» del catálogo. */
        priceCents: (state) => priceOfDay(state.offered, state.selected),
    },

    actions: {
        setLocale(locale) {
            this.locale = locale || 'es';
        },

        /**
         * Recibe la oferta de días y coloca el calendario donde debe abrir.
         *
         * ⚠️ **Abre en el PRIMER mes con oferta, no en el actual**: si el producto no se vende hasta
         * dentro de dos meses, abrir en «hoy» enseñaría una rejilla vacía. La regla vive en
         * `calendar.js::initialMonth()` desde 4.7·2b·2·B, con su respaldo en horario LOCAL.
         */
        setOffer(days) {
            this.offered = Array.isArray(days) ? days : [];
            this.month = initialMonth(this.offered);
            // Ver `calendarOpen`: una oferta nueva es un producto nuevo, y el paso vuelve a su forma
            // por defecto.
            this.calendarOpen = false;
        },

        select(date) {
            this.selected = date;
        },

        /** Pide al servidor los días que se ofrecen de un producto y coloca el calendario. */
        async loadOffer({ api, productId }) {
            const response = await api.get(`/availability/${productId}/dates`);

            this.setOffer(response.ok ? (response.data?.data ?? []) : []);

            return response.ok;
        },

        /** Mueve el mes visible. El tope lo ponen `canPrev`/`canNext`; esto no lo comprueba. */
        shift(delta) {
            this.month = shiftMonth(this.month, delta);
        },

        /** Despliega o pliega el calendario mensual. La tira sigue estando debajo en los dos estados. */
        toggleCalendar() {
            this.calendarOpen = ! this.calendarOpen;
        },

        /** Olvida el día elegido y conserva la oferta (volver atrás dentro del mismo producto). */
        clearSelection() {
            this.selected = null;
        },
    },
});
