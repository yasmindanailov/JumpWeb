/**
 * Compone la REJILLA del calendario a partir de los días que la API ofrece (Fase 4 · paso 4.2).
 *
 * ⚠️ **Esto no decide qué días se pueden reservar**: eso lo dice `SlotOffer` a través de
 * `GET availability/{producto}/dates`, y una copia de esa regla en el cliente es lo que `CE-4`
 * prohíbe. Lo que hay aquí es lo otro: repartir esos días en semanas de lunes a domingo y rellenar
 * los huecos del mes anterior y siguiente. Eso sí es presentación.
 *
 * **Módulo plano, sin Vue**, por el mismo motivo que la máquina de estados: así se prueba con
 * `node --test` y su equivalencia con la composición del servidor es verificable (`SidebarCalendarParityTest`
 * compara las dos para el mismo mes).
 *
 * ⚠️ **Todo se calcula en horario LOCAL, no en UTC.** `new Date('2026-08-01')` se interpreta como
 * medianoche UTC, así que en un huso al oeste el día se convierte en el 31 de julio y el mes entero
 * se desplaza una casilla. Por eso las fechas se parten a mano y se construyen con `new Date(y, m, d)`.
 */

/** `YYYY-MM-DD` → `[año, mes (1-12), día]`, sin pasar por el parser de `Date`. */
function parts(ymd) {
    const [y, m, d] = ymd.split('-').map(Number);

    return [y, m, d];
}

/** `Date` → `YYYY-MM-DD`, en horario local. */
function toYmd(date) {
    const y = date.getFullYear();
    const m = String(date.getMonth() + 1).padStart(2, '0');
    const d = String(date.getDate()).padStart(2, '0');

    return `${y}-${m}-${d}`;
}

/**
 * El lunes de la semana de una fecha. `getDay()` devuelve 0 para domingo, así que el domingo
 * retrocede seis días y no cero — el error clásico de las semanas que empiezan en lunes.
 */
function startOfWeekMonday(date) {
    const copy = new Date(date.getFullYear(), date.getMonth(), date.getDate());
    const weekday = (copy.getDay() + 6) % 7;
    copy.setDate(copy.getDate() - weekday);

    return copy;
}

/**
 * Las semanas del mes, con cada celda resuelta contra la oferta.
 *
 * @param {string} month  el mes en `YYYY-MM`
 * @param {Array<{date: string, price_cents: number|null, rate_key: string}>} offeredDates  lo que devuelve la API
 * @param {string|null} selectedDate
 * @returns {Array<Array<{date: string, day: number, in_month: boolean, selectable: boolean, type: string|null, price_cents: number|null, selected: boolean}>>}
 */
export function buildWeeks(month, offeredDates = [], selectedDate = null) {
    const [year, monthNumber] = parts(month + '-01');

    const offered = new Map(offeredDates.map((day) => [day.date, day]));

    const firstOfMonth = new Date(year, monthNumber - 1, 1);
    const lastOfMonth = new Date(year, monthNumber, 0);

    const cursor = startOfWeekMonday(firstOfMonth);

    // El final es el domingo de la semana del último día: la rejilla siempre cierra semanas enteras.
    const end = startOfWeekMonday(lastOfMonth);
    end.setDate(end.getDate() + 6);

    const weeks = [];
    let week = [];

    while (cursor <= end) {
        const ymd = toYmd(cursor);
        const offer = offered.get(ymd) ?? null;

        week.push({
            date: ymd,
            day: cursor.getDate(),
            in_month: cursor.getMonth() === monthNumber - 1,
            selectable: offer !== null,
            type: offer?.rate_key ?? null,
            price_cents: offer?.price_cents ?? null,
            selected: selectedDate === ymd,
        });

        if (week.length === 7) {
            weeks.push(week);
            week = [];
        }

        cursor.setDate(cursor.getDate() + 1);
    }

    return weeks;
}

/**
 * El mes de una fecha (`YYYY-MM`), para colocar el calendario donde el cliente ya tiene algo elegido.
 */
export function monthOf(ymd) {
    const [y, m] = parts(ymd);

    return `${y}-${String(m).padStart(2, '0')}`;
}

/** El mes anterior / siguiente, en `YYYY-MM`. Sin librería de fechas: es aritmética de doce. */
export function shiftMonth(month, delta) {
    const [y, m] = parts(month + '-01');
    const index = (y * 12) + (m - 1) + delta;

    return `${Math.floor(index / 12)}-${String((index % 12) + 1).padStart(2, '0')}`;
}
