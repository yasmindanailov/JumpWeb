import test from 'node:test';
import assert from 'node:assert/strict';

import {
    buildWeeks, canGoNext, canGoPrev, initialMonth, monthLabel, monthOf, offeredMonths, shiftMonth,
    weekdayHeaders,
} from './calendar.js';

/**
 * La rejilla del calendario (Fase 4 · paso 4.7·2b·2·B).
 *
 * ⚠️ **`calendar.js` no tenía fichero de test hasta aquí.** Lo cubría solo `SidebarCalendarParityTest`,
 * en PHP, comparando su salida con la del servidor. Eso está bien mientras haya servidor con el que
 * comparar —y por eso ese test existe— pero deja el módulo sin red propia el día que el motor Livewire
 * se retire, que es justo lo que 4.7 hace.
 *
 * Los dos casos frontera de abajo NO son inventados: son los que `SidebarCalendarParityTest` documenta
 * como MEDIDOS —un mes que empieza en domingo y el huso del navegador—, traídos aquí en forma
 * ejecutable sin servidor. El aviso que dejó aquel paso sigue valiendo: la primera versión del caso de
 * husos **pasaba con el bug dentro**, porque el desfase solo mueve el lunes si el día 1 ya era lunes.
 */

const OFFER = [
    { date: '2026-08-12', price_cents: 990, rate_key: 'normal' },
    { date: '2026-08-15', price_cents: 1200, rate_key: 'special' },
];

/** Aplana la rejilla a `YYYY-MM-DD` para poder afirmar sobre el reparto sin ruido. */
function flat(weeks) {
    return weeks.flat().map((cell) => cell.date);
}

test('la rejilla siempre cierra semanas ENTERAS de siete días', () => {
    for (const month of ['2026-08', '2026-02', '2026-11', '2027-01']) {
        const weeks = buildWeeks(month, OFFER, null);

        assert.ok(weeks.length >= 4 && weeks.length <= 6, `${month} → ${weeks.length} semanas`);
        for (const week of weeks) {
            assert.equal(week.length, 7, `una semana de ${month} no tiene 7 días`);
        }
    }
});

test('la rejilla empieza en LUNES y termina en domingo', () => {
    const cells = flat(buildWeeks('2026-08', OFFER, null));

    // 2026-08-01 es sábado → la rejilla arranca el lunes 27 de julio.
    assert.equal(cells[0], '2026-07-27');
    assert.equal(new Date(2026, 6, 27).getDay(), 1, 'el ancla elegida tiene que ser lunes');
    assert.equal(new Date(...cells[cells.length - 1].split('-').map((n, i) => (i === 1 ? Number(n) - 1 : Number(n)))).getDay(), 0);
});

/**
 * ⚠️ **El mes que empieza en DOMINGO**, el caso que destapó el error clásico de las semanas de lunes.
 *
 * `getDay()` devuelve 0 para domingo, así que un cálculo ingenuo retrocede CERO días en vez de seis y
 * el mes entero se desplaza una casilla. Noviembre de 2026 empieza en domingo.
 */
test('un mes que empieza en DOMINGO no desplaza la rejilla', () => {
    assert.equal(new Date(2026, 10, 1).getDay(), 0, 'el mes elegido tiene que empezar en domingo');

    const cells = flat(buildWeeks('2026-11', [], null));

    // El domingo 1 pertenece a la semana que empezó el lunes 26 de octubre, no a la siguiente.
    assert.equal(cells[0], '2026-10-26');
    assert.equal(cells[6], '2026-11-01');
});

/**
 * ⚠️ **El huso del navegador.** `new Date('2026-08-01')` es medianoche UTC: al oeste de Greenwich
 * retrocede al 31 de julio y el mes entero se corre una casilla. El módulo parte las cadenas a mano
 * justo para eso, y este caso lo ejerce corriendo con `TZ` distintos.
 *
 * Se comprueba con un mes que **NO** empieza en lunes (agosto de 2026 empieza en sábado), porque con
 * uno que sí, el desfase no movería nada y el caso pasaría con el bug dentro — el aviso de 4.2.
 */
test('la rejilla no se mueve con el huso del navegador', () => {
    const original = process.env.TZ;

    try {
        const reparto = {};

        for (const tz of ['UTC', 'America/Los_Angeles', 'Pacific/Kiritimati']) {
            process.env.TZ = tz;
            reparto[tz] = flat(buildWeeks('2026-08', OFFER, '2026-08-15'));
        }

        assert.deepEqual(reparto['America/Los_Angeles'], reparto['UTC'], 'un huso al oeste mueve la rejilla');
        assert.deepEqual(reparto['Pacific/Kiritimati'], reparto['UTC'], 'un huso al este mueve la rejilla');
        assert.equal(reparto['UTC'][0], '2026-07-27');
    } finally {
        process.env.TZ = original;
    }
});

/**
 * ⚠️ **La SEGUNDA puerta del mismo peligro, y hace falta un mes distinto para verla.**
 *
 * El huso puede colarse por la SALIDA (derivar `YYYY-MM-DD` pasando por UTC) o por la ENTRADA
 * (`new Date('2026-08-01')`, que es medianoche UTC). El caso de arriba caza la primera —medido: mutar
 * `toYmd()` a `toISOString()` lo pone rojo— pero **no la segunda con agosto de 2026**, y el motivo es
 * el aviso de 4.2 leído del derecho: el desfase retrasa un día el «primero de mes», y el arranque de
 * la rejilla solo se mueve **si ese primero ya era lunes**. Agosto de 2026 empieza en sábado, así que
 * con o sin bug la semana arranca el mismo lunes.
 *
 * Junio de 2026 empieza en LUNES. Ahí el desfase salta a la semana anterior y se ve. **Verificado con
 * la mutación**: `new Date(month + '-01')` deja este caso rojo y el de arriba verde.
 */
test('un mes que empieza en LUNES tampoco se mueve con el huso', () => {
    const original = process.env.TZ;

    try {
        assert.equal(new Date(2026, 5, 1).getDay(), 1, 'el mes elegido tiene que empezar en lunes');

        process.env.TZ = 'UTC';
        const utc = flat(buildWeeks('2026-06', [], null));

        process.env.TZ = 'America/Los_Angeles';
        const oeste = flat(buildWeeks('2026-06', [], null));

        assert.equal(utc[0], '2026-06-01', 'con el día 1 en lunes, la rejilla arranca en él');
        assert.deepEqual(oeste, utc, 'al oeste la rejilla arranca una semana antes: el desfase entró por la ENTRADA');
    } finally {
        process.env.TZ = original;
    }
});

test('cada celda lleva su oferta, y las que no se ofrecen no son seleccionables', () => {
    const cells = buildWeeks('2026-08', OFFER, '2026-08-15').flat();
    const byDate = Object.fromEntries(cells.map((c) => [c.date, c]));

    assert.deepEqual(
        { selectable: byDate['2026-08-12'].selectable, type: byDate['2026-08-12'].type, price_cents: byDate['2026-08-12'].price_cents },
        { selectable: true, type: 'normal', price_cents: 990 }
    );
    assert.equal(byDate['2026-08-15'].type, 'special');
    assert.equal(byDate['2026-08-13'].selectable, false, 'un día sin oferta no se puede elegir');
    assert.equal(byDate['2026-08-13'].type, null);
});

test('marca el día elegido, y solo ese', () => {
    const seleccionados = buildWeeks('2026-08', OFFER, '2026-08-15').flat().filter((c) => c.selected);

    assert.deepEqual(seleccionados.map((c) => c.date), ['2026-08-15']);
});

/** Los días de relleno pertenecen a otro mes y el marcado los distingue: `in_month` es contrato. */
test('los días de relleno se marcan fuera de mes', () => {
    const cells = buildWeeks('2026-08', OFFER, null).flat();

    assert.equal(cells.find((c) => c.date === '2026-07-27').in_month, false);
    assert.equal(cells.find((c) => c.date === '2026-08-01').in_month, true);
});

test('monthOf y shiftMonth hacen aritmética de doce, incluido el salto de año', () => {
    assert.equal(monthOf('2026-08-15'), '2026-08');
    assert.equal(shiftMonth('2026-12', 1), '2027-01');
    assert.equal(shiftMonth('2026-01', -1), '2025-12');
    assert.equal(shiftMonth('2026-08', 0), '2026-08');
});

test('los meses ofertados salen ordenados y sin repetir', () => {
    assert.deepEqual(
        offeredMonths([
            { date: '2026-09-02' }, { date: '2026-08-31' }, { date: '2026-09-20' }, { date: '2026-08-01' },
        ]),
        ['2026-08', '2026-09']
    );
    assert.deepEqual(offeredMonths([]), []);
    assert.deepEqual(offeredMonths(null), []);
});

/**
 * ⚠️ **La navegación se acota a los meses CON oferta.** Sin esta guarda el cliente dejaría pasear por
 * meses vacíos, que es una pantalla sin nada que elegir y una petición inútil por cada clic.
 */
test('solo se navega dentro de los meses que tienen oferta', () => {
    const months = ['2026-08', '2026-09', '2026-10'];

    assert.equal(canGoPrev('2026-08', months), false, 'en el primero no hay anterior');
    assert.equal(canGoNext('2026-08', months), true);
    assert.equal(canGoPrev('2026-10', months), true);
    assert.equal(canGoNext('2026-10', months), false, 'en el último no hay siguiente');
});

test('sin oferta no se navega a ninguna parte', () => {
    assert.equal(canGoPrev('2026-08', []), false);
    assert.equal(canGoNext('2026-08', []), false);
    assert.equal(canGoPrev(null, ['2026-08']), false);
});

/**
 * ⚠️ Lo que se fija aquí es que sean SIETE y en orden de lunes a domingo — de eso depende la
 * estructura de la rejilla. El TEXTO puede diferir del servidor (Carbon escribe `sáb.` con punto e
 * ICU `sáb`); esa divergencia está declarada y tiene ficha en `DEUDA.md`.
 */
test('las cabeceras son siete, de lunes a domingo y capitalizadas', () => {
    const headers = weekdayHeaders('es');

    assert.equal(headers.length, 7);
    assert.equal(headers[0].charAt(0), headers[0].charAt(0).toUpperCase());
    assert.ok(headers[0].toLowerCase().startsWith('lun'), `el primero debería ser lunes: ${headers[0]}`);
    assert.ok(headers[6].toLowerCase().startsWith('dom'), `el último debería ser domingo: ${headers[6]}`);
    assert.equal(headers.filter((h) => h.includes('.')).length, 0, 'sin puntos de abreviatura');
});

test('las cabeceras siguen el idioma pedido', () => {
    assert.ok(weekdayHeaders('fr')[0].toLowerCase().startsWith('lun'));
    assert.ok(weekdayHeaders('en')[0].toLowerCase().startsWith('mon'));
});

test('el rótulo del mes lleva mes y año, capitalizado', () => {
    const label = monthLabel('2026-08', 'es');

    assert.ok(label.includes('2026'), label);
    assert.equal(label.charAt(0), label.charAt(0).toUpperCase());
    assert.ok(label.toLowerCase().includes('agosto'), label);
});

/** Sin mes elegido el rótulo es vacío, no «Invalid Date» ni «NaN». */
test('sin mes el rótulo es una cadena vacía', () => {
    for (const month of [null, undefined, '', 42]) {
        assert.equal(monthLabel(month, 'es'), '', String(month));
    }
});

test('el calendario abre en el PRIMER mes con oferta, no en el actual', () => {
    const oferta = [{ date: '2026-10-03' }, { date: '2026-09-28' }, { date: '2026-11-01' }];

    assert.equal(initialMonth(oferta, new Date(2026, 7, 12)), '2026-09');
});

/**
 * ⚠️ **El respaldo se calcula en horario LOCAL**, y este es el caso que lo fija.
 *
 * Vivía en `Sidebar.vue` como `new Date().toISOString().slice(0, 10)` — UTC—, así que en Madrid, a la
 * 01:00 del día 1, devolvía el mes ANTERIOR. Con `Date(2026, 8, 1, 1, 0)` (1 de septiembre, 01:00
 * local) y `TZ=Europe/Madrid`, el `toISOString()` da agosto y lo correcto es septiembre.
 */
test('sin oferta, el respaldo es el mes de HOY en local, no en UTC', () => {
    const original = process.env.TZ;

    try {
        process.env.TZ = 'Europe/Madrid';
        const madrugadaDelPrimero = new Date(2026, 8, 1, 1, 0, 0);

        assert.equal(madrugadaDelPrimero.toISOString().slice(0, 7), '2026-08', 'el fixture tiene que exhibir el desfase');
        assert.equal(initialMonth([], madrugadaDelPrimero), '2026-09');
    } finally {
        process.env.TZ = original;
    }
});
