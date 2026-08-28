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

/**
 * Los meses que tienen oferta, en orden (`YYYY-MM`).
 *
 * ⚠️ **La navegación del calendario se acota a estos**, y es decisión de producto, no un detalle: no
 * se deja pasear por meses vacíos. Sale de los días que devuelve la API, así que **quién decide qué
 * meses hay sigue siendo el servidor** (`AFORO-02`); aquí solo se agrupan.
 *
 * @param {Array<{date: string}>} offeredDates
 * @returns {Array<string>}
 */
export function offeredMonths(offeredDates = []) {
    const list = Array.isArray(offeredDates) ? offeredDates : [];

    return [...new Set(list.map((d) => monthOf(d.date)))].sort();
}

/**
 * ¿Hay mes anterior / siguiente al que se está viendo?
 *
 * La comparación es de CADENAS `YYYY-MM` a propósito: con cero a la izquierda el orden lexicográfico
 * y el cronológico coinciden, así que no hace falta convertir a fecha para saber si un mes va antes
 * que otro. Es la misma razón por la que el formato se usa en todo el módulo.
 */
export function canGoPrev(month, months = []) {
    return months.length > 0 && typeof month === 'string' && month > months[0];
}

export function canGoNext(month, months = []) {
    return months.length > 0 && typeof month === 'string' && month < months[months.length - 1];
}

/**
 * Las siete cabeceras de día, de LUNES a domingo y capitalizadas.
 *
 * ⚠️ **El ancla es el 1 de enero de 2024 porque fue lunes.** Sin un ancla conocida habría que
 * derivar el lunes de «hoy», y entonces el resultado dependería del día en que corre el test — la
 * clase de dependencia que ya costó una sesión (`#64`).
 *
 * ⚠️ **El texto puede NO coincidir con el del servidor, y está declarado**: Carbon abrevia `sáb.` con
 * punto e ICU escribe `sáb`. El diff de árbol no lo ve (descarta los nodos de texto) y la divergencia
 * tiene ficha propia en `DEUDA.md`. Lo que sí importa aquí, y por eso hay caso, es que **sean siete**
 * y en ese orden: de eso sí depende la estructura de la rejilla.
 */
export function weekdayHeaders(locale = 'es') {
    const formatter = new Intl.DateTimeFormat(locale, { weekday: 'short' });

    return Array.from({ length: 7 }, (_, i) => {
        const label = formatter.format(new Date(2024, 0, 1 + i)).replace('.', '');

        return label.charAt(0).toUpperCase() + label.slice(1);
    });
}

/** El rótulo del mes («Agosto 2026»). Mismo aviso de divergencia de texto que las cabeceras. */
export function monthLabel(month, locale = 'es') {
    if (typeof month !== 'string' || month === '') {
        return '';
    }

    const [y, m] = parts(month + '-01');
    const label = new Intl.DateTimeFormat(locale, { month: 'long', year: 'numeric' }).format(new Date(y, m - 1, 1));

    return label.charAt(0).toUpperCase() + label.slice(1);
}

/**
 * El mes en el que ABRE el calendario: el primero con oferta.
 *
 * Si el producto no se vende hasta dentro de dos meses, abrir en «hoy» enseñaría una rejilla vacía y
 * una flecha de «siguiente» como única pista. Por eso manda la oferta, y «hoy» es solo el respaldo
 * para cuando no hay ninguna.
 *
 * ⚠️ **El respaldo se calcula en horario LOCAL.** Vivía en `Sidebar.vue` derivándolo con
 * `new Date().toISOString().slice(0, 10)`, que es UTC: en Madrid, entre las 00:00 y las 02:00 del día
 * 1 de un mes, eso devuelve el mes ANTERIOR. Es la misma trampa contra la que se escribió todo este
 * módulo, colada por la única puerta que no pasaba por él. Corregido al extraerlo (4.7·2b·2·B).
 *
 * @param {Array<{date: string}>} offeredDates
 * @param {Date} [now] inyectable para poder fijarlo en un test sin depender del día en que corre
 */
export function initialMonth(offeredDates = [], now = new Date()) {
    const months = offeredMonths(offeredDates);

    if (months.length > 0) {
        return months[0];
    }

    return `${now.getFullYear()}-${String(now.getMonth() + 1).padStart(2, '0')}`;
}

/**
 * La TIRA de días reservables, agrupados por mes (`DECISIONES #239`).
 *
 * ⚠️ **Por qué existe, medido el 2026-08-28.** El calendario mensual pintaba **42 celdas** y abría
 * en el mes en curso, donde la mayoría ya han pasado: a 28 de agosto quedaban **4 de 42**
 * seleccionables. El cliente no elegía, **buscaba**. Y el problema no era que hubiera pocos días
 * —hay **182**, seis meses de horizonte con el parque abierto a diario—: era que la rejilla obliga a
 * leer el mes entero para encontrar «pasado mañana», que es lo que compra casi todo el mundo.
 *
 * Esto no sustituye al calendario, que sigue existiendo tras «ver más fechas» para el salto largo
 * —un cumpleaños dentro de tres meses no se alcanza deslizando—. `[DECIDIDO owner, 2026-08-28]`.
 *
 * ⚠️ **Aquí no se decide qué días se ofrecen** (`AFORO-02`, `CE-4`): eso lo dice `SlotOffer` y llega
 * por `GET availability/{producto}/dates`. Esto agrupa por mes y pone el nombre del día. Igual que
 * `buildWeeks()`, es presentación.
 *
 * ⚠️ **Y también en horario LOCAL**, por lo mismo que todo este módulo: `new Date('2026-08-01')` es
 * medianoche UTC y en un huso al oeste cae en julio. Las fechas se parten a mano.
 *
 * ⚠️ **El AÑO entra en el rótulo del mes solo cuando cambia.** Con seis meses de horizonte desde
 * agosto la tira llega a febrero del año siguiente, y dos «Feb» indistinguibles a 182 chips de
 * distancia son un error de reserva. Ponerlo siempre sería ruido en el 90 % de los casos.
 *
 * @param {Array<{date: string, price_cents: number|null, rate_key: string}>} offeredDates lo que devuelve la API
 * @param {string|null} selectedDate
 * @param {string} locale
 * @returns {Array<{month: string, label: string, days: Array<{date: string, day: number, weekday: string, price_cents: number|null, type: string|null, selected: boolean}>}>}
 */
export function buildStrip(offeredDates = [], selectedDate = null, locale = 'es') {
    const list = Array.isArray(offeredDates) ? offeredDates : [];

    if (list.length === 0) {
        return [];
    }

    const weekdayOf = new Intl.DateTimeFormat(locale, { weekday: 'short' });
    const monthOnly = new Intl.DateTimeFormat(locale, { month: 'short' });
    const monthAndYear = new Intl.DateTimeFormat(locale, { month: 'short', year: 'numeric' });

    // El año de referencia es el del PRIMER día ofrecido, no el de «hoy»: la tira se rotula contra
    // sí misma, así que abrir en enero un horizonte que empieza en diciembre no repite el año en
    // todos los grupos.
    const [firstYear] = parts(list[0].date);

    const groups = [];
    let current = null;

    for (const offer of list) {
        const [year, month, day] = parts(offer.date);
        const key = `${year}-${String(month).padStart(2, '0')}`;
        // ⚠️ Construida con tres números, NUNCA parseando la cadena: ver la cabecera del módulo.
        const date = new Date(year, month - 1, day);

        if (current === null || current.month !== key) {
            current = { month: key, label: capitalize((year === firstYear ? monthOnly : monthAndYear).format(date)), days: [] };
            groups.push(current);
        }

        current.days.push({
            date: offer.date,
            day,
            weekday: capitalize(weekdayOf.format(date).replace('.', '')),
            price_cents: offer.price_cents ?? null,
            type: offer.rate_key ?? null,
            selected: offer.date === selectedDate,
        });
    }

    return groups;
}

/** Mayúscula inicial. `Intl` devuelve «vie» y «ago» en español, y la tira los pinta como rótulo. */
function capitalize(label) {
    return label.charAt(0).toUpperCase() + label.slice(1);
}
